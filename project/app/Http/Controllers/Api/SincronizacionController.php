<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sincronizacion;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SincronizacionController extends Controller
{
    /** Bitácora de envíos desde la aplicación móvil, para dar soporte a los que fallan. */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'resultado' => ['nullable', 'in:exitoso,error,duplicado'],
            'dispositivo_id' => ['nullable', 'uuid'],
            'usuario_id' => ['nullable', 'uuid'],
            'codigo_error' => ['nullable', 'string', 'max:40'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $consulta = Sincronizacion::query()
            ->with([
                'dispositivo:id,codigo_instalacion,plataforma,modelo,version_app',
                'usuario:id,nombres,apellidos',
            ])
            ->orderByDesc('intentado_en');

        foreach (['resultado', 'dispositivo_id', 'usuario_id', 'codigo_error'] as $campo) {
            if (isset($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if (isset($filtros['desde'])) {
            $consulta->where('intentado_en', '>=', Carbon::parse($filtros['desde'])->startOfDay());
        }
        if (isset($filtros['hasta'])) {
            $consulta->where('intentado_en', '<', Carbon::parse($filtros['hasta'])->addDay()->startOfDay());
        }

        $porPagina = min(max($request->integer('por_pagina', 25), 1), 100);

        return response()->json($consulta->paginate($porPagina));
    }
}
