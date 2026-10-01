<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sesion extends Model
{
    protected $table = 'sesiones';
    
    protected $fillable = ['unidad_id', 'titulo', 'descripcion', 'fecha_sesion', 'hora_inicio', 'hora_fin', 'tipo', 'url_videoconferencia', 'estado'];

    protected $casts = [
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
        'fecha_sesion' => 'date',
    ];

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }

    public function materiales(): HasMany
    {
        return $this->hasMany(Material::class, 'sesion_id');
    }
}
