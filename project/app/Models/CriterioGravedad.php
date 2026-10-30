<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterioGravedad extends Model
{
    protected $table = 'criterios_gravedad';

    public $timestamps = false;

    protected $fillable = [
        'tipo_deterioro_id',
        'severidad_id',
        'grupo_condicion',
        'parametro',
        'valor_minimo',
        'valor_maximo',
        'minimo_inclusivo',
        'maximo_inclusivo',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'grupo_condicion' => 'integer',
            'valor_minimo' => 'decimal:2',
            'valor_maximo' => 'decimal:2',
            'minimo_inclusivo' => 'boolean',
            'maximo_inclusivo' => 'boolean',
        ];
    }

    public function tipoDeterioro(): BelongsTo
    {
        return $this->belongsTo(TipoDeterioro::class);
    }

    public function severidad(): BelongsTo
    {
        return $this->belongsTo(Severidad::class);
    }
}
