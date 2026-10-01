<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstudianteApoderado extends Model
{
    protected $table = 'estudiante_apoderado';

    public $timestamps = false;

    protected $fillable = [
        'estudiante_id',
        'apoderado_id',
        'parentesco',
        'es_principal',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
    ];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function apoderado(): BelongsTo
    {
        return $this->belongsTo(Apoderado::class, 'apoderado_id');
    }
}
