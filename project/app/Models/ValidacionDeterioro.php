<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ValidacionDeterioro extends Model
{
    use HasUuids;

    protected $table = 'validaciones_deterioros';

    public $timestamps = false;

    protected $fillable = [
        'validacion_id',
        'tipo_deterioro_id',
        'severidad_id',
        'longitud_m',
        'ancho_mm',
        'profundidad_mm',
        'area_m2',
        'cantidad',
        'diametro_max_mm',
        'longitud_afectada_pct',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'longitud_m' => 'decimal:2',
            'ancho_mm' => 'decimal:2',
            'profundidad_mm' => 'decimal:2',
            'area_m2' => 'decimal:2',
            'cantidad' => 'integer',
            'diametro_max_mm' => 'decimal:2',
            'longitud_afectada_pct' => 'decimal:2',
        ];
    }

    public function validacion(): BelongsTo
    {
        return $this->belongsTo(Validacion::class);
    }

    public function tipoDeterioro(): BelongsTo
    {
        return $this->belongsTo(TipoDeterioro::class);
    }

    public function severidad(): BelongsTo
    {
        return $this->belongsTo(Severidad::class);
    }

    /**
     * Gravedad (id de severidades) que proponen los criterios del INVÍAS para las
     * medidas registradas, o null si el deterioro solo tiene criterios descriptivos.
     */
    public function severidadSugerida(): ?int
    {
        $fila = DB::selectOne(
            'select fn_gravedad_sugerida(?::smallint, ?::numeric, ?::numeric, ?::numeric, ?::numeric, ?::numeric) as severidad_id',
            [
                $this->tipo_deterioro_id,
                $this->profundidad_mm,
                $this->longitud_afectada_pct,
                $this->cantidad,
                $this->diametro_max_mm,
                $this->area_m2,
            ]
        );

        return $fila?->severidad_id;
    }
}
