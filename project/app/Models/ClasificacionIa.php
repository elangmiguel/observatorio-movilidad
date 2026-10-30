<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClasificacionIa extends Model
{
    use HasUuids;

    protected $table = 'clasificaciones_ia';

    public $timestamps = false;
    const CREATED_AT = 'procesado_en';
    const UPDATED_AT = null;

    protected $fillable = [
        'reporte_id',
        'fotografia_id',
        'tipo_deterioro_id',
        'severidad_id',
        'confianza',
        'confianza_severidad',
        'resultado_segmentacion',
        'version_modelo',
    ];

    protected function casts(): array
    {
        return [
            'confianza' => 'decimal:4',
            'confianza_severidad' => 'decimal:4',
            'resultado_segmentacion' => 'array',
            'procesado_en' => 'datetime',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    public function fotografia(): BelongsTo
    {
        return $this->belongsTo(Fotografia::class);
    }

    public function tipoDeterioro(): BelongsTo
    {
        return $this->belongsTo(TipoDeterioro::class);
    }

    public function severidad(): BelongsTo
    {
        return $this->belongsTo(Severidad::class);
    }

    public function validaciones(): HasMany
    {
        return $this->hasMany(Validacion::class, 'clasificacion_ia_id');
    }
}
