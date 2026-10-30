<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Severidad extends Model
{
    protected $table = 'severidades';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'nivel',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'nivel' => 'integer',
        ];
    }

    public function clasificacionesIa(): HasMany
    {
        return $this->hasMany(ClasificacionIa::class);
    }

    public function validaciones(): HasMany
    {
        return $this->hasMany(Validacion::class);
    }

    public function validacionesDeterioros(): HasMany
    {
        return $this->hasMany(ValidacionDeterioro::class);
    }

    public function criteriosGravedad(): HasMany
    {
        return $this->hasMany(CriterioGravedad::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
