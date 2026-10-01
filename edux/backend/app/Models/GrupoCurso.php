<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoCurso extends Model
{
    protected $table = 'grupos_curso';

    protected $fillable = [
        'institucion_id',
        'curso_id',
        'plan_curso_id',
        'periodo_id',
        'docente_id',
        'seccion_id',
        'codigo_grupo',
        'turno',
        'aula',
        'cupo',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'cerrado_at',
        'cerrado_por',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
        'cupo'         => 'integer',
        'cerrado_at'   => 'datetime',
    ];

    protected $appends = [
        'cupos',
        'cupos_disponibles',
    ];

    public function getCuposAttribute(): int
    {
        return (int) ($this->cupo ?? 0);
    }

    public function getCuposDisponiblesAttribute(): int
    {
        $matriculados = $this->matriculas_count ?? (
            $this->relationLoaded('matriculas')
                ? $this->matriculas->where('estado', 'MATRICULADO')->count()
                : 0
        );

        return max(0, (int) ($this->cupo ?? 0) - (int) $matriculados);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope());
    }

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'periodo_id');
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class, 'seccion_id');
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'grupo_curso_id');
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class, 'grupo_curso_id');
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'grupo_curso_id');
    }

    public function avisos(): HasMany
    {
        return $this->hasMany(Aviso::class, 'grupo_curso_id');
    }

    public function calificaciones(): HasMany
    {
        return $this->hasMany(Calificacion::class, 'grupo_curso_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'grupo_curso_id');
    }

    public function solicitudesInclusion(): HasMany
    {
        return $this->hasMany(SolicitudInclusionGrupo::class, 'grupo_curso_id');
    }
}
