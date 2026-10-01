<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanCurso extends Model
{
    protected $table = 'plan_cursos';

    public $timestamps = false;

    protected $fillable = [
        'plan_estudio_id',
        'curso_id',
        'ciclo_id',
        'creditos',
        'horas_teoria',
        'horas_practica',
        'tipo',
        'orden',
    ];

    protected $casts = [
        'creditos'       => 'decimal:1',
        'horas_teoria'   => 'integer',
        'horas_practica' => 'integer',
        'orden'          => 'integer',
    ];

    public function planEstudio(): BelongsTo
    {
        return $this->belongsTo(PlanEstudio::class, 'plan_estudio_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(CicloPlan::class, 'ciclo_id');
    }
}
