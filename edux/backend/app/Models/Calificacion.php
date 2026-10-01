<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Calificacion extends Model
{
    protected $table = 'calificaciones';
    
    protected $fillable = [
        'grupo_curso_id',
        'estudiante_id',
        'registrado_por',
        'tipo_origen',
        'origen_id',
        'nombre_rubro',
        'nota',
        'peso_porcentual'
    ];

    protected $casts = [
        'nota' => 'decimal:2',
        'peso_porcentual' => 'decimal:2',
    ];

    public function grupoCurso(): BelongsTo
    {
        return $this->belongsTo(GrupoCurso::class, 'grupo_curso_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'registrado_por');
    }
}
