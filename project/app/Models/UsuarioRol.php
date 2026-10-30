<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioRol extends Model
{
    use HasUuids;

    protected $table = 'usuarios_roles';

    public $timestamps = false;
    const CREATED_AT = 'asignado_en';
    const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id',
        'rol_id',
        'asignado_por_usuario_id',
        'asignado_en',
        'revocado_en',
    ];

    protected function casts(): array
    {
        return [
            'asignado_en' => 'datetime',
            'revocado_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'asignado_por_usuario_id');
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('revocado_en');
    }
}
