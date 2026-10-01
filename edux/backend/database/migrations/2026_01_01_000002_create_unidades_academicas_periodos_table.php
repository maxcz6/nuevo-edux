<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_academicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->foreignId('nivel_educativo_id')->nullable()->constrained('niveles_educativos');
            $table->foreignId('padre_id')->nullable()->constrained('unidades_academicas');
            $table->string('codigo', 30);
            $table->string('nombre', 200);
            $table->string('tipo', 30); // FACULTAD|PROGRAMA_ESTUDIOS|DEPARTAMENTO_ACADEMICO|NIVEL_ESCOLAR
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
            $table->unique(['institucion_id', 'codigo']);
        });

        Schema::create('periodos_academicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->string('codigo', 30);
            $table->string('nombre', 100);
            $table->string('tipo_periodo', 20); // ANUAL|SEMESTRAL|TRIMESTRAL|BIMESTRAL|CICLO
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 20)->default('PLANIFICADO');
            $table->timestamps();
            $table->unique(['institucion_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos_academicos');
        Schema::dropIfExists('unidades_academicas');
    }
};
