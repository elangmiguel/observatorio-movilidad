<?php

namespace App\Http\Controllers\Api;

use App\Models\Campana;
use Illuminate\Database\Eloquent\Model;

class CampanaController extends CatalogoController
{
    protected const MODELO = Campana::class;
    protected const PERMISO = 'campanas.gestionar';
    protected const CAMPO_ACTIVO = 'activa';
    protected const ORDEN = 'fecha_inicio';

    protected function reglas(?Model $registro = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'activa' => ['sometimes', 'boolean'],
        ];
    }
}
