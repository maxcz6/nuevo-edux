<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Postulante extends Model
{
    protected $table = 'postulantes';

    protected $fillable = [
        'institucion_id',
        'convocatoria_id',
        'programa_id',
        'turno',
        'modalidad',
        'codigo_postulante',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'documento_tipo',
        'documento_numero',
        'email',
        'telefono',
        'puntaje_examen',
        'orden_merito',
        'condicion',
        'usuario_id',
        'estudiante_id',
    ];

    protected $casts = [
        'puntaje_examen' => 'decimal:2',
        'orden_merito' => 'integer',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellido_paterno} " . ($this->apellido_materno ?? ''));
    }
}
