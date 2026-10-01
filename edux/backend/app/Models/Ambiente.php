<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ambiente extends Model
{
    protected $table = 'ambientes';

    protected $fillable = [
        'institucion_id',
        'codigo',
        'nombre',
        'tipo',
        'pabellon',
        'capacidad',
        'estado',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new InstitutionScope);
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioGrupo::class, 'ambiente_id');
    }
}
