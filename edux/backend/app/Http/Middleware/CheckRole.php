<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckRole — Middleware de verificación de roles.
 *
 * Uso: Route::middleware('check.role:ADMINISTRADOR,COORDINADOR')
 *
 * Permite el acceso si el usuario tiene al menos uno de los roles especificados.
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $user->loadMissing('roles');

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'No tienes el rol requerido para esta acción.',
            'error'   => 'forbidden',
            'roles_required' => $roles,
        ], 403);
    }
}
