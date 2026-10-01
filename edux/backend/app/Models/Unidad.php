<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unidad extends Model
{
    protected $table = 'unidades';
    
    protected $fillable = ['grupo_curso_id', 'titulo', 'descripcion', 'orden', 'estado'];

    public function grupoCurso(): BelongsTo
    {
        return $this->belongsTo(GrupoCurso::class, 'grupo_curso_id');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class, 'unidad_id');
    }

    public function materiales(): HasMany
    {
        return $this->hasMany(Material::class, 'unidad_id');
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'unidad_id');
    }
}
