<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesPermisosSeeder::class,
        ]);

        // Cargar datos demo solo si no estamos en producción o si está explícitamente habilitado
        if (!app()->environment('production') || env('SEED_DEMO_DATA', false)) {
            $this->call([
                InstitucionDemoSeeder::class,
            ]);
        }
    }
}
