<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('materiales') && !Schema::hasColumn('materiales', 'descripcion')) {
            Schema::table('materiales', function (Blueprint $table) {
                $table->text('descripcion')->nullable()->after('titulo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('materiales') && Schema::hasColumn('materiales', 'descripcion')) {
            Schema::table('materiales', function (Blueprint $table) {
                $table->dropColumn('descripcion');
            });
        }
    }
};
