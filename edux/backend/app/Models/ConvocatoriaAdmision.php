<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConvocatoriaAdmision extends Model
{
    protected $table = 'convocatorias_admision';

    protected $fillable = [
        'institucion_id',
        'periodo_id',
        'codigo',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'fecha_examen',
        'puntaje_minimo_aprobatorio',
        'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_examen' => 'date',
        'puntaje_minimo_aprobatorio' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new InstitutionScope);
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'periodo_id');
    }

    public function vacantes(): HasMany
    {
        return $this->hasMany(VacanteAdmision::class, 'convocatoria_id');
    }

    public function postulantes(): HasMany
    {
        return $this->hasMany(Postulante::class, 'convocatoria_id');
    }
}
