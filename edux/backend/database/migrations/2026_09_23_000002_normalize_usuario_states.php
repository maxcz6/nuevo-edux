<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Existing accounts marked as BLOQUEADO use the supported inactive state.
     * Account blocking is not implemented as a separate domain state.
     */
    public function up(): void
    {
        DB::table('usuarios')
            ->where('estado', 'BLOQUEADO')
            ->update(['estado' => 'INACTIVO']);
    }

    public function down(): void
    {
        // Data normalization is intentionally not reversed.
    }
};
