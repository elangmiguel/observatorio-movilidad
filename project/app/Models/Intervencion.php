<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Intervencion extends Model
{
    use HasUuids;

    protected $table = 'intervenciones';

    public $timestamps = false;
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'tipo_intervencion_id',
        'estado_intervencion_id',
        'descripcion',
        'fecha_programada_inicio',
        'fecha_programada_fin',
        'fecha_inicio_real',
        'fecha_fin_real',
        'responsable_usuario_id',
        'ejecutor',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_programada_inicio' => 'date',
            'fecha_programada_fin' => 'date',
            'fecha_inicio_real' => 'date',
            'fecha_fin_real' => 'date',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function tipoIntervencion(): BelongsTo
    {
        return $this->belongsTo(TipoIntervencion::class);
    }

    public function estadoIntervencion(): BelongsTo
    {
        return $this->belongsTo(EstadoIntervencion::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_usuario_id');
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function reportes(): BelongsToMany
    {
        return $this->belongsToMany(Reporte::class, 'intervenciones_reportes', 'intervencion_id', 'reporte_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(IntervencionHistorial::class);
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(EvidenciaIntervencion::class);
    }
}
