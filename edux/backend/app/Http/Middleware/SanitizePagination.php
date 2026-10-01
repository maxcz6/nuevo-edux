<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizePagination
{
    /**
     * Limita el parámetro per_page para evitar ataques de denegación de servicio (DoS)
     * por sobrecarga de memoria en consultas paginadas.
     * Rango permitido: 1 a 100 elementos por página.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('per_page')) {
            $perPage = (int) $request->input('per_page');
            $clamped = max(1, min(100, $perPage));
            $request->query->set('per_page', $clamped);
            $request->merge(['per_page' => $clamped]);
        }

        return $next($request);
    }
}
