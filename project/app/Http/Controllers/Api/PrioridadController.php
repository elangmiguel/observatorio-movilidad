<?php

namespace App\Http\Controllers\Api;

use App\Models\Prioridad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class PrioridadController extends CatalogoController
{
    protected const MODELO = Prioridad::class;
    protected const ORDEN = 'nivel';

    protected function reglas(?Model $registro = null): array
    {
        return $this->reglasBase('prioridades', $registro) + [
            'nivel' => [
                'required', 'integer', 'between:1,4',
                Rule::unique('prioridades', 'nivel')->ignore($registro?->getKey()),
            ],
        ];
    }
}
