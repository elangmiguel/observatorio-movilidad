<?php

namespace App\Services;

use App\Models\Dispositivo;
use App\Models\Fotografia;
use App\Models\Reporte;
use App\Models\Sincronizacion;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Recibe un reporte enviado desde la aplicación móvil: es idempotente, guarda la
 * fotografía en el almacenamiento de objetos, lee sus metadatos EXIF y deja
 * constancia de cada intento en la bitácora de sincronizaciones.
 */
class RecepcionReporteService
{
    /**
     * @return array{reporte: Reporte, duplicado: bool}
     */
    public function recibir(Usuario $usuario, array $datos, UploadedFile $foto): array
    {
        $dispositivo = Dispositivo::firstOrCreate(
            ['codigo_instalacion' => $datos['codigo_instalacion']],
            [
                'plataforma' => $datos['plataforma'] ?? null,
                'modelo' => $datos['modelo'] ?? null,
                'version_app' => $datos['version_app'] ?? null,
            ]
        );
        $dispositivo->update([
            'ultimo_envio_en' => now(),
            'version_app' => $datos['version_app'] ?? $dispositivo->version_app,
        ]);

        $intento = Sincronizacion::where('dispositivo_id', $dispositivo->id)
            ->where('codigo_local', $datos['codigo_local'])
            ->count() + 1;

        // Un reintento del mismo envío devuelve el reporte ya registrado.
        $existente = Reporte::withTrashed()
            ->where('dispositivo_id', $dispositivo->id)
            ->where('codigo_local', $datos['codigo_local'])
            ->first();

        if ($existente) {
            $this->bitacora($dispositivo, $usuario, $datos['codigo_local'], $existente->id, 'duplicado', $intento);

            return ['reporte' => $existente, 'duplicado' => true];
        }

        $hash = hash_file('sha256', $foto->getRealPath());

        if (Fotografia::where('hash_sha256', $hash)->exists()) {
            $this->bitacora(
                $dispositivo, $usuario, $datos['codigo_local'], null, 'error', $intento,
                'FOTO_DUPLICADA', 'La fotografía ya fue registrada en otro reporte.'
            );

            throw ValidationException::withMessages([
                'foto' => 'Esta fotografía ya fue registrada en otro reporte.',
            ]);
        }

        try {
            $reporte = DB::transaction(
                fn () => $this->crear($usuario, $dispositivo, $datos, $foto, $hash)
            );
        } catch (Throwable $e) {
            $this->bitacora(
                $dispositivo, $usuario, $datos['codigo_local'], null, 'error', $intento,
                'ERROR_RECEPCION', $e->getMessage()
            );

            throw $e;
        }

        $this->bitacora($dispositivo, $usuario, $datos['codigo_local'], $reporte->id, 'exitoso', $intento);

        return ['reporte' => $reporte, 'duplicado' => false];
    }

