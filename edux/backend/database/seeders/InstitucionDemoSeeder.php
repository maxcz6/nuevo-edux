<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Institucion;
use App\Models\Sede;
use App\Models\NivelEducativo;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\PeriodoAcademico;
use App\Models\UnidadAcademica;
use App\Models\Curso;
use App\Models\CursoPrerrequisito;
use App\Models\GrupoCurso;
use App\Models\Matricula;
use App\Models\Unidad;
use App\Models\Tarea;
use App\Models\EstudiantePrograma;
use App\Models\PlanEstudio;
use App\Models\ConvocatoriaAdmision;
use App\Models\VacanteAdmision;
use App\Models\Postulante;
use App\Models\Ambiente;
use App\Models\HorarioGrupo;
use App\Models\TramiteEstudiante;
use App\Models\CertificadoModular;
use App\Models\Titulacion;

class InstitucionDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Institución IESTP "Santiago Antúnez de Mayolo" ─────────────
        $institucion = Institucion::updateOrCreate(
            ['codigo' => 'IESTP-SAM'],
            [
                'nombre'           => 'IESTP "Santiago Antúnez de Mayolo"',
                'nombre_corto'     => 'IESTP SAM',
                'tipo_institucion' => 'INSTITUTO',
                'ruc'              => '20185012345',
                'direccion'        => 'Av. La Victoria N.º 300–302, Anexo Palián, Huancayo, Junín',
                'telefono'         => '064-231632',
                'correo'           => 'contacto@sam.edu.pe',
                'zona_horaria'     => 'America/Lima',
                'estado'           => 'ACTIVO',
            ]
        );

        // Sede principal
        $sede = Sede::updateOrCreate(
            ['institucion_id' => $institucion->id, 'nombre' => 'Sede Central Palián'],
            [
                'direccion' => 'Av. La Victoria N.º 300–302, Anexo Palián, Huancayo',
                'estado'    => 'ACTIVO',
            ]
        );

        // Nivel educativo
        $nivel = NivelEducativo::updateOrCreate(
            ['institucion_id' => $institucion->id, 'codigo' => 'NIV-TEC'],
            [
                'nombre' => 'Educación Superior Tecnológica',
                'orden'  => 1,
                'estado' => 'ACTIVO',
            ]
        );

        // ── 2. Roles del Sistema ──────────────────────────────────────────
        $rolSuperAdmin = Rol::where('nombre', 'SUPERADMIN')->first();
        $rolAdmin      = Rol::where('nombre', 'ADMINISTRADOR')->first();
        $rolCoord      = Rol::where('nombre', 'COORDINADOR')->first();
        $rolDocente    = Rol::where('nombre', 'DOCENTE')->first();
        $rolEstudiante = Rol::where('nombre', 'ESTUDIANTE')->first();

        // ── 3. Los 8 Programas Oficiales de Estudio del IESTP SAM ─────────
        $programasData = [
            ['codigo' => 'PROG-ENF',  'nombre' => 'Enfermería Técnica', 'tipo' => 'PROGRAMA_ESTUDIOS'],
            ['codigo' => 'PROG-CONT', 'nombre' => 'Contabilidad', 'tipo' => 'PROGRAMA_ESTUDIOS'],
            ['codigo' => 'PROG-DPW',  'nombre' => 'Diseño y Programación Web', 'tipo' => 'PROGRAMA_ESTUDIOS'],
            ['codigo' => 'PROG-ASIS', 'nombre' => 'Asistencia Administrativa', 'tipo' => 'PROGRAMA_ESTUDIOS'],
            ['codigo' => 'PROG-AGRO', 'nombre' => 'Producción Agropecuaria', 'tipo' => 'PROGRAMA_ESTUDIOS'],
            ['codigo' => 'PROG-ALIM', 'nombre' => 'Industrias Alimentarias', 'tipo' => 'PROGRAMA_ESTUDIOS'],
            ['codigo' => 'PROG-FARM', 'nombre' => 'Farmacia Técnica', 'tipo' => 'PROGRAMA_ESTUDIOS'],
            ['codigo' => 'PROG-MEC',  'nombre' => 'Mecánica de Producción Industrial', 'tipo' => 'PROGRAMA_ESTUDIOS'],
        ];

        $programas = [];
        $planesEstudio = [];
        foreach ($programasData as $pData) {
            $prog = UnidadAcademica::withoutGlobalScopes()->updateOrCreate(
                ['institucion_id' => $institucion->id, 'codigo' => $pData['codigo']],
                [
                    'nombre'             => $pData['nombre'],
                    'tipo'               => $pData['tipo'],
                    'nivel_educativo_id' => $nivel->id,
                    'estado'             => 'ACTIVO',
                ]
            );
            $programas[$pData['codigo']] = $prog;

            $plan = PlanEstudio::withoutGlobalScopes()->firstOrCreate(
                ['institucion_id' => $institucion->id, 'codigo' => 'PLAN-' . $pData['codigo']],
                [
                    'programa_id'    => $prog->id,
                    'nombre'         => 'Plan Modular 2026 - ' . $pData['nombre'],
                    'anio_inicio'    => 2026,
                    'total_ciclos'   => 6,
                    'total_creditos' => 120,
                    'estado'         => 'ACTIVO',
                ]
            );
            $planesEstudio[$pData['codigo']] = $plan;
        }

        // ── 4. Periodo Académico 2026-I ──────────────────────────────────
        $periodo = PeriodoAcademico::withoutGlobalScopes()->updateOrCreate(
            ['institucion_id' => $institucion->id, 'codigo' => '2026-I'],
            [
                'nombre'       => 'Semestre Académico 2026-I',
                'tipo_periodo' => 'SEMESTRAL',
                'fecha_inicio' => '2026-03-01',
                'fecha_fin'    => '2026-07-31',
                'estado'       => 'ACTIVO',
            ]
        );

        // ── 5. Superadministrador ─────────────────────────────────────────
        $superAdmin = Usuario::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'superadmin@modux.demo'],
            [
                'institucion_id'   => $institucion->id,
                'username'         => 'superadmin',
                'password'         => Hash::make(env('SUPERADMIN_INITIAL_PASSWORD', 'SuperAdmin2026!')),
                'nombres'          => 'Super',
                'apellido_paterno' => 'Administrador',
                'apellido_materno' => 'Global',
                'estado'           => 'ACTIVO',
            ]
        );
        if ($rolSuperAdmin) {
            $superAdmin->roles()->sync([$rolSuperAdmin->id]);
        }

        // ── 6. Administrador General del Instituto ───────────────────────
        $admin = Usuario::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'admin@modux.demo'],
            [
                'institucion_id'   => $institucion->id,
                'username'         => 'admin',
                'password'         => Hash::make(env('ADMIN_INITIAL_PASSWORD', 'Admin2026!')),
                'nombres'          => 'Director General',
                'apellido_paterno' => 'IESTP SAM',
                'apellido_materno' => 'Palián',
                'estado'           => 'ACTIVO',
            ]
        );
        if ($rolAdmin) {
            $admin->roles()->sync([$rolAdmin->id]);
        }


        // ── 8. Coordinadores por Programa ─────────────────────────────────
        $coordEnf = Usuario::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'coord.enf@modux.demo'],
            [
                'institucion_id'      => $institucion->id,
                'unidad_academica_id' => $programas['PROG-ENF']->id,
                'username'            => 'coord.enf',
                'password'            => Hash::make(env('COORD_INITIAL_PASSWORD', 'Coord2026!')),
                'nombres'             => 'Elena',
                'apellido_paterno'    => 'Vargas',
                'apellido_materno'    => 'Coordinadora',
                'estado'              => 'ACTIVO',
            ]
        );
        if ($rolCoord) {
            $coordEnf->roles()->sync([$rolCoord->id]);
        }

        $coordDpw = Usuario::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'coord.dpw@modux.demo'],
            [
                'institucion_id'      => $institucion->id,
                'unidad_academica_id' => $programas['PROG-DPW']->id,
                'username'            => 'coord.dpw',
                'password'            => Hash::make(env('COORD_INITIAL_PASSWORD', 'Coord2026!')),
                'nombres'             => 'Roberto',
                'apellido_paterno'    => 'Mendoza',
                'apellido_materno'    => 'Coordinador',
                'estado'              => 'ACTIVO',
            ]
        );
        if ($rolCoord) {
            $coordDpw->roles()->sync([$rolCoord->id]);
        }

        // ── 8. Docentes Vinculados por Programa ───────────────────────────
        $docentesData = [
            ['username' => 'docente01',     'email' => 'docente01@modux.demo',     'nombres' => 'Maria',  'apellido_pat' => 'Garcia',   'apellido_mat' => 'Salas',     'especialidad' => 'Enfermería y Salud Comunitaria',          'prog_code' => 'PROG-ENF'],
            ['username' => 'docente.dpw',   'email' => 'docente.dpw@modux.demo',   'nombres' => 'Carlos', 'apellido_pat' => 'Mendoza',  'apellido_mat' => 'Quispe',    'especialidad' => 'Desarrollo Web Fullstack & Cloud',        'prog_code' => 'PROG-DPW'],
            ['username' => 'docente.cont',  'email' => 'docente.cont@modux.demo',  'nombres' => 'Ana',    'apellido_pat' => 'Palacios', 'apellido_mat' => 'Huaman',    'especialidad' => 'Contabilidad Financiera y Tributaria',    'prog_code' => 'PROG-CONT'],
            ['username' => 'docente.agro',  'email' => 'docente.agro@modux.demo',  'nombres' => 'Jorge',  'apellido_pat' => 'Rojas',    'apellido_mat' => 'Valdez',    'especialidad' => 'Producción Agropecuaria y Riego',         'prog_code' => 'PROG-AGRO'],
            ['username' => 'docente.alim',  'email' => 'docente.alim@modux.demo',  'nombres' => 'Lucia',  'apellido_pat' => 'Meza',     'apellido_mat' => 'Campos',    'especialidad' => 'Industrias Alimentarias y Calidad',       'prog_code' => 'PROG-ALIM'],
            ['username' => 'docente.asis',  'email' => 'docente.asis@modux.demo',  'nombres' => 'Diana',  'apellido_pat' => 'Flores',   'apellido_mat' => 'Rios',      'especialidad' => 'Gestión Documental y Asistencia',         'prog_code' => 'PROG-ASIS'],
            ['username' => 'docente.farm',  'email' => 'docente.farm@modux.demo',  'nombres' => 'Ruth',   'apellido_pat' => 'Chavez',   'apellido_mat' => 'Medina',    'especialidad' => 'Farmacología y Técnica Farmacéutica',     'prog_code' => 'PROG-FARM'],
            ['username' => 'docente.mec',   'email' => 'docente.mec@modux.demo',   'nombres' => 'Victor', 'apellido_pat' => 'Sedano',   'apellido_mat' => 'Alvarado',  'especialidad' => 'Mecanizado Industrial CNC y Matricería',  'prog_code' => 'PROG-MEC'],
        ];

        $docentesEntities = [];
        foreach ($docentesData as $d) {
            $prog = $programas[$d['prog_code']];
            $uDoc = Usuario::withoutGlobalScopes()->updateOrCreate(
                ['email' => $d['email']],
                [
                    'institucion_id'      => $institucion->id,
                    'unidad_academica_id' => $prog->id,
                    'username'            => $d['username'],
                    'password'            => Hash::make(env('DOCENTE_INITIAL_PASSWORD', 'Docente2026!')),
                    'nombres'             => $d['nombres'],
                    'apellido_paterno'    => $d['apellido_pat'],
                    'apellido_materno'    => $d['apellido_mat'],
                    'estado'              => 'ACTIVO',
                ]
            );
            if ($rolDocente) {
                $uDoc->roles()->sync([$rolDocente->id]);
            }

            $docEntity = Docente::withoutGlobalScopes()->updateOrCreate(
                ['usuario_id' => $uDoc->id],
                [
                    'institucion_id'      => $institucion->id,
                    'unidad_academica_id' => $prog->id,
                    'especialidad'        => $d['especialidad'],
                    'estado'              => 'ACTIVO',
                ]
            );
            $docentesEntities[$d['username']] = $docEntity;
        }

        // ── 9. Estudiantes Vinculados por Programa ─────────────────────────
        $estudiantesData = [
            ['username' => 'est001', 'email' => 'est001@modux.demo', 'nom' => 'Lucia',   'pat' => 'Huaman',  'mat' => 'Soto',     'code' => 'EST-ENF-001',  'prog' => 'PROG-ENF'],
            ['username' => 'est002', 'email' => 'est002@modux.demo', 'nom' => 'Pedro',   'pat' => 'Soto',    'mat' => 'Vega',     'code' => 'EST-ENF-002',  'prog' => 'PROG-ENF'],
            ['username' => 'est003', 'email' => 'est003@modux.demo', 'nom' => 'David',   'pat' => 'Quispe',  'mat' => 'Alarcon',  'code' => 'EST-DPW-003',  'prog' => 'PROG-DPW'],
            ['username' => 'est004', 'email' => 'est004@modux.demo', 'nom' => 'Sofia',   'pat' => 'Rios',    'mat' => 'Paredes',  'code' => 'EST-DPW-004',  'prog' => 'PROG-DPW'],
            ['username' => 'est005', 'email' => 'est005@modux.demo', 'nom' => 'Juan',    'pat' => 'Poma',    'mat' => 'Diaz',     'code' => 'EST-CONT-005', 'prog' => 'PROG-CONT'],
            ['username' => 'est006', 'email' => 'est006@modux.demo', 'nom' => 'Rosa',    'pat' => 'Castro',  'mat' => 'Ramos',    'code' => 'EST-AGRO-006', 'prog' => 'PROG-AGRO'],
            ['username' => 'est007', 'email' => 'est007@modux.demo', 'nom' => 'Marcos',  'pat' => 'Meza',    'mat' => 'Chavez',   'code' => 'EST-ALIM-007', 'prog' => 'PROG-ALIM'],
            ['username' => 'est008', 'email' => 'est008@modux.demo', 'nom' => 'Elena',   'pat' => 'Torres',  'mat' => 'Gomez',    'code' => 'EST-ASIS-008', 'prog' => 'PROG-ASIS'],
            ['username' => 'est009', 'email' => 'est009@modux.demo', 'nom' => 'Gabriela','pat' => 'Astudillo','mat' => 'Perez',  'code' => 'EST-FARM-009', 'prog' => 'PROG-FARM'],
            ['username' => 'est010', 'email' => 'est010@modux.demo', 'nom' => 'Walter',  'pat' => 'Carbajal', 'mat' => 'Leon',    'code' => 'EST-MEC-010',  'prog' => 'PROG-MEC'],
        ];

        $estudiantesEntities = [];
        foreach ($estudiantesData as $e) {
            $prog = $programas[$e['prog']];
            $uEst = Usuario::withoutGlobalScopes()->updateOrCreate(
                ['email' => $e['email']],
                [
                    'institucion_id'      => $institucion->id,
                    'unidad_academica_id' => $prog->id,
                    'username'            => $e['username'],
                    'password'            => Hash::make(env('ALUMNO_INITIAL_PASSWORD', 'Alumno2026!')),
                    'nombres'             => $e['nom'],
                    'apellido_paterno'    => $e['pat'],
                    'apellido_materno'    => $e['mat'],
                    'documento_tipo'      => 'DNI',
                    'documento_numero'    => '7' . rand(1000000, 9999999),
                    'estado'              => 'ACTIVO',
                ]
            );
            if ($rolEstudiante) {
                $uEst->roles()->sync([$rolEstudiante->id]);
            }

            $estEntity = Estudiante::withoutGlobalScopes()->updateOrCreate(
                ['usuario_id' => $uEst->id],
                [
                    'institucion_id'      => $institucion->id,
                    'unidad_academica_id' => $prog->id,
                    'codigo_estudiante'   => $e['code'],
                    'estado'              => 'ACTIVO',
                ]
            );
            $estudiantesEntities[$e['username']] = $estEntity;

            EstudiantePrograma::withoutGlobalScopes()->updateOrCreate(
                [
                    'estudiante_id' => $estEntity->id,
                    'programa_id'   => $prog->id,
                ],
                [
                    'plan_estudio_id' => $planesEstudio[$e['prog']]->id,
                    'fecha_ingreso'   => '2026-03-01',
                    'estado'          => 'ACTIVO',
                ]
            );
        }


        // ── 11. Ambientes de Formación Práctica ───────────────────────────
        $ambientesData = [
            ['codigo' => 'LAB-WEB-01',  'nombre' => 'Laboratorio de Desarrollo Web y Software', 'tipo' => 'LABORATORIO', 'pabellon' => 'Pabellón C', 'capacidad' => 35],
            ['codigo' => 'LAB-FARM-01', 'nombre' => 'Laboratorio de Farmacotecnia y Bioquímica', 'tipo' => 'LABORATORIO', 'pabellon' => 'Pabellón B', 'capacidad' => 30],
            ['codigo' => 'TAL-MEC-01',  'nombre' => 'Taller de Mecanizado CNC y Matricería',    'tipo' => 'TALLER',      'pabellon' => 'Pabellón D', 'capacidad' => 25],
            ['codigo' => 'CAM-AGRO-01', 'nombre' => 'Campo Experimental y Parcelas Demostrativas','tipo' => 'CAMPO',     'pabellon' => 'Sector Palián','capacidad' => 40],
            ['codigo' => 'AUL-MAGNA',   'nombre' => 'Auditorio Central / Aula Magna SAM',        'tipo' => 'AULA',       'pabellon' => 'Pabellón Central','capacidad' => 120],
        ];

        $ambientesEntities = [];
        foreach ($ambientesData as $amb) {
            $ambientesEntities[$amb['codigo']] = Ambiente::withoutGlobalScopes()->updateOrCreate(
                ['institucion_id' => $institucion->id, 'codigo' => $amb['codigo']],
                $amb
            );
        }

        // ── 12. Cursos, Prerrequisitos y Grupos ───────────────────────────
        $cursosConfig = [
            // Enfermería
            ['prog_code' => 'PROG-ENF',  'codigo' => 'CUR-ENF-101', 'nombre' => 'Anatomía y Fisiología Humana',         'creditos' => 4, 'docente_user' => 'docente01',   'students' => ['est001', 'est002'], 'ambiente' => 'AUL-MAGNA'],
            ['prog_code' => 'PROG-ENF',  'codigo' => 'CUR-ENF-102', 'nombre' => 'Primeros Auxilios y Salud Comunitaria', 'creditos' => 4, 'docente_user' => 'docente01',   'students' => ['est001', 'est002'], 'ambiente' => 'AUL-MAGNA'],
            // DPW (Con Prerrequisito)
            ['prog_code' => 'PROG-DPW',  'codigo' => 'CUR-DPW-101', 'nombre' => 'Desarrollo Web Frontend con Vue 3',    'creditos' => 4, 'docente_user' => 'docente.dpw', 'students' => ['est003', 'est004'], 'ambiente' => 'LAB-WEB-01'],
            ['prog_code' => 'PROG-DPW',  'codigo' => 'CUR-DPW-201', 'nombre' => 'Arquitectura Cloud y APIs REST',      'creditos' => 5, 'docente_user' => 'docente.dpw', 'students' => ['est003'],           'ambiente' => 'LAB-WEB-01', 'prereq' => 'CUR-DPW-101'],
            // Contabilidad
            ['prog_code' => 'PROG-CONT', 'codigo' => 'CUR-CONT-101', 'nombre' => 'Contabilidad Financiera y Tributaria', 'creditos' => 4, 'docente_user' => 'docente.cont', 'students' => ['est005'],           'ambiente' => 'AUL-MAGNA'],
            // Agropecuaria
            ['prog_code' => 'PROG-AGRO', 'codigo' => 'CUR-AGRO-101', 'nombre' => 'Tecnología de Cultivos Andinos y Riego','creditos' => 4, 'docente_user' => 'docente.agro', 'students' => ['est006'],           'ambiente' => 'CAM-AGRO-01'],
            // Alimentarias
            ['prog_code' => 'PROG-ALIM', 'codigo' => 'CUR-ALIM-101', 'nombre' => 'Microbiología y Conservación de Alim.', 'creditos' => 4, 'docente_user' => 'docente.alim', 'students' => ['est007'],           'ambiente' => 'LAB-FARM-01'],
            // Asistencia
            ['prog_code' => 'PROG-ASIS', 'codigo' => 'CUR-ASIS-101', 'nombre' => 'Redacción Técnica y Gestión Digital',   'creditos' => 4, 'docente_user' => 'docente.asis', 'students' => ['est008'],           'ambiente' => 'AUL-MAGNA'],
            // Farmacia (Con Prerrequisito)
            ['prog_code' => 'PROG-FARM', 'codigo' => 'CUR-FARM-101', 'nombre' => 'Farmacología General y Toxicología',    'creditos' => 4, 'docente_user' => 'docente.farm', 'students' => ['est009'],           'ambiente' => 'LAB-FARM-01'],
            ['prog_code' => 'PROG-FARM', 'codigo' => 'CUR-FARM-201', 'nombre' => 'Tecnología Farmacéutica y Dispensación', 'creditos' => 5, 'docente_user' => 'docente.farm', 'students' => [],                  'ambiente' => 'LAB-FARM-01', 'prereq' => 'CUR-FARM-101'],
            // Mecánica Industrial
            ['prog_code' => 'PROG-MEC',  'codigo' => 'CUR-MEC-101',  'nombre' => 'Mecanizado por Arranque de Viruta CNC',  'creditos' => 5, 'docente_user' => 'docente.mec',  'students' => ['est010'],           'ambiente' => 'TAL-MEC-01'],
        ];

        $cursosCreated = [];
        foreach ($cursosConfig as $cCfg) {
            $prog = $programas[$cCfg['prog_code']];
            $curso = Curso::withoutGlobalScopes()->updateOrCreate(
                ['institucion_id' => $institucion->id, 'codigo' => $cCfg['codigo']],
                [
                    'unidad_academica_id' => $prog->id,
                    'nombre'              => $cCfg['nombre'],
                    'creditos'            => $cCfg['creditos'],
                    'horas_semanales'     => 5,
                    'estado'              => 'ACTIVO',
                ]
            );
            $cursosCreated[$cCfg['codigo']] = $curso;

            // Prerrequisito si corresponde
            if (isset($cCfg['prereq']) && isset($cursosCreated[$cCfg['prereq']])) {
                CursoPrerrequisito::firstOrCreate([
                    'curso_id' => $curso->id,
                    'prerrequisito_curso_id' => $cursosCreated[$cCfg['prereq']]->id,
                ]);
            }

            $docente = $docentesEntities[$cCfg['docente_user']];
            $grupo = GrupoCurso::withoutGlobalScopes()->updateOrCreate(
                [
                    'institucion_id' => $institucion->id,
                    'curso_id'       => $curso->id,
                    'periodo_id'     => $periodo->id,
                    'codigo_grupo'   => 'A',
                ],
                [
                    'docente_id' => $docente->id,
                    'turno'      => 'MANANA',
                    'cupo'       => 35,
                    'estado'     => 'ACTIVO',
                ]
            );

            // Programación horaria con ambiente
            if (isset($ambientesEntities[$cCfg['ambiente']])) {
                HorarioGrupo::firstOrCreate([
                    'institucion_id' => $institucion->id,
                    'grupo_curso_id' => $grupo->id,
                    'ambiente_id'    => $ambientesEntities[$cCfg['ambiente']]->id,
                    'dia_semana'     => 1, // Lunes
                    'hora_inicio'    => '08:00',
                    'hora_fin'       => '11:00',
                ]);
            }

            // Matricular a los estudiantes
            foreach ($cCfg['students'] as $stUser) {
                if (isset($estudiantesEntities[$stUser])) {
                    Matricula::withoutGlobalScopes()->updateOrCreate(
                        [
                            'institucion_id' => $institucion->id,
                            'grupo_curso_id' => $grupo->id,
                            'estudiante_id'  => $estudiantesEntities[$stUser]->id,
                        ],
                        [
                            'origen'          => 'MANUAL',
                            'fecha_matricula' => now()->toDateString(),
                            'estado'          => 'MATRICULADO',
                        ]
                    );
                }
            }

            // Crear Unidad y Tarea para el aula
            $unidad = Unidad::withoutGlobalScopes()->firstOrCreate(
                [
                    'grupo_curso_id' => $grupo->id,
                    'orden'          => 1,
                ],
                [
                    'titulo'      => 'Unidad 1: Fundamentos y Procedimientos Prácticos',
                    'descripcion' => 'Principios teórico-prácticos aplicados al programa ' . $prog->nombre,
                    'estado'      => 'ACTIVO',
                ]
            );

            Tarea::withoutGlobalScopes()->firstOrCreate(
                [
                    'grupo_curso_id' => $grupo->id,
                    'unidad_id'      => $unidad->id,
                    'titulo'         => 'Tarea Práctica 1: Aplicación Formativa Regional',
                ],
                [
                    'docente_id'             => $docente->id,
                    'descripcion'            => 'Presentar el informe de prácticas de taller según norma técnica MINEDU.',
                    'puntaje_maximo'         => 20,
                    'fecha_inicio'           => now(),
                    'fecha_limite'           => now()->addDays(30),
                    'permite_entrega_tardia' => true,
                    'estado'                 => 'PUBLICADA',
                ]
            );
        }

        // ── 13. Convocatoria de Admisión 2026-I ────────────────────────────
        $convocatoria = ConvocatoriaAdmision::withoutGlobalScopes()->updateOrCreate(
            ['institucion_id' => $institucion->id, 'codigo' => 'ADM-2026-1'],
            [
                'periodo_id'                 => $periodo->id,
                'nombre'                     => 'Proceso de Admisión Ordinario y Exonerado 2026-I',
                'fecha_inicio'               => '2026-01-15',
                'fecha_fin'                  => '2026-03-10',
                'fecha_examen'               => '2026-03-08',
                'puntaje_minimo_aprobatorio' => 11.00,
                'estado'                     => 'FINALIZADA',
            ]
        );

        // Vacantes para los 8 programas
        foreach ($programas as $pCode => $prog) {
            VacanteAdmision::withoutGlobalScopes()->updateOrCreate(
                [
                    'institucion_id'  => $institucion->id,
                    'convocatoria_id' => $convocatoria->id,
                    'programa_id'     => $prog->id,
                    'turno'           => 'DIURNO',
                    'modalidad'       => 'ORDINARIO',
                ],
                [
                    'vacantes_ofertadas' => 40,
                    'vacantes_cubiertas' => 38,
                ]
            );
        }

        // Postulantes de muestra con orden de mérito
        $postulantesSample = [
            ['nom' => 'Alvaro',   'pat' => 'Morales',  'mat' => 'Aliaga',  'dni' => '71234501', 'prog' => 'PROG-DPW',  'nota' => 18.50, 'orden' => 1, 'cond' => 'MATRICULADO'],
            ['nom' => 'Katia',    'pat' => 'Fernandez','mat' => 'Zevallos','dni' => '71234502', 'prog' => 'PROG-DPW',  'nota' => 16.75, 'orden' => 2, 'cond' => 'ALCANZO_VACANTE'],
            ['nom' => 'Rodrigo',  'pat' => 'Paucar',   'mat' => 'Ayllon',  'dni' => '71234503', 'prog' => 'PROG-ENF',  'nota' => 17.20, 'orden' => 1, 'cond' => 'ALCANZO_VACANTE'],
            ['nom' => 'Milagros', 'pat' => 'Ccanto',   'mat' => 'Meza',    'dni' => '71234504', 'prog' => 'PROG-FARM', 'nota' => 15.40, 'orden' => 1, 'cond' => 'ALCANZO_VACANTE'],
            ['nom' => 'Cesar',    'pat' => 'Huaman',   'mat' => 'Rios',    'dni' => '71234505', 'prog' => 'PROG-MEC',  'nota' => 14.80, 'orden' => 1, 'cond' => 'ALCANZO_VACANTE'],
            ['nom' => 'Javier',   'pat' => 'Cardenas', 'mat' => 'Lopez',   'dni' => '71234506', 'prog' => 'PROG-DPW',  'nota' => 08.50, 'orden' => 41,'cond' => 'NO_INGRESO'],
        ];

        foreach ($postulantesSample as $p) {
            Postulante::withoutGlobalScopes()->updateOrCreate(
                [
                    'convocatoria_id'  => $convocatoria->id,
                    'documento_numero' => $p['dni'],
                ],
                [
                    'institucion_id'   => $institucion->id,
                    'programa_id'      => $programas[$p['prog']]->id,
                    'turno'            => 'DIURNO',
                    'modalidad'        => 'ORDINARIO',
                    'codigo_postulante'=> 'POST-2026-1-' . $p['dni'],
                    'nombres'          => $p['nom'],
                    'apellido_paterno' => $p['pat'],
                    'apellido_materno' => $p['mat'],
                    'documento_tipo'   => 'DNI',
                    'email'            => strtolower($p['nom']) . '.' . strtolower($p['pat']) . '@gmail.com',
                    'puntaje_examen'   => $p['nota'],
                    'orden_merito'     => $p['orden'],
                    'condicion'        => $p['cond'],
                ]
            );
        }

        // ── 14. Trámites de Estudiantes de Ejemplo ─────────────────────────
        if (isset($estudiantesEntities['est001'])) {
            TramiteEstudiante::withoutGlobalScopes()->firstOrCreate(
                [
                    'institucion_id' => $institucion->id,
                    'estudiante_id'  => $estudiantesEntities['est001']->id,
                    'tipo_tramite'   => 'RESERVA_MATRICULA',
                ],
                [
                    'resolucion_directoral' => 'RD N° 0142-2026-IESTP-SAM',
                    'fecha_solicitud'       => '2026-02-10',
                    'fecha_resolucion'      => '2026-02-20',
                    'motivo'                => 'Motivos laborales comprobados en el sector salud.',
                    'observaciones'         => 'Aprobado conforme a los Lineamientos Académicos Generales RVM 277-2019-MINEDU.',
                    'estado'                => 'APROBADO',
                ]
            );
        }

        if (isset($estudiantesEntities['est003'])) {
            TramiteEstudiante::withoutGlobalScopes()->firstOrCreate(
                [
                    'institucion_id' => $institucion->id,
                    'estudiante_id'  => $estudiantesEntities['est003']->id,
                    'tipo_tramite'   => 'CONVALIDACION',
                ],
                [
                    'resolucion_directoral' => null,
                    'fecha_solicitud'       => '2026-03-02',
                    'motivo'                => 'Convalidación de Unidad Didáctica de Base de Datos cursada en IESTP Continental.',
                    'observaciones'         => 'En revisión por la comisión de convalidaciones del programa DPW.',
                    'estado'                => 'PENDIENTE',
                ]
            );
        }

        // ── 15. Certificados Modulares (Ley 30512 & RVM 277-2019-MINEDU) ─
        if (isset($estudiantesEntities['est001']) && isset($programas['PROG-ENF'])) {
            CertificadoModular::withoutGlobalScopes()->firstOrCreate(
                [
                    'institucion_id'      => $institucion->id,
                    'codigo_verificacion' => 'MOD-SAM-2026-ENF-001',
                ],
                [
                    'estudiante_id'         => $estudiantesEntities['est001']->id,
                    'programa_id'           => $programas['PROG-ENF']->id,
                    'numero_modulo'         => 1,
                    'nombre_modulo'         => 'Módulo I: Atención Integral en Salud y Primeros Auxilios',
                    'resolucion_directoral' => 'RD N° 088-2025-DG-IESTP-SAM',
                    'horas_teoria'          => 144,
                    'horas_practica'        => 288,
                    'creditos'              => 22.00,
                    'horas_efsrt'           => 120,
                    'fecha_emision'         => '2025-12-20',
                    'estado'                => 'EMITIDO',
                ]
            );
        }

        // ── 16. Titulaciones y Libro de Títulos Institucional ──────────────
        if (isset($estudiantesEntities['est002']) && isset($programas['PROG-AGRO'])) {
            Titulacion::withoutGlobalScopes()->firstOrCreate(
                [
                    'institucion_id'      => $institucion->id,
                    'codigo_verificacion' => 'TIT-SAM-2026-AGRO-042',
                ],
                [
                    'estudiante_id'         => $estudiantesEntities['est002']->id,
                    'programa_id'           => $programas['PROG-AGRO']->id,
                    'modalidad_titulacion'  => 'PROYECTO_APLICACION_PROFESIONAL',
                    'numero_titulo'         => 'T-2026-0042',
                    'numero_registro'       => 'REG-2026-042',
                    'numero_folio'          => '028',
                    'numero_libro'          => 'LIB-IV',
                    'resolucion_directoral' => 'RD N° 0210-2026-DG-IESTP-SAM',
                    'codigo_minedu'         => 'TIT-MINEDU-2026-JUN-0881',
                    'fecha_sustentacion'    => '2026-02-15',
                    'fecha_emision'         => '2026-02-28',
                    'estado'                => 'REPORTADO_MINEDU',
                ]
            );
        }
    }
}
