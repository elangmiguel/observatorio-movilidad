<?php

namespace App\Http\Controllers\Api;

use App\Models\Permiso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class PermisoController extends CatalogoController
{
    protected const MODELO = Permiso::class;
    protected const PERMISO = 'roles.gestionar';
    protected const CAMPO_ACTIVO = null;
    protected const ORDEN = 'codigo';
    protected const FILTROS = ['modulo'];

    // Un permiso en uso no se elimina: lo referencian los roles.
    public const ACCIONES = ['index', 'show', 'store', 'update'];

    protected function reglas(?Model $registro = null): array
    {
        return [
            'codigo' => [
                'required', 'string', 'max:60', 'regex:/^[a-z_]+\.[a-z_]+$/',
                Rule::unique('permisos', 'codigo')->ignore($registro?->getKey()),
            ],
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'modulo' => ['required', 'string', 'max:40'],
        ];
    }
}
