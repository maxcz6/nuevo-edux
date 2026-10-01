<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tarea extends Model
{
    protected $table = 'tareas';
    
    protected $fillable = [
        'grupo_curso_id',
        'unidad_id',
        'docente_id',
        'titulo',
        'descripcion',
        'comentarios_instrucciones',
        'archivo_adjunto_url',
        'archivo_nombre_original',
        'archivo_tipo',
        'enlace_recurso',
        'tipo_entrega',
        'puntaje_maximo',
        'fecha_inicio',
        'fecha_limite',
        'permite_entrega_tardia',
        'estado',
    ];

    // Ocultar la ruta interna de disco en respuestas JSON según AGENTS.md
    protected $hidden = [
        'archivo_adjunto_url',
    ];

    protected $appends = [
        'tiene_archivo_adjunto',
        'archivo_adjunto_nombre',
    ];

    protected $casts = [
        'puntaje_maximo'         => 'decimal:2',
        'fecha_inicio'           => 'datetime',
        'fecha_limite'           => 'datetime',
        'permite_entrega_tardia' => 'boolean',
    ];

    public function getTieneArchivoAdjuntoAttribute(): bool
    {
        return !empty($this->archivo_adjunto_url);
    }

    public function getArchivoAdjuntoNombreAttribute(): ?string
    {
        return $this->archivo_nombre_original;
    }

    public function grupoCurso(): BelongsTo
    {
        return $this->belongsTo(GrupoCurso::class, 'grupo_curso_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(EntregaTarea::class, 'tarea_id');
    }
}
