<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Auth guard uses 'usuarios' table via config/auth.php
        // Sanctum token auth is the default for the API
    }
}
