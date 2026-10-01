<?php
namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estudiante extends Model
{
    protected $table = 'estudiantes';
    
    protected $fillable = ['institucion_id', 'usuario_id', 'unidad_academica_id', 'codigo_estudiante', 'fecha_ingreso', 'fecha_nacimiento', 'sexo', 'direccion', 'estado'];

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

    public function programas(): HasMany
    {
        return $this->hasMany(EstudiantePrograma::class, 'estudiante_id');
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'estudiante_id');
    }

    public function tramites(): HasMany
    {
        return $this->hasMany(TramiteEstudiante::class, 'estudiante_id');
    }

    public function certificadosModulares(): HasMany
    {
        return $this->hasMany(CertificadoModular::class, 'estudiante_id');
    }

    public function titulaciones(): HasMany
    {
        return $this->hasMany(Titulacion::class, 'estudiante_id');
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }
}
