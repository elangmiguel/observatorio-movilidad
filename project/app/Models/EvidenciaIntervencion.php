<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenciaIntervencion extends Model
{
    use HasUuids;

    protected $table = 'evidencias_intervencion';

    public $timestamps = false;
    const CREATED_AT = 'subida_en';
    const UPDATED_AT = null;
    const MOMENTO_ANTES = 'antes';
    const MOMENTO_DURANTE = 'durante';
    const MOMENTO_DESPUES = 'despues';

    protected $fillable = [
        'intervencion_id',
        'reporte_id',
        'momento',
        'nombre_archivo',
        'url_almacenamiento',
        'hash_sha256',
        'tamano_bytes',
        'tipo_mime',
        'latitud',
        'longitud',
        'descripcion',
        'tomada_en',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'latitud' => 'decimal:6',
            'longitud' => 'decimal:6',
            'tomada_en' => 'datetime',
            'subida_en' => 'datetime',
        ];
    }

    public function intervencion(): BelongsTo
    {
        return $this->belongsTo(Intervencion::class);
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
