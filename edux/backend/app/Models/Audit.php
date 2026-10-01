<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    public const UPDATED_AT = null;
    protected $table = 'audits';

    protected $fillable = [
        'institucion_id',
        'usuario_id',
        'accion',
        'entidad_tipo',
        'entidad_id',
        'valores_anteriores',
        'valores_nuevos',
        'ip',
    ];

    protected $casts = ['valores_anteriores' => 'array', 'valores_nuevos' => 'array'];

    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope());
    }
}
