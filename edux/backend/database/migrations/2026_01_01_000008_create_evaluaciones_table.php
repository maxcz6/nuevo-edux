<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_curso_id')->constrained('grupos_curso');
            $table->foreignId('unidad_id')->nullable()->constrained('unidades');
            $table->foreignId('docente_id')->constrained('docentes');
            $table->string('titulo', 150);
            $table->text('instrucciones')->nullable();
            $table->decimal('puntaje_maximo', 5, 2)->default(20);
            $table->integer('duracion_minutos')->nullable();
            $table->smallInteger('intentos_permitidos')->default(1);
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_fin');
            $table->string('estado', 20)->default('PROGRAMADA');
            $table->timestamps();
        });

        Schema::create('preguntas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_id')->constrained('evaluaciones')->cascadeOnDelete();
            $table->string('tipo', 30); // ALTERNATIVA|VERDADERO_FALSO|RESPUESTA_CORTA|ABIERTA
            $table->text('enunciado');
            $table->decimal('puntaje', 5, 2)->default(1);
            $table->smallInteger('orden')->default(0);
        });

        Schema::create('opciones_pregunta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregunta_id')->constrained('preguntas')->cascadeOnDelete();
            $table->text('texto');
            $table->boolean('es_correcta')->default(false);
            $table->smallInteger('orden')->default(0);
        });

        Schema::create('intentos_evaluacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_id')->constrained('evaluaciones');
            $table->foreignId('estudiante_id')->constrained('estudiantes');
            $table->smallInteger('numero_intento')->default(1);
            $table->timestamp('fecha_inicio')->useCurrent();
            $table->timestamp('fecha_fin')->nullable();
            $table->decimal('nota', 5, 2)->nullable();
            $table->string('estado', 20)->default('EN_CURSO');
            $table->unique(['evaluacion_id', 'estudiante_id', 'numero_intento'], 'idx_intentos_eval_est_num');
        });

        Schema::create('respuestas_estudiante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intento_id')->constrained('intentos_evaluacion')->cascadeOnDelete();
            $table->foreignId('pregunta_id')->constrained('preguntas');
            $table->foreignId('opcion_id')->nullable()->constrained('opciones_pregunta');
            $table->text('respuesta_texto')->nullable();
            $table->decimal('puntaje_obtenido', 5, 2)->nullable();
            $table->boolean('revisado_por_docente')->default(false);
            $table->unique(['intento_id', 'pregunta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_estudiante');
        Schema::dropIfExists('intentos_evaluacion');
        Schema::dropIfExists('opciones_pregunta');
        Schema::dropIfExists('preguntas');
        Schema::dropIfExists('evaluaciones');
    }
};
