<?php

namespace App\Http\Controllers\Api\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros compartidos por la bandeja, el mapa y el listado priorizado.
 * Se aplican sobre la vista v_reportes_consolidados.
 */
trait FiltraReportes
{
    protected function filtrarReportes($consulta, Request $request)
    {
        $filtros = $request->validate([
            'comuna_id' => ['nullable', 'integer'],
            'barrio_id' => ['nullable', 'integer'],
            'severidad' => ['nullable', 'string', 'max:40'],
            'estado_reporte' => ['nullable', 'string', 'max:40'],
            'estado_atencion' => ['nullable', 'string', 'max:40'],
            'tipo_deterioro_id' => ['nullable', 'integer'],
            'campana_id' => ['nullable', 'uuid'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        foreach (['comuna_id', 'barrio_id', 'severidad', 'estado_reporte', 'estado_atencion', 'tipo_deterioro_id', 'campana_id'] as $campo) {
            if (isset($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }

        if (isset($filtros['desde'])) {
            $consulta->where('fecha_captura', '>=', Carbon::parse($filtros['desde'])->startOfDay());
        }

        if (isset($filtros['hasta'])) {
            $consulta->where('fecha_captura', '<', Carbon::parse($filtros['hasta'])->addDay()->startOfDay());
        }

        if ($request->boolean('pendientes')) {
            $consulta->whereIn('estado_reporte', ['recibido', 'en_revision']);
        }

        return $consulta;
    }

    protected function porPagina(Request $request): int
    {
        return min(max($request->integer('por_pagina', 25), 1), 100);
    }
}
