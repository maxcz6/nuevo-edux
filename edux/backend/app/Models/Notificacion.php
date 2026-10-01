<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    protected $table = 'notificaciones';
    
    public $timestamps = false;

    protected $fillable = ['usuario_id', 'titulo', 'mensaje', 'tipo', 'referencia_tipo', 'referencia_id', 'leido', 'created_at'];

    protected $casts = [
        'leido' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
