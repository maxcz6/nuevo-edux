<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

// Example: run cleanup of expired tokens daily at midnight
Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::call(function (): void {
    DB::table('password_reset_tokens')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->name('prune-expired-password-reset-tokens')->withoutOverlapping();
