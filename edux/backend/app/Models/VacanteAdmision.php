<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacanteAdmision extends Model
{
    protected $table = 'vacantes_admision';

    protected $fillable = [
        'institucion_id',
        'convocatoria_id',
        'programa_id',
        'turno',
        'modalidad',
        'vacantes_ofertadas',
        'vacantes_cubiertas',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new InstitutionScope);
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function convocatoria(): BelongsTo
    {
        return $this->belongsTo(ConvocatoriaAdmision::class, 'convocatoria_id');
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'programa_id');
    }
}
