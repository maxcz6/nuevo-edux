<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PreventCrlfInjection — Mitigación proactiva contra inyecciones CRLF (CVE-2026-48019 / GHSA-5vg9-5847-vvmq).
 *
 * Rechaza o sanitiza de forma estricta cualquier carácter de salto de línea (\r, \n) en campos de email o
 * encabezados que pudieran ser utilizados para manipular envíos SMTP o inyección de cabeceras.
 */
class PreventCrlfInjection
{
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        array_walk_recursive($input, function (&$value, $key) {
            if (is_string($value)) {
                // Si el campo es un email, rechazar terminantemente si contiene \r o \n
                if (str_contains(strtolower($key), 'email') || str_contains(strtolower($key), 'correo')) {
                    if (str_contains($value, "\r") || str_contains($value, "\n")) {
                        abort(422, 'El correo electrónico contiene caracteres no permitidos (CRLF injection detectado).');
                    }
                }
            }
        });

        return $next($request);
    }
}
