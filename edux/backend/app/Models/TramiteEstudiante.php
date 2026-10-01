<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TramiteEstudiante extends Model
{
    protected $table = 'tramites_estudiante';

    protected $fillable = [
        'institucion_id',
        'estudiante_id',
        'tipo_tramite',
        'programa_origen_id',
        'programa_destino_id',
        'resolucion_directoral',
        'fecha_solicitud',
        'fecha_resolucion',
        'motivo',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'fecha_resolucion' => 'date',
    ];

    protected static function booted()
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

    public function programaOrigen(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'programa_origen_id');
    }

    public function programaDestino(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'programa_destino_id');
    }
}
