<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'usuarios';

    // El id es el de la cuenta de Supabase Auth: lo crea el disparador, no Eloquent.
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';
    const DELETED_AT = 'eliminado_en';

    protected $fillable = [
        'tipo_documento_id',
        'numero_documento',
        'nombres',
        'apellidos',
        'correo',
        'telefono',
        'entidad',
        'matricula_profesional',
        'activo',
        'ultimo_acceso_en',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultimo_acceso_en' => 'datetime',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
            'eliminado_en' => 'datetime',
        ];
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'usuarios_roles', 'usuario_id', 'rol_id')
            ->withPivot('asignado_por_usuario_id', 'asignado_en', 'revocado_en')
            ->wherePivotNull('revocado_en');
    }

    public function asignacionesRol(): HasMany
    {
        return $this->hasMany(UsuarioRol::class);
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
    }

    public function validaciones(): HasMany
    {
        return $this->hasMany(Validacion::class);
    }

    public function priorizaciones(): HasMany
    {
        return $this->hasMany(Priorizacion::class);
    }

    public function intervencionesRegistradas(): HasMany
    {
        return $this->hasMany(Intervencion::class, 'usuario_id');
    }

    public function intervencionesAsignadas(): HasMany
    {
        return $this->hasMany(Intervencion::class, 'responsable_usuario_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(EvidenciaIntervencion::class);
    }

    public function sincronizaciones(): HasMany
    {
        return $this->hasMany(Sincronizacion::class);
    }

    public function auditorias(): HasMany
    {
        return $this->hasMany(Auditoria::class);
    }

    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => trim($this->nombres . ' ' . $this->apellidos));
    }

    public function tieneRol(string $codigo): bool
    {
        return $this->roles()->where('roles.codigo', $codigo)->exists();
    }

    public function tienePermiso(string $codigo): bool
    {
        return $this->roles()
            ->where('roles.activo', true)
            ->whereHas('permisos', fn ($query) => $query->where('permisos.codigo', $codigo))
            ->exists();
    }
}
