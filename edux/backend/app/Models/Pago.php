<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = [
        'institucion_id',
        'estudiante_id',
        'matricula_id',
        'tramite_id',
        'numero_comprobante',
        'tipo_comprobante',
        'concepto',
        'monto',
        'metodo_pago',
        'numero_operacion',
        'fecha_pago',
        'estado',
        'motivo_anulacion',
        'observaciones',
        'registrado_por_usuario_id',
    ];

    protected $casts = [
        'fecha_pago' => 'datetime',
        'monto' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope());
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(TramiteEstudiante::class, 'tramite_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_usuario_id');
    }
}
