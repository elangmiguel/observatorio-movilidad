<?php

namespace App\Http\Controllers\Api;

use App\Models\Comuna;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ComunaController extends CatalogoController
{
    protected const MODELO = Comuna::class;
    protected const PERMISO = 'territorio.gestionar';
    protected const CAMPO_ACTIVO = 'activa';

    protected function reglas(?Model $registro = null): array
    {
        return [
            'codigo' => ['required', 'string', 'max:10', Rule::unique('comunas', 'codigo')->ignore($registro?->getKey())],
            'nombre' => ['required', 'string', 'max:100'],
            'activa' => ['sometimes', 'boolean'],
            // Límite de la comuna en GeoJSON (Polygon o MultiPolygon, WGS84).
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
