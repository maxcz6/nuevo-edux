<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->foreignId('unidad_academica_id')->nullable()->constrained('unidades_academicas');
            $table->foreignId('periodo_id')->constrained('periodos_academicos');
            $table->string('grado', 20)->nullable();
            $table->string('nombre', 50);
            $table->foreignId('tutor_docente_id')->nullable()->constrained('docentes');
            $table->integer('capacidad')->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
            $table->unique(['institucion_id', 'periodo_id', 'nombre']);
        });

        Schema::create('grupos_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->foreignId('curso_id')->constrained('cursos');
            $table->foreignId('plan_curso_id')->nullable()->constrained('plan_cursos');
            $table->foreignId('periodo_id')->constrained('periodos_academicos');
            $table->foreignId('docente_id')->constrained('docentes');
            $table->foreignId('seccion_id')->nullable()->constrained('secciones');
            $table->string('codigo_grupo', 30)->nullable();
            $table->string('turno', 30)->nullable();
            $table->string('aula', 50)->nullable();
            $table->integer('cupo')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
        });

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION sync_grupos_curso_curso_id()
        RETURNS TRIGGER AS $$
        BEGIN
            IF NEW.plan_curso_id IS NOT NULL THEN
                SELECT curso_id INTO NEW.curso_id
                FROM plan_cursos WHERE id = NEW.plan_curso_id;
            END IF;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        CREATE OR REPLACE TRIGGER trg_sync_grupos_curso_curso_id
        BEFORE INSERT OR UPDATE ON grupos_curso
        FOR EACH ROW EXECUTE FUNCTION sync_grupos_curso_curso_id();
        SQL);

        Schema::create('matricula_seccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->foreignId('seccion_id')->constrained('secciones');
            $table->foreignId('estudiante_id')->constrained('estudiantes');
            $table->date('fecha_matricula')->useCurrent();
            $table->string('estado', 20)->default('MATRICULADO');
            $table->timestamps();
            $table->unique(['seccion_id', 'estudiante_id']);
        });

        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->foreignId('grupo_curso_id')->constrained('grupos_curso');
            $table->foreignId('estudiante_id')->constrained('estudiantes');
            $table->string('origen', 20)->default('MANUAL');
            $table->date('fecha_matricula')->useCurrent();
            $table->string('estado', 20)->default('MATRICULADO');
            $table->timestamps();
            $table->unique(['grupo_curso_id', 'estudiante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculas');
        Schema::dropIfExists('matricula_seccion');
        DB::statement('DROP TRIGGER IF EXISTS trg_sync_grupos_curso_curso_id ON grupos_curso');
        DB::statement('DROP FUNCTION IF EXISTS sync_grupos_curso_curso_id');
        Schema::dropIfExists('grupos_curso');
        Schema::dropIfExists('secciones');
    }
};
