<?php

namespace App\Http\Controllers\Api;

use App\Models\TipoDeterioro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class TipoDeterioroController extends CatalogoController
{
    protected const MODELO = TipoDeterioro::class;
    protected const FILTROS = ['categoria'];

    protected function reglas(?Model $registro = null): array
    {
        return $this->reglasBase('tipos_deterioro', $registro) + [
            'codigo_invias' => [
                'nullable', 'string', 'max:5',
                Rule::unique('tipos_deterioro', 'codigo_invias')->ignore($registro?->getKey()),
            ],
            'categoria' => ['nullable', Rule::in(['A', 'B'])],
            'unidad_medida' => ['nullable', Rule::in(['m', 'un', 'm2'])],
            'causas_probables' => ['nullable', 'string'],
        ];
    }
}