    private function crear(Usuario $usuario, Dispositivo $dispositivo, array $datos, UploadedFile $foto, string $hash): Reporte
    {
        $reporte = Reporte::create(array_merge(
            [
                'codigo_local' => $datos['codigo_local'],
                'dispositivo_id' => $dispositivo->id,
                'usuario_id' => $usuario->id,
            ],
            Arr::only($datos, [
                'campana_id', 'latitud', 'longitud', 'precision_gps_m', 'fuente_ubicacion',
                'fecha_captura', 'via_sector', 'barrio_reportado', 'punto_referencia',
                'tipo_deterioro_id', 'tipo_superficie_id', 'descripcion_ciudadano',
            ])
        ));

        $ruta = $foto->getRealPath();
        $dimensiones = @getimagesize($ruta) ?: [null, null];
        $exif = $this->leerExif($ruta);

        $extension = strtolower($foto->extension() ?: 'jpg');
        $guardada = Storage::disk('observatorio')->putFileAs(
            'reportes/' . now()->format('Y/m'),
            $foto,
            $reporte->id . '.' . $extension
        );

        if ($guardada === false) {
            throw new RuntimeException('No se pudo guardar la fotografía en el almacenamiento.');
        }

        // La columna admite hasta 999999.99 m; una coordenada EXIF absurda no debe impedir el reporte.
        $distancia = isset($exif['latitud'], $exif['longitud'])
            ? min(round($this->distanciaMetros(
                (float) $datos['latitud'], (float) $datos['longitud'], $exif['latitud'], $exif['longitud']
            ), 2), 999999.99)
            : null;

        Fotografia::create([
            'reporte_id' => $reporte->id,
            'es_principal' => true,
            'nombre_archivo' => Str::limit($foto->getClientOriginalName(), 255, ''),
            'url_almacenamiento' => $guardada,
            'hash_sha256' => $hash,
            'tamano_bytes' => $foto->getSize(),
            'ancho_px' => $dimensiones[0],
            'alto_px' => $dimensiones[1],
            'tipo_mime' => $foto->getMimeType() ?: 'image/jpeg',
            'exif_latitud' => $exif['latitud'] ?? null,
            'exif_longitud' => $exif['longitud'] ?? null,
            'exif_fecha_captura' => $exif['fecha'] ?? null,
            'exif_orientacion' => $exif['orientacion'] ?? null,
            'distancia_gps_exif_m' => $distancia,
        ]);

        // Recarga los valores que asigna la base: estado, comuna y barrio.
        return $reporte->refresh();
    }

    private function bitacora(
        Dispositivo $dispositivo,
        Usuario $usuario,
        string $codigoLocal,
        ?string $reporteId,
        string $resultado,
        int $intento,
        ?string $codigoError = null,
        ?string $mensajeError = null,
    ): void {
        Sincronizacion::create([
            'reporte_id' => $reporteId,
            'dispositivo_id' => $dispositivo->id,
            'usuario_id' => $usuario->id,
            'codigo_local' => $codigoLocal,
            'resultado' => $resultado,
            'codigo_error' => $codigoError,
            'mensaje_error' => $mensajeError,
            'numero_intento' => $intento,
        ]);
    }

    /** @return array{latitud?: float, longitud?: float, fecha?: Carbon, orientacion?: int} */
    private function leerExif(string $ruta): array
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($ruta, 'ANY_TAG', true) : false;

        if (! is_array($exif)) {
            return [];
        }

        $gps = $exif['GPS'] ?? [];
        $latitud = isset($gps['GPSLatitude'], $gps['GPSLatitudeRef'])
            ? $this->gpsAGrados($gps['GPSLatitude'], $gps['GPSLatitudeRef']) : null;
        $longitud = isset($gps['GPSLongitude'], $gps['GPSLongitudeRef'])
            ? $this->gpsAGrados($gps['GPSLongitude'], $gps['GPSLongitudeRef']) : null;

        $orientacion = (int) ($exif['IFD0']['Orientation'] ?? 0);

        return array_filter([
            'latitud' => $latitud !== null && abs($latitud) <= 90 ? $latitud : null,
            'longitud' => $longitud !== null && abs($longitud) <= 180 ? $longitud : null,
            'fecha' => $this->parsearFechaExif($exif['EXIF']['DateTimeOriginal'] ?? null),
            'orientacion' => $orientacion >= 1 && $orientacion <= 8 ? $orientacion : null,
        ], fn ($valor) => $valor !== null);
    }

    private function gpsAGrados(array $partes, string $referencia): ?float
    {
        if (count($partes) < 3) {
            return null;
        }

        $fraccion = function (string $valor): float {
            $p = explode('/', $valor);

            return count($p) === 2 && (float) $p[1] != 0
                ? (float) $p[0] / (float) $p[1]
                : (float) $valor;
        };

        $grados = $fraccion($partes[0]) + $fraccion($partes[1]) / 60 + $fraccion($partes[2]) / 3600;

        return in_array($referencia, ['S', 'W'], true) ? -$grados : $grados;
    }

    private function parsearFechaExif(?string $valor): ?Carbon
    {
        if (! $valor) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y:m:d H:i:s', $valor);
        } catch (Throwable) {
            return null;
        }
    }

    private function distanciaMetros(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $radio = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $radio * asin(min(1, sqrt($a)));
    }
}
