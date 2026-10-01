<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->string('archivo_adjunto_url', 255)->nullable()->after('descripcion');
            $table->string('archivo_nombre_original', 255)->nullable()->after('archivo_adjunto_url');
            $table->string('archivo_tipo', 50)->nullable()->after('archivo_nombre_original'); // PDF, DOCX, etc.
            $table->text('comentarios_instrucciones')->nullable()->after('archivo_tipo');
            $table->string('enlace_recurso', 255)->nullable()->after('comentarios_instrucciones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropColumn([
                'archivo_adjunto_url',
                'archivo_nombre_original',
                'archivo_tipo',
                'comentarios_instrucciones',
                'enlace_recurso',
            ]);
        });
    }
};
