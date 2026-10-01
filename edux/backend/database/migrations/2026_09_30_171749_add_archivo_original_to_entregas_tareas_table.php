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
        Schema::table('entregas_tareas', function (Blueprint $table) {
            $table->string('archivo_original')->nullable()->after('archivo_url');
            $table->string('archivo_nombre')->nullable()->after('archivo_original');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entregas_tareas', function (Blueprint $table) {
            $table->dropColumn(['archivo_original', 'archivo_nombre']);
        });
    }
};
