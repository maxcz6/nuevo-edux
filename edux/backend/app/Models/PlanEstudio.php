<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanEstudio extends Model
{
    protected $table = 'planes_estudio';

    protected $fillable = [
        'institucion_id',
        'programa_id',
        'codigo',
        'nombre',
        'anio_inicio',
        'fecha_vigencia',
        'fecha_fin_vigencia',
        'total_ciclos',
        'total_creditos',
        'estado',
    ];

    protected $casts = [
        'anio_inicio'        => 'integer',
        'fecha_vigencia'     => 'date:Y-m-d',
        'fecha_fin_vigencia' => 'date:Y-m-d',
        'total_ciclos'       => 'integer',
        'total_creditos'     => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope);
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'programa_id');
    }

    public function ciclos(): HasMany
    {
        return $this->hasMany(CicloPlan::class, 'plan_estudio_id')->orderBy('numero', 'asc');
    }

    public function planCursos(): HasMany
    {
        return $this->hasMany(PlanCurso::class, 'plan_estudio_id');
    }

    public function estudiantesProgramas(): HasMany
    {
        return $this->hasMany(EstudiantePrograma::class, 'plan_estudio_id');
    }
}
