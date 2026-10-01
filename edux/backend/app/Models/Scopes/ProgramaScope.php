<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

/**
 * ProgramaScope — Scope de aislamiento por Programa de Estudios (Unidad Académica)
 * 
 * Si el usuario autenticado tiene el rol COORDINADOR, restringe automáticamente
 * las consultas al programa de estudio asignado (unidad_academica_id / programa_id).
 */
class ProgramaScope implements Scope
{
    protected static bool $resolvingAuth = false;

    public function apply(Builder $builder, Model $model): void
    {
        if (static::$resolvingAuth) {
            return;
        }

        static::$resolvingAuth = true;
        try {
            if (auth()->check()) {
                $user = auth()->user();

                // Solo aplica para el rol COORDINADOR
                if ($user && $user->hasRole('COORDINADOR') && $user->unidad_academica_id) {
                    $table = $model->getTable();
                    
                    // Comprobar si el modelo usa unidad_academica_id o programa_id
                    if (in_array('unidad_academica_id', $model->getFillable()) || $model->isFillable('unidad_academica_id')) {
                        $builder->where($table . '.unidad_academica_id', $user->unidad_academica_id);
                    } elseif (in_array('programa_id', $model->getFillable()) || $model->isFillable('programa_id')) {
                        $builder->where($table . '.programa_id', $user->unidad_academica_id);
                    }
                }
            }
        } finally {
            static::$resolvingAuth = false;
        }
    }
}
