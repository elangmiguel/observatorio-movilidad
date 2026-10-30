<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntervencionHistorial extends Model
{
    protected $table = 'intervenciones_historial';

    public $timestamps = false;
    const CREATED_AT = 'cambiado_en';
    const UPDATED_AT = null;

    protected $fillable = [
        'intervencion_id',
        'estado_anterior_id',
        'estado_nuevo_id',
        'usuario_id',
        'comentario',
    ];

    protected function casts(): array
    {
        return [
            'cambiado_en' => 'datetime',
        ];
    }

    public function intervencion(): BelongsTo
    {
        return $this->belongsTo(Intervencion::class);
    }

    public function estadoAnterior(): BelongsTo
    {
        return $this->belongsTo(EstadoIntervencion::class);
    }

    public function estadoNuevo(): BelongsTo
    {
        return $this->belongsTo(EstadoIntervencion::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
