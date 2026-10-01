<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadAcademica extends Model
{
    protected $table = 'unidades_academicas';

    protected $fillable = [
        'institucion_id',
        'nivel_educativo_id',
        'padre_id',
        'codigo',
        'nombre',
        'tipo',
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

    public function nivelEducativo(): BelongsTo
    {
        return $this->belongsTo(NivelEducativo::class, 'nivel_educativo_id');
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(UnidadAcademica::class, 'padre_id');
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class, 'unidad_academica_id');
    }

    public function planesEstudio(): HasMany
    {
        return $this->hasMany(PlanEstudio::class, 'programa_id');
    }

    public function docentes(): HasMany
    {
        return $this->hasMany(Docente::class, 'unidad_academica_id');
    }

    public function estudiantes(): HasMany
    {
        return $this->hasMany(Estudiante::class, 'unidad_academica_id');
    }
}
