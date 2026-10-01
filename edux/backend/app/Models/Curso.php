<?php
namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    protected $table = 'cursos';
    
    protected $fillable = ['institucion_id', 'unidad_academica_id', 'codigo', 'nombre', 'creditos', 'horas_teoria', 'horas_practica', 'descripcion', 'estado'];

    protected static function booted()
    {
        static::addGlobalScope(new InstitutionScope);
    }

    public function prerrequisitos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_prerrequisitos', 'curso_id', 'prerrequisito_curso_id');
    }

    public function esPrerrequisitoDe(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_prerrequisitos', 'prerrequisito_curso_id', 'curso_id');
    }

    public function gruposCurso(): HasMany
    {
        return $this->hasMany(GrupoCurso::class, 'curso_id');
    }

    public function unidadAcademica(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'unidad_academica_id');
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }
}
