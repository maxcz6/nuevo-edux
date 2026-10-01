<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para habilitar PostgreSQL Row-Level Security (RLS) nativo.
 *
 * Los Global Scopes de Eloquent (InstitutionScope y ProgramaScope) operan en la capa ORM.
 * PostgreSQL Row-Level Security (RLS) opera como una capa complementaria y defensiva
 * a nivel de motor relacional de base de datos (Defense-in-Depth).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
            'usuarios',
            'docentes',
            'estudiantes',
            'periodos_academicos',
            'cursos',
            'grupos_curso',
            'secciones',
            'matriculas',
            'unidades',
            'sesiones',
            'materiales',
            'tareas',
            'entregas_tareas',
            'calificaciones',
            'asistencias',
            'evaluaciones',
            'convocatorias_admision',
            'tramites_estudiantes',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            // 1. Habilitar Row Level Security en la tabla
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");

            // 2. Política de aislamiento institucional complementaria
            // Permite acceso si es SUPERADMIN o si el institucion_id coincide con el contexto de sesión
            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_policies 
                        WHERE tablename = '{$table}' AND policyname = '{$table}_tenant_isolation_policy'
                    ) THEN
                        CREATE POLICY {$table}_tenant_isolation_policy ON {$table}
                        USING (
                            current_setting('app.is_superadmin', true) = 'true'
                            OR institucion_id = NULLIF(current_setting('app.current_tenant_id', true), '')::bigint
                        )
                        WITH CHECK (
                            current_setting('app.is_superadmin', true) = 'true'
                            OR institucion_id = NULLIF(current_setting('app.current_tenant_id', true), '')::bigint
                        );
                    END IF;
                END $$;
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
            'usuarios',
            'docentes',
            'estudiantes',
            'periodos_academicos',
            'cursos',
            'grupos_curso',
            'secciones',
            'matriculas',
            'unidades',
            'sesiones',
            'materiales',
            'tareas',
            'entregas_tareas',
            'calificaciones',
            'asistencias',
            'evaluaciones',
            'convocatorias_admision',
            'tramites_estudiantes',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation_policy ON {$table};");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY;");
        }
    }
};
