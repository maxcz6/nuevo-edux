<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluacion extends Model
{
    protected $table = 'evaluaciones';

    protected $fillable = [
        'grupo_curso_id',
        'unidad_id',
        'docente_id',
        'titulo',
        'instrucciones',
        'archivo_adjunto',
        'archivo_nombre_original',
        'tipo_evaluacion',
        'permite_archivo_estudiante',
        'puntaje_maximo',
        'duracion_minutos',
        'intentos_permitidos',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected $hidden = [
        'archivo_adjunto',
    ];

    protected $appends = [
        'tiene_archivo_adjunto',
    ];

    protected $casts = [
        'puntaje_maximo'             => 'decimal:2',
        'duracion_minutos'           => 'integer',
        'intentos_permitidos'        => 'integer',
        'permite_archivo_estudiante' => 'boolean',
        'fecha_inicio'               => 'datetime',
        'fecha_fin'                  => 'datetime',
    ];

    public function getTieneArchivoAdjuntoAttribute(): bool
    {
        return !empty($this->attributes['archivo_adjunto'] ?? null);
    }

    public function grupoCurso(): BelongsTo
    {
        return $this->belongsTo(GrupoCurso::class, 'grupo_curso_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    public function preguntas(): HasMany
    {
        return $this->hasMany(Pregunta::class, 'evaluacion_id')->orderBy('orden', 'asc');
    }

    public function intentos(): HasMany
    {
        return $this->hasMany(IntentoEvaluacion::class, 'evaluacion_id');
    }
}
