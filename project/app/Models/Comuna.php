<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comuna extends Model
{
    protected $table = 'comunas';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'activa',
        'geometria',
    ];

    protected $hidden = [
        'geometria',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function barrios(): HasMany
    {
        return $this->hasMany(Barrio::class);
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
    }
}
