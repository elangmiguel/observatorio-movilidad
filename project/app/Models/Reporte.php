<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reporte extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'reportes';

    public $timestamps = false;
    const CREATED_AT = 'recibido_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'codigo_local',
        'dispositivo_id',
        'usuario_id',
        'campana_id',
        'latitud',
        'longitud',
        'precision_gps_m',
        'fuente_ubicacion',
        'fecha_captura',
        'via_sector',
        'barrio_reportado',
        'punto_referencia',
        'tipo_deterioro_id',
        'tipo_superficie_id',
        'descripcion_ciudadano',
        'estado_reporte_id',
        'estado_ia',
        'reporte_principal_id',
    ];

    protected $hidden = [
        'ubicacion',
    ];

    protected function casts(): array
    {
        return [
            'latitud' => 'decimal:6',
            'longitud' => 'decimal:6',
            'precision_gps_m' => 'decimal:2',
            'fecha_captura' => 'datetime',
            'recibido_en' => 'datetime',
            'actualizado_en' => 'datetime',
            'eliminado_en' => 'datetime',
        ];
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function campana(): BelongsTo
    {
        return $this->belongsTo(Campana::class);
    }

    public function comuna(): BelongsTo
    {
        return $this->belongsTo(Comuna::class);
    }

    public function barrio(): BelongsTo
    {
        return $this->belongsTo(Barrio::class);
    }

    public function tipoDeterioro(): BelongsTo
    {
        return $this->belongsTo(TipoDeterioro::class);
    }

    public function tipoSuperficie(): BelongsTo
    {
        return $this->belongsTo(TipoSuperficie::class);
    }

    public function estadoReporte(): BelongsTo
    {
        return $this->belongsTo(EstadoReporte::class);
    }

    public function reportePrincipal(): BelongsTo
    {
        return $this->belongsTo(Reporte::class, 'reporte_principal_id');
    }

    public function duplicados(): HasMany
    {
        return $this->hasMany(Reporte::class, 'reporte_principal_id');
    }

    public function fotografias(): HasMany
    {
        return $this->hasMany(Fotografia::class);
    }

    public function fotografiaPrincipal(): HasOne
    {
        return $this->hasOne(Fotografia::class)
            ->where('es_principal', true);
    }

    public function clasificacionesIa(): HasMany
    {
        return $this->hasMany(ClasificacionIa::class);
    }

    public function ultimaClasificacionIa(): HasOne
    {
        return $this->hasOne(ClasificacionIa::class)
            ->latestOfMany('procesado_en');
    }

    public function validaciones(): HasMany
    {
        return $this->hasMany(Validacion::class);
    }

    public function validacionVigente(): HasOne
    {
        return $this->hasOne(Validacion::class)
            ->where('es_vigente', true);
    }

    public function priorizaciones(): HasMany
    {
        return $this->hasMany(Priorizacion::class);
    }

    public function priorizacionVigente(): HasOne
    {
        return $this->hasOne(Priorizacion::class)
            ->where('es_vigente', true);
    }

    public function intervenciones(): BelongsToMany
    {
        return $this->belongsToMany(Intervencion::class, 'intervenciones_reportes', 'reporte_id', 'intervencion_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(EvidenciaIntervencion::class);
    }

    public function sincronizaciones(): HasMany
    {
        return $this->hasMany(Sincronizacion::class);
    }

    public function scopePendientesRevision(Builder $query): Builder
    {
        return $query->whereHas(
            'estadoReporte',
            fn (Builder $estado) => $estado->whereIn('codigo', ['recibido', 'en_revision'])
        );
    }

    public function scopeCercaDe(Builder $query, float $latitud, float $longitud, float $metros): Builder
    {
        return $query->whereRaw(
            'ST_DWithin(reportes.ubicacion::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
            [$longitud, $latitud, $metros]
        );
    }
}
