<?php

namespace App\Http\Controllers\Api;

use App\Models\EstadoIntervencion;
use Illuminate\Database\Eloquent\Model;

class EstadoIntervencionController extends CatalogoController
{
    protected const MODELO = EstadoIntervencion::class;

    // Los códigos de estado los usan los disparadores y el flujo de seguimiento: no se crean ni se dan de baja.
    public const ACCIONES = ['index', 'show', 'update'];

    protected function reglas(?Model $registro = null): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
