<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auditor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Base para los recursos de referencia que el administrador mantiene con
 * operaciones simples: listar, consultar, crear, actualizar y dar de baja.
 *
 * Cada subclase declara únicamente su modelo, sus reglas de validación y,
 * si difiere del valor por defecto, el permiso de escritura. La lectura solo
 * exige autenticación, porque los formularios de toda la aplicación la usan.
 */
abstract class CatalogoController extends Controller implements HasMiddleware
{
    /** Modelo Eloquent que administra el controlador. */
    protected const MODELO = Model::class;

    /** Permiso que exigen las operaciones de escritura. */
    protected const PERMISO = 'catalogos.gestionar';

    /** Columna booleana de baja lógica; null si el recurso no se desactiva. */
    protected const CAMPO_ACTIVO = 'activo';

    /** Columna sobre la que busca el parámetro ?buscar=; null si no aplica. */
    protected const CAMPO_BUSQUEDA = 'nombre';

    /** Columna por la que se ordena el listado. */
    protected const ORDEN = 'id';

    /** Columnas que se pueden filtrar por igualdad desde la URL (?campo=valor). */
    protected const FILTROS = [];

    /** Acciones disponibles; las rutas se registran solo para estas. */
    public const ACCIONES = ['index', 'show', 'store', 'update', 'destroy'];

    /** Reglas de validación del recurso; $registro es el modelo en edición, o null al crear. */
    abstract protected function reglas(?Model $registro = null): array;

    public static function middleware(): array
    {
        return [
            new Middleware('permiso:' . static::PERMISO, except: ['index', 'show']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $this->permitir('index');

        $modelo = static::MODELO;
        $consulta = $modelo::query()->orderBy(static::ORDEN);

        foreach (static::FILTROS as $campo) {
            if ($request->filled($campo)) {
                $consulta->where($campo, $request->input($campo));
            }
        }

        if (static::CAMPO_ACTIVO !== null && $request->filled('activo')) {
            $consulta->where(static::CAMPO_ACTIVO, $request->boolean('activo'));
        }

        if (static::CAMPO_BUSQUEDA !== null && $request->filled('buscar')) {
            $texto = addcslashes((string) $request->input('buscar'), '%_\\');
            $consulta->where(static::CAMPO_BUSQUEDA, 'ilike', "%{$texto}%");
        }

        if ($request->boolean('todos')) {
            return response()->json($consulta->get());
        }

        $porPagina = min(max($request->integer('por_pagina', 25), 1), 100);

        return response()->json($consulta->paginate($porPagina));
    }

    public function show(string $id): JsonResponse
    {
        $this->permitir('show');

        return response()->json($this->alMostrar($this->buscar($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->permitir('store');

        $modelo = static::MODELO;
        $datos = $this->prepararDatos($request->validate($this->reglas()), null);
        $registro = $modelo::create($datos)->refresh();

        Auditor::registrar($request, 'crear', $registro->getTable(), $registro->getKey(), null, $registro->toArray());

        return response()->json($registro, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->permitir('update');

        $registro = $this->buscar($id);
        $reglas = $this->reglas($registro);

        // PATCH valida solo los campos enviados; PUT exige los obligatorios.
        if ($request->isMethod('patch')) {
            $reglas = array_map(fn (array $regla) => ['sometimes', ...$regla], $reglas);
        }

        $antes = $registro->toArray();
        $registro->update($this->prepararDatos($request->validate($reglas), $registro));
        $registro->refresh();

        Auditor::registrar($request, 'actualizar', $registro->getTable(), $registro->getKey(), $antes, $registro->toArray());

        return response()->json($registro);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->permitir('destroy');

        $registro = $this->buscar($id);
        $antes = $registro->toArray();

        // Con baja lógica el valor sigue disponible en los datos históricos.
        if (static::CAMPO_ACTIVO !== null) {
            $registro->update([static::CAMPO_ACTIVO => false]);
            Auditor::registrar($request, 'desactivar', $registro->getTable(), $registro->getKey(), $antes, $registro->refresh()->toArray());

            return response()->json($registro);
        }

        $registro->delete();
        Auditor::registrar($request, 'eliminar', $registro->getTable(), $registro->getKey(), $antes, null);

        return response()->json(null, 204);
    }

    /** Ajusta los datos validados antes de guardarlos (por ejemplo, convertir geometrías). */
    protected function prepararDatos(array $datos, ?Model $registro): array
    {
        return $datos;
    }

    /** Completa el registro antes de devolverlo en la consulta individual. */
    protected function alMostrar(Model $registro): Model
    {
        return $registro;
    }

    /** Reglas comunes de los catálogos: codigo, nombre, descripcion y activo. */
    protected function reglasBase(string $tabla, ?Model $registro): array
    {
        return [
            'codigo' => [
                'required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/',
                Rule::unique($tabla, 'codigo')->ignore($registro?->getKey()),
            ],
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /** Convierte un GeoJSON en un MultiPolygon PostGIS (SRID 4326) listo para guardar. */
    protected function geometriaDesdeGeoJson(array $geojson): string
    {
        try {
            $fila = DB::selectOne(
                'select ST_AsHEXEWKB(ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))) as geometria',
                [json_encode($geojson)]
            );
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'geometria' => 'El GeoJSON de la geometría no es válido.',
            ]);
        }

        return $fila->geometria;
    }

    /** Agrega al registro su geometría como GeoJSON (atributo {columna}_geojson). */
    protected function conGeoJson(Model $registro, string $columna = 'geometria'): Model
    {
        $geojson = DB::scalar(
            "select ST_AsGeoJSON({$columna}) from {$registro->getTable()} where id = ?",
            [$registro->getKey()]
        );
        $registro->setAttribute($columna . '_geojson', $geojson ? json_decode($geojson, true) : null);

        return $registro;
    }

    private function buscar(string $id): Model
    {
        $modelo = static::MODELO;

        return $modelo::query()->findOrFail($id);
    }

    private function permitir(string $accion): void
    {
        abort_unless(
            in_array($accion, static::ACCIONES, true),
            405,
            'Operación no disponible para este recurso.'
        );
    }
}
