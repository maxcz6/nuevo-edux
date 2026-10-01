<?php
namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodoAcademico extends Model
{
    protected $table = 'periodos_academicos';
    
    protected $fillable = ['institucion_id', 'codigo', 'nombre', 'tipo_periodo', 'fecha_inicio', 'fecha_fin', 'estado'];

    protected static function booted()
    {
        static::addGlobalScope(new InstitutionScope);
    }

    public function gruposCurso(): HasMany
    {
        return $this->hasMany(GrupoCurso::class, 'periodo_id');
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }
}
