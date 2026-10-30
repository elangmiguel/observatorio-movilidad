<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Validacion extends Model
{
    use HasUuids;

    protected $table = 'validaciones';

    public $timestamps = false;
    const CREATED_AT = 'validado_en';
    const UPDATED_AT = null;
    const DECISION_APROBADO = 'aprobado';
    const DECISION_CORREGIDO = 'corregido';
    const DECISION_RECHAZADO = 'rechazado';

    protected $fillable = [
        'reporte_id',
        'usuario_id',
        'clasificacion_ia_id',
        'decision',
        'tipo_deterioro_id',
        'severidad_id',
        'tipo_superficie_id',
        'motivo_rechazo',
        'observacion',
        'es_vigente',
        'validado_en',
    ];

    protected function casts(): array
    {
        return [
            'es_vigente' => 'boolean',
            'validado_en' => 'datetime',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function clasificacionIa(): BelongsTo
    {
        return $this->belongsTo(ClasificacionIa::class);
    }

    public function tipoDeterioro(): BelongsTo
    {
        return $this->belongsTo(TipoDeterioro::class);
    }

    public function severidad(): BelongsTo
    {
        return $this->belongsTo(Severidad::class);
    }

    public function tipoSuperficie(): BelongsTo
    {
        return $this->belongsTo(TipoSuperficie::class);
    }

    public function deterioros(): HasMany
    {
        return $this->hasMany(ValidacionDeterioro::class);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('es_vigente', true);
    }
}
