<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorarioGrupo extends Model
{
    protected $table = 'horarios_grupo';

    protected $fillable = [
        'institucion_id',
        'grupo_curso_id',
        'ambiente_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new InstitutionScope);
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function grupoCurso(): BelongsTo
    {
        return $this->belongsTo(GrupoCurso::class, 'grupo_curso_id');
    }

    public function ambiente(): BelongsTo
    {
        return $this->belongsTo(Ambiente::class, 'ambiente_id');
    }
}
