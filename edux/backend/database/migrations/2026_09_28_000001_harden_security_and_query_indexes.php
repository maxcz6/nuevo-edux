<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('institucion_id')->nullable()->change();
        });

        // PostgreSQL permite múltiples NULL en una restricción UNIQUE compuesta.
        // Estos índices preservan la unicidad de las cuentas globales.
        DB::statement('CREATE UNIQUE INDEX usuarios_global_username_unique ON usuarios (username) WHERE institucion_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX usuarios_global_email_unique ON usuarios (email) WHERE institucion_id IS NULL');

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->index();
        });

        Schema::table('matriculas', function (Blueprint $table) {
            $table->index(['institucion_id', 'estado'], 'idx_matriculas_institucion_estado');
            $table->index(['institucion_id', 'estudiante_id'], 'idx_matriculas_institucion_estudiante');
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->index(['institucion_id', 'created_at'], 'idx_audits_institucion_created');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropIndex('idx_audits_institucion_created');
        });

        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropIndex('idx_matriculas_institucion_estado');
            $table->dropIndex('idx_matriculas_institucion_estudiante');
        });

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });

        DB::statement('DROP INDEX IF EXISTS usuarios_global_email_unique');
        DB::statement('DROP INDEX IF EXISTS usuarios_global_username_unique');

        if (DB::table('usuarios')->whereNull('institucion_id')->exists()) {
            throw new RuntimeException(
                'No se puede restaurar usuarios.institucion_id como NOT NULL mientras existan usuarios globales.'
            );
        }

        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('institucion_id')->nullable(false)->change();
        });
    }
};
