<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Ambiente;
use App\Models\Institucion;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);
    }

    public function test_audit_rechaza_atributos_no_autorizados_en_asignacion_masiva(): void
    {
        $audit = new Audit([
            'id' => 999,
            'created_at' => now()->subYear(),
            'accion' => 'PRUEBA',
            'entidad_tipo' => Usuario::class,
            'entidad_id' => 1,
        ]);

        $this->assertNull($audit->id);
        $this->assertNull($audit->created_at);
        $this->assertSame('PRUEBA', $audit->accion);
    }

    public function test_docente_y_estudiante_no_pueden_modificar_matriculas(): void
    {
        foreach (['DOCENTE', 'ESTUDIANTE'] as $roleName) {
            $user = $this->userWithRole($roleName);
            Sanctum::actingAs($user);

            $this->patchJson('/api/v1/academico/matriculas/999999', [
                'estado' => 'ANULADO',
            ])->assertForbidden();

            $this->deleteJson('/api/v1/academico/matriculas/999999')
                ->assertForbidden();

            $this->postJson('/api/v1/academico/matriculas', [])
                ->assertForbidden();

            $this->postJson('/api/v1/academico/matriculas/seccion', [])
                ->assertForbidden();
        }
    }

    public function test_health_publico_no_expone_servicios_ni_version(): void
    {
        $response = $this->getJson('/api/v1/health');

        $this->assertContains($response->status(), [200, 503]);
        $this->assertSame(['status'], array_keys($response->json()));
    }

    public function test_superadmin_con_afiliacion_institucional_administra_globalmente(): void
    {
        $institutionA = Institucion::create([
            'codigo' => 'GLOBAL-A',
            'nombre' => 'Institución Global A',
            'tipo_institucion' => 'INSTITUTO',
            'estado' => 'ACTIVO',
        ]);
        $institutionB = Institucion::create([
            'codigo' => 'GLOBAL-B',
            'nombre' => 'Institución Global B',
            'tipo_institucion' => 'INSTITUTO',
            'estado' => 'ACTIVO',
        ]);

        $ambienteA = Ambiente::withoutGlobalScopes()->create([
            'institucion_id' => $institutionA->id,
            'codigo' => 'AMB-A',
            'nombre' => 'Ambiente A',
            'tipo' => 'AULA',
            'capacidad' => 20,
            'estado' => 'ACTIVO',
        ]);
        $ambienteB = Ambiente::withoutGlobalScopes()->create([
            'institucion_id' => $institutionB->id,
            'codigo' => 'AMB-B',
            'nombre' => 'Ambiente B',
            'tipo' => 'AULA',
            'capacidad' => 20,
            'estado' => 'ACTIVO',
        ]);

        // Superadmin afiliado obligatoriamente a institución A
        $superAdmin = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $institutionA->id,
            'username' => 'superadmin-global',
            'email' => 'superadmin-global@edux.test',
            'password' => 'Password2026!',
            'nombres' => 'Superadmin',
            'apellido_paterno' => 'Global',
            'estado' => 'ACTIVO',
        ]);
        $superAdmin->roles()->attach(Rol::where('nombre', 'SUPERADMIN')->firstOrFail());
        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/v1/admin/ambientes');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ambienteA->id));
        $this->assertTrue($ids->contains($ambienteB->id));
    }

    private function userWithRole(string $roleName): Usuario
    {
        $institution = Institucion::create([
            'codigo' => 'INST-' . $roleName,
            'nombre' => 'Institución ' . $roleName,
            'tipo_institucion' => 'INSTITUTO',
            'estado' => 'ACTIVO',
        ]);

        $user = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $institution->id,
            'username' => strtolower($roleName),
            'email' => strtolower($roleName) . '@modux.test',
            'password' => 'Password2026!',
            'nombres' => $roleName,
            'apellido_paterno' => 'Prueba',
            'estado' => 'ACTIVO',
        ]);
        $user->roles()->attach(Rol::where('nombre', $roleName)->firstOrFail());

        return $user;
    }
}
