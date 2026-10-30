<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EstadoIntervencion;
use App\Models\Intervencion;
use App\Models\IntervencionHistorial;
use App\Services\Auditor;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IntervencionController extends Controller
{
    /** Cambios de estado permitidos en el seguimiento de una intervención. */
    private const TRANSICIONES = [
        'pendiente' => ['programada', 'cancelada'],
        'programada' => ['pendiente', 'en_ejecucion', 'cancelada'],
        'en_ejecucion' => ['finalizada', 'cancelada'],
        'finalizada' => [],
        'cancelada' => [],
    ];

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(array_keys(self::TRANSICIONES))],
            'tipo_intervencion_id' => ['nullable', 'integer'],
            'responsable_usuario_id' => ['nullable', 'uuid'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $consulta = Intervencion::query()
            ->with([
                'tipoIntervencion:id,codigo,nombre',
                'estadoIntervencion:id,codigo,nombre',
                'responsable:id,nombres,apellidos',
            ])
            ->withCount('reportes')
            ->orderByRaw('fecha_programada_inicio asc nulls last')
            ->orderByDesc('creado_en');

        if (isset($filtros['estado'])) {
            $consulta->whereHas('estadoIntervencion', fn ($q) => $q->where('codigo', $filtros['estado']));
        }
        foreach (['tipo_intervencion_id', 'responsable_usuario_id'] as $campo) {
            if (isset($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if (isset($filtros['desde'])) {
            $consulta->where('fecha_programada_inicio', '>=', $filtros['desde']);
        }
        if (isset($filtros['hasta'])) {
            $consulta->where('fecha_programada_inicio', '<=', $filtros['hasta']);
        }

        $porPagina = min(max($request->integer('por_pagina', 25), 1), 100);

        return response()->json($consulta->paginate($porPagina));
    }

    public function show(string $intervencion): JsonResponse
    {
        return response()->json(
            Intervencion::with([
                'tipoIntervencion:id,codigo,nombre',
                'estadoIntervencion:id,codigo,nombre',
                'responsable:id,nombres,apellidos',
                'registradaPor:id,nombres,apellidos',
                'reportes:id,codigo_local,via_sector,punto_referencia,comuna_id,barrio_id',
                'historial.estadoAnterior:id,codigo,nombre',
                'historial.estadoNuevo:id,codigo,nombre',
                'historial.usuario:id,nombres,apellidos',
            ])->findOrFail($intervencion)
        );
    }

    /** Programa una intervención sobre uno o varios reportes aprobados. */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo_intervencion_id' => ['required', 'integer', 'exists:tipos_intervencion,id'],
            'descripcion' => ['nullable', 'string'],
            'fecha_programada_inicio' => ['nullable', 'date'],
            'fecha_programada_fin' => ['nullable', 'date', 'after_or_equal:fecha_programada_inicio'],
            'responsable_usuario_id' => ['nullable', 'uuid', 'exists:usuarios,id'],
            'ejecutor' => ['nullable', 'string', 'max:150'],
            'reportes' => ['required', 'array', 'min:1'],
            'reportes.*' => ['uuid', 'distinct', 'exists:reportes,id'],
        ]);

        $usuario = $request->user();
        $reportes = $datos['reportes'];
        unset($datos['reportes']);

        // Con fecha de inicio la intervención nace programada; sin ella, pendiente.
        $estado = isset($datos['fecha_programada_inicio']) ? 'programada' : 'pendiente';

        try {
            $intervencion = DB::transaction(function () use ($datos, $reportes, $usuario, $estado) {
                $this->indicarUsuario($usuario->getKey());

                $intervencion = Intervencion::create($datos + [
                    'estado_intervencion_id' => $this->estadoId($estado),
                    'usuario_id' => $usuario->getKey(),
                ]);
                $intervencion->reportes()->attach($reportes);

                return $intervencion->refresh();
            });
        } catch (QueryException $e) {
            $this->traducirRechazo($e);
        }

        Auditor::registrar($request, 'programar', 'intervenciones', $intervencion->id, null, [
            'codigo' => $intervencion->codigo, 'reportes' => $reportes,
        ]);

        return response()->json(
            $intervencion->load(['tipoIntervencion:id,codigo,nombre', 'estadoIntervencion:id,codigo,nombre', 'reportes:id,codigo_local']),
            201
        );
    }

    /** Edita los datos de programación; el estado cambia únicamente con cambiarEstado. */
    public function update(Request $request, string $intervencion): JsonResponse
    {
        $modelo = Intervencion::with('estadoIntervencion')->findOrFail($intervencion);

        abort_if(
            in_array($modelo->estadoIntervencion->codigo, ['finalizada', 'cancelada'], true),
            422,
            'La intervención ya está cerrada y no puede editarse.'
        );

        $datos = $request->validate([
            'tipo_intervencion_id' => ['sometimes', 'integer', 'exists:tipos_intervencion,id'],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'fecha_programada_inicio' => ['sometimes', 'nullable', 'date'],
            'fecha_programada_fin' => ['sometimes', 'nullable', 'date'],
            'responsable_usuario_id' => ['sometimes', 'nullable', 'uuid', 'exists:usuarios,id'],
            'ejecutor' => ['sometimes', 'nullable', 'string', 'max:150'],
        ]);

        $inicio = array_key_exists('fecha_programada_inicio', $datos) ? $datos['fecha_programada_inicio'] : $modelo->fecha_programada_inicio;
        $fin = array_key_exists('fecha_programada_fin', $datos) ? $datos['fecha_programada_fin'] : $modelo->fecha_programada_fin;

        if ($inicio && $fin && strtotime((string) $fin) < strtotime((string) $inicio)) {
            throw ValidationException::withMessages([
                'fecha_programada_fin' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
            ]);
        }

        $antes = $modelo->only(array_keys($datos));
        $modelo->update($datos);

        Auditor::registrar($request, 'actualizar', 'intervenciones', $modelo->id, $antes, $datos);

        return response()->json($modelo->refresh());
    }

    /** Cambia el estado de seguimiento; la base registra el cambio en el historial. */
    public function cambiarEstado(Request $request, string $intervencion): JsonResponse
    {
        $modelo = Intervencion::with('estadoIntervencion')->findOrFail($intervencion);

        $datos = $request->validate([
            'estado' => ['required', Rule::in(array_keys(self::TRANSICIONES))],
            'comentario' => ['nullable', 'string'],
        ]);

        $actual = $modelo->estadoIntervencion->codigo;

        abort_unless(
            in_array($datos['estado'], self::TRANSICIONES[$actual], true),
            422,
            "No se puede pasar de «{$actual}» a «{$datos['estado']}»."
        );
        abort_if(
            $datos['estado'] === 'programada' && $modelo->fecha_programada_inicio === null,
            422,
            'Defina la fecha programada de inicio antes de programar la intervención.'
        );

        $usuario = $request->user();

        DB::transaction(function () use ($modelo, $datos, $usuario) {
            $this->indicarUsuario($usuario->getKey());

            $modelo->update(['estado_intervencion_id' => $this->estadoId($datos['estado'])]);

            if (! empty($datos['comentario'])) {
                IntervencionHistorial::where('intervencion_id', $modelo->id)
                    ->orderByDesc('id')
                    ->first()
                    ?->update(['comentario' => $datos['comentario']]);
            }
        });

        Auditor::registrar($request, 'cambiar_estado', 'intervenciones', $modelo->id, ['estado' => $actual], ['estado' => $datos['estado']]);

        return response()->json($modelo->refresh()->load('estadoIntervencion:id,codigo,nombre'));
    }

    /** Agrega reportes aprobados a una intervención abierta. */
    public function agregarReportes(Request $request, string $intervencion): JsonResponse
    {
        $modelo = $this->abierta($intervencion);

        $datos = $request->validate([
            'reportes' => ['required', 'array', 'min:1'],
            'reportes.*' => ['uuid', 'distinct', 'exists:reportes,id'],
        ]);

        try {
            $modelo->reportes()->syncWithoutDetaching($datos['reportes']);
        } catch (QueryException $e) {
            $this->traducirRechazo($e);
        }

        Auditor::registrar($request, 'agregar_reportes', 'intervenciones', $modelo->id, null, ['reportes' => $datos['reportes']]);

        return response()->json($modelo->load('reportes:id,codigo_local'));
    }

    /** Retira un reporte de una intervención abierta; debe quedar al menos uno. */
    public function quitarReporte(Request $request, string $intervencion, string $reporte): JsonResponse
    {
        $modelo = $this->abierta($intervencion);

        abort_if($modelo->reportes()->count() <= 1, 422, 'La intervención debe conservar al menos un reporte.');

        $modelo->reportes()->detach($reporte);

        Auditor::registrar($request, 'quitar_reporte', 'intervenciones', $modelo->id, ['reporte_id' => $reporte], null);

        return response()->json($modelo->load('reportes:id,codigo_local'));
    }

    private function abierta(string $id): Intervencion
    {
        $modelo = Intervencion::with('estadoIntervencion')->findOrFail($id);

        abort_if(
            in_array($modelo->estadoIntervencion->codigo, ['finalizada', 'cancelada'], true),
            422,
            'La intervención ya está cerrada.'
        );

        return $modelo;
    }

    /** Informa a la base quién actúa, para que el historial registre al usuario correcto. */
    private function indicarUsuario(string $usuarioId): void
    {
        DB::select("select set_config('app.usuario_id', ?, true)", [$usuarioId]);
    }

    private function estadoId(string $codigo): int
    {
        return EstadoIntervencion::where('codigo', $codigo)->value('id');
    }

    /** La base rechaza (check_violation) los reportes que no están aprobados. */
    private function traducirRechazo(QueryException $e): never
    {
        if ($e->getCode() === '23514') {
            throw ValidationException::withMessages([
                'reportes' => 'Solo los reportes aprobados pueden intervenirse.',
            ]);
        }

        throw $e;
    }
}
