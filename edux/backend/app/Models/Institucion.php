<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institucion extends Model
{
    protected $table = 'instituciones';
    protected $fillable = ['codigo','codigo_modular','nombre','nombre_corto','tipo_institucion','ruc','direccion','telefono','correo','logo','zona_horaria','estado'];
    protected $casts = ['created_at' => 'datetime', 'updated_at' => 'datetime'];
    
    public function sedes(): HasMany { return $this->hasMany(Sede::class, 'institucion_id'); }
    public function usuarios(): HasMany { return $this->hasMany(Usuario::class, 'institucion_id'); }
    public function periodos(): HasMany { return $this->hasMany(PeriodoAcademico::class, 'institucion_id'); }
    public function cursos(): HasMany { return $this->hasMany(Curso::class, 'institucion_id'); }
    public function docentes(): HasMany { return $this->hasMany(Docente::class, 'institucion_id'); }
    public function estudiantes(): HasMany { return $this->hasMany(Estudiante::class, 'institucion_id'); }
}
