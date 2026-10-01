<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->string('archivo_adjunto')->nullable()->after('instrucciones');
            $table->string('archivo_nombre_original')->nullable()->after('archivo_adjunto');
            $table->string('tipo_evaluacion', 20)->default('QUIZ')->after('archivo_nombre_original');
            $table->boolean('permite_archivo_estudiante')->default(false)->after('tipo_evaluacion');
        });

        Schema::table('intentos_evaluacion', function (Blueprint $table) {
            $table->string('archivo_estudiante')->nullable()->after('nota');
            $table->string('archivo_estudiante_nombre')->nullable()->after('archivo_estudiante');
            $table->text('comentario_estudiante')->nullable()->after('archivo_estudiante_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('intentos_evaluacion', function (Blueprint $table) {
            $table->dropColumn(['archivo_estudiante', 'archivo_estudiante_nombre', 'comentario_estudiante']);
        });

        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropColumn(['archivo_adjunto', 'archivo_nombre_original', 'tipo_evaluacion', 'permite_archivo_estudiante']);
        });
    }
};
