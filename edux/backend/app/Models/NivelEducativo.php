<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NivelEducativo extends Model
{
    protected $table = 'niveles_educativos';

    public $timestamps = false;

    protected $fillable = [
        'institucion_id',
        'codigo',
        'nombre',
        'orden',
        'estado',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope);
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function unidadesAcademicas(): HasMany
    {
        return $this->hasMany(UnidadAcademica::class, 'nivel_educativo_id');
    }
}
