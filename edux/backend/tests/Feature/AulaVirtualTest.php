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
use App\Models\Unidad;
use App\Models\Evaluacion;
use App\Models\Pregunta;
use App\Models\OpcionPregunta;
use Illuminate\Support\Facades\Hash;

class AulaVirtualTest extends TestCase
{
    use RefreshDatabase;

    private Institucion $institucion;
    private Usuario $userDocente1;
    private Docente $docente1;
    private Usuario $userDocente2;
    private Docente $docente2;
    private Usuario $userEstudiante;
    private Estudiante $estudiante;
    private GrupoCurso $grupo;
    private Unidad $unidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);

        $this->institucion = Institucion::create([
            'codigo'           => 'INST-AULA',
            'nombre'           => 'Institución Aula Test',
            'tipo_institucion' => 'INSTITUTO',
            'estado'           => 'ACTIVO',
        ]);

        $rolDocente    = Rol::where('nombre', 'DOCENTE')->first();
        $rolEstudiante = Rol::where('nombre', 'ESTUDIANTE')->first();

        // Docente 1 (Asignado al grupo)
        $this->userDocente1 = Usuario::withoutGlobalScopes()->create([
            'institucion_id'   => $this->institucion->id,
            'username'         => 'docente1',
            'email'            => 'doc1@modux.local',
            'password'         => Hash::make('password'),
            'nombres'          => 'Docente',
            'apellido_paterno' => 'Uno',
            'estado'           => 'ACTIVO',
        ]);
        $this->userDocente1->roles()->attach($rolDocente->id);
        $this->docente1 = Docente::withoutGlobalScopes()->create([
            'institucion_id' => $this->institucion->id,
            'usuario_id'     => $this->userDocente1->id,
            'codigo_docente' => 'DOC001',
            'estado'         => 'ACTIVO',
        ]);

        // Docente 2 (No asignado)
        $this->userDocente2 = Usuario::withoutGlobalScopes()->create([
            'institucion_id'   => $this->institucion->id,
            'username'         => 'docente2',
            'email'            => 'doc2@modux.local',
            'password'         => Hash::make('password'),
            'nombres'          => 'Docente',
            'apellido_paterno' => 'Dos',
            'estado'           => 'ACTIVO',
        ]);
        $this->userDocente2->roles()->attach($rolDocente->id);
        $this->docente2 = Docente::withoutGlobalScopes()->create([
            'institucion_id' => $this->institucion->id,
            'usuario_id'     => $this->userDocente2->id,
            'codigo_docente' => 'DOC002',
            'estado'         => 'ACTIVO',
        ]);

        // Estudiante
        $this->userEstudiante = Usuario::withoutGlobalScopes()->create([
            'institucion_id'   => $this->institucion->id,
            'username'         => 'estudiante1',
            'email'            => 'est1@modux.local',
            'password'         => Hash::make('password'),
            'nombres'          => 'Estudiante',
            'apellido_paterno' => 'Uno',
            'estado'           => 'ACTIVO',
        ]);
        $this->userEstudiante->roles()->attach($rolEstudiante->id);
        $this->estudiante = Estudiante::withoutGlobalScopes()->create([
            'institucion_id'    => $this->institucion->id,
            'usuario_id'        => $this->userEstudiante->id,
            'codigo_estudiante' => 'EST001',
            'estado'            => 'ACTIVO',
        ]);

        $periodo = PeriodoAcademico::withoutGlobalScopes()->create([
            'institucion_id' => $this->institucion->id,
            'codigo'         => '2026-I',
            'nombre'         => 'Periodo 2026-I',
            'tipo_periodo'   => 'SEMESTRAL',
            'fecha_inicio'   => '2026-03-01',
            'fecha_fin'      => '2026-07-31',
            'estado'         => 'ACTIVO',
        ]);

        $curso = Curso::withoutGlobalScopes()->create([
            'institucion_id' => $this->institucion->id,
            'codigo'         => 'CS101',
            'nombre'         => 'Algoritmos I',
            'creditos'       => 4,
            'horas_semanales'=> 5,
            'estado'         => 'ACTIVO',
        ]);

        $this->grupo = GrupoCurso::withoutGlobalScopes()->create([
            'institucion_id' => $this->institucion->id,
            'periodo_id'     => $periodo->id,
            'curso_id'       => $curso->id,
            'docente_id'     => $this->docente1->id,
            'codigo_grupo'   => 'G-CS101-A',
            'cupo'           => 30,
            'estado'         => 'ACTIVO',
        ]);

        $this->unidad = Unidad::withoutGlobalScopes()->create([
            'institucion_id'  => $this->institucion->id,
            'grupo_curso_id'  => $this->grupo->id,
            'numero'          => 1,
            'titulo'          => 'Unidad 1: Introducción',
            'estado'          => 'PUBLICADO',
        ]);

        // Matricular al estudiante en el grupo
        Matricula::withoutGlobalScopes()->create([
            'institucion_id'  => $this->institucion->id,
            'estudiante_id'   => $this->estudiante->id,
            'grupo_curso_id'  => $this->grupo->id,
            'estado'          => 'MATRICULADO',
            'fecha_matricula' => now()->toDateString(),
        ]);
    }

    public function test_docente_asignado_puede_crear_tarea_con_docente_id_automatico(): void
    {
        $token = $this->userDocente1->createToken('doc1')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/aula/grupos/{$this->grupo->id}/tareas", [
                'unidad_id'             => $this->unidad->id,
                'titulo'                => 'Tarea 1: Algoritmos Voraces',
                'puntos_maximos'        => 20,
                'fecha_limite'          => now()->addDays(7)->toDateTimeString(),
                'permite_entrega_tardia'=> false,
                'calificable'           => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.titulo', 'Tarea 1: Algoritmos Voraces')
            ->assertJsonPath('data.docente_id', $this->docente1->id);

        $this->assertDatabaseHas('tareas', [
            'grupo_curso_id' => $this->grupo->id,
            'docente_id'     => $this->docente1->id,
            'titulo'         => 'Tarea 1: Algoritmos Voraces',
        ]);
    }

    public function test_docente_no_asignado_recibe_403_al_crear_tarea(): void
    {
        $token = $this->userDocente2->createToken('doc2')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/aula/grupos/{$this->grupo->id}/tareas", [
                'unidad_id'      => $this->unidad->id,
                'titulo'         => 'Intento no autorizado',
                'fecha_limite'   => now()->addDays(7)->toDateTimeString(),
                'puntos_maximos' => 20,
            ]);

        $response->assertStatus(403);
    }

    public function test_docente_puede_registrar_asistencias_masivas(): void
    {
        $token = $this->userDocente1->createToken('doc1')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/aula/grupos/{$this->grupo->id}/asistencias", [
                'fecha'       => '2026-03-15',
                'asistencias' => [
                    [
                        'estudiante_id'     => $this->estudiante->id,
                        'estado_asistencia' => 'PRESENTE',
                        'observacion'       => 'Puntual',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Asistencias registradas exitosamente.');

        $this->assertDatabaseHas('asistencias', [
            'grupo_curso_id'    => $this->grupo->id,
            'estudiante_id'     => $this->estudiante->id,
            'estado_asistencia' => 'PRESENTE',
        ]);
    }

    public function test_estudiante_matriculado_puede_iniciar_intento_y_enviar_evaluacion(): void
    {
        // 1. Docente crea evaluacion
        $evaluacion = Evaluacion::create([
            'grupo_curso_id'      => $this->grupo->id,
            'unidad_id'           => $this->unidad->id,
            'docente_id'          => $this->docente1->id,
            'titulo'              => 'Quiz 1',
            'duracion_minutos'    => 30,
            'intentos_permitidos' => 1,
            'puntaje_maximo'      => 20,
            'fecha_inicio'        => now()->subMinute(),
            'fecha_fin'           => now()->addHour(),
            'estado'              => 'PUBLICADA',
        ]);

        $pregunta = Pregunta::create([
            'evaluacion_id' => $evaluacion->id,
            'enunciado'     => '¿Cuál es la complejidad de búsqueda binaria?',
            'tipo'          => 'ALTERNATIVA',
            'puntaje'       => 20,
            'orden'         => 1,
        ]);

        $opcionCorrecta = OpcionPregunta::create([
            'pregunta_id' => $pregunta->id,
            'texto'       => 'O(log n)',
            'es_correcta' => true,
            'orden'       => 1,
        ]);

        $opcionIncorrecta = OpcionPregunta::create([
            'pregunta_id' => $pregunta->id,
            'texto'       => 'O(n^2)',
            'es_correcta' => false,
            'orden'       => 2,
        ]);

        // 2. Estudiante inicia intento
        $tokenEst = $this->userEstudiante->createToken('est1')->plainTextToken;

        $resInicio = $this->withHeader('Authorization', 'Bearer ' . $tokenEst)
            ->postJson("/api/v1/aula/evaluaciones/{$evaluacion->id}/iniciar-intento");

        $resInicio->assertStatus(200)
            ->assertJsonPath('intento.numero_intento', 1)
            ->assertJsonPath('intento.estado', 'EN_CURSO');

        $intentoId = $resInicio->json('intento.id');

        // 3. Estudiante envía respuestas
        $resEnvio = $this->withHeader('Authorization', 'Bearer ' . $tokenEst)
            ->postJson("/api/v1/aula/evaluaciones/{$evaluacion->id}/intentos/{$intentoId}/enviar", [
                'respuestas' => [
                    [
                        'pregunta_id' => $pregunta->id,
                        'opcion_id'   => $opcionCorrecta->id,
                    ],
                ],
            ]);

        $resEnvio->assertStatus(200)
            ->assertJsonPath('intento_id', $intentoId)
            ->assertJsonPath('nota', 20);

        $this->assertDatabaseHas('intentos_evaluacion', [
            'id'     => $intentoId,
            'estado' => 'FINALIZADO',
            'nota'   => 20,
        ]);
    }

    public function test_docente_puede_crear_actividad_con_archivos_pdf_word_y_comentarios(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $tokenDoc = $this->userDocente1->createToken('doc1')->plainTextToken;
        $tokenEst = $this->userEstudiante->createToken('est1')->plainTextToken;

        $filePdf = \Illuminate\Http\UploadedFile::fake()->create('guia_practica_laboratorio.pdf', 300, 'application/pdf');

        // 1. Docente crea actividad con archivo PDF, instrucciones detalladas y enlace de apoyo
        $res = $this->withHeader('Authorization', 'Bearer ' . $tokenDoc)
            ->post("/api/v1/aula/grupos/{$this->grupo->id}/tareas", [
                'titulo'                    => 'Guía de Práctica 01 - Estructuras de Datos',
                'descripcion'               => 'Desarrollar los ejercicios prácticos de la guía adjunta.',
                'comentarios_instrucciones' => 'Pautas: Entregar en formato PDF o ZIP con el código fuente debidamente comentado.',
                'enlace_recurso'            => 'https://modux.edu.pe/recursos/guia1',
                'fecha_limite'              => now()->addDays(7)->toDateTimeString(),
                'puntaje_maximo'            => 20,
                'permite_entrega_tardia'    => true,
                'archivo'                   => $filePdf,
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.titulo', 'Guía de Práctica 01 - Estructuras de Datos')
            ->assertJsonPath('data.archivo_tipo', 'PDF')
            ->assertJsonPath('data.archivo_adjunto_nombre', 'guia_practica_laboratorio.pdf')
            ->assertJsonPath('data.tiene_archivo_adjunto', true);

        // Verificar regla de oro: nunca exponer la ruta raw de disco
        $this->assertArrayNotHasKey('archivo_adjunto_url', $res->json('data'));

        $tareaId = $res->json('data.id');

        // 2. Docente y estudiante pueden descargar el archivo adjunto
        $resDescargaDoc = $this->withHeader('Authorization', 'Bearer ' . $tokenDoc)
            ->get("/api/v1/aula/tareas/{$tareaId}/descargar-adjunto");
        $resDescargaDoc->assertStatus(200);

        $resDescargaEst = $this->withHeader('Authorization', 'Bearer ' . $tokenEst)
            ->get("/api/v1/aula/tareas/{$tareaId}/descargar-adjunto");
        $resDescargaEst->assertStatus(200);
    }
}

