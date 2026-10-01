<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureInstitution — Middleware de verificación de institución y contexto de aislamiento.
 *
 * 1. Todos los usuarios (incluyendo SUPERADMIN) deben contar con una afiliación
 *    institucional y un programa académico registrado para mantener la integridad referencial.
 * 2. Si el usuario es SUPERADMIN, mantiene su institución base pero cuenta con autorizaciones
 *    globales independientes para supervisar múltiples sedes e instituciones.
 * 3. Configura las variables de sesión de PostgreSQL para soporte de Row-Level Security (RLS)
 *    a nivel de motor de base de datos como mecanismo complementario a Eloquent Scopes.
 */
class EnsureInstitution
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
                'error'   => 'unauthenticated',
            ], 401);
        }

        // Cada usuario debe tener una institución obligatoria
        if (!$user->institucion_id) {
            return response()->json([
                'message' => 'Usuario sin institución asignada. La afiliación institucional es obligatoria.',
                'error'   => 'no_institution',
            ], 403);
        }

        // Exponer institucion_id como atributo del request
        $request->attributes->set('institucion_id', $user->institucion_id);

        // Configuración de contexto para PostgreSQL Row-Level Security (RLS) complementario
        try {
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';
            if ($isPgsql) {
                $isSuperAdmin = $user->hasRole('SUPERADMIN') ? 'true' : 'false';
                $tenantId = (int) $user->institucion_id;
                $programaId = $user->unidad_academica_id ? (int) $user->unidad_academica_id : 0;

                DB::statement("SET LOCAL app.current_tenant_id = '{$tenantId}'");
                DB::statement("SET LOCAL app.is_superadmin = '{$isSuperAdmin}'");
                DB::statement("SET LOCAL app.current_programa_id = '{$programaId}'");
            }
        } catch (\Throwable) {
            // Silencioso en entornos de testing SQLite o si la conexión aún no inicializó transaccionalidad
        }

        return $next($request);
    }
}
