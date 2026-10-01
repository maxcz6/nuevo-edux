<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoPrerrequisito extends Model
{
    protected $table = 'curso_prerrequisitos';

    protected $fillable = [
        'curso_id',
        'prerrequisito_curso_id',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function prerrequisito(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'prerrequisito_curso_id');
    }
}
