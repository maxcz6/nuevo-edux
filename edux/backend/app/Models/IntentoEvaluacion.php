<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntentoEvaluacion extends Model
{
    protected $table = 'intentos_evaluacion';

    public $timestamps = false;

    protected $fillable = [
        'evaluacion_id',
        'estudiante_id',
        'numero_intento',
        'fecha_inicio',
        'fecha_fin',
        'nota',
        'archivo_estudiante',
        'archivo_estudiante_nombre',
        'comentario_estudiante',
        'retroalimentacion',
        'estado',
    ];

    protected $hidden = [
        'archivo_estudiante',
    ];

    protected $appends = [
        'tiene_archivo_estudiante',
    ];

    protected $casts = [
        'numero_intento' => 'integer',
        'fecha_inicio'   => 'datetime',
        'fecha_fin'      => 'datetime',
        'nota'           => 'decimal:2',
    ];

    public function getTieneArchivoEstudianteAttribute(): bool
    {
        return !empty($this->attributes['archivo_estudiante'] ?? null);
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class, 'evaluacion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaEstudiante::class, 'intento_id');
    }
}
