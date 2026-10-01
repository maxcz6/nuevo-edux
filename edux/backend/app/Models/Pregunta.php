<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pregunta extends Model
{
    protected $table = 'preguntas';

    public $timestamps = false;

    protected $fillable = [
        'evaluacion_id',
        'tipo',
        'enunciado',
        'puntaje',
        'orden',
    ];

    protected $casts = [
        'puntaje' => 'decimal:2',
        'orden'   => 'integer',
    ];

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class, 'evaluacion_id');
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(OpcionPregunta::class, 'pregunta_id')->orderBy('orden', 'asc');
    }

    public function respuestasEstudiantes(): HasMany
    {
        return $this->hasMany(RespuestaEstudiante::class, 'pregunta_id');
    }
}
