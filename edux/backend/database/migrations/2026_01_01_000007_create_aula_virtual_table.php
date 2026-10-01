<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_curso_id')->constrained('grupos_curso')->cascadeOnDelete();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->smallInteger('orden')->default(0);
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
        });

        Schema::create('sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->date('fecha_sesion')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->string('enlace_virtual', 255)->nullable();
            $table->smallInteger('orden')->default(0);
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
        });

        Schema::create('materiales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->nullable()->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('sesion_id')->nullable()->constrained('sesiones')->cascadeOnDelete();
            $table->foreignId('docente_id')->constrained('docentes');
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->string('tipo', 20); // PDF|DOCX|PPTX|XLSX|IMAGEN|VIDEO|ENLACE|ZIP
            $table->string('archivo_url', 255)->nullable();
            $table->string('enlace_externo', 255)->nullable();
            $table->timestamp('visible_desde')->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
        });

        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_curso_id')->constrained('grupos_curso');
            $table->foreignId('unidad_id')->nullable()->constrained('unidades');
            $table->foreignId('docente_id')->constrained('docentes');
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->decimal('puntaje_maximo', 5, 2)->default(20);
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_limite');
            $table->boolean('permite_entrega_tardia')->default(false);
            $table->string('estado', 20)->default('PUBLICADA');
            $table->timestamps();
        });

        Schema::create('entregas_tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes');
            $table->string('archivo_url', 255)->nullable();
            $table->text('comentario_estudiante')->nullable();
            $table->timestamp('fecha_entrega')->useCurrent();
            $table->boolean('es_tardia')->default(false);
            $table->decimal('nota', 5, 2)->nullable();
            $table->text('comentario_docente')->nullable();
            $table->string('estado', 20)->default('ENTREGADA');
            $table->timestamps();
            $table->unique(['tarea_id', 'estudiante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas_tareas');
        Schema::dropIfExists('tareas');
        Schema::dropIfExists('materiales');
        Schema::dropIfExists('sesiones');
        Schema::dropIfExists('unidades');
    }
};
