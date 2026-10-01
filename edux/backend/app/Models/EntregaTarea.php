<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntregaTarea extends Model
{
    protected $table = 'entregas_tareas';
    
    protected $fillable = [
        'tarea_id',
        'estudiante_id',
        'archivo_url',
        'archivo_original',
        'archivo_nombre',
        'comentario_estudiante',
        'fecha_entrega',
        'es_tardia',
        'nota',
        'comentario_docente',
        'estado'
    ];

    protected $hidden = [
        'archivo_url',
    ];

    protected $appends = [
        'tiene_archivo',
    ];

    protected $casts = [
        'nota' => 'decimal:2',
        'fecha_entrega' => 'datetime',
        'es_tardia' => 'boolean',
    ];

    public function getArchivoOriginalAttribute($value)
    {
        if ($value) return $value;
        if (!empty($this->attributes['archivo_url'])) {
            return basename($this->attributes['archivo_url']);
        }
        return null;
    }

    public function getTieneArchivoAttribute(): bool
    {
        return !empty($this->attributes['archivo_url'] ?? null);
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class, 'tarea_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }
}
