<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Admisión Tecnológica ──────────────────────────────────────────
        Schema::create('convocatorias_admision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones')->cascadeOnDelete();
            $table->foreignId('periodo_id')->nullable()->constrained('periodos_academicos')->nullOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 150);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->date('fecha_examen')->nullable();
            $table->decimal('puntaje_minimo_aprobatorio', 5, 2)->default(11.00);
            $table->string('estado', 30)->default('PUBLICADA'); // BORRADOR, PUBLICADA, EN_EVALUACION, FINALIZADA
            $table->timestamps();
            $table->unique(['institucion_id', 'codigo']);
        });

        Schema::create('vacantes_admision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones')->cascadeOnDelete();
            $table->foreignId('convocatoria_id')->constrained('convocatorias_admision')->cascadeOnDelete();
            $table->foreignId('programa_id')->constrained('unidades_academicas')->cascadeOnDelete();
            $table->string('turno', 20)->default('DIURNO'); // DIURNO, NOCTURNO
            $table->string('modalidad', 30)->default('ORDINARIO'); // ORDINARIO, EXONERADO, PRIMEROS_PUESTOS, DEPORTISTA
            $table->integer('vacantes_ofertadas')->default(40);
            $table->integer('vacantes_cubiertas')->default(0);
            $table->timestamps();
            $table->unique(['convocatoria_id', 'programa_id', 'turno', 'modalidad'], 'unique_vacante_conv_prog_turno_mod');
        });

        Schema::create('postulantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones')->cascadeOnDelete();
            $table->foreignId('convocatoria_id')->constrained('convocatorias_admision')->cascadeOnDelete();
            $table->foreignId('programa_id')->constrained('unidades_academicas')->cascadeOnDelete();
            $table->string('turno', 20)->default('DIURNO');
            $table->string('modalidad', 30)->default('ORDINARIO');
            $table->string('codigo_postulante', 30);
            $table->string('nombres', 100);
            $table->string('apellido_paterno', 100);
            $table->string('apellido_materno', 100)->nullable();
            $table->string('documento_tipo', 20)->default('DNI');
            $table->string('documento_numero', 20);
            $table->string('email', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->decimal('puntaje_examen', 5, 2)->nullable();
            $table->integer('orden_merito')->nullable();
            $table->string('condicion', 30)->default('INSCRITO'); // INSCRITO, EVALUADO, ALCANZO_VACANTE, NO_INGRESO, MATRICULADO
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('estudiante_id')->nullable()->constrained('estudiantes')->nullOnDelete();
            $table->timestamps();
            $table->unique(['convocatoria_id', 'codigo_postulante']);
            $table->unique(['convocatoria_id', 'documento_numero']);
        });

        // ── 2. Prerrequisitos entre Unidades Didácticas (Cursos) ───────────
        Schema::create('curso_prerrequisitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->foreignId('prerrequisito_curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['curso_id', 'prerrequisito_curso_id']);
        });

        // ── 3. Trámites Académicos y Trayectoria del Estudiante ────────────
        Schema::create('tramites_estudiante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->string('tipo_tramite', 40); // RESERVA_MATRICULA, REINCORPORACION, TRASLADO_INTERNO, TRASLADO_EXTERNO, CONVALIDACION, RECTIFICACION
            $table->foreignId('programa_origen_id')->nullable()->constrained('unidades_academicas')->nullOnDelete();
            $table->foreignId('programa_destino_id')->nullable()->constrained('unidades_academicas')->nullOnDelete();
            $table->string('resolucion_directoral', 100)->nullable();
            $table->date('fecha_solicitud');
            $table->date('fecha_resolucion')->nullable();
            $table->text('motivo')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 30)->default('PENDIENTE'); // PENDIENTE, APROBADO, RECHAZADO
            $table->timestamps();
        });

        // ── 4. Ambientes, Talleres y Horarios ──────────────────────────────
        Schema::create('ambientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones')->cascadeOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 100);
            $table->string('tipo', 30)->default('AULA'); // AULA, TALLER, LABORATORIO, CAMPO
            $table->string('pabellon', 50)->nullable();
            $table->integer('capacidad')->default(35);
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
            $table->unique(['institucion_id', 'codigo']);
        });

        Schema::create('horarios_grupo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones')->cascadeOnDelete();
            $table->foreignId('grupo_curso_id')->constrained('grupos_curso')->cascadeOnDelete();
            $table->foreignId('ambiente_id')->constrained('ambientes')->cascadeOnDelete();
            $table->smallInteger('dia_semana'); // 1: Lunes, 2: Martes ... 6: Sábado
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios_grupo');
        Schema::dropIfExists('ambientes');
        Schema::dropIfExists('tramites_estudiante');
        Schema::dropIfExists('curso_prerrequisitos');
        Schema::dropIfExists('postulantes');
        Schema::dropIfExists('vacantes_admision');
        Schema::dropIfExists('convocatorias_admision');
    }
};
