<?php

namespace App\Http\Controllers\Api;

use App\Models\Barrio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class BarrioController extends CatalogoController
{
    protected const MODELO = Barrio::class;
    protected const PERMISO = 'territorio.gestionar';
    protected const FILTROS = ['comuna_id'];

    protected function reglas(?Model $registro = null): array
    {
        $comunaId = request()->input('comuna_id', $registro?->comuna_id);

        return [
            'comuna_id' => ['required', 'integer', 'exists:comunas,id'],
            'codigo' => ['nullable', 'string', 'max:10', Rule::unique('barrios', 'codigo')->ignore($registro?->getKey())],
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('barrios', 'nombre')->where('comuna_id', $comunaId)->ignore($registro?->getKey()),
            ],
            'activo' => ['sometimes', 'boolean'],
            // Límite del barrio en GeoJSON (Polygon o MultiPolygon, WGS84).
            'geometria' => [$registro ? 'sometimes' : 'required', 'array'],
            'geometria.type' => ['required_with:geometria', Rule::in(['Polygon', 'MultiPolygon'])],
            'geometria.coordinates' => ['required_with:geometria', 'array'],
        ];
    }

    protected function prepararDatos(array $datos, ?Model $registro): array
    {
        if (isset($datos['geometria'])) {
            $datos['geometria'] = $this->geometriaDesdeGeoJson($datos['geometria']);
        }

        return $datos;
    }

    protected function alMostrar(Model $registro): Model
    {
        return $this->conGeoJson($registro);
    }
}
