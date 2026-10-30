<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntervencionReporte extends Model
{
    use HasUuids;

    protected $table = 'intervenciones_reportes';

    public $timestamps = false;

    protected $fillable = [
        'intervencion_id',
        'reporte_id',
    ];

    protected function casts(): array
    {
        return [
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
}
