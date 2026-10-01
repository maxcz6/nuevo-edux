<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Admin\InstitucionController;
use App\Http\Controllers\Api\Admin\UsuarioController;
use App\Http\Controllers\Api\Admin\RolController;
use App\Http\Controllers\Api\Admin\DocenteController;
use App\Http\Controllers\Api\Admin\EstudianteController;
use App\Http\Controllers\Api\Academico\PeriodoController;
use App\Http\Controllers\Api\Academico\CursoController;
use App\Http\Controllers\Api\Academico\GrupoCursoController;
use App\Http\Controllers\Api\Academico\SeccionController;
use App\Http\Controllers\Api\Academico\MatriculaController;
use App\Http\Controllers\Api\Aula\UnidadController;
use App\Http\Controllers\Api\Aula\SesionController;
use App\Http\Controllers\Api\Aula\MaterialController;
use App\Http\Controllers\Api\Aula\TareaController;
use App\Http\Controllers\Api\Aula\EntregaController;
use App\Http\Controllers\Api\Aula\CalificacionController;
use App\Http\Controllers\Api\Aula\AvisoController;
use App\Http\Controllers\Api\Aula\NotificacionController;
use App\Http\Controllers\Api\Aula\AsistenciaController;
use App\Http\Controllers\Api\Aula\EvaluacionController;
use App\Http\Controllers\Api\Admin\SedeController;
use App\Http\Controllers\Api\Admin\NivelEducativoController;
use App\Http\Controllers\Api\Admin\UnidadAcademicaController;
use App\Http\Controllers\Api\Admin\PlanEstudioController;
use App\Http\Controllers\Api\Academico\ReporteController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\AdmisionController;
use App\Http\Controllers\Api\Admin\TramiteEstudianteController;
use App\Http\Controllers\Api\Admin\AmbienteController;
use App\Http\Controllers\Api\Academico\RegistraExportController;
use App\Http\Controllers\Api\Admin\TitulacionController;

