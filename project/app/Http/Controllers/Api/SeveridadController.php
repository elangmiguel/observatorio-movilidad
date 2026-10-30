<?php

namespace App\Http\Controllers\Api;

use App\Models\Severidad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SeveridadController extends CatalogoController
{
    protected const MODELO = Severidad::class;
    protected const ORDEN = 'nivel';

    protected function reglas(?Model $registro = null): array
    {
        return $this->reglasBase('severidades', $registro) + [
            'nivel' => [
                'required', 'integer', 'between:1,3',
                Rule::unique('severidades', 'nivel')->ignore($registro?->getKey()),
            ],
        ];
    }
}
