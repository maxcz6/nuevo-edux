<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seccion extends Model
{
    protected $table = 'secciones';

    protected $fillable = [
        'institucion_id',
        'unidad_academica_id',
        'periodo_id',
        'grado',
        'nombre',
        'tutor_docente_id',
        'capacidad',
        'estado',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope());
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'periodo_id');
    }

    public function unidadAcademica(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'unidad_academica_id');
    }

    public function tutorDocente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'tutor_docente_id');
    }

    public function gruposCurso(): HasMany
    {
        return $this->hasMany(GrupoCurso::class, 'seccion_id');
    }

    public function matriculasSeccion(): HasMany
    {
        return $this->hasMany(MatriculaSeccion::class, 'seccion_id');
    }
}
