<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Apoderado extends Model
{
    protected $table = 'apoderados';

    protected $fillable = [
        'institucion_id',
        'usuario_id',
        'nombres',
        'apellidos',
        'documento_numero',
        'telefono',
        'correo',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope);
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'estudiante_apoderado', 'apoderado_id', 'estudiante_id')
            ->withPivot(['parentesco', 'es_principal']);
    }
}
