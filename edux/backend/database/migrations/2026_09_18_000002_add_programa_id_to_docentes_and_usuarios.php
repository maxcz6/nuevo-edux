<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docentes', function (Blueprint $table) {
            $table->foreignId('unidad_academica_id')
                ->nullable()
                ->after('usuario_id')
                ->constrained('unidades_academicas')
                ->nullOnDelete();
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreignId('unidad_academica_id')
                ->nullable()
                ->after('institucion_id')
                ->constrained('unidades_academicas')
                ->nullOnDelete();
        });

        Schema::table('estudiantes', function (Blueprint $table) {
            $table->foreignId('unidad_academica_id')
                ->nullable()
                ->after('usuario_id')
                ->constrained('unidades_academicas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->dropForeign(['unidad_academica_id']);
            $table->dropColumn('unidad_academica_id');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['unidad_academica_id']);
            $table->dropColumn('unidad_academica_id');
        });

        Schema::table('docentes', function (Blueprint $table) {
            $table->dropForeign(['unidad_academica_id']);
            $table->dropColumn('unidad_academica_id');
        });
    }
};
