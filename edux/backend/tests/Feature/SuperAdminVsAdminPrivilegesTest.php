<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Institucion;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\Curso;
use App\Models\UnidadAcademica;
use Illuminate\Support\Facades\Hash;

class SuperAdminVsAdminPrivilegesTest extends TestCase
{
    use RefreshDatabase;

    private Institucion $instA;
    private Institucion $instB;
    private Usuario $superAdmin;
    private Usuario $adminA;
    private string $superToken;
    private string $adminTokenA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);

        $this->instA = Institucion::create([
            'codigo' => 'INST-A',
            'nombre' => 'Instituto Santiago Antúnez de Mayolo',
            'tipo_institucion' => 'INSTITUTO',
            'estado' => 'ACTIVO',
        ]);

        $this->instB = Institucion::create([
            'codigo' => 'INST-B',
            'nombre' => 'Instituto Continental',
            'tipo_institucion' => 'INSTITUTO',
            'estado' => 'ACTIVO',
        ]);

        $rolSuperAdmin = Rol::where('nombre', 'SUPERADMIN')->first();
        $rolAdmin = Rol::where('nombre', 'ADMINISTRADOR')->first();

        // Superadmin
        $this->superAdmin = Usuario::withoutGlobalScopes()->create([
            'institucion_id'   => $this->instA->id,
            'username'         => 'superadmin',
            'email'            => 'superadmin@modux.test',
            'password'         => Hash::make('SuperAdmin2026!'),
            'nombres'          => 'Super',
            'apellido_paterno' => 'Global',
            'estado'           => 'ACTIVO',
        ]);
        $this->superAdmin->roles()->attach($rolSuperAdmin->id);
        $this->superToken = $this->superAdmin->createToken('test-super')->plainTextToken;

        // Admin Institución A
        $this->adminA = Usuario::withoutGlobalScopes()->create([
            'institucion_id'   => $this->instA->id,
            'username'         => 'adminA',
            'email'            => 'adminA@modux.test',
            'password'         => Hash::make('Admin2026!'),
            'nombres'          => 'Admin',
            'apellido_paterno' => 'Alpha',
            'estado'           => 'ACTIVO',
        ]);
        $this->adminA->roles()->attach($rolAdmin->id);
        $this->adminTokenA = $this->adminA->createToken('test-admin-a')->plainTextToken;
    }

    public function test_superadmin_puede_ver_y_editar_datos_de_cualquier_institucion(): void
    {
        // Crear curso en institución B
        $cursoB = Curso::withoutGlobalScopes()->create([
            'institucion_id' => $this->instB->id,
            'codigo'         => 'CURSO-B1',
            'nombre'         => 'Programación en Institución B',
            'creditos'       => 4,
            'horas_semanales'=> 4,
            'estado'         => 'ACTIVO',
        ]);

        // 1. SUPERADMIN lista cursos y puede ver el curso de la Institución B
        $response = $this->withHeader('Authorization', "Bearer {$this->superToken}")
            ->getJson('/api/v1/academico/cursos');

        $response->assertStatus(200);
        $this->assertTrue(collect($response->json('data'))->contains('id', $cursoB->id));

        // 2. SUPERADMIN puede editar el curso de la Institución B
        $editResponse = $this->withHeader('Authorization', "Bearer {$this->superToken}")
            ->putJson("/api/v1/academico/cursos/{$cursoB->id}", [
                'nombre' => 'Programación Avanzada Editada por Superadmin',
            ]);

        $editResponse->assertStatus(200);
        $this->assertDatabaseHas('cursos', [
            'id'     => $cursoB->id,
            'nombre' => 'Programación Avanzada Editada por Superadmin',
        ]);
    }

    public function test_admin_solo_ve_y_edita_datos_de_su_institucion(): void
    {
        // Crear curso en institución B
        $cursoB = Curso::withoutGlobalScopes()->create([
            'institucion_id' => $this->instB->id,
            'codigo'         => 'CURSO-B2',
            'nombre'         => 'Base de Datos Beta',
            'creditos'       => 3,
            'horas_semanales'=> 3,
            'estado'         => 'ACTIVO',
        ]);

        // 1. Admin A no ve el curso de Institución B
        $response = $this->withHeader('Authorization', "Bearer {$this->adminTokenA}")
            ->getJson('/api/v1/academico/cursos');

        $response->assertStatus(200);
        $this->assertFalse(collect($response->json('data'))->contains('id', $cursoB->id));

        // 2. Admin A no puede editar el curso de Institución B
        $editResponse = $this->withHeader('Authorization', "Bearer {$this->adminTokenA}")
            ->putJson("/api/v1/academico/cursos/{$cursoB->id}", [
                'nombre' => 'Intento Hack Admin A',
            ]);

        $editResponse->assertStatus(404);
    }

    public function test_admin_solo_desactiva_y_no_puede_eliminar_permanentemente(): void
    {
        $rolDocente = Rol::where('nombre', 'DOCENTE')->first();
        $userDocente = Usuario::withoutGlobalScopes()->create([
            'institucion_id'   => $this->instA->id,
            'username'         => 'docenteA1',
            'email'            => 'docenteA1@modux.test',
            'password'         => Hash::make('Docente2026!'),
            'nombres'          => 'Docente',
            'apellido_paterno' => 'Uno',
            'estado'           => 'ACTIVO',
        ]);
        $userDocente->roles()->attach($rolDocente->id);

        $curso = Curso::withoutGlobalScopes()->create([
            'institucion_id' => $this->instA->id,
            'codigo'         => 'CURSO-A1',
            'nombre'         => 'Curso para desactivar',
            'creditos'       => 2,
            'horas_semanales'=> 2,
            'estado'         => 'ACTIVO',
        ]);

        // 1. Admin A desactiva el usuario: no se elimina de la base de datos sino que pasa a INACTIVO
        $resDeleteUser = $this->withHeader('Authorization', "Bearer {$this->adminTokenA}")
            ->deleteJson("/api/v1/admin/usuarios/{$userDocente->id}");

        $resDeleteUser->assertStatus(200);
        $this->assertDatabaseHas('usuarios', [
            'id'     => $userDocente->id,
            'estado' => 'INACTIVO',
        ]);

        // 2. Admin A intenta borrado forzado permanente (?force=true): debe ser denegado con 403 Forbidden
        $resForceDelete = $this->withHeader('Authorization', "Bearer {$this->adminTokenA}")
            ->deleteJson("/api/v1/admin/usuarios/{$userDocente->id}?force=true");

        $resForceDelete->assertStatus(403);
        $resForceDelete->assertJsonFragment(['error' => 'forbidden_action']);

        // 3. Admin A desactiva curso: pasa a INACTIVO sin borrarse
        $resDeleteCurso = $this->withHeader('Authorization', "Bearer {$this->adminTokenA}")
            ->deleteJson("/api/v1/academico/cursos/{$curso->id}");

        $resDeleteCurso->assertStatus(200);
        $this->assertDatabaseHas('cursos', [
            'id'     => $curso->id,
            'estado' => 'INACTIVO',
        ]);

        // 4. Admin A intenta borrado definitivo de curso: 403 Forbidden
        $resForceCurso = $this->withHeader('Authorization', "Bearer {$this->adminTokenA}")
            ->deleteJson("/api/v1/academico/cursos/{$curso->id}?force=true");

        $resForceCurso->assertStatus(403);
    }

    public function test_superadmin_puede_desactivar_y_eliminar_definitivamente(): void
    {
        $curso = Curso::withoutGlobalScopes()->create([
            'institucion_id' => $this->instA->id,
            'codigo'         => 'CURSO-SA-DEL',
            'nombre'         => 'Curso de Prueba Superadmin',
            'creditos'       => 2,
            'horas_semanales'=> 2,
            'estado'         => 'ACTIVO',
        ]);

        // 1. Superadmin puede desactivar el curso
        $resDeactivate = $this->withHeader('Authorization', "Bearer {$this->superToken}")
            ->deleteJson("/api/v1/academico/cursos/{$curso->id}?action=desactivar");

        $resDeactivate->assertStatus(200);
        $this->assertDatabaseHas('cursos', [
            'id'     => $curso->id,
            'estado' => 'INACTIVO',
        ]);

        // 2. Superadmin puede eliminar definitivamente el curso
        $resHardDelete = $this->withHeader('Authorization', "Bearer {$this->superToken}")
            ->deleteJson("/api/v1/academico/cursos/{$curso->id}?force=true");

        $resHardDelete->assertStatus(200);
        $this->assertDatabaseMissing('cursos', [
            'id' => $curso->id,
        ]);
    }

    public function test_superadmin_puede_reactivar_un_usuario_inactivo(): void
    {
        $usuario = Usuario::withoutGlobalScopes()->create([
            'institucion_id'   => $this->instB->id,
            'username'         => 'usuarioInactivo',
            'email'            => 'inactivo@modux.test',
            'password'         => Hash::make('Usuario2026!'),
            'nombres'          => 'Usuario',
            'apellido_paterno' => 'Inactivo',
            'estado'           => 'INACTIVO',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->superToken}")
            ->putJson("/api/v1/admin/usuarios/{$usuario->id}", [
                'estado' => 'ACTIVO',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.estado', 'ACTIVO');

        $this->assertDatabaseHas('usuarios', [
            'id'     => $usuario->id,
            'estado' => 'ACTIVO',
        ]);
    }
}
