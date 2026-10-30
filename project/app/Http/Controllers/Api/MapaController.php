<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FiltraReportes;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapaController extends Controller
{
    use FiltraReportes;

    /**
     * Daños georreferenciados como FeatureCollection GeoJSON, con filtros por
     * comuna, barrio, fecha y severidad. Excluye duplicados y rechazados, salvo
     * que se pida ?incluir_descartados=1.
     */
    public function reportes(Request $request): JsonResponse
    {
        $consulta = DB::table('v_reportes_consolidados')
            ->select([
                'id', 'fecha_captura', 'latitud', 'longitud', 'via_sector', 'comuna_id', 'comuna',
                'barrio_id', 'barrio', 'estado_reporte', 'tipo_deterioro_id', 'severidad',
                'prioridad', 'estado_atencion',
            ])
            ->selectRaw('ST_AsGeoJSON(ubicacion) as geometria');

        // Las fotografías solo se indican a quien tiene permiso para consultarlas.
        if ($request->user()->tienePermiso('mapa.ver_fotografias')) {
            $consulta->addSelect('fotografia_id');
        }

        if (! $request->boolean('incluir_descartados')) {
            $consulta->whereNotIn('estado_reporte', ['duplicado', 'rechazado']);
        }

        $this->filtrarReportes($consulta, $request);

        $limite = min(max($request->integer('limite', 2000), 1), 5000);

        $caracteristicas = $consulta
            ->orderByDesc('fecha_captura')
            ->limit($limite)
            ->get()
            ->map(function ($fila) {
                $geometria = json_decode($fila->geometria, true);
                unset($fila->geometria);

                return ['type' => 'Feature', 'geometry' => $geometria, 'properties' => $fila];
            });

        return response()->json(['type' => 'FeatureCollection', 'features' => $caracteristicas]);
    }

    /**
     * Grupos de daños cercanos (a menos de 30 m entre sí), de mayor a menor número
     * de reportes: identifica los sectores con más concentración. ?minimo= fija el
     * mínimo de reportes por grupo (por defecto 2).
     */
    public function concentracion(Request $request): JsonResponse
    {
        $minimo = max($request->integer('minimo', 2), 1);
        $limite = min(max($request->integer('limite', 500), 1), 2000);

        $grupos = DB::table('vm_concentracion_danos')
            ->select('grupo_id', 'total_reportes')
            ->selectRaw('ST_AsGeoJSON(centroide) as geometria')
            ->selectRaw('array_to_json(reporte_ids) as reportes')
            ->where('total_reportes', '>=', $minimo)
            ->orderByDesc('total_reportes')
            ->limit($limite)
            ->get()
            ->map(fn ($fila) => [
                'type' => 'Feature',
                'geometry' => json_decode($fila->geometria, true),
                'properties' => [
                    'grupo_id' => $fila->grupo_id,
                    'total_reportes' => (int) $fila->total_reportes,
                    'reportes' => json_decode($fila->reportes, true),
                ],
            ]);

        return response()->json(['type' => 'FeatureCollection', 'features' => $grupos]);
    }
}
