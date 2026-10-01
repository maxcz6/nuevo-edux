<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grupos_curso', function (Blueprint $table) {
            $table->unique(['institucion_id', 'periodo_id', 'curso_id', 'codigo_grupo'], 'grupos_curso_unique_codigo_por_curso_periodo');
        });
    }

    public function down(): void
    {
        Schema::table('grupos_curso', function (Blueprint $table) {
            $table->dropUnique('grupos_curso_unique_codigo_por_curso_periodo');
        });
    }
};
