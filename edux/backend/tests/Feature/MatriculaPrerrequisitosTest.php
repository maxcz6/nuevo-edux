<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Institucion;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\Estudiante;
use App\Models\Docente;
use App\Models\Curso;
use App\Models\CursoPrerrequisito;
use App\Models\GrupoCurso;
use App\Models\PeriodoAcademico;
use App\Models\Matricula;
use Illuminate\Support\Facades\Hash;

class MatriculaPrerrequisitosTest extends TestCase
{
    use RefreshDatabase;

    private Institucion $inst;
    private UnidadAcademica $progDpw;
    private Usuario $admin;
    private string $tokenAdmin;
    private PeriodoAcademico $periodo;
    private Estudiante $estudiante;
    private Curso $cursoBasico;
    private Curso $cursoAvanzado;
    private GrupoCurso $grupoBasico;
    private GrupoCurso $grupoAvanzado;

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
        $rolDocente = Rol::where('nombre', 'DOCENTE')->first();
        $rolEst = Rol::where('nombre', 'ESTUDIANTE')->first();

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

        // Docente
        $uDoc = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'username' => 'docente.web',
            'email' => 'docente@sam.edu.pe',
            'password' => Hash::make('Docente123!'),
            'nombres' => 'Docente',
            'apellido_paterno' => 'Web',
            'estado' => 'ACTIVO',
        ]);
        $uDoc->roles()->sync([$rolDocente->id]);
        $doc = Docente::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'usuario_id' => $uDoc->id,
            'especialidad' => 'Software',
            'estado' => 'ACTIVO',
        ]);

        // Estudiante
        $uEst = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'username' => 'estudiante.sam',
            'email' => 'estudiante@sam.edu.pe',
            'password' => Hash::make('Alumno123!'),
            'nombres' => 'Estudiante',
            'apellido_paterno' => 'SAM',
            'estado' => 'ACTIVO',
        ]);
        $uEst->roles()->sync([$rolEst->id]);
        $this->estudiante = Estudiante::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'usuario_id' => $uEst->id,
            'unidad_academica_id' => $this->progDpw->id,
            'codigo_estudiante' => 'EST-SAM-001',
            'estado' => 'ACTIVO',
        ]);

        // Curso 1 (Básico)
        $this->cursoBasico = Curso::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'unidad_academica_id' => $this->progDpw->id,
            'codigo' => 'CUR-DPW-101',
            'nombre' => 'Taller Web I',
            'creditos' => 4,
            'horas_semanales' => 5,
            'estado' => 'ACTIVO',
        ]);

        // Curso 2 (Avanzado, requiere Curso 1)
        $this->cursoAvanzado = Curso::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'unidad_academica_id' => $this->progDpw->id,
            'codigo' => 'CUR-DPW-201',
            'nombre' => 'Taller Web II Avanzado',
            'creditos' => 4,
            'horas_semanales' => 5,
            'estado' => 'ACTIVO',
        ]);

        CursoPrerrequisito::create([
            'curso_id' => $this->cursoAvanzado->id,
            'prerrequisito_curso_id' => $this->cursoBasico->id,
        ]);

        // Grupos
        $this->grupoBasico = GrupoCurso::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'curso_id' => $this->cursoBasico->id,
            'periodo_id' => $this->periodo->id,
            'docente_id' => $doc->id,
            'codigo_grupo' => 'A',
            'turno' => 'MANANA',
            'cupo' => 30,
            'estado' => 'ACTIVO',
        ]);

        $this->grupoAvanzado = GrupoCurso::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'curso_id' => $this->cursoAvanzado->id,
            'periodo_id' => $this->periodo->id,
            'docente_id' => $doc->id,
            'codigo_grupo' => 'A',
            'turno' => 'MANANA',
            'cupo' => 30,
            'estado' => 'ACTIVO',
        ]);
    }

    public function test_rechaza_matricula_si_no_cumple_prerrequisito(): void
    {
        // El estudiante intenta matricularse en Taller Web II sin haber aprobado Taller Web I
        $res = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson('/api/v1/academico/matriculas', [
                'grupo_curso_id' => $this->grupoAvanzado->id,
                'estudiante_id' => $this->estudiante->id,
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('prerrequisito obligatorio', $res->json('message'));
    }

    public function test_permite_matricula_si_aprobo_prerrequisito(): void
    {
        // Simulamos que el estudiante aprobó Taller Web I (estado FINALIZADO)
        Matricula::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'grupo_curso_id' => $this->grupoBasico->id,
            'estudiante_id' => $this->estudiante->id,
            'origen' => 'MANUAL',
            'fecha_matricula' => '2025-08-01',
            'estado' => 'FINALIZADO',
        ]);

        // Ahora debe permitir matricularse en Taller Web II
        $res = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson('/api/v1/academico/matriculas', [
                'grupo_curso_id' => $this->grupoAvanzado->id,
                'estudiante_id' => $this->estudiante->id,
            ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('matriculas', [
            'grupo_curso_id' => $this->grupoAvanzado->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => 'MATRICULADO',
        ]);
    }
}
