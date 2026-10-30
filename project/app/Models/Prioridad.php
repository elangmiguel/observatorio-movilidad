<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prioridad extends Model
{
    protected $table = 'prioridades';

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

    public function priorizaciones(): HasMany
    {
        return $this->hasMany(Priorizacion::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
