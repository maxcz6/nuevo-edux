<?php
namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Docente extends Model
{
    protected $table = 'docentes';
    
    protected $fillable = ['institucion_id', 'usuario_id', 'unidad_academica_id', 'especialidad', 'grado_academico', 'fecha_ingreso', 'estado'];

    protected static function booted()
    {
        static::addGlobalScope(new InstitutionScope);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'unidad_academica_id');
    }

    public function gruposCurso(): HasMany
    {
        return $this->hasMany(GrupoCurso::class, 'docente_id');
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }
}
