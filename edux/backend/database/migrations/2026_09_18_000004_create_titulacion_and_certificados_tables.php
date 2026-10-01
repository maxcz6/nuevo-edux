<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Certificación Modular (Ley N° 30512 & RVM N° 277-2019-MINEDU) ─
        Schema::create('certificados_modulares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('programa_id')->constrained('unidades_academicas')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero_modulo'); // 1, 2 o 3 según itinerario formativo
            $table->string('nombre_modulo', 255);
            $table->string('resolucion_directoral', 100)->nullable();
            $table->unsignedInteger('horas_teoria')->default(0);
            $table->unsignedInteger('horas_practica')->default(0);
            $table->decimal('creditos', 5, 2)->default(0.00);
            $table->unsignedInteger('horas_efsrt')->default(0); // Prácticas pre-profesionales / EFSRT
            $table->string('codigo_verificacion', 64)->unique(); // Token alfanumérico público para QR
            $table->date('fecha_emision');
            $table->string('estado', 30)->default('EMITIDO'); // EMITIDO, ANULADO
            $table->timestamps();

            $table->index(['institucion_id', 'estudiante_id']);
            $table->index(['institucion_id', 'programa_id']);
        });

        // ── 2. Titulación y Libro de Títulos Institucional ────────────────────
        Schema::create('titulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institucion_id')->constrained('instituciones')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('programa_id')->constrained('unidades_academicas')->cascadeOnDelete();
            $table->string('modalidad_titulacion', 60)->default('PROYECTO_APLICACION_PROFESIONAL'); 
            // PROYECTO_APLICACION_PROFESIONAL, EXAMEN_SUFICIENCIA_PROFESIONAL, TRABAJO_INVESTIGACION
            $table->string('numero_titulo', 50); // Ej: T-2026-001
            $table->string('numero_registro', 50); // Ej: REG-2026-088
            $table->string('numero_folio', 30); // Ej: 045
            $table->string('numero_libro', 30); // Ej: LIB-IV
            $table->string('resolucion_directoral', 120); // RD Directoral del SAM
            $table->string('codigo_minedu', 100)->nullable(); // Código asignado en REGISTRA / DRE Junín
            $table->date('fecha_sustentacion')->nullable();
            $table->date('fecha_emision');
            $table->string('codigo_verificacion', 64)->unique(); // Código QR único institucional
            $table->string('estado', 30)->default('REGISTRADO'); // REGISTRADO, EMITIDO, REPORTADO_MINEDU, ANULADO
            $table->timestamps();

            $table->index(['institucion_id', 'estudiante_id']);
            $table->unique(['institucion_id', 'numero_libro', 'numero_folio', 'numero_registro'], 'uniq_libro_folio_reg_inst');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('titulaciones');
        Schema::dropIfExists('certificados_modulares');
    }
};
