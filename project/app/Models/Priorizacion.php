<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Priorizacion extends Model
{
    use HasUuids;

    protected $table = 'priorizaciones';

    public $timestamps = false;
    const CREATED_AT = 'asignada_en';
    const UPDATED_AT = null;

    protected $fillable = [
        'reporte_id',
        'prioridad_id',
        'puntaje',
        'justificacion',
        'usuario_id',
        'es_vigente',
    ];

    protected function casts(): array
    {
        return [
            'puntaje' => 'decimal:2',
            'es_vigente' => 'boolean',
            'asignada_en' => 'datetime',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    public function prioridad(): BelongsTo
    {
        return $this->belongsTo(Prioridad::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('es_vigente', true);
    }
}
