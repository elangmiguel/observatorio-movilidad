<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sincronizacion extends Model
{
    protected $table = 'sincronizaciones';

    public $timestamps = false;
    const CREATED_AT = 'intentado_en';
    const UPDATED_AT = null;

    protected $fillable = [
        'reporte_id',
        'dispositivo_id',
        'usuario_id',
        'codigo_local',
        'resultado',
        'codigo_error',
        'mensaje_error',
        'numero_intento',
    ];

    protected function casts(): array
    {
        return [
            'numero_intento' => 'integer',
            'intentado_en' => 'datetime',
        ];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(Reporte::class);
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
