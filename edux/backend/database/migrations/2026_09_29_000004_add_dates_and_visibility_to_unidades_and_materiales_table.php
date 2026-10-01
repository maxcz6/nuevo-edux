<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->date('fecha_inicio')->nullable()->after('orden');
            $table->date('fecha_fin')->nullable()->after('fecha_inicio');
            $table->timestamp('visible_desde')->nullable()->after('fecha_fin');
            $table->timestamp('visible_hasta')->nullable()->after('visible_desde');
        });

        Schema::table('materiales', function (Blueprint $table) {
            $table->timestamp('visible_hasta')->nullable()->after('visible_desde');
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropColumn(['fecha_inicio', 'fecha_fin', 'visible_desde', 'visible_hasta']);
        });

        Schema::table('materiales', function (Blueprint $table) {
            $table->dropColumn(['visible_hasta']);
        });
    }
};
