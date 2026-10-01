<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instituciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 200);
            $table->string('nombre_corto', 100)->nullable();
            $table->string('tipo_institucion', 20); // COLEGIO|ESCUELA|INSTITUTO|UNIVERSIDAD
            $table->string('ruc', 11)->nullable();
            $table->string('direccion', 250)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('logo', 255)->nullable();
            $table->string('zona_horaria', 50)->default('America/Lima');
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
        });

        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->string('nombre', 150);
            $table->string('direccion', 250)->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
        });

        Schema::create('niveles_educativos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones');
            $table->string('codigo', 20);
            $table->string('nombre', 100);
            $table->smallInteger('orden')->default(0);
            $table->string('estado', 20)->default('ACTIVO');
            $table->unique(['institucion_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveles_educativos');
        Schema::dropIfExists('sedes');
        Schema::dropIfExists('instituciones');
    }
};
