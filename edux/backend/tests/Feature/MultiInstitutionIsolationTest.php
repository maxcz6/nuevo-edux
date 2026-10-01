<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Institucion;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\PeriodoAcademico;
use Illuminate\Support\Facades\Hash;

/**
 * MultiInstitutionIsolationTest — Aislamiento entre instituciones.
 */
class MultiInstitutionIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Institucion $instA;
    private Institucion $instB;
    private Usuario $adminA;
    private Usuario $adminB;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);

        $this->instA = Institucion::create([
            'codigo' => 'INST-A', 'nombre' => 'Institución Alpha',
            'tipo_institucion' => 'INSTITUTO', 'estado' => 'ACTIVO',
        ]);

        $this->instB = Institucion::create([
            'codigo' => 'INST-B', 'nombre' => 'Institución Beta',
            'tipo_institucion' => 'COLEGIO', 'estado' => 'ACTIVO',
        ]);

        $rolAdmin = Rol::where('nombre', 'ADMINISTRADOR')->first();

        $this->adminA = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->instA->id,
            'username' => 'adminA', 'email' => 'admin@alpha.local',
            'password' => Hash::make('Admin2026!'),
            'nombres' => 'Admin Alpha', 'apellido_paterno' => 'Alpha',
            'estado' => 'ACTIVO',
        ]);
        $this->adminA->roles()->attach($rolAdmin->id);
        $this->tokenA = $this->adminA->createToken('test-a')->plainTextToken;

        $this->adminB = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->instB->id,
            'username' => 'adminB', 'email' => 'admin@beta.local',
            'password' => Hash::make('Admin2026!'),
            'nombres' => 'Admin Beta', 'apellido_paterno' => 'Beta',
            'estado' => 'ACTIVO',
        ]);
        $this->adminB->roles()->attach($rolAdmin->id);
        $this->tokenB = $this->adminB->createToken('test-b')->plainTextToken;

        // Período solo de institución B
        PeriodoAcademico::withoutGlobalScopes()->create([
            'institucion_id' => $this->instB->id,
            'codigo' => '2026-B', 'nombre' => 'Año 2026',
            'tipo_periodo' => 'ANUAL',
            'fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-12-31',
            'estado' => 'ACTIVO',
        ]);
    }

    public function test_admin_A_no_puede_ver_periodos_de_institucion_B(): void
    {
        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/academico/periodos');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $periodoBId = PeriodoAcademico::withoutGlobalScopes()
            ->where('institucion_id', $this->instB->id)->first()?->id;

        $this->assertFalse($ids->contains($periodoBId),
            'adminA puede ver períodos de institución B — FALLO DE AISLAMIENTO');
    }

    public function test_admin_A_no_puede_ver_usuarios_de_institucion_B(): void
    {
        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/admin/usuarios');

        $response->assertStatus(200);

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertFalse($emails->contains('admin@beta.local'),
            'adminA puede ver usuarios de institución B — FALLO DE AISLAMIENTO');
    }

    public function test_admin_A_no_puede_modificar_usuario_de_institucion_B(): void
    {
        $response = $this->withToken($this->tokenA)
            ->putJson("/api/v1/admin/usuarios/{$this->adminB->id}", [
                'nombres' => 'Hackeado',
            ]);

        // Debe ser 403 o 404 — NUNCA 200
        $this->assertContains($response->status(), [403, 404],
            'adminA pudo modificar usuario de institución B — FALLO DE AISLAMIENTO');
    }

    public function test_header_x_institution_id_no_permite_cruzar_instituciones(): void
    {
        // adminA intenta ver datos de instB usando el header
        $response = $this->withToken($this->tokenA)
            ->withHeaders(['X-Institution-Id' => (string) $this->instB->id])
            ->getJson('/api/v1/academico/periodos');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $periodoBId = PeriodoAcademico::withoutGlobalScopes()
            ->where('institucion_id', $this->instB->id)->first()?->id;

        // El header no debe tener efecto — adminA nunca debe ver datos de instB
        $this->assertFalse($ids->contains($periodoBId),
            'X-Institution-Id header permitió cruzar instituciones — VULNERABILIDAD CRÍTICA');
    }

    public function test_admin_B_no_puede_ver_datos_de_institucion_A(): void
    {
        // Crear usuario en instA
        Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->instA->id,
            'username' => 'userA01', 'email' => 'userA01@alpha.local',
            'password' => Hash::make('Pass2026!'),
            'nombres' => 'User A01', 'apellido_paterno' => 'Alpha',
            'estado' => 'ACTIVO',
        ]);

        $response = $this->withToken($this->tokenB)
            ->getJson('/api/v1/admin/usuarios');

        $response->assertStatus(200);
        $emails = collect($response->json('data'))->pluck('email');
        $this->assertFalse($emails->contains('userA01@alpha.local'),
            'adminB puede ver usuarios de institución A — FALLO DE AISLAMIENTO');
    }

    public function test_superadmin_tiene_afiliacion_obligatoria_y_administra_globalmente(): void
    {
        $rolSuperAdmin = Rol::where('nombre', 'SUPERADMIN')->first();

        // Superadmin afiliado a la institución A
        $superadmin = Usuario::withoutGlobalScopes()->create([
            'institucion_id'      => $this->instA->id,
            'username'            => 'superadmin_global',
            'email'               => 'superadmin@edux.global',
            'password'            => Hash::make('SuperAdmin2026!'),
            'nombres'             => 'Super Administrador',
            'apellido_paterno'    => 'Global',
            'estado'              => 'ACTIVO',
        ]);
        $superadmin->roles()->attach($rolSuperAdmin->id);
        $tokenSuper = $superadmin->createToken('test-super')->plainTextToken;

        // La afiliación institucional del superadmin no es nula
        $this->assertNotNull($superadmin->institucion_id, 'El SuperAdmin debe poseer una afiliación institucional obligatoria.');

        // Puede ver periodos de la institución B gracias a su autorización global
        $response = $this->withToken($tokenSuper)
            ->getJson('/api/v1/academico/periodos');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $periodoBId = PeriodoAcademico::withoutGlobalScopes()
            ->where('institucion_id', $this->instB->id)->first()?->id;

        $this->assertTrue($ids->contains($periodoBId),
            'El SuperAdmin debe poder supervisar globalmente todas las instituciones.');
    }

    public function test_usuario_sin_institucion_es_bloqueado_estrictamente(): void
    {
        $rolAdmin = Rol::where('nombre', 'ADMINISTRADOR')->first();

        // Usuario forzado sin institucion_id
        $usuarioHuerfano = new Usuario([
            'username'         => 'huerfano',
            'email'            => 'huerfano@test.local',
            'password'         => Hash::make('Pass2026!'),
            'nombres'          => 'Sin Institución',
            'apellido_paterno' => 'Huerfano',
            'estado'           => 'ACTIVO',
        ]);
        // Guardar sin scopes
        $usuarioHuerfano->saveQuietly();
        $usuarioHuerfano->roles()->attach($rolAdmin->id);
        $tokenHuerfano = $usuarioHuerfano->createToken('test-huerfano')->plainTextToken;

        $response = $this->withToken($tokenHuerfano)
            ->getJson('/api/v1/academico/periodos');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'no_institution',
        ]);
    }
}
