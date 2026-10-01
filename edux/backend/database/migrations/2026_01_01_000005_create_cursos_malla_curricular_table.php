<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->foreignId('unidad_academica_id')->nullable()->constrained('unidades_academicas');
            $table->string('codigo', 30);
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->smallInteger('creditos')->nullable();
            $table->smallInteger('horas_semanales')->nullable();
            $table->smallInteger('ciclo_sugerido')->nullable();
            $table->string('grado_sugerido', 20)->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
            $table->unique(['institucion_id', 'codigo']);
        });

        Schema::create('planes_estudio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->foreignId('programa_id')->constrained('unidades_academicas');
            $table->string('codigo', 30);
            $table->string('nombre', 150);
            $table->smallInteger('anio_inicio')->nullable();
            $table->date('fecha_vigencia')->nullable();
            $table->date('fecha_fin_vigencia')->nullable();
            $table->smallInteger('total_ciclos')->nullable();
            $table->decimal('total_creditos', 6, 2)->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
            $table->unique(['programa_id', 'codigo']);
        });

        Schema::create('ciclos_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_estudio_id')->constrained('planes_estudio')->cascadeOnDelete();
            $table->smallInteger('numero');
            $table->string('nombre', 50)->nullable();
            $table->unique(['plan_estudio_id', 'numero']);
        });

        Schema::create('plan_cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_estudio_id')->constrained('planes_estudio')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos');
            $table->foreignId('ciclo_id')->constrained('ciclos_plan');
            $table->decimal('creditos', 4, 1)->nullable();
            $table->smallInteger('horas_teoria')->default(0);
            $table->smallInteger('horas_practica')->default(0);
            $table->string('tipo', 30)->default('OBLIGATORIO');
            $table->smallInteger('orden')->nullable();
            $table->unique(['plan_estudio_id', 'curso_id']);
        });

        Schema::create('estudiante_programa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes');
            $table->foreignId('programa_id')->constrained('unidades_academicas');
            $table->foreignId('plan_estudio_id')->constrained('planes_estudio');
            $table->date('fecha_ingreso');
            $table->date('fecha_egreso')->nullable();
            $table->string('estado', 30)->default('ACTIVO');
            $table->timestamps();
        });

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION sync_estudiante_programa_programa_id()
        RETURNS TRIGGER AS $$
        BEGIN
            SELECT programa_id INTO NEW.programa_id
            FROM planes_estudio WHERE id = NEW.plan_estudio_id;
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        CREATE OR REPLACE TRIGGER trg_sync_estudiante_programa_programa_id
        BEFORE INSERT OR UPDATE ON estudiante_programa
        FOR EACH ROW EXECUTE FUNCTION sync_estudiante_programa_programa_id();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_sync_estudiante_programa_programa_id ON estudiante_programa');
        DB::statement('DROP FUNCTION IF EXISTS sync_estudiante_programa_programa_id');
        Schema::dropIfExists('estudiante_programa');
        Schema::dropIfExists('plan_cursos');
        Schema::dropIfExists('ciclos_plan');
        Schema::dropIfExists('planes_estudio');
        Schema::dropIfExists('cursos');
    }
};
