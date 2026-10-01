<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    protected $table = 'asistencias';

    public $timestamps = false;

    protected $fillable = [
        'grupo_curso_id',
        'seccion_id',
        'estudiante_id',
        'fecha',
        'estado_asistencia',
        'observacion',
        'registrado_por',
        'created_at',
    ];

    protected $casts = [
        'fecha'      => 'date:Y-m-d',
        'created_at' => 'datetime',
    ];

    public function grupoCurso(): BelongsTo
    {
        return $this->belongsTo(GrupoCurso::class, 'grupo_curso_id');
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class, 'seccion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'registrado_por');
    }
}
