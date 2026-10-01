<?php

namespace App\Services;

/**
 * TenantContext — Contexto de institución para procesos en segundo plano y CLI.
 *
 * Esta clase provee el tenant para tareas que NO cuentan con un usuario HTTP autenticado
 * (p. ej. Workers de colas Redis, Comandos Artisan de consola, Cron Jobs o Tests).
 *
 * En peticiones web autenticadas, InstitutionScope ignora este contexto y utiliza
 * estrictamente auth()->user()->institucion_id como autoridad única para prevenir cualquier
 * riesgo de suplantación o manipulación de tenant.
 */
class TenantContext
{
    protected static ?int $tenantId = null;

    /**
     * Obtener el ID de la institución actualmente activa en el contexto.
     */
    public static function getTenantId(): ?int
    {
        return static::$tenantId;
    }

    /**
     * Establecer explícitamente la institución activa para tareas internas.
     */
    public static function setTenantId(?int $id): void
    {
        static::$tenantId = $id;
    }

    /**
     * Limpiar el contexto de institución.
     */
    public static function clear(): void
    {
        static::$tenantId = null;
    }

    /**
     * Ejecutar una operación dentro del contexto de una institución específica,
     * restaurando de forma determinista el contexto anterior al finalizar.
     */
    public static function runWithTenant(?int $tenantId, callable $callback): mixed
    {
        $previousTenantId = static::$tenantId;
        static::$tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            static::$tenantId = $previousTenantId;
        }
    }
}
