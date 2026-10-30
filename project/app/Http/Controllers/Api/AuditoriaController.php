<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    /** Consulta del registro de acciones de los usuarios. */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'usuario_id' => ['nullable', 'uuid'],
            'entidad' => ['nullable', 'string', 'max:60'],
            'entidad_id' => ['nullable', 'string', 'max:64'],
            'accion' => ['nullable', 'string', 'max:40'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $consulta = Auditoria::query()
            ->with('usuario:id,nombres,apellidos')
            ->orderByDesc('creado_en');

        foreach (['usuario_id', 'entidad', 'entidad_id', 'accion'] as $campo) {
            if (isset($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if (isset($filtros['desde'])) {
            $consulta->where('creado_en', '>=', Carbon::parse($filtros['desde'])->startOfDay());
        }
        if (isset($filtros['hasta'])) {
            $consulta->where('creado_en', '<', Carbon::parse($filtros['hasta'])->addDay()->startOfDay());
        }

        $porPagina = min(max($request->integer('por_pagina', 25), 1), 100);

        return response()->json($consulta->paginate($porPagina));
    }
}
