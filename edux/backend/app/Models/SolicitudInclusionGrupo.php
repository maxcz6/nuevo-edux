<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudInclusionGrupo extends Model
{
    protected $table = 'solicitudes_inclusion_grupo';

    protected $fillable = [
        'institucion_id',
        'grupo_curso_id',
        'docente_id',
        'estudiante_id',
        'coordinador_id',
        'motivo',
        'observaciones_coordinador',
        'estado',
        'fecha_resolucion',
    ];

    protected $casts = [
        'fecha_resolucion' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope());
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function grupoCurso(): BelongsTo
    {
        return $this->belongsTo(GrupoCurso::class, 'grupo_curso_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'coordinador_id');
    }
}
