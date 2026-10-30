<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FiltraReportes;
use App\Http\Controllers\Controller;
use App\Models\Priorizacion;
use App\Models\Reporte;
use App\Services\Auditor;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriorizacionController extends Controller
{
    use FiltraReportes;

    /**
     * Listado de daños priorizados, de mayor a menor prioridad. Con ?sin_prioridad=1
     * devuelve los reportes aprobados que aún no tienen prioridad asignada.
     */
    public function index(Request $request): JsonResponse
    {
        $consulta = DB::table('v_reportes_consolidados as r')
            ->leftJoin('prioridades as p', 'p.id', '=', 'r.prioridad_id')
            ->where('r.estado_reporte', 'aprobado')
            ->select([
                'r.id', 'r.codigo_local', 'r.via_sector', 'r.punto_referencia', 'r.latitud', 'r.longitud',
                'r.comuna_id', 'r.comuna', 'r.barrio_id', 'r.barrio', 'r.fecha_captura',
                'r.tipo_deterioro_id', 'r.severidad_id', 'r.severidad',
                'r.prioridad_id', 'r.prioridad', 'r.puntaje', 'r.intervencion_id', 'r.estado_atencion',
            ]);

        $request->boolean('sin_prioridad')
            ? $consulta->whereNull('r.prioridad_id')
            : $consulta->whereNotNull('r.prioridad_id');

        $this->filtrarReportes($consulta, $request);

        return response()->json(
            $consulta
                ->orderByRaw('p.nivel desc nulls last')
                ->orderByRaw('r.puntaje desc nulls last')
                ->orderBy('r.fecha_captura')
                ->paginate($this->porPagina($request))
        );
    }

    /** Historial de prioridades asignadas a un reporte. */
    public function historial(string $reporte): JsonResponse
    {
        Reporte::findOrFail($reporte);

        return response()->json(
            Priorizacion::where('reporte_id', $reporte)
                ->with(['prioridad:id,codigo,nombre,nivel', 'usuario:id,nombres,apellidos'])
                ->orderByDesc('asignada_en')
                ->get()
        );
    }

    /** Asigna la prioridad técnica a un reporte aprobado; la anterior queda como histórica. */
    public function store(Request $request, string $reporte): JsonResponse
    {
        $modelo = Reporte::findOrFail($reporte);

        $datos = $request->validate([
            'prioridad_id' => ['required', 'integer', 'exists:prioridades,id'],
            'puntaje' => ['nullable', 'numeric', 'between:0,100'],
            'justificacion' => ['nullable', 'string'],
        ]);

        try {
            $priorizacion = Priorizacion::create($datos + [
                'reporte_id' => $modelo->id,
                'usuario_id' => $request->user()->getKey(),
            ]);
        } catch (QueryException $e) {
            // La base rechaza (check_violation) los reportes que no están aprobados.
            if ($e->getCode() === '23514') {
                throw ValidationException::withMessages([
                    'reporte' => 'El reporte debe estar aprobado para priorizarse.',
                ]);
            }

            throw $e;
        }

        Auditor::registrar($request, 'priorizar', 'reportes', $modelo->id, null, $datos);

        return response()->json($priorizacion->load('prioridad:id,codigo,nombre,nivel'), 201);
    }
}
