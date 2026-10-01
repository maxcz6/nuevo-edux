<?php

namespace Tests\Feature;

use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NuevosRolesInstitucionalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);
    }

    public function test_los_roles_nuevos_existen_y_tienen_permisos_restringidos(): void
    {
        $esperado = [
            'SUPERADMIN'    => ['instituciones.ver', 'usuarios.ver', 'roles.ver'],
            'ADMINISTRADOR' => ['usuarios.ver', 'docentes.ver', 'estudiantes.ver', 'matriculas.ver'],
            'COORDINADOR'   => ['docentes.ver', 'estudiantes.ver', 'cursos.ver', 'grupos.ver'],
            'DOCENTE'       => ['aula.ver', 'tareas.crear', 'calificaciones.crear'],
            'ESTUDIANTE'    => ['aula.ver', 'entregas.crear', 'calificaciones.ver'],
        ];

        foreach ($esperado as $nombreRol => $codigos) {
            $rol = Rol::where('nombre', $nombreRol)->firstOrFail();

            $this->assertNotNull($rol);
            $this->assertGreaterThan(0, $rol->permisos()->count());

            foreach ($codigos as $codigo) {
                $this->assertTrue(
                    $rol->permisos()->where('codigo', $codigo)->exists(),
                    "El rol {$nombreRol} debe tener el permiso {$codigo}."
                );
            }
        }
    }
}
