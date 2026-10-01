<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Institucion;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\PeriodoAcademico;
use App\Models\ConvocatoriaAdmision;
use App\Models\VacanteAdmision;
use App\Models\Postulante;
use App\Models\Estudiante;
use Illuminate\Support\Facades\Hash;

class AdmisionTecnologicaTest extends TestCase
{
    use RefreshDatabase;

    private Institucion $inst;
    private UnidadAcademica $progDpw;
    private Usuario $admin;
    private string $tokenAdmin;
    private PeriodoAcademico $periodo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);

        $this->inst = Institucion::create([
            'codigo' => 'IESTP-SAM',
            'nombre' => 'IESTP Santiago Antunez de Mayolo',
            'tipo_institucion' => 'INSTITUTO',
            'estado' => 'ACTIVO',
        ]);

        $this->progDpw = UnidadAcademica::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'codigo' => 'PROG-DPW',
            'nombre' => 'Diseño y Programación Web',
            'tipo' => 'PROGRAMA_ESTUDIOS',
            'estado' => 'ACTIVO',
        ]);

        $this->periodo = PeriodoAcademico::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'codigo' => '2026-I',
            'nombre' => 'Semestre 2026-I',
            'tipo_periodo' => 'SEMESTRAL',
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-07-31',
            'estado' => 'ACTIVO',
        ]);

        $rolAdmin = Rol::where('nombre', 'ADMINISTRADOR')->first();

        $this->admin = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'username' => 'admin.sam',
            'email' => 'admin@sam.edu.pe',
            'password' => Hash::make('Admin123!'),
            'nombres' => 'Admin',
            'apellido_paterno' => 'SAM',
            'estado' => 'ACTIVO',
        ]);
        $this->admin->roles()->sync([$rolAdmin->id]);
        $this->tokenAdmin = $this->admin->createToken('test')->plainTextToken;
    }

    public function test_crear_convocatoria_y_configurar_vacantes(): void
    {
        $resConv = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson('/api/v1/admin/admision/convocatorias', [
                'codigo' => 'CONV-2026-I',
                'nombre' => 'Admisión 2026-I Ordinario',
                'periodo_id' => $this->periodo->id,
                'fecha_inicio' => '2026-01-10',
                'fecha_fin' => '2026-03-10',
                'fecha_examen' => '2026-03-05',
                'puntaje_minimo_aprobatorio' => 11.0,
                'estado' => 'PUBLICADA',
            ]);

        $resConv->assertStatus(201);
        $convId = $resConv->json('data.id');

        $resVac = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson("/api/v1/admin/admision/convocatorias/{$convId}/vacantes", [
                'vacantes' => [
                    [
                        'programa_id' => $this->progDpw->id,
                        'turno' => 'DIURNO',
                        'modalidad' => 'ORDINARIO',
                        'vacantes_ofertadas' => 2,
                    ]
                ]
            ]);

        $resVac->assertStatus(200);
        $this->assertDatabaseHas('vacantes_admision', [
            'convocatoria_id' => $convId,
            'programa_id' => $this->progDpw->id,
            'vacantes_ofertadas' => 2,
        ]);
    }

    public function test_proceso_completo_postulacion_evaluacion_ranking_y_matricula(): void
    {
        $conv = ConvocatoriaAdmision::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'periodo_id' => $this->periodo->id,
            'codigo' => 'CONV-2026',
            'nombre' => 'Admision 2026',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-03-01',
            'fecha_examen' => '2026-02-28',
            'puntaje_minimo_aprobatorio' => 11.0,
            'estado' => 'PUBLICADA',
        ]);

        VacanteAdmision::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'convocatoria_id' => $conv->id,
            'programa_id' => $this->progDpw->id,
            'turno' => 'DIURNO',
            'modalidad' => 'ORDINARIO',
            'vacantes_ofertadas' => 2,
            'vacantes_cubiertas' => 0,
        ]);

        // Registrar 3 postulantes
        $p1 = Postulante::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'convocatoria_id' => $conv->id,
            'programa_id' => $this->progDpw->id,
            'turno' => 'DIURNO',
            'modalidad' => 'ORDINARIO',
            'codigo_postulante' => 'POST-001',
            'nombres' => 'Ana',
            'apellido_paterno' => 'Perez',
            'documento_tipo' => 'DNI',
            'documento_numero' => '71111111',
            'condicion' => 'INSCRITO',
        ]);

        $p2 = Postulante::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'convocatoria_id' => $conv->id,
            'programa_id' => $this->progDpw->id,
            'turno' => 'DIURNO',
            'modalidad' => 'ORDINARIO',
            'codigo_postulante' => 'POST-002',
            'nombres' => 'Bernardo',
            'apellido_paterno' => 'Quinto',
            'documento_tipo' => 'DNI',
            'documento_numero' => '72222222',
            'condicion' => 'INSCRITO',
        ]);

        $p3 = Postulante::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'convocatoria_id' => $conv->id,
            'programa_id' => $this->progDpw->id,
            'turno' => 'DIURNO',
            'modalidad' => 'ORDINARIO',
            'codigo_postulante' => 'POST-003',
            'nombres' => 'Carlos',
            'apellido_paterno' => 'Ramos',
            'documento_tipo' => 'DNI',
            'documento_numero' => '73333333',
            'condicion' => 'INSCRITO',
        ]);

        // Registrar calificaciones
        $resCal = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson("/api/v1/admin/admision/convocatorias/{$conv->id}/registrar-calificaciones", [
                'calificaciones' => [
                    ['postulante_id' => $p1->id, 'puntaje_examen' => 17.50],
                    ['postulante_id' => $p2->id, 'puntaje_examen' => 14.00],
                    ['postulante_id' => $p3->id, 'puntaje_examen' => 09.00],
                ]
            ]);

        $resCal->assertStatus(200);

        // Calcular orden de mérito
        $resMerito = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson("/api/v1/admin/admision/convocatorias/{$conv->id}/calcular-merito");

        $resMerito->assertStatus(200);

        // Validar condiciones: p1 y p2 alcanzaron vacante (ofertadas: 2), p3 desaprobó (<11.0)
        $this->assertEquals('ALCANZO_VACANTE', $p1->fresh()->condicion);
        $this->assertEquals(1, $p1->fresh()->orden_merito);

        $this->assertEquals('ALCANZO_VACANTE', $p2->fresh()->condicion);
        $this->assertEquals(2, $p2->fresh()->orden_merito);

        $this->assertEquals('NO_INGRESO', $p3->fresh()->condicion);

        // Matricular ingresantes
        $resMat = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson("/api/v1/admin/admision/convocatorias/{$conv->id}/matricular-ingresantes");

        $resMat->assertStatus(200);
        $this->assertEquals(2, $resMat->json('matriculados'));

        // Verificar que se crearon los estudiantes oficiales en el sistema
        $this->assertEquals('MATRICULADO', $p1->fresh()->condicion);
        $this->assertNotNull($p1->fresh()->estudiante_id);

        $this->assertDatabaseHas('estudiantes', [
            'id' => $p1->fresh()->estudiante_id,
            'unidad_academica_id' => $this->progDpw->id,
            'estado' => 'ACTIVO',
        ]);
    }
}
