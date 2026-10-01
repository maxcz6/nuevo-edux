<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespuestaEstudiante extends Model
{
    protected $table = 'respuestas_estudiante';

    public $timestamps = false;

    protected $fillable = [
        'intento_id',
        'pregunta_id',
        'opcion_id',
        'respuesta_texto',
        'puntaje_obtenido',
        'revisado_por_docente',
    ];

    protected $casts = [
        'puntaje_obtenido'      => 'decimal:2',
        'revisado_por_docente' => 'boolean',
    ];

    public function intento(): BelongsTo
    {
        return $this->belongsTo(IntentoEvaluacion::class, 'intento_id');
    }

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(Pregunta::class, 'pregunta_id');
    }

    public function opcion(): BelongsTo
    {
        return $this->belongsTo(OpcionPregunta::class, 'opcion_id');
    }
}
