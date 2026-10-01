<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Institucion;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\Docente;
use App\Models\Estudiante;
use Illuminate\Support\Facades\Hash;

class ProgramaIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Institucion $inst;
    private UnidadAcademica $progEnf;
    private UnidadAcademica $progDpw;
    private Usuario $coordEnf;
    private Usuario $admin;
    private Usuario $superAdmin;
    private string $tokenCoordEnf;
    private string $tokenAdmin;
    private string $tokenSuperAdmin;

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

        $this->progEnf = UnidadAcademica::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'codigo' => 'PROG-ENF',
            'nombre' => 'Enfermeria Tecnica',
            'tipo' => 'PROGRAMA_ESTUDIOS',
            'estado' => 'ACTIVO',
        ]);

        $this->progDpw = UnidadAcademica::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'codigo' => 'PROG-DPW',
            'nombre' => 'Diseno y Programacion Web',
            'tipo' => 'PROGRAMA_ESTUDIOS',
            'estado' => 'ACTIVO',
        ]);

        $rolSuperAdmin = Rol::where('nombre', 'SUPERADMIN')->first();
        $rolAdmin = Rol::where('nombre', 'ADMINISTRADOR')->first();
        $rolCoord = Rol::where('nombre', 'COORDINADOR')->first();
        $rolDocente = Rol::where('nombre', 'DOCENTE')->first();
        $rolEstudiante = Rol::where('nombre', 'ESTUDIANTE')->first();

        // Superadmin
        $this->superAdmin = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'username' => 'superadmin',
            'email' => 'superadmin@modux.demo',
            'password' => Hash::make('SuperAdmin2026!'),
            'nombres' => 'Super',
            'apellido_paterno' => 'Admin',
            'estado' => 'ACTIVO',
        ]);
        $this->superAdmin->roles()->attach($rolSuperAdmin->id);
        $this->tokenSuperAdmin = $this->superAdmin->createToken('test-super')->plainTextToken;

        // Admin
        $this->admin = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'username' => 'admin',
            'email' => 'admin@modux.demo',
            'password' => Hash::make('Admin2026!'),
            'nombres' => 'Admin',
            'apellido_paterno' => 'SAM',
            'estado' => 'ACTIVO',
        ]);
        $this->admin->roles()->attach($rolAdmin->id);
        $this->tokenAdmin = $this->admin->createToken('test-admin')->plainTextToken;

        // Coordinador Enfermería
        $this->coordEnf = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'unidad_academica_id' => $this->progEnf->id,
            'username' => 'coord.enf',
            'email' => 'coord.enf@modux.demo',
            'password' => Hash::make('Coord2026!'),
            'nombres' => 'Elena',
            'apellido_paterno' => 'Vargas',
            'estado' => 'ACTIVO',
        ]);
        $this->coordEnf->roles()->attach($rolCoord->id);
        $this->tokenCoordEnf = $this->coordEnf->createToken('test-coord')->plainTextToken;

        // Docente Enfermería
        $uDocEnf = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'unidad_academica_id' => $this->progEnf->id,
            'username' => 'doc.enf',
            'email' => 'doc.enf@modux.demo',
            'password' => Hash::make('Docente2026!'),
            'nombres' => 'Maria',
            'apellido_paterno' => 'Enfermera',
            'estado' => 'ACTIVO',
        ]);
        $uDocEnf->roles()->attach($rolDocente->id);
        Docente::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'usuario_id' => $uDocEnf->id,
            'unidad_academica_id' => $this->progEnf->id,
            'especialidad' => 'Salud Comunitaria',
            'estado' => 'ACTIVO',
        ]);

        // Docente Web
        $uDocDpw = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'unidad_academica_id' => $this->progDpw->id,
            'username' => 'doc.dpw',
            'email' => 'doc.dpw@modux.demo',
            'password' => Hash::make('Docente2026!'),
            'nombres' => 'Carlos',
            'apellido_paterno' => 'Web',
            'estado' => 'ACTIVO',
        ]);
        $uDocDpw->roles()->attach($rolDocente->id);
        Docente::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'usuario_id' => $uDocDpw->id,
            'unidad_academica_id' => $this->progDpw->id,
            'especialidad' => 'Web',
            'estado' => 'ACTIVO',
        ]);

        // Estudiante Enfermería
        $uEstEnf = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'unidad_academica_id' => $this->progEnf->id,
            'username' => 'est.enf',
            'email' => 'est.enf@modux.demo',
            'password' => Hash::make('Alumno2026!'),
            'nombres' => 'Lucia',
            'apellido_paterno' => 'Enf',
            'estado' => 'ACTIVO',
        ]);
        $uEstEnf->roles()->attach($rolEstudiante->id);
        Estudiante::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'usuario_id' => $uEstEnf->id,
            'unidad_academica_id' => $this->progEnf->id,
            'codigo_estudiante' => 'EST-ENF-001',
            'estado' => 'ACTIVO',
        ]);

        // Estudiante Web
        $uEstDpw = Usuario::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'unidad_academica_id' => $this->progDpw->id,
            'username' => 'est.dpw',
            'email' => 'est.dpw@modux.demo',
            'password' => Hash::make('Alumno2026!'),
            'nombres' => 'David',
            'apellido_paterno' => 'Web',
            'estado' => 'ACTIVO',
        ]);
        $uEstDpw->roles()->attach($rolEstudiante->id);
        Estudiante::withoutGlobalScopes()->create([
            'institucion_id' => $this->inst->id,
            'usuario_id' => $uEstDpw->id,
            'unidad_academica_id' => $this->progDpw->id,
            'codigo_estudiante' => 'EST-DPW-001',
            'estado' => 'ACTIVO',
        ]);
    }

    public function test_coordinador_solo_ve_docentes_de_su_programa(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenCoordEnf)
            ->getJson('/api/v1/admin/docentes');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->progEnf->id, $data[0]['unidad_academica_id']);
    }

    public function test_coordinador_solo_ve_estudiantes_de_su_programa(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenCoordEnf)
            ->getJson('/api/v1/admin/estudiantes');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->progEnf->id, $data[0]['unidad_academica_id']);
        $this->assertEquals('EST-ENF-001', $data[0]['codigo_estudiante']);
    }

    public function test_admin_puede_filtrar_por_programa(): void
    {
        // Admin ve todos sin filtro
        $resAll = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->getJson('/api/v1/admin/docentes');
        $resAll->assertStatus(200);
        $this->assertCount(2, $resAll->json('data'));

        // Admin filtra por Enfermería
        $resEnf = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->getJson('/api/v1/admin/docentes?programa_id=' . $this->progEnf->id);
        $resEnf->assertStatus(200);
        $this->assertCount(1, $resEnf->json('data'));
        $this->assertEquals($this->progEnf->id, $resEnf->json('data.0.unidad_academica_id'));
    }

    public function test_superadmin_accede_con_exito(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenSuperAdmin)
            ->getJson('/api/v1/admin/unidades-academicas');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    public function test_admin_puede_editar_datos_de_un_programa_y_el_codigo_es_unico(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->putJson('/api/v1/admin/unidades-academicas/' . $this->progEnf->id, [
                'codigo' => 'PROG-ENF-EDIT',
                'nombre' => 'Enfermeria Tecnica Actualizada',
                'estado' => 'INACTIVO',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.codigo', 'PROG-ENF-EDIT')
            ->assertJsonPath('data.nombre', 'Enfermeria Tecnica Actualizada')
            ->assertJsonPath('data.estado', 'INACTIVO');

        $this->assertDatabaseHas('unidades_academicas', [
            'id' => $this->progEnf->id,
            'codigo' => 'PROG-ENF-EDIT',
            'nombre' => 'Enfermeria Tecnica Actualizada',
            'estado' => 'INACTIVO',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->putJson('/api/v1/admin/unidades-academicas/' . $this->progDpw->id, [
                'codigo' => 'PROG-ENF-EDIT',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('codigo');
    }

    public function test_coordinador_no_puede_editar_programas(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenCoordEnf)
            ->putJson('/api/v1/admin/unidades-academicas/' . $this->progEnf->id, [
                'nombre' => 'Cambio no autorizado',
            ])
            ->assertStatus(403);
    }

    public function test_coordinador_solo_ve_cursos_de_su_programa_y_falla_con_ajeno(): void
    {
        $cursoEnf = \App\Models\Curso::withoutGlobalScopes()->create([
            'institucion_id'      => $this->inst->id,
            'unidad_academica_id' => $this->progEnf->id,
            'codigo'              => 'CUR-ENF-TEST',
            'nombre'              => 'Curso Enfermeria',
            'estado'              => 'ACTIVO',
        ]);

        $cursoDpw = \App\Models\Curso::withoutGlobalScopes()->create([
            'institucion_id'      => $this->inst->id,
            'unidad_academica_id' => $this->progDpw->id,
            'codigo'              => 'CUR-DPW-TEST',
            'nombre'              => 'Curso DPW',
            'estado'              => 'ACTIVO',
        ]);

        // Coordinador Enfermeria lista cursos: solo ve su curso
        $resList = $this->withHeader('Authorization', 'Bearer ' . $this->tokenCoordEnf)
            ->getJson('/api/v1/academico/cursos');
        $resList->assertStatus(200);
        $codigos = collect($resList->json('data'))->pluck('codigo')->all();
        $this->assertContains('CUR-ENF-TEST', $codigos);
        $this->assertNotContains('CUR-DPW-TEST', $codigos);

        // Coordinador intenta ver curso de DPW: debe dar 404 (oculto por scope)
        $resShow = $this->withHeader('Authorization', 'Bearer ' . $this->tokenCoordEnf)
            ->getJson('/api/v1/academico/cursos/' . $cursoDpw->id);
        $resShow->assertStatus(404);
    }
}
