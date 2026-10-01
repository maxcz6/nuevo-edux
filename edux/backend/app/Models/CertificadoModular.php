<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoModular extends Model
{
    protected $table = 'certificados_modulares';

    protected $fillable = [
        'institucion_id',
        'estudiante_id',
        'programa_id',
        'numero_modulo',
        'nombre_modulo',
        'resolucion_directoral',
        'horas_teoria',
        'horas_practica',
        'creditos',
        'horas_efsrt',
        'codigo_verificacion',
        'fecha_emision',
        'estado',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'numero_modulo' => 'integer',
        'horas_teoria' => 'integer',
        'horas_practica' => 'integer',
        'creditos' => 'float',
        'horas_efsrt' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope);
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'programa_id');
    }
}
