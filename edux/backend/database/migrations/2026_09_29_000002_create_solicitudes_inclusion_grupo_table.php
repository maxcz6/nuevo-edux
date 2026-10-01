<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_inclusion_grupo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones')->cascadeOnDelete();
            $table->foreignId('grupo_curso_id')->constrained('grupos_curso')->cascadeOnDelete();
            $table->foreignId('docente_id')->constrained('docentes')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('coordinador_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->text('motivo')->nullable();
            $table->text('observaciones_coordinador')->nullable();
            $table->string('estado', 20)->default('PENDIENTE'); // PENDIENTE, APROBADO, RECHAZADO, CANCELADO
            $table->timestamp('fecha_resolucion')->nullable();
            $table->timestamps();

            $table->index(['institucion_id', 'estado']);
            $table->index(['grupo_curso_id', 'estado']);
            $table->index(['estudiante_id', 'grupo_curso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_inclusion_grupo');
    }
};
