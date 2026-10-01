<?php

namespace App\Models\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * InstitutionScope — Scope de aislamiento multiinstitución.
 *
 * En peticiones web normales con usuario autenticado (auth()->check()),
 * la institución activa SIEMPRE proviene de auth()->user()->institucion_id
 * y NO puede ser sobrescrita.
 *
 * Para procesos en segundo plano, colas (workers), cron jobs o comandos de consola (CLI),
 * donde no existe sesión HTTP autenticada, se recurre a TenantContext::getTenantId().
 */
class InstitutionScope implements Scope
{
    protected static bool $resolvingAuth = false;

    public function apply(Builder $builder, Model $model): void
    {
        // Salir de inmediato si ya estamos resolviendo la autenticación para evitar ciclo recursivo
        if (static::$resolvingAuth) {
            return;
        }

        // 1. En peticiones con usuario autenticado, la institución del usuario es la autoridad inmutable.
        static::$resolvingAuth = true;
        try {
            if (auth()->check()) {
                $user = auth()->user();
                // SUPERADMIN tiene acceso global completo a cualquier información del sistema
                if ($user && $user->hasRole('SUPERADMIN')) {
                    return;
                }
                if ($user && $user->institucion_id) {
                    $builder->where(
                        $model->getTable() . '.institucion_id',
                        $user->institucion_id
                    );
                    return;
                }
            }
        } finally {
            static::$resolvingAuth = false;
        }

        // 2. Para tareas en segundo plano, comandos CLI, workers o jobs sin sesión HTTP:
        $tenantId = TenantContext::getTenantId();
        if ($tenantId !== null) {
            $builder->where(
                $model->getTable() . '.institucion_id',
                $tenantId
            );
        }
    }
}
