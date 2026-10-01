<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Institucion;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\PeriodoAcademico;
use App\Models\Curso;
use App\Models\GrupoCurso;
use App\Models\Matricula;
use App\Models\Calificacion;
use Illuminate\Support\Facades\Hash;

class ReportesAcademicosTest extends TestCase
{
    use RefreshDatabase;

    private Institucion $institucion;
    private Usuario $userAdmin;
    private Usuario $userDocente;
    private Docente $docente;
    private Usuario $userEstudiante;
    private Estudiante $estudiante;
    private PeriodoAcademico $periodo;
    private Curso $curso;
    private GrupoCurso $grupo;
    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);

        $this->institucion = Institucion::create([
            'codigo'           => 'INST-REP',
            'nombre'           => 'Institución Reportes Demo',
            'tipo_institucion' => 'INSTITUTO',
            'estado'           => 'ACTIVO',
        ]);

        $rolAdmin      = Rol::firstOrCreate(['nombre' => 'ADMINISTRADOR'], ['descripcion' => 'Administrador', 'es_sistema' => true, 'estado' => true]);
        $rolDocente    = Rol::firstOrCreate(['nombre' => 'DOCENTE'], ['descripcion' => 'Docente', 'es_sistema' => true, 'estado' => true]);
        $rolEstudiante = Rol::firstOrCreate(['nombre' => 'ESTUDIANTE'], ['descripcion' => 'Estudiante', 'es_sistema' => true, 'estado' => true]);
        $rolApoderado  = Rol::firstOrCreate(['nombre' => 'APODERADO'], ['descripcion' => 'Apoderado', 'es_sistema' => false, 'estado' => true]);

        // Admin
        $this->userAdmin = Usuario::withoutGlobalScopes()->create([
            'institucion_id'   => $this->institucion->id,
            'username'         => 'adminrep',
            'email'            => 'admin@rep.test',
            'password'         => Hash::make('password'),
            'nombres'          => 'Admin',
            'apellido_paterno' => 'Rep',
            'estado'           => 'ACTIVO',
        ]);
        $this->userAdmin->roles()->attach($rolAdmin->id);

        // Docente
        $this->userDocente = Usuario::withoutGlobalScopes()->create([
            'institucion_id'   => $this->institucion->id,
            'username'         => 'docenterep',
            'email'            => 'doc@rep.test',
            'password'         => Hash::make('password'),
            'nombres'          => 'Doc',
            'apellido_paterno' => 'Rep',
            'estado'           => 'ACTIVO',
        ]);
        $this->userDocente->roles()->attach($rolDocente->id);
        $this->docente = Docente::withoutGlobalScopes()->create([
            'institucion_id' => $this->institucion->id,
            'usuario_id'     => $this->userDocente->id,
            'codigo_docente' => 'DOC-REP',
            'estado'         => 'ACTIVO',
        ]);

        // Estudiante
        $this->userEstudiante = Usuario::withoutGlobalScopes()->create([
            'institucion_id'   => $this->institucion->id,
            'username'         => 'estrep',
            'email'            => 'est@rep.test',
            'password'         => Hash::make('password'),
            'nombres'          => 'Juan',
            'apellido_paterno' => 'Perez',
            'apellido_materno' => 'Gomez',
            'estado'           => 'ACTIVO',
        ]);
        $this->userEstudiante->roles()->attach($rolEstudiante->id);
        $this->estudiante = Estudiante::withoutGlobalScopes()->create([
            'institucion_id'    => $this->institucion->id,
            'usuario_id'        => $this->userEstudiante->id,
            'codigo_estudiante' => 'EST-REP-01',
            'estado'            => 'ACTIVO',
        ]);

        $this->periodo = PeriodoAcademico::withoutGlobalScopes()->create([
            'institucion_id' => $this->institucion->id,
            'codigo'         => '2026-I',
            'nombre'         => 'Periodo Académico 2026-I',
            'tipo_periodo'   => 'SEMESTRAL',
            'fecha_inicio'   => '2026-03-01',
            'fecha_fin'      => '2026-07-31',
            'estado'         => 'ACTIVO',
        ]);

        $this->curso = Curso::withoutGlobalScopes()->create([
            'institucion_id' => $this->institucion->id,
            'codigo'         => 'MAT101',
            'nombre'         => 'Cálculo I',
            'creditos'       => 5,
            'estado'         => 'ACTIVO',
        ]);

        $this->grupo = GrupoCurso::withoutGlobalScopes()->create([
            'institucion_id' => $this->institucion->id,
            'periodo_id'     => $this->periodo->id,
            'curso_id'       => $this->curso->id,
            'docente_id'     => $this->docente->id,
            'codigo_grupo'   => 'G-MAT101-A',
            'cupo'           => 30,
            'estado'         => 'ACTIVO',
        ]);

        $this->matricula = Matricula::withoutGlobalScopes()->create([
            'institucion_id'  => $this->institucion->id,
            'estudiante_id'   => $this->estudiante->id,
            'grupo_curso_id'  => $this->grupo->id,
            'estado'          => 'MATRICULADO',
            'fecha_matricula' => now()->toDateString(),
        ]);

        // Registrar calificación para el estudiante
        Calificacion::create([
            'grupo_curso_id'  => $this->grupo->id,
            'estudiante_id'   => $this->estudiante->id,
            'registrado_por'  => $this->docente->id,
            'tipo_origen'     => 'MANUAL',
            'nombre_rubro'    => 'Examen Final',
            'peso_porcentual' => 100,
            'nota'            => 18.00,
        ]);
    }

    public function test_generar_boleta_de_notas_formal(): void
    {
        $token = $this->userAdmin->createToken('admin')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/v1/academico/reportes/boleta-notas/{$this->estudiante->id}/{$this->periodo->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.estudiante.id', $this->estudiante->id)
            ->assertJsonPath('data.periodo.codigo', '2026-I')
            ->assertJsonPath('data.resumen.promedio_ponderado_semestral', 18)
            ->assertJsonPath('data.cursos.0.codigo_curso', 'MAT101')
            ->assertJsonPath('data.cursos.0.promedio_final', 18);
    }

    public function test_boleta_se_descarga_como_pdf(): void
    {
        $token = $this->userAdmin->createToken('admin-pdf')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/api/v1/academico/reportes/boleta-notas/{$this->estudiante->id}/{$this->periodo->id}?formato=pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_cerrar_acta_bloquea_cambios_de_notas_y_registra_auditoria(): void
    {
        $token = $this->userDocente->createToken('doc-cierre')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/academico/grupos/{$this->grupo->id}/cerrar-acta")
            ->assertOk()
            ->assertJsonPath('data.estado', 'FINALIZADO');

        $calificacion = Calificacion::firstOrFail();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/v1/aula/grupos/{$this->grupo->id}/calificaciones/{$calificacion->id}", ['nota' => 10])
            ->assertStatus(422);

        $this->assertDatabaseHas('audits', [
            'accion' => 'ACTA_CERRADA',
            'entidad_id' => $this->grupo->id,
        ]);
    }

    public function test_generar_acta_consolidada_grupo(): void
    {
        $token = $this->userDocente->createToken('doc')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/v1/academico/reportes/acta-consolidada/{$this->grupo->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.curso.nombre', 'Cálculo I')
            ->assertJsonPath('data.alumnos.0.promedio_final', 18)
            ->assertJsonPath('data.alumnos.0.condicion', 'APROBADO');
    }
}
