<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermisoRol extends Model
{
    use HasUuids;

    protected $table = 'permisos_rol';

    public $timestamps = false;

    protected $fillable = [
        'rol_id',
        'permiso_id',
    ];

    protected function casts(): array
    {
        return [
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function permiso(): BelongsTo
    {
        return $this->belongsTo(Permiso::class);
    }
}
