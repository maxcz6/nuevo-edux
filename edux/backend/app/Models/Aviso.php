<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aviso extends Model
{
    protected $table = 'avisos';
    
    protected $fillable = [
        'institucion_id',
        'grupo_curso_id',
        'autor_usuario_id',
        'titulo',
        'contenido',
        'importancia',
        'estado',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope());
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function grupoCurso(): BelongsTo
    {
        return $this->belongsTo(GrupoCurso::class, 'grupo_curso_id');
    }

    public function autorUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autor_usuario_id');
    }
}
