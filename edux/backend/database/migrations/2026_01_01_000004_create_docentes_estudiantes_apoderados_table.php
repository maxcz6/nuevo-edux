<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->foreignId('usuario_id')->unique()->constrained('usuarios');
            $table->string('codigo_docente', 30)->nullable();
            $table->string('especialidad', 150)->nullable();
            $table->string('grado_academico', 100)->nullable();
            $table->string('tipo_contrato', 30)->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
        });

        Schema::create('estudiantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->foreignId('usuario_id')->unique()->constrained('usuarios');
            $table->string('codigo_estudiante', 30)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo', 20)->nullable();
            $table->string('direccion', 250)->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
        });

        Schema::create('apoderados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->string('nombres', 120);
            $table->string('apellidos', 150);
            $table->string('documento_numero', 20)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('estudiante_apoderado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('apoderado_id')->constrained('apoderados')->cascadeOnDelete();
            $table->string('parentesco', 50)->nullable();
            $table->boolean('es_principal')->default(false);
            $table->unique(['estudiante_id', 'apoderado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante_apoderado');
        Schema::dropIfExists('apoderados');
        Schema::dropIfExists('estudiantes');
        Schema::dropIfExists('docentes');
    }
};