Route::prefix('v1')->group(function () {

    // ── Público ───────────────────────────────────────────────────────────────
    Route::get('/health', function () {
        $dbStatus = 'ok';
        $redisStatus = 'ok';
        $storageStatus = 'ok';

        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
        } catch (\Throwable) {
            $dbStatus = 'disconnected';
        }

        try {
            \Illuminate\Support\Facades\Redis::connection()->ping();
        } catch (\Throwable) {
            $redisStatus = 'disconnected';
        }

        try {
            $probeFile = 'probe_' . time() . '.tmp';
            \Illuminate\Support\Facades\Storage::disk('local')->put($probeFile, 'health');
            \Illuminate\Support\Facades\Storage::disk('local')->delete($probeFile);
        } catch (\Throwable) {
            $storageStatus = 'disconnected';
        }

        $healthy = ($dbStatus === 'ok') && ($redisStatus === 'ok') && ($storageStatus === 'ok');

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
        ], $healthy ? 200 : 503);
    });

    // Verificación Pública de Documentos (QR / Código Ciudadano)
    Route::get('/public/verificar-documento/{codigo}', [TitulacionController::class, 'verificarDocumentoPublico']);
    Route::get('/consulta-publica/verificar-documento/{codigo}', [TitulacionController::class, 'verificarDocumentoPublico']);

    // ── Autenticación ─────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        $loginThrottle = app()->environment('local', 'testing') ? 'throttle:1000,1' : 'throttle:10,1';
        $resetThrottle = app()->environment('local', 'testing') ? 'throttle:1000,1' : 'throttle:5,1';
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware($loginThrottle)
            ->name('login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware($resetThrottle);
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware($resetThrottle);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // ── Protegidas (Sanctum + institución) ────────────────────────────────────
    Route::middleware(['auth:sanctum', 'ensure.institution'])->group(function () {

        // ── Perfil propio (todos los roles autenticados) ───────────────────────
        Route::get('/me/perfil', [UsuarioController::class, 'miPerfil']);
        Route::patch('/me/perfil', [UsuarioController::class, 'actualizarMiPerfil']);
        Route::post('/me/cambiar-password', [UsuarioController::class, 'cambiarMiPassword']);
        Route::get('/me/foto', [UsuarioController::class, 'miFoto']);
        Route::post('/me/foto', [UsuarioController::class, 'actualizarMiFoto']);
        Route::delete('/me/foto', [UsuarioController::class, 'eliminarMiFoto']);

        // Dashboard stats restringido a personal con roles de gestión y docentes
        Route::get('/dashboard/stats', [DashboardController::class, 'stats'])
            ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');

        // ── Instituciones (solo SUPERADMIN) ───────────────────────────────────
        Route::apiResource('instituciones', InstitucionController::class)
            ->middleware('check.role:SUPERADMIN');

        // ── Administración ─────────────────────────────────────────────────────
        Route::prefix('admin')->group(function () {
            // Usuarios
            Route::apiResource('usuarios', UsuarioController::class)
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            // Cambiar contraseña de cualquier usuario
            Route::post('usuarios/{id}/cambiar-password', [UsuarioController::class, 'cambiarPassword'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');

            // Roles y permisos
            Route::get('roles', [RolController::class, 'index'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::get('roles/{rol}', [RolController::class, 'show'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::post('roles', [RolController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::match(['put', 'patch'], 'roles/{rol}', [RolController::class, 'update'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::delete('roles/{rol}', [RolController::class, 'destroy'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::post('roles/{rol}/permisos', [RolController::class, 'assignPermissions'])
                ->middleware('check.role:SUPERADMIN');

            // Docentes
            Route::get('docentes', [DocenteController::class, 'index'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::get('docentes/{docente}', [DocenteController::class, 'show'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::post('docentes', [DocenteController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::put('docentes/{docente}', [DocenteController::class, 'update'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::delete('docentes/{docente}', [DocenteController::class, 'destroy'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');

            // Estudiantes
            Route::apiResource('estudiantes', EstudianteController::class)
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');

            // Sedes y Niveles Educativos
            Route::apiResource('sedes', SedeController::class)
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::apiResource('niveles-educativos', NivelEducativoController::class)
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');

            // Unidades Académicas / Programas de Estudio
            Route::get('unidades-academicas', [UnidadAcademicaController::class, 'index'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::get('unidades-academicas/{unidadAcademica}', [UnidadAcademicaController::class, 'show'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::post('unidades-academicas', [UnidadAcademicaController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::match(['put', 'patch'], 'unidades-academicas/{unidadAcademica}', [UnidadAcademicaController::class, 'update'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::delete('unidades-academicas/{unidadAcademica}', [UnidadAcademicaController::class, 'destroy'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');

            // Malla Curricular (Planes de estudio)
            Route::apiResource('planes-estudio', PlanEstudioController::class)
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::post('planes-estudio/{plan}/cursos', [PlanEstudioController::class, 'agregarCurso'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::delete('planes-estudio/{plan}/cursos/{planCurso}', [PlanEstudioController::class, 'removerCurso'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');

            // Admisión Tecnológica
            Route::get('admision/convocatorias', [AdmisionController::class, 'indexConvocatorias'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::post('admision/convocatorias', [AdmisionController::class, 'storeConvocatoria'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::get('admision/convocatorias/{id}', [AdmisionController::class, 'showConvocatoria'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::match(['put', 'patch'], 'admision/convocatorias/{id}', [AdmisionController::class, 'updateConvocatoria'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::post('admision/convocatorias/{id}/vacantes', [AdmisionController::class, 'guardarVacantes'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::get('admision/convocatorias/{id}/postulantes', [AdmisionController::class, 'indexPostulantes'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::post('admision/convocatorias/{id}/postulantes', [AdmisionController::class, 'storePostulante'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::post('admision/convocatorias/{id}/registrar-calificaciones', [AdmisionController::class, 'registrarCalificaciones'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::post('admision/convocatorias/{id}/calcular-merito', [AdmisionController::class, 'calcularOrdenMerito'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::post('admision/convocatorias/{id}/matricular-ingresantes', [AdmisionController::class, 'matricularIngresantes'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');

            // Trámites Académicos
            Route::get('tramites', [TramiteEstudianteController::class, 'index'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,ESTUDIANTE');
            Route::get('tramites/{tramite}', [TramiteEstudianteController::class, 'show'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,ESTUDIANTE');
            Route::post('tramites', [TramiteEstudianteController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,ESTUDIANTE');
            Route::match(['put', 'patch'], 'tramites/{tramite}', [TramiteEstudianteController::class, 'update'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::delete('tramites/{tramite}', [TramiteEstudianteController::class, 'destroy'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,ESTUDIANTE');

            // Ambientes, Talleres y Laboratorios
            Route::apiResource('ambientes', AmbienteController::class)
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');

            // Titulación y Libro de Títulos Institucional
            Route::get('titulacion', [TitulacionController::class, 'indexTitulaciones'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::post('titulacion', [TitulacionController::class, 'storeTitulacion'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::match(['put', 'patch'], 'titulacion/{id}', [TitulacionController::class, 'updateTitulacion'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::get('titulacion/exportar/libro-titulos', [TitulacionController::class, 'exportLibroTitulosCsv'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');

            // Certificación Modular
            Route::get('certificados-modulares', [TitulacionController::class, 'indexCertificados'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::post('certificados-modulares', [TitulacionController::class, 'storeCertificado'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::match(['put', 'patch'], 'certificados-modulares/{id}', [TitulacionController::class, 'updateCertificado'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR');
            Route::get('certificados-modulares/exportar/padron', [TitulacionController::class, 'exportCertificadosCsv'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
        });

        // ── Académico ──────────────────────────────────────────────────────────
        Route::prefix('academico')->group(function () {
            Route::apiResource('periodos', PeriodoController::class)
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');

            Route::apiResource('cursos', CursoController::class)
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');

            Route::apiResource('grupos', GrupoCursoController::class)
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::post('grupos/{grupo}/cerrar-acta', [GrupoCursoController::class, 'cerrarActa'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');

            Route::apiResource('secciones', SeccionController::class)
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');

            // Matrículas
            Route::get('matriculas', [MatriculaController::class, 'index'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::get('matriculas/{matricula}', [MatriculaController::class, 'show'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::post('matriculas', [MatriculaController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::match(['put', 'patch'], 'matriculas/{matricula}', [MatriculaController::class, 'update'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::delete('matriculas/{matricula}', [MatriculaController::class, 'destroy'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::post('matriculas/seccion', [MatriculaController::class, 'matricularSeccion'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');

            // Reportes Oficiales
            Route::get('reportes/boleta-notas/{estudiante}/{periodo}', [ReporteController::class, 'boletaNotas'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,ESTUDIANTE');
            Route::get('reportes/acta-consolidada/{grupo}', [ReporteController::class, 'actaConsolidada'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::get('reportes/ficha-matricula/{matricula}', [ReporteController::class, 'fichaMatricula'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,ESTUDIANTE');

            // Exportaciones Formato MINEDU REGISTRA
            Route::get('exportar/registra-ingresantes/{convocatoria}', [RegistraExportController::class, 'exportarPadronIngresantes'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::get('exportar/registra-nomina', [RegistraExportController::class, 'exportarNominaMatricula'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR');
            Route::get('exportar/registra-acta-ud/{grupo}', [RegistraExportController::class, 'exportarActaConsolidadaUd'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
        });

        // ── Aula Virtual ───────────────────────────────────────────────────────
        Route::prefix('aula')->group(function () {
            // Mis grupos (para docentes y estudiantes)
            Route::get('mis-grupos', [GrupoCursoController::class, 'misGrupos']);

            // Unidades del grupo
            Route::get('grupos/{grupo}/unidades', [UnidadController::class, 'index']);
            Route::get('grupos/{grupo}/unidades/{unidad}', [UnidadController::class, 'show']);
            Route::post('grupos/{grupo}/unidades', [UnidadController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::match(['put', 'patch'], 'grupos/{grupo}/unidades/{unidad}', [UnidadController::class, 'update'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::delete('grupos/{grupo}/unidades/{unidad}', [UnidadController::class, 'destroy'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');

            // Sesiones de la unidad
            Route::get('unidades/{unidad}/sesiones', [SesionController::class, 'index']);
            Route::get('unidades/{unidad}/sesiones/{sesion}', [SesionController::class, 'show']);
            Route::post('unidades/{unidad}/sesiones', [SesionController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::match(['put', 'patch'], 'unidades/{unidad}/sesiones/{sesion}', [SesionController::class, 'update'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::delete('unidades/{unidad}/sesiones/{sesion}', [SesionController::class, 'destroy'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');

            // Materiales de la unidad + descarga autorizada
            Route::get('unidades/{unidad}/materiales', [MaterialController::class, 'index']);
            Route::get('unidades/{unidad}/materiales/{material}', [MaterialController::class, 'show']);
            Route::post('unidades/{unidad}/materiales', [MaterialController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::match(['put', 'patch'], 'unidades/{unidad}/materiales/{material}', [MaterialController::class, 'update'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::delete('unidades/{unidad}/materiales/{material}', [MaterialController::class, 'destroy'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::get('materiales/{material}/descargar', [MaterialController::class, 'descargar'])
                ->name('materiales.descargar');

            // Tareas del grupo
            Route::get('grupos/{grupo}/tareas', [TareaController::class, 'index']);
            Route::get('grupos/{grupo}/tareas/{tarea}', [TareaController::class, 'show']);
            Route::post('grupos/{grupo}/tareas', [TareaController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::match(['put', 'patch'], 'grupos/{grupo}/tareas/{tarea}', [TareaController::class, 'update'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::delete('grupos/{grupo}/tareas/{tarea}', [TareaController::class, 'destroy'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::get('tareas/{tarea}/descargar-adjunto', [TareaController::class, 'descargarAdjunto'])
                ->name('tareas.descargar-adjunto');

            // Entregas de tarea
            Route::get('tareas/{tarea}/entregas', [EntregaController::class, 'index']);
            Route::post('tareas/{tarea}/entregas', [EntregaController::class, 'store'])
                ->middleware('check.role:ESTUDIANTE');
            Route::get('tareas/{tarea}/entregas/{entrega}', [EntregaController::class, 'show']);
            Route::patch('tareas/{tarea}/entregas/{entrega}/calificar', [EntregaController::class, 'calificar'])
                ->name('entregas.calificar')
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::get('entregas/{entrega}/descargar', [EntregaController::class, 'descargar'])
                ->name('entregas.descargar');

            // Calificaciones del grupo
            Route::get('grupos/{grupo}/calificaciones', [CalificacionController::class, 'index']);
            Route::get('grupos/{grupo}/calificaciones/consolidado', [CalificacionController::class, 'consolidado']);
            Route::get('grupos/{grupo}/calificaciones/{calificacion}', [CalificacionController::class, 'show']);
            Route::post('grupos/{grupo}/calificaciones', [CalificacionController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::match(['put', 'patch'], 'grupos/{grupo}/calificaciones/{calificacion}', [CalificacionController::class, 'update'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::delete('grupos/{grupo}/calificaciones/{calificacion}', [CalificacionController::class, 'destroy'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');

            // Avisos del grupo
            Route::get('grupos/{grupo}/avisos', [AvisoController::class, 'index']);
            Route::get('grupos/{grupo}/avisos/{aviso}', [AvisoController::class, 'show']);
            Route::post('grupos/{grupo}/avisos', [AvisoController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::match(['put', 'patch'], 'grupos/{grupo}/avisos/{aviso}', [AvisoController::class, 'update'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::delete('grupos/{grupo}/avisos/{aviso}', [AvisoController::class, 'destroy'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');

            // Asistencias del grupo
            Route::get('grupos/{grupo}/asistencias', [AsistenciaController::class, 'index']);
            Route::post('grupos/{grupo}/asistencias', [AsistenciaController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::get('grupos/{grupo}/asistencias/resumen', [AsistenciaController::class, 'resumen']);
            Route::get('grupos/{grupo}/asistencias/mis-asistencias', [AsistenciaController::class, 'misAsistencias'])
                ->middleware('check.role:ESTUDIANTE');

            // Evaluaciones del grupo
            Route::get('grupos/{grupo}/evaluaciones', [EvaluacionController::class, 'index']);
            Route::get('grupos/{grupo}/evaluaciones/{evaluacion}', [EvaluacionController::class, 'show']);
            Route::post('grupos/{grupo}/evaluaciones', [EvaluacionController::class, 'store'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::match(['put', 'patch'], 'grupos/{grupo}/evaluaciones/{evaluacion}', [EvaluacionController::class, 'update'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');
            Route::delete('grupos/{grupo}/evaluaciones/{evaluacion}', [EvaluacionController::class, 'destroy'])
                ->middleware('check.role:SUPERADMIN,ADMINISTRADOR,COORDINADOR,DOCENTE');

            // Intentos de evaluación
            Route::post('evaluaciones/{evaluacion}/iniciar-intento', [EvaluacionController::class, 'iniciarIntento'])
                ->middleware('check.role:ESTUDIANTE');
            Route::post('evaluaciones/{evaluacion}/intentos/{intento}/enviar', [EvaluacionController::class, 'enviarRespuestas'])
                ->middleware('check.role:ESTUDIANTE');

            // Notificaciones del usuario
            Route::get('notificaciones', [NotificacionController::class, 'index']);
            Route::patch('notificaciones/{id}/leer', [NotificacionController::class, 'marcarLeida']);
            Route::post('notificaciones/leer-todas', [NotificacionController::class, 'marcarTodasLeidas']);
        });
    });
});
