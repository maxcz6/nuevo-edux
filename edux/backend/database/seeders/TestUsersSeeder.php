<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $rolCoord = Rol::where('nombre', 'COORDINADOR')->first();
        $rolDocente = Rol::where('nombre', 'DOCENTE')->first();
        $rolEstudiante = Rol::where('nombre', 'ESTUDIANTE')->first();

        if (!$rolCoord || !$rolDocente || !$rolEstudiante) {
            $this->command?->error('Roles base no encontrados. Ejecuta RolesPermisosSeeder primero.');
            return;
        }

        $programas = UnidadAcademica::where('tipo', 'PROGRAMA_ESTUDIOS')->get();

        foreach ($programas as $prog) {
            $coordEmail = 'coord.' . strtolower($prog->codigo) . '@edux.demo';

            $coord = Usuario::where('email', $coordEmail)->first();
            if (!$coord) {
                $coord = Usuario::create([
                    'institucion_id' => 1,
                    'unidad_academica_id' => $prog->id,
                    'username' => 'coord.' . strtolower($prog->codigo),
                    'email' => $coordEmail,
                    'password' => Hash::make('Coord2026!'),
                    'nombres' => 'Coordinador',
                    'apellido_paterno' => $prog->nombre,
                    'apellido_materno' => 'Test',
                    'documento_numero' => '7000000' . $prog->id,
                    'telefono' => '90000000' . $prog->id,
                    'estado' => 'ACTIVO',
                ]);
                $coord->roles()->sync([$rolCoord->id]);
                echo "Coordinador: {$coord->email} -> {$prog->nombre}\n";
            }

            for ($i = 1; $i <= 3; $i++) {
                $docEmail = 'doc.' . strtolower($prog->codigo) . "t{$i}@edux.demo";
                if (Usuario::where('email', $docEmail)->exists()) {
                    echo "Docente ya existe: {$docEmail}\n";
                    continue;
                }
                $doc = Usuario::create([
                    'institucion_id' => 1,
                    'unidad_academica_id' => $prog->id,
                    'username' => 'doc.' . strtolower($prog->codigo) . "t{$i}",
                    'email' => $docEmail,
                    'password' => Hash::make('Docente2026!'),
                    'nombres' => "Docente {$i}",
                    'apellido_paterno' => $prog->nombre,
                    'apellido_materno' => 'Test',
                    'documento_numero' => '7100000' . $prog->id . $i,
                    'telefono' => '91000000' . $prog->id . $i,
                    'estado' => 'ACTIVO',
                ]);
                $doc->roles()->sync([$rolDocente->id]);
                echo "  Docente: {$doc->email}\n";
            }

            for ($i = 1; $i <= 2; $i++) {
                $estEmail = 'est.' . strtolower($prog->codigo) . "t{$i}@edux.demo";
                if (Usuario::where('email', $estEmail)->exists()) {
                    echo "Estudiante ya existe: {$estEmail}\n";
                    continue;
                }
                $est = Usuario::create([
                    'institucion_id' => 1,
                    'unidad_academica_id' => $prog->id,
                    'username' => 'est.' . strtolower($prog->codigo) . "t{$i}",
                    'email' => $estEmail,
                    'password' => Hash::make('Alumno2026!'),
                    'nombres' => "Estudiante {$i}",
                    'apellido_paterno' => $prog->nombre,
                    'apellido_materno' => 'Test',
                    'documento_numero' => '7200000' . $prog->id . $i,
                    'telefono' => '92000000' . $prog->id . $i,
                    'estado' => 'ACTIVO',
                ]);
                $est->roles()->sync([$rolEstudiante->id]);
                echo "  Estudiante: {$est->email}\n";
            }
        }

        echo "\n=== RESUMEN ===\n";
        $totalCoord = Usuario::whereHas('roles', fn($q) => $q->where('nombre', 'COORDINADOR'))->count();
        $totalDoc = Usuario::whereHas('roles', fn($q) => $q->where('nombre', 'DOCENTE'))->count();
        $totalEst = Usuario::whereHas('roles', fn($q) => $q->where('nombre', 'ESTUDIANTE'))->count();
        echo "Coordinadores: {$totalCoord}\n";
        echo "Docentes: {$totalDoc}\n";
        echo "Estudiantes: {$totalEst}\n";
    }
}