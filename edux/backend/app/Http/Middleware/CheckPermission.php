<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckPermission — Middleware de verificación de permisos granulares.
 *
 * Uso: Route::middleware('check.permission:cursos.crear')
 *
 * SUPERADMIN y ADMINISTRADOR tienen acceso implícito a todo
 * dentro de su institución.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        // Solo SUPERADMIN tiene acceso completo sin comprobación granular de permisos.
        // ADMINISTRADOR debe respetar sus permisos asignados en el sistema.
        if ($user->hasRole('SUPERADMIN')) {
            return $next($request);
        }

        $user->loadMissing('roles.permisos');

        if (!$user->hasPermission($permission)) {
            return response()->json([
                'message'             => 'No tienes permiso para realizar esta acción.',
                'error'               => 'forbidden',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
