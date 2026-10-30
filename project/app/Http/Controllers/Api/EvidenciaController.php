<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvidenciaIntervencion;
use App\Models\Intervencion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EvidenciaController extends Controller
{
    /** Evidencias agrupadas por momento, para comparar el antes y el después de la obra. */
    public function index(string $intervencion): JsonResponse
    {
        Intervencion::findOrFail($intervencion);

        $disco = Storage::disk('observatorio');

        $evidencias = EvidenciaIntervencion::where('intervencion_id', $intervencion)
            ->with('usuario:id,nombres,apellidos')
            ->orderBy('tomada_en')
            ->orderBy('subida_en')
            ->get()
            ->map(function (EvidenciaIntervencion $evidencia) use ($disco) {
                $evidencia->setAttribute('url', $disco->temporaryUrl($evidencia->url_almacenamiento, now()->addMinutes(15)));

                return $evidencia->makeHidden('url_almacenamiento');
            });

        return response()->json([
            EvidenciaIntervencion::MOMENTO_ANTES => $evidencias->where('momento', EvidenciaIntervencion::MOMENTO_ANTES)->values(),
            EvidenciaIntervencion::MOMENTO_DURANTE => $evidencias->where('momento', EvidenciaIntervencion::MOMENTO_DURANTE)->values(),
            EvidenciaIntervencion::MOMENTO_DESPUES => $evidencias->where('momento', EvidenciaIntervencion::MOMENTO_DESPUES)->values(),
        ]);
    }

    /** Carga una fotografía de evidencia (multipart) de una intervención abierta. */
    public function store(Request $request, string $intervencion): JsonResponse
    {
        $modelo = Intervencion::with('estadoIntervencion')->findOrFail($intervencion);

        abort_if($modelo->estadoIntervencion->codigo === 'cancelada', 422, 'La intervención está cancelada.');

        $datos = $request->validate([
            'foto' => ['required', 'file', 'image', 'max:15360'],
            'momento' => ['required', Rule::in([
                EvidenciaIntervencion::MOMENTO_ANTES,
                EvidenciaIntervencion::MOMENTO_DURANTE,
                EvidenciaIntervencion::MOMENTO_DESPUES,
            ])],
            // El reporte, si se indica, debe estar entre los atendidos por la intervención.
            'reporte_id' => [
                'nullable', 'uuid',
                Rule::exists('intervenciones_reportes', 'reporte_id')->where('intervencion_id', $modelo->id),
            ],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'descripcion' => ['nullable', 'string'],
            'tomada_en' => ['nullable', 'date'],
        ]);

        $foto = $request->file('foto');
        $hash = hash_file('sha256', $foto->getRealPath());

        if (EvidenciaIntervencion::where('hash_sha256', $hash)->exists()) {
            throw ValidationException::withMessages(['foto' => 'Esta fotografía ya fue cargada.']);
        }

        $ruta = Storage::disk('observatorio')->putFileAs(
            "intervenciones/{$modelo->id}/{$datos['momento']}",
            $foto,
            Str::uuid() . '.' . strtolower($foto->extension() ?: 'jpg')
        );

        $evidencia = EvidenciaIntervencion::create([
            'intervencion_id' => $modelo->id,
            'reporte_id' => $datos['reporte_id'] ?? null,
            'momento' => $datos['momento'],
            'nombre_archivo' => Str::limit($foto->getClientOriginalName(), 255, ''),
            'url_almacenamiento' => $ruta,
            'hash_sha256' => $hash,
            'tamano_bytes' => $foto->getSize(),
            'tipo_mime' => $foto->getMimeType() ?: 'image/jpeg',
            'latitud' => $datos['latitud'] ?? null,
            'longitud' => $datos['longitud'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'tomada_en' => $datos['tomada_en'] ?? null,
            'usuario_id' => $request->user()->getKey(),
        ]);

        return response()->json($evidencia->makeHidden('url_almacenamiento'), 201);
    }
}
