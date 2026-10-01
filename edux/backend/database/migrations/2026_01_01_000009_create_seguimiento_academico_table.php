<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_curso_id')->constrained('grupos_curso');
            $table->foreignId('estudiante_id')->constrained('estudiantes');
            $table->string('tipo_origen', 20); // TAREA|EVALUACION|PARTICIPACION|MANUAL
            $table->bigInteger('origen_id')->nullable();
            $table->string('nombre_rubro', 100);
            $table->decimal('nota', 5, 2);
            $table->decimal('peso_porcentual', 5, 2)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('docentes');
            $table->timestamps();
        });

        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_curso_id')->nullable()->constrained('grupos_curso');
            $table->foreignId('seccion_id')->nullable()->constrained('secciones');
            $table->foreignId('estudiante_id')->constrained('estudiantes');
            $table->date('fecha');
            $table->string('estado_asistencia', 20); // PRESENTE|AUSENTE|TARDANZA|JUSTIFICADO
            $table->string('observacion', 255)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('docentes');
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement("ALTER TABLE asistencias ADD CONSTRAINT chk_asistencias_origen CHECK (grupo_curso_id IS NOT NULL OR seccion_id IS NOT NULL)");

        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->foreignId('grupo_curso_id')->nullable()->constrained('grupos_curso');
            $table->foreignId('autor_usuario_id')->constrained('usuarios');
            $table->string('titulo', 150);
            $table->text('contenido');
            $table->timestamp('fecha_publicacion')->useCurrent();
            $table->string('estado', 20)->default('PUBLICADO');
        });

        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('tipo', 30); // TAREA_NUEVA|NOTA_PUBLICADA|MATERIAL_NUEVO|EVALUACION_PROXIMA|AVISO
            $table->string('titulo', 150);
            $table->text('mensaje')->nullable();
            $table->string('referencia_tipo', 30)->nullable();
            $table->bigInteger('referencia_id')->nullable();
            $table->boolean('leido')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        // Add all indexes from schema.sql
        Schema::table('usuarios', function (Blueprint $table) {
            $table->index('institucion_id', 'idx_usuarios_institucion');
        });
        
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->index('usuario_id', 'idx_estudiantes_usuario');
        });

        Schema::table('docentes', function (Blueprint $table) {
            $table->index('usuario_id', 'idx_docentes_usuario');
        });

        Schema::table('grupos_curso', function (Blueprint $table) {
            $table->index('periodo_id', 'idx_grupos_curso_periodo');
            $table->index('docente_id', 'idx_grupos_curso_docente');
        });

        Schema::table('planes_estudio', function (Blueprint $table) {
            $table->index('programa_id', 'idx_planes_estudio_programa');
        });

        Schema::table('plan_cursos', function (Blueprint $table) {
            $table->index('plan_estudio_id', 'idx_plan_cursos_plan');
            $table->index('curso_id', 'idx_plan_cursos_curso');
        });

        Schema::table('estudiante_programa', function (Blueprint $table) {
            $table->index('estudiante_id', 'idx_estudiante_programa_estudiante');
            $table->index('estado', 'idx_estudiante_programa_estado');
        });

        Schema::table('matriculas', function (Blueprint $table) {
            $table->index('estudiante_id', 'idx_matriculas_estudiante');
            $table->index('grupo_curso_id', 'idx_matriculas_grupo');
        });

        Schema::table('tareas', function (Blueprint $table) {
            $table->index('grupo_curso_id', 'idx_tareas_grupo');
        });

        Schema::table('entregas_tareas', function (Blueprint $table) {
            $table->index('estudiante_id', 'idx_entregas_estudiante');
        });

        Schema::table('calificaciones', function (Blueprint $table) {
            $table->index(['estudiante_id', 'grupo_curso_id'], 'idx_calificaciones_estudiante');
        });

        Schema::table('asistencias', function (Blueprint $table) {
            $table->index(['estudiante_id', 'fecha'], 'idx_asistencias_estudiante_fecha');
        });

        Schema::table('notificaciones', function (Blueprint $table) {
            $table->index(['usuario_id', 'leido'], 'idx_notificaciones_usuario_leido');
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropIndex('idx_notificaciones_usuario_leido');
        });
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropIndex('idx_asistencias_estudiante_fecha');
        });
        Schema::table('calificaciones', function (Blueprint $table) {
            $table->dropIndex('idx_calificaciones_estudiante');
        });
        Schema::table('entregas_tareas', function (Blueprint $table) {
            $table->dropIndex('idx_entregas_estudiante');
        });
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropIndex('idx_tareas_grupo');
        });
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropIndex('idx_matriculas_grupo');
            $table->dropIndex('idx_matriculas_estudiante');
        });
        Schema::table('estudiante_programa', function (Blueprint $table) {
            $table->dropIndex('idx_estudiante_programa_estado');
            $table->dropIndex('idx_estudiante_programa_estudiante');
        });
        Schema::table('plan_cursos', function (Blueprint $table) {
            $table->dropIndex('idx_plan_cursos_curso');
            $table->dropIndex('idx_plan_cursos_plan');
        });
        Schema::table('planes_estudio', function (Blueprint $table) {
            $table->dropIndex('idx_planes_estudio_programa');
        });
        Schema::table('grupos_curso', function (Blueprint $table) {
            $table->dropIndex('idx_grupos_curso_docente');
            $table->dropIndex('idx_grupos_curso_periodo');
        });
        Schema::table('docentes', function (Blueprint $table) {
            $table->dropIndex('idx_docentes_usuario');
        });
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->dropIndex('idx_estudiantes_usuario');
        });
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropIndex('idx_usuarios_institucion');
        });

        Schema::dropIfExists('notificaciones');
        Schema::dropIfExists('avisos');
        Schema::dropIfExists('asistencias');
        Schema::dropIfExists('calificaciones');
    }
};
