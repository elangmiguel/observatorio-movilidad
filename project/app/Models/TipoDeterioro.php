<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoDeterioro extends Model
{
    protected $table = 'tipos_deterioro';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'codigo_invias',
        'nombre',
        'categoria',
        'unidad_medida',
        'descripcion',
        'causas_probables',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
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
