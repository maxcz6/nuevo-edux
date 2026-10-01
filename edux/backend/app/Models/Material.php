<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    protected $table = 'materiales';
    
    protected $fillable = [
        'unidad_id',
        'sesion_id',
        'docente_id',
        'titulo',
        'descripcion',
        'tipo',
        'archivo_url',
        'enlace_externo',
        'visible_desde',
        'visible_hasta',
        'estado',
    ];

    protected $hidden = [
        'archivo_url',
    ];

    protected $appends = [
        'tiene_archivo',
    ];

    protected $casts = [
        'visible_desde' => 'datetime',
        'visible_hasta' => 'datetime',
    ];

    public function getTieneArchivoAttribute(): bool
    {
        return !empty($this->attributes['archivo_url'] ?? null);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Sesion::class, 'sesion_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }
}
