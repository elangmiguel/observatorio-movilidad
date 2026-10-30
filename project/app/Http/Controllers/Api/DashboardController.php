<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Totales del observatorio: reportes, pendientes de revisión, severidad y estado de atención. */
    public function resumen(): JsonResponse
    {
        $filas = DB::table('vm_dashboard_resumen')->get();
        $suma = fn ($coleccion) => (int) $coleccion->sum('total');

        return response()->json([
            'total' => $suma($filas),
            'pendientes_revision' => $suma($filas->whereIn('estado_reporte', ['recibido', 'en_revision'])),
            'por_estado_reporte' => $filas->groupBy('estado_reporte')->map($suma),
            'por_severidad' => $filas->groupBy(fn ($fila) => $fila->severidad ?? 'sin_clasificar')->map($suma),
            'por_estado_atencion' => $filas
                ->where('estado_reporte', 'aprobado')
                ->groupBy('estado_atencion')
                ->map($suma),
        ]);
    }

    /** Distribución de reportes por comuna y barrio, con filtros opcionales. */
    public function distribucion(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'comuna_id' => ['nullable', 'integer'],
            'severidad' => ['nullable', 'string', 'max:40'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $consulta = DB::table('vm_distribucion_territorial')
            ->select('comuna_id', 'comuna', 'barrio_id', 'barrio')
            ->selectRaw('sum(total)::int as total')
            ->groupBy('comuna_id', 'comuna', 'barrio_id', 'barrio')
            ->orderByDesc('total');

        if (isset($filtros['comuna_id'])) {
            $consulta->where('comuna_id', $filtros['comuna_id']);
        }
        if (isset($filtros['severidad'])) {
            $consulta->where('severidad', $filtros['severidad']);
        }
        if (isset($filtros['desde'])) {
            $consulta->where('mes', '>=', Carbon::parse($filtros['desde'])->startOfMonth()->toDateString());
        }
        if (isset($filtros['hasta'])) {
            $consulta->where('mes', '<=', Carbon::parse($filtros['hasta'])->startOfMonth()->toDateString());
        }

        $porBarrio = $consulta->get();

        return response()->json([
            'por_barrio' => $porBarrio,
            'por_comuna' => $porBarrio
                ->groupBy('comuna_id')
                ->map(fn ($grupo) => [
                    'comuna_id' => $grupo->first()->comuna_id,
                    'comuna' => $grupo->first()->comuna,
                    'total' => (int) $grupo->sum('total'),
                ])
                ->sortByDesc('total')
                ->values(),
        ]);
    }
}
