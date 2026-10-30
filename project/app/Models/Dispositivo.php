<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispositivo extends Model
{
    use HasUuids;

    protected $table = 'dispositivos';

    public $timestamps = false;
    const CREATED_AT = 'primer_registro_en';
    const UPDATED_AT = null;

    protected $fillable = [
        'codigo_instalacion',
        'plataforma',
        'modelo',
        'version_app',
        'ultimo_envio_en',
    ];

    protected function casts(): array
    {
        return [
            'primer_registro_en' => 'datetime',
            'ultimo_envio_en' => 'datetime',
        ];
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
    }

    public function sincronizaciones(): HasMany
    {
        return $this->hasMany(Sincronizacion::class);
    }
}
