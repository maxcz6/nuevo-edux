<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuarios';

    protected $fillable = [
        'institucion_id',
        'unidad_academica_id',
        'username',
        'email',
        'password',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'documento_tipo',
        'documento_numero',
        'telefono',
        'foto',
        'estado',
        'email_verified_at',
        'ultimo_acceso_at',
    ];

    // La ruta interna de la foto nunca debe serializarse en respuestas API.
    protected $hidden = ['password', 'remember_token', 'foto'];

    protected $casts = [
        'password'          => 'hashed',
        'email_verified_at' => 'datetime',
        'ultimo_acceso_at'  => 'datetime',
    ];

    protected $appends = ['numero_documento', 'tipo_documento'];

    public function getNumeroDocumentoAttribute(): ?string
    {
        return $this->attributes['documento_numero'] ?? null;
    }

    public function setNumeroDocumentoAttribute($value): void
    {
        $this->attributes['documento_numero'] = $value;
    }

    public function getTipoDocumentoAttribute(): ?string
    {
        return $this->attributes['documento_tipo'] ?? null;
    }

    public function setTipoDocumentoAttribute($value): void
    {
        $this->attributes['documento_tipo'] = $value;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope());
    }

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol', 'usuario_id', 'rol_id');
    }

    public function docente(): HasOne
    {
        return $this->hasOne(Docente::class, 'usuario_id');
    }

    public function estudiante(): HasOne
    {
        return $this->hasOne(Estudiante::class, 'usuario_id');
    }

    public function apoderado(): HasOne
    {
        return $this->hasOne(Apoderado::class, 'usuario_id');
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function unidadAcademica(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'unidad_academica_id');
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class, 'usuario_id');
    }

    // ── Helpers de autorización ───────────────────────────────────────────────

    /**
     * Verifica si el usuario tiene un permiso específico (por código).
     * Carga los roles y permisos si no están cargados.
     */
    public function hasPermission(string $codigo): bool
    {
        $this->loadMissing('roles.permisos');

        foreach ($this->roles as $rol) {
            if ($rol->permisos->contains('codigo', $codigo)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica si el usuario tiene un rol específico (por nombre).
     */
    public function hasRole(string $nombre): bool
    {
        $this->loadMissing('roles');
        return $this->roles->contains('nombre', $nombre);
    }

    /**
     * Verifica si el usuario tiene al menos uno de los roles indicados.
     */
    public function hasAnyRole(array $nombres): bool
    {
        $this->loadMissing('roles');
        return $this->roles->contains(fn ($rol) => in_array($rol->nombre, $nombres));
    }

    /**
     * Retorna todos los códigos de permisos del usuario (de todos sus roles).
     */
    public function allPermissions(): Collection
    {
        $this->loadMissing('roles.permisos');
        return $this->roles->flatMap(fn ($r) => $r->permisos)->pluck('codigo')->unique();
    }
}
