<?php

namespace App\Http\Controllers\Api;

use App\Models\CriterioGravedad;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CriterioGravedadController extends CatalogoController
{
    protected const MODELO = CriterioGravedad::class;
    protected const CAMPO_ACTIVO = null;          // se elimina; no tiene dependientes
    protected const CAMPO_BUSQUEDA = 'descripcion';
    protected const FILTROS = ['tipo_deterioro_id', 'severidad_id'];

    protected function reglas(?Model $registro = null): array
    {
        return [
            'tipo_deterioro_id' => ['required', 'integer', 'exists:tipos_deterioro,id'],
            'severidad_id' => ['required', 'integer', 'exists:severidades,id'],
            'grupo_condicion' => ['sometimes', 'integer', 'min:1'],
            'parametro' => [
                'nullable',
                Rule::in(['profundidad_mm', 'longitud_afectada_pct', 'cantidad', 'diametro_max_mm', 'area_m2']),
            ],
            'valor_minimo' => ['nullable', 'numeric'],
            'valor_maximo' => [
                'nullable', 'numeric',
                function (string $atributo, mixed $valor, Closure $falla) {
                    $minimo = request()->input('valor_minimo');
                    if ($valor !== null && $minimo !== null && (float) $valor < (float) $minimo) {
                        $falla('El valor máximo debe ser mayor o igual al mínimo.');
                    }
                },
            ],
            'minimo_inclusivo' => ['sometimes', 'boolean'],
            'maximo_inclusivo' => ['sometimes', 'boolean'],
            'descripcion' => ['required', 'string'],
        ];
    }
}
