<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Institucion;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\Estudiante;
use App\Models\CertificadoModular;
use App\Models\Titulacion;
use Illuminate\Support\Facades\Hash;

class TitulacionAndCertificacionTest extends TestCase
{
    use RefreshDatabase;

    private Institucion $instA;
    private Institucion $instB;
    private UnidadAcademica $progEnfA;
    private UnidadAcademica $progContB;
    private Usuario $adminA;
    private Usuario $adminB;
    private string $tokenA;
    private string $tokenB;
    private Estudiante $estudianteA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);

        $rolAdmin = Rol::where('nombre', 'ADMINISTRADOR')->first();
        $rolEst = Rol::where('nombre', 'ESTUDIANTE')->first();

        // Institución A (SAM)
        $this->instA = Institucion::create([
            'codigo' => 'IESTP-SAM',
            'nombre' => 'IESTP Santiago Antunez de Mayolo',
            'tipo_institucion' => 'INSTITUTO',
            'estado' => 'ACTIVO',
        ]);

        $this->progEnfA = UnidadAcademica::withoutGlobalScopes()->create([
            'institucion_id' => $this->instA->id,
            'codigo' => 'PROG-ENF',
            'nombre' => 'Enfermería Técnica',
            'tipo' => 'PROGRAMA_ESTUDIOS',
            'estado' => 'ACTIVO',
        ]);

        $this->adminA = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->instA->id,
            'username' => 'admin.sam',
            'nombres' => 'Admin',
            'apellido_paterno' => 'SAM',
            'email' => 'admin@sam.edu.pe',
            'password' => Hash::make('Admin2026!'),
            'documento_tipo' => 'DNI',
            'documento_numero' => '10000001',
            'estado' => 'ACTIVO',
        ]);
        $this->adminA->roles()->sync([$rolAdmin->id]);
        $this->tokenA = $this->adminA->createToken('test')->plainTextToken;

        $userEstA = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->instA->id,
            'username' => 'est.rosa',
            'nombres' => 'Rosa',
            'apellido_paterno' => 'Huaman',
            'email' => 'rosa@sam.edu.pe',
            'password' => Hash::make('Alumno2026!'),
            'documento_tipo' => 'DNI',
            'documento_numero' => '45678901',
            'estado' => 'ACTIVO',
        ]);
        $userEstA->roles()->sync([$rolEst->id]);

        $this->estudianteA = Estudiante::withoutGlobalScopes()->create([
            'institucion_id' => $this->instA->id,
            'usuario_id' => $userEstA->id,
            'unidad_academica_id' => $this->progEnfA->id,
            'codigo_estudiante' => 'EST-SAM-001',
            'estado' => 'ACTIVO',
        ]);

        // Institución B
        $this->instB = Institucion::create([
            'codigo' => 'OTRO-INST',
            'nombre' => 'Instituto Secundario',
            'tipo_institucion' => 'INSTITUTO',
            'estado' => 'ACTIVO',
        ]);

        $this->progContB = UnidadAcademica::withoutGlobalScopes()->create([
            'institucion_id' => $this->instB->id,
            'codigo' => 'PROG-CONT',
            'nombre' => 'Contabilidad',
            'tipo' => 'PROGRAMA_ESTUDIOS',
            'estado' => 'ACTIVO',
        ]);

        $this->adminB = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->instB->id,
            'username' => 'admin.otro',
            'nombres' => 'Admin',
            'apellido_paterno' => 'Otro',
            'email' => 'admin@otro.edu.pe',
            'password' => Hash::make('Admin2026!'),
            'documento_tipo' => 'DNI',
            'documento_numero' => '20000002',
            'estado' => 'ACTIVO',
        ]);
        $this->adminB->roles()->sync([$rolAdmin->id]);
        $this->tokenB = $this->adminB->createToken('test')->plainTextToken;
    }

    public function test_admin_puede_registrar_titulo_en_libro_de_titulos(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/admin/titulacion', [
                'estudiante_id' => $this->estudianteA->id,
                'programa_id' => $this->progEnfA->id,
                'modalidad_titulacion' => 'PROYECTO_APLICACION_PROFESIONAL',
                'numero_titulo' => 'T-2026-001',
                'numero_registro' => 'REG-2026-001',
                'numero_folio' => '010',
                'numero_libro' => 'LIB-IV',
                'resolucion_directoral' => 'RD N° 050-2026-DG-IESTP-SAM',
                'codigo_minedu' => 'TIT-MINEDU-2026-001',
                'fecha_sustentacion' => '2026-01-15',
                'fecha_emision' => '2026-02-01',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals('T-2026-001', $data['numero_titulo']);
        $this->assertNotEmpty($data['codigo_verificacion']);
        $this->assertStringStartsWith('TIT-SAM-', $data['codigo_verificacion']);

        $this->assertDatabaseHas('titulaciones', [
            'institucion_id' => $this->instA->id,
            'numero_titulo' => 'T-2026-001',
            'numero_registro' => 'REG-2026-001',
        ]);
    }

    public function test_admin_puede_emitir_certificado_modular_con_efsrt(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
            ->postJson('/api/v1/admin/certificados-modulares', [
                'estudiante_id' => $this->estudianteA->id,
                'programa_id' => $this->progEnfA->id,
                'numero_modulo' => 1,
                'nombre_modulo' => 'Módulo I: Atención Integral en Salud',
                'resolucion_directoral' => 'RD N° 088-2025-DG-IESTP-SAM',
                'horas_teoria' => 144,
                'horas_practica' => 288,
                'creditos' => 22.0,
                'horas_efsrt' => 120,
                'fecha_emision' => '2025-12-20',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals(1, $data['numero_modulo']);
        $this->assertEquals(120, $data['horas_efsrt']);
        $this->assertStringStartsWith('MOD-SAM-', $data['codigo_verificacion']);

        $this->assertDatabaseHas('certificados_modulares', [
            'institucion_id' => $this->instA->id,
            'numero_modulo' => 1,
            'horas_efsrt' => 120,
        ]);
    }

    public function test_verificacion_publica_por_codigo_qr_sin_autenticacion(): void
    {
        // Registrar título
        $titulacion = Titulacion::withoutGlobalScopes()->create([
            'institucion_id' => $this->instA->id,
            'estudiante_id' => $this->estudianteA->id,
            'programa_id' => $this->progEnfA->id,
            'modalidad_titulacion' => 'PROYECTO_APLICACION_PROFESIONAL',
            'numero_titulo' => 'T-2026-099',
            'numero_registro' => 'REG-2026-099',
            'numero_folio' => '050',
            'numero_libro' => 'LIB-IV',
            'resolucion_directoral' => 'RD N° 099-2026-DG-IESTP-SAM',
            'codigo_verificacion' => 'TIT-SAM-PRUEBA-99',
            'fecha_emision' => '2026-03-01',
            'estado' => 'EMITIDO',
        ]);

        // Petición pública sin token Bearer
        $response = $this->getJson('/api/v1/public/verificar-documento/TIT-SAM-PRUEBA-99');
        $response->assertStatus(200);
        $response->assertJson([
            'tipo_documento' => 'TITULO_PROFESIONAL_TECNICO',
            'valido' => true,
            'institucion' => 'IESTP Santiago Antunez de Mayolo',
            'programa_estudio' => 'Enfermería Técnica',
        ]);
    }

    public function test_aislamiento_institucional_en_titulaciones(): void
    {
        // Crear título en Institución A
        Titulacion::withoutGlobalScopes()->create([
            'institucion_id' => $this->instA->id,
            'estudiante_id' => $this->estudianteA->id,
            'programa_id' => $this->progEnfA->id,
            'modalidad_titulacion' => 'PROYECTO_APLICACION_PROFESIONAL',
            'numero_titulo' => 'T-2026-INST-A',
            'numero_registro' => 'REG-001',
            'numero_folio' => '001',
            'numero_libro' => 'LIB-I',
            'resolucion_directoral' => 'RD N° 01',
            'codigo_verificacion' => 'TIT-A-001',
            'fecha_emision' => '2026-01-01',
            'estado' => 'EMITIDO',
        ]);

        // Admin B no debe ver el título de Institución A
        $responseB = $this->withToken($this->tokenB)
            ->getJson('/api/v1/admin/titulacion');

        $responseB->assertStatus(200);
        $dataB = $responseB->json('data.data');
        $this->assertCount(0, $dataB);
    }

    public function test_admin_a_puede_ver_sus_propias_titulaciones(): void
    {
        Titulacion::withoutGlobalScopes()->create([
            'institucion_id' => $this->instA->id,
            'estudiante_id' => $this->estudianteA->id,
            'programa_id' => $this->progEnfA->id,
            'modalidad_titulacion' => 'PROYECTO_APLICACION_PROFESIONAL',
            'numero_titulo' => 'T-2026-INST-A',
            'numero_registro' => 'REG-001',
            'numero_folio' => '001',
            'numero_libro' => 'LIB-I',
            'resolucion_directoral' => 'RD N° 01',
            'codigo_verificacion' => 'TIT-A-001',
            'fecha_emision' => '2026-01-01',
            'estado' => 'EMITIDO',
        ]);

        $responseA = $this->withToken($this->tokenA)
            ->getJson('/api/v1/admin/titulacion');

        $responseA->assertStatus(200);
        $dataA = $responseA->json('data.data');
        $this->assertCount(1, $dataA);
        $this->assertEquals('T-2026-INST-A', $dataA[0]['numero_titulo']);
    }

    public function test_exportacion_csv_libro_de_titulos(): void
    {
        Titulacion::withoutGlobalScopes()->create([
            'institucion_id' => $this->instA->id,
            'estudiante_id' => $this->estudianteA->id,
            'programa_id' => $this->progEnfA->id,
            'modalidad_titulacion' => 'PROYECTO_APLICACION_PROFESIONAL',
            'numero_titulo' => 'T-2026-CSV',
            'numero_registro' => 'REG-CSV',
            'numero_folio' => '002',
            'numero_libro' => 'LIB-I',
            'resolucion_directoral' => 'RD N° 02',
            'codigo_verificacion' => 'TIT-CSV-001',
            'fecha_emision' => '2026-01-01',
            'estado' => 'EMITIDO',
        ]);

        $response = $this->withToken($this->tokenA)
            ->get('/api/v1/admin/titulacion/exportar/libro-titulos');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('N° Libro', $content);
        $this->assertStringContainsString('N° Folio', $content);
        $this->assertStringContainsString('T-2026-CSV', $content);
    }
}
