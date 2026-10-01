<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Titulacion extends Model
{
    protected $table = 'titulaciones';

    protected $fillable = [
        'institucion_id',
        'estudiante_id',
        'programa_id',
        'modalidad_titulacion',
        'numero_titulo',
        'numero_registro',
        'numero_folio',
        'numero_libro',
        'resolucion_directoral',
        'codigo_minedu',
        'fecha_sustentacion',
        'fecha_emision',
        'codigo_verificacion',
        'estado',
    ];

    protected $casts = [
        'fecha_sustentacion' => 'date',
        'fecha_emision' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope);
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'programa_id');
    }
}
