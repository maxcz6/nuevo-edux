<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CicloPlan extends Model
{
    protected $table = 'ciclos_plan';

    public $timestamps = false;

    protected $fillable = [
        'plan_estudio_id',
        'numero',
        'nombre',
    ];

    protected $casts = [
        'numero' => 'integer',
    ];

    public function planEstudio(): BelongsTo
    {
        return $this->belongsTo(PlanEstudio::class, 'plan_estudio_id');
    }

    public function planCursos(): HasMany
    {
        return $this->hasMany(PlanCurso::class, 'ciclo_id');
    }
}
