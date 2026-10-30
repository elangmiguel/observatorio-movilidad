<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fotografia extends Model
{
    use HasUuids;

    protected $table = 'fotografias';

    public $timestamps = false;
    const CREATED_AT = 'subida_en';
    const UPDATED_AT = null;

    protected $fillable = [
        'reporte_id',
        'es_principal',
        'nombre_archivo',
        'url_almacenamiento',
        'hash_sha256',
        'tamano_bytes',
        'ancho_px',
        'alto_px',
        'tipo_mime',
        'exif_latitud',
        'exif_longitud',
        'exif_fecha_captura',
        'exif_orientacion',
        'distancia_gps_exif_m',
    ];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'tamano_bytes' => 'integer',
            'ancho_px' => 'integer',
            'alto_px' => 'integer',
            'exif_latitud' => 'decimal:6',
            'exif_longitud' => 'decimal:6',
            'exif_fecha_captura' => 'datetime',
            'exif_orientacion' => 'integer',
            'distancia_gps_exif_m' => 'decimal:2',
            'subida_en' => 'datetime',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    public function clasificacionesIa(): HasMany
    {
        return $this->hasMany(ClasificacionIa::class);
    }
}
