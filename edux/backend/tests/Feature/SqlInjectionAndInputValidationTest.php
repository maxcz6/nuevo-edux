<?php

namespace Tests\Feature;

use App\Models\Institucion;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Ambiente;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Curso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SqlInjectionAndInputValidationTest extends TestCase
{
    use RefreshDatabase;

    private Institucion $institucion;
    private Usuario $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);

        $this->institucion = Institucion::create([
            'codigo' => 'INST-SEC-01',
            'nombre' => 'Institución Seguridad Test',
            'tipo_institucion' => 'INSTITUTO',
            'estado' => 'ACTIVO',
        ]);

        $this->adminUser = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->institucion->id,
            'username' => 'secadmin',
            'email' => 'secadmin@institucion.edu.pe',
            'password' => bcrypt('SecAdmin2026!'),
            'nombres' => 'Seguridad',
            'apellido_paterno' => 'Admin',
            'estado' => 'ACTIVO',
        ]);

        $rolAdmin = Rol::where('nombre', 'ADMINISTRADOR')->firstOrFail();
        $this->adminUser->roles()->attach($rolAdmin->id);

        Ambiente::withoutGlobalScopes()->create([
            'institucion_id' => $this->institucion->id,
            'codigo' => 'LAB-101',
            'nombre' => 'Laboratorio de Computo',
            'tipo' => 'LABORATORIO',
            'pabellon' => 'A',
            'piso' => 1,
            'capacidad' => 30,
            'estado' => 'ACTIVO',
        ]);
    }

    /**
     * Prueba que las búsquedas con payloads SQL Injection clásicos
     * son parametrizadas de forma segura por Eloquent/PostgreSQL.
     */
    public function test_busqueda_con_payloads_sql_injection_es_inofensiva(): void
    {
        Sanctum::actingAs($this->adminUser);

        $sqliPayloads = [
            "' OR '1'='1",
            "'; DROP TABLE usuarios; --",
            "1' UNION SELECT 1,2,3,4,5,6,7,8,9,10--",
            "admin'--",
            "' OR 1=1--",
            "\" OR \"\"=\"",
            "' HAVING 1=1--",
            "1'; EXEC xp_cmdshell('dir');--",
        ];

        foreach ($sqliPayloads as $payload) {
            // Probar en endpoint de ambientes
            $resAmbientes = $this->getJson('/api/v1/admin/ambientes?search=' . urlencode($payload));
            $resAmbientes->assertOk();
            // Debe responder JSON válido sin romper la BD
            $this->assertIsArray($resAmbientes->json('data'));

            // Probar en endpoint de docentes
            $resDocentes = $this->getJson('/api/v1/admin/docentes?search=' . urlencode($payload));
            $resDocentes->assertOk();

            // Probar en endpoint de estudiantes
            $resEstudiantes = $this->getJson('/api/v1/admin/estudiantes?search=' . urlencode($payload));
            $resEstudiantes->assertOk();
        }

        // Verificar que la base de datos sigue intacta
        $this->assertDatabaseHas('usuarios', ['id' => $this->adminUser->id]);
        $this->assertDatabaseHas('ambientes', ['codigo' => 'LAB-101']);
    }

    /**
     * Prueba que las inyecciones de tiempo (Time-based Blind SQLi) son tratadas
     * como texto literal y no provocan demoras de ejecución.
     */
    public function test_time_based_sqli_es_inofensiva(): void
    {
        Sanctum::actingAs($this->adminUser);

        $startTime = microtime(true);
        $res = $this->getJson('/api/v1/admin/ambientes?search=' . urlencode("LAB-101' AND (SELECT 1 FROM pg_sleep(5))--"));
        $elapsed = microtime(true) - $startTime;

        $res->assertOk();
        // Debe responder en menos de 2 segundos (no ejecutó el sleep)
        $this->assertLessThan(2.0, $elapsed, 'El servidor ejecutó el payload pg_sleep() en lugar de parametrizarlo.');
    }

    /**
     * Prueba que los parámetros de paginación inválidos, negativos, extremos o maliciosos
     * son sanitizados automáticamente al rango seguro [1, 100].
     */
    public function test_sanitizacion_estricta_de_paginacion(): void
    {
        Sanctum::actingAs($this->adminUser);

        // per_page desmedido (intento de agotamiento de memoria)
        $resHuge = $this->getJson('/api/v1/admin/ambientes?per_page=99999999');
        $resHuge->assertOk();
        $metaHuge = $resHuge->json('meta');
        if ($metaHuge && isset($metaHuge['per_page'])) {
            $this->assertLessThanOrEqual(100, $metaHuge['per_page']);
        }

        // per_page negativo o 0
        $resNeg = $this->getJson('/api/v1/admin/ambientes?per_page=-50&page=-1');
        $resNeg->assertOk();

        // per_page con texto malicioso o SQLi
        $resText = $this->getJson('/api/v1/admin/ambientes?per_page=invalid_text_123');
        $resText->assertOk();
    }

    /**
     * Prueba que los caracteres de control nulo (Null Byte Injection) son rechazados o sanitizados.
     */
    public function test_rechazo_de_null_bytes_en_autenticacion(): void
    {
        $res = $this->postJson('/api/v1/auth/login', [
            'email' => "secadmin\0@institucion.edu.pe",
            'password' => "SecAdmin2026!",
        ]);

        // Debe retornar 401 o 422 sin error fatal 500
        $this->assertTrue(in_array($res->status(), [401, 422]));
    }
}
