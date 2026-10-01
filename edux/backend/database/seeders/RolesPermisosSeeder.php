<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rol;
use App\Models\Permiso;
use Illuminate\Support\Facades\DB;

class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $rolesData = [
            ['nombre' => 'SUPERADMIN',    'descripcion' => 'Super Administrador del Sistema — acceso global completo',     'es_sistema' => true, 'estado' => true, 'institucion_id' => null],
            ['nombre' => 'ADMINISTRADOR', 'descripcion' => 'Administrador de la Institución — gestión general moderada',   'es_sistema' => true, 'estado' => true, 'institucion_id' => null],
            ['nombre' => 'COORDINADOR',   'descripcion' => 'Coordinador de Programa — gestión de su carrera específica',   'es_sistema' => true, 'estado' => true, 'institucion_id' => null],
            ['nombre' => 'SECRETARIA',     'descripcion' => 'Secretaria institucional — gestión documental y de trámites',   'es_sistema' => true, 'estado' => true, 'institucion_id' => null],
            ['nombre' => 'CAJA',          'descripcion' => 'Caja — matrículas, cobros y operaciones de caja',              'es_sistema' => true, 'estado' => true, 'institucion_id' => null],
            ['nombre' => 'MESA_DE_PARTES','descripcion' => 'Mesa de partes — consulta y seguimiento documental',           'es_sistema' => true, 'estado' => true, 'institucion_id' => null],
            ['nombre' => 'DOCENTE',       'descripcion' => 'Docente — gestión de sus grupos y aula virtual',               'es_sistema' => true, 'estado' => true, 'institucion_id' => null],
            ['nombre' => 'ESTUDIANTE',    'descripcion' => 'Estudiante — acceso a cursos, tareas y notas propias',          'es_sistema' => true, 'estado' => true, 'institucion_id' => null],
            ['nombre' => 'APODERADO',     'descripcion' => 'Apoderado / Padre de Familia — seguimiento de sus hijos',      'es_sistema' => true, 'estado' => true, 'institucion_id' => null],
        ];

        $roles = [];
        foreach ($rolesData as $data) {
            $roles[$data['nombre']] = Rol::firstOrCreate(
                ['nombre' => $data['nombre'], 'institucion_id' => $data['institucion_id']],
                $data
            );
        }

        $permisosData = [
            // ─── Instituciones (solo SUPERADMIN) ──────────────────────────────
            ['modulo' => 'instituciones', 'codigo' => 'instituciones.ver',      'nombre' => 'Ver instituciones'],
            ['modulo' => 'instituciones', 'codigo' => 'instituciones.crear',    'nombre' => 'Crear instituciones'],
            ['modulo' => 'instituciones', 'codigo' => 'instituciones.editar',   'nombre' => 'Editar instituciones'],
            ['modulo' => 'instituciones', 'codigo' => 'instituciones.eliminar', 'nombre' => 'Eliminar instituciones'],

            // ─── Usuarios ─────────────────────────────────────────────────────
            ['modulo' => 'usuarios', 'codigo' => 'usuarios.ver',               'nombre' => 'Ver lista de usuarios'],
            ['modulo' => 'usuarios', 'codigo' => 'usuarios.crear',             'nombre' => 'Crear usuarios'],
            ['modulo' => 'usuarios', 'codigo' => 'usuarios.editar',            'nombre' => 'Editar usuarios'],
            ['modulo' => 'usuarios', 'codigo' => 'usuarios.eliminar',          'nombre' => 'Desactivar usuarios'],
            ['modulo' => 'usuarios', 'codigo' => 'usuarios.cambiar_password',  'nombre' => 'Cambiar contraseña de cualquier usuario'],

            // ─── Roles y permisos (solo SUPERADMIN) ──────────────────────────
            ['modulo' => 'roles', 'codigo' => 'roles.ver',      'nombre' => 'Ver roles y permisos'],
            ['modulo' => 'roles', 'codigo' => 'roles.crear',    'nombre' => 'Crear roles'],
            ['modulo' => 'roles', 'codigo' => 'roles.editar',   'nombre' => 'Editar roles'],
            ['modulo' => 'roles', 'codigo' => 'roles.eliminar', 'nombre' => 'Eliminar roles'],
            ['modulo' => 'roles', 'codigo' => 'roles.asignar',  'nombre' => 'Asignar roles a usuarios'],

            // ─── Programas de estudio ─────────────────────────────────────────
            ['modulo' => 'programas', 'codigo' => 'programas.ver',     'nombre' => 'Ver programas de estudio'],
            ['modulo' => 'programas', 'codigo' => 'programas.crear',   'nombre' => 'Crear programas de estudio'],
            ['modulo' => 'programas', 'codigo' => 'programas.editar',  'nombre' => 'Editar programas de estudio'],
            ['modulo' => 'programas', 'codigo' => 'programas.eliminar','nombre' => 'Eliminar programas de estudio'],

            // ─── Docentes ────────────────────────────────────────────────────
            ['modulo' => 'docentes', 'codigo' => 'docentes.ver',      'nombre' => 'Ver docentes'],
            ['modulo' => 'docentes', 'codigo' => 'docentes.crear',    'nombre' => 'Crear/vincular docentes'],
            ['modulo' => 'docentes', 'codigo' => 'docentes.editar',   'nombre' => 'Editar docentes'],
            ['modulo' => 'docentes', 'codigo' => 'docentes.eliminar', 'nombre' => 'Desvincular docentes'],

            // ─── Estudiantes ──────────────────────────────────────────────────
            ['modulo' => 'estudiantes', 'codigo' => 'estudiantes.ver',      'nombre' => 'Ver estudiantes'],
            ['modulo' => 'estudiantes', 'codigo' => 'estudiantes.crear',    'nombre' => 'Crear/vincular estudiantes'],
            ['modulo' => 'estudiantes', 'codigo' => 'estudiantes.editar',   'nombre' => 'Editar estudiantes'],
            ['modulo' => 'estudiantes', 'codigo' => 'estudiantes.eliminar', 'nombre' => 'Desvincular estudiantes'],

            // ─── Académico ────────────────────────────────────────────────────
            ['modulo' => 'academico', 'codigo' => 'periodos.ver',     'nombre' => 'Ver períodos académicos'],
            ['modulo' => 'academico', 'codigo' => 'periodos.crear',   'nombre' => 'Crear períodos académicos'],
            ['modulo' => 'academico', 'codigo' => 'periodos.editar',  'nombre' => 'Editar períodos académicos'],
            ['modulo' => 'academico', 'codigo' => 'cursos.ver',       'nombre' => 'Ver cursos/UDs'],
            ['modulo' => 'academico', 'codigo' => 'cursos.crear',     'nombre' => 'Crear cursos/UDs'],
            ['modulo' => 'academico', 'codigo' => 'cursos.editar',    'nombre' => 'Editar cursos/UDs'],
            ['modulo' => 'academico', 'codigo' => 'grupos.ver',       'nombre' => 'Ver grupos de curso'],
            ['modulo' => 'academico', 'codigo' => 'grupos.crear',     'nombre' => 'Crear grupos de curso'],
            ['modulo' => 'academico', 'codigo' => 'grupos.editar',    'nombre' => 'Editar grupos de curso'],
            ['modulo' => 'academico', 'codigo' => 'secciones.ver',    'nombre' => 'Ver secciones'],
            ['modulo' => 'academico', 'codigo' => 'secciones.crear',  'nombre' => 'Crear secciones'],
            ['modulo' => 'academico', 'codigo' => 'secciones.editar', 'nombre' => 'Editar secciones'],
            ['modulo' => 'academico', 'codigo' => 'matriculas.ver',   'nombre' => 'Ver matrículas'],
            ['modulo' => 'academico', 'codigo' => 'matriculas.crear', 'nombre' => 'Procesar matrículas'],
            ['modulo' => 'academico', 'codigo' => 'matriculas.editar','nombre' => 'Editar matrículas'],

            // ─── Admisión ─────────────────────────────────────────────────────
            ['modulo' => 'admision', 'codigo' => 'admision.ver',      'nombre' => 'Ver admisión y postulantes'],
            ['modulo' => 'admision', 'codigo' => 'admision.crear',    'nombre' => 'Crear convocatorias de admisión'],
            ['modulo' => 'admision', 'codigo' => 'admision.gestionar','nombre' => 'Gestionar admisión (vacantes, méritos)'],

            // ─── Trámites ─────────────────────────────────────────────────────
            ['modulo' => 'tramites', 'codigo' => 'tramites.ver',    'nombre' => 'Ver trámites académicos'],
            ['modulo' => 'tramites', 'codigo' => 'tramites.crear',  'nombre' => 'Crear trámites académicos'],
            ['modulo' => 'tramites', 'codigo' => 'tramites.editar', 'nombre' => 'Editar y aprobar trámites'],

            // ─── Ambientes ────────────────────────────────────────────────────
            ['modulo' => 'ambientes', 'codigo' => 'ambientes.ver',    'nombre' => 'Ver ambientes y talleres'],
            ['modulo' => 'ambientes', 'codigo' => 'ambientes.crear',  'nombre' => 'Crear ambientes'],
            ['modulo' => 'ambientes', 'codigo' => 'ambientes.editar', 'nombre' => 'Editar ambientes'],

            // ─── Titulación ───────────────────────────────────────────────────
            ['modulo' => 'titulacion', 'codigo' => 'titulacion.ver',    'nombre' => 'Ver titulación y egresados'],
            ['modulo' => 'titulacion', 'codigo' => 'titulacion.crear',  'nombre' => 'Registrar titulaciones'],
            ['modulo' => 'titulacion', 'codigo' => 'titulacion.editar', 'nombre' => 'Editar titulaciones'],

            // ─── Aula Virtual ─────────────────────────────────────────────────
            ['modulo' => 'aula', 'codigo' => 'aula.ver',               'nombre' => 'Ver aula virtual'],
            ['modulo' => 'aula', 'codigo' => 'unidades.ver',           'nombre' => 'Ver unidades'],
            ['modulo' => 'aula', 'codigo' => 'unidades.crear',         'nombre' => 'Crear unidades'],
            ['modulo' => 'aula', 'codigo' => 'unidades.editar',        'nombre' => 'Editar unidades'],
            ['modulo' => 'aula', 'codigo' => 'sesiones.ver',           'nombre' => 'Ver sesiones'],
            ['modulo' => 'aula', 'codigo' => 'sesiones.crear',         'nombre' => 'Crear sesiones'],
            ['modulo' => 'aula', 'codigo' => 'sesiones.editar',        'nombre' => 'Editar sesiones'],
            ['modulo' => 'aula', 'codigo' => 'materiales.ver',         'nombre' => 'Ver materiales'],
            ['modulo' => 'aula', 'codigo' => 'materiales.crear',       'nombre' => 'Subir materiales'],
            ['modulo' => 'aula', 'codigo' => 'materiales.editar',      'nombre' => 'Editar materiales'],
            ['modulo' => 'aula', 'codigo' => 'materiales.eliminar',    'nombre' => 'Eliminar materiales'],
            ['modulo' => 'aula', 'codigo' => 'tareas.ver',             'nombre' => 'Ver tareas'],
            ['modulo' => 'aula', 'codigo' => 'tareas.crear',           'nombre' => 'Crear tareas'],
            ['modulo' => 'aula', 'codigo' => 'tareas.editar',          'nombre' => 'Editar tareas'],
            ['modulo' => 'aula', 'codigo' => 'tareas.eliminar',        'nombre' => 'Eliminar tareas'],
            ['modulo' => 'aula', 'codigo' => 'evaluaciones.ver',       'nombre' => 'Ver evaluaciones y cuestionarios'],
            ['modulo' => 'aula', 'codigo' => 'evaluaciones.crear',     'nombre' => 'Crear evaluaciones y cuestionarios'],
            ['modulo' => 'aula', 'codigo' => 'evaluaciones.editar',    'nombre' => 'Editar evaluaciones y cuestionarios'],
            ['modulo' => 'aula', 'codigo' => 'evaluaciones.eliminar',  'nombre' => 'Eliminar evaluaciones y cuestionarios'],
            ['modulo' => 'aula', 'codigo' => 'evaluaciones.rendir',    'nombre' => 'Rendir evaluaciones y cuestionarios'],
            ['modulo' => 'aula', 'codigo' => 'entregas.ver',           'nombre' => 'Ver entregas'],
            ['modulo' => 'aula', 'codigo' => 'entregas.crear',         'nombre' => 'Entregar tareas'],
            ['modulo' => 'aula', 'codigo' => 'entregas.calificar',     'nombre' => 'Calificar entregas'],
            ['modulo' => 'aula', 'codigo' => 'calificaciones.ver',     'nombre' => 'Ver calificaciones'],
            ['modulo' => 'aula', 'codigo' => 'calificaciones.crear',   'nombre' => 'Registrar calificaciones'],
            ['modulo' => 'aula', 'codigo' => 'calificaciones.editar',  'nombre' => 'Editar calificaciones'],
            ['modulo' => 'aula', 'codigo' => 'avisos.ver',             'nombre' => 'Ver avisos'],
            ['modulo' => 'aula', 'codigo' => 'avisos.crear',           'nombre' => 'Publicar avisos'],
            ['modulo' => 'aula', 'codigo' => 'asistencias.ver',        'nombre' => 'Ver asistencias'],
            ['modulo' => 'aula', 'codigo' => 'asistencias.registrar',  'nombre' => 'Registrar asistencias'],

            // ─── Perfil propio (todos los roles) ──────────────────────────────
            ['modulo' => 'perfil', 'codigo' => 'perfil.ver',              'nombre' => 'Ver mi perfil'],
            ['modulo' => 'perfil', 'codigo' => 'perfil.editar',           'nombre' => 'Editar mi perfil'],
            ['modulo' => 'perfil', 'codigo' => 'perfil.cambiar_password', 'nombre' => 'Cambiar mi contraseña'],
        ];

        $permisos = [];
        foreach ($permisosData as $data) {
            $data['descripcion'] = $data['nombre'];
            $permiso = Permiso::firstOrCreate(['codigo' => $data['codigo']], $data);
            $permisos[$data['codigo']] = $permiso->id;
        }

        // ─────────────────────────────────────────────────────────────────────
        // SUPERADMIN — todo sin excepción
        // ─────────────────────────────────────────────────────────────────────
        $roles['SUPERADMIN']->permisos()->sync(array_values($permisos));

        // ─────────────────────────────────────────────────────────────────────
        // ADMINISTRADOR — gestión completa de la institución, SIN instituciones
        // ni gestión de roles. Puede cambiar contraseñas pero no de SUPERADMIN.
        // ─────────────────────────────────────────────────────────────────────
        $adminCodes = [
            'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar', 'usuarios.cambiar_password',
            'roles.ver',
            'programas.ver', 'programas.crear', 'programas.editar', 'programas.eliminar',
            'docentes.ver', 'docentes.crear', 'docentes.editar', 'docentes.eliminar',
            'estudiantes.ver', 'estudiantes.crear', 'estudiantes.editar', 'estudiantes.eliminar',
            'periodos.ver', 'periodos.crear', 'periodos.editar',
            'cursos.ver', 'cursos.crear', 'cursos.editar',
            'grupos.ver', 'grupos.crear', 'grupos.editar',
            'secciones.ver', 'secciones.crear', 'secciones.editar',
            'matriculas.ver', 'matriculas.crear', 'matriculas.editar',
            'admision.ver', 'admision.crear', 'admision.gestionar',
            'tramites.ver', 'tramites.crear', 'tramites.editar',
            'ambientes.ver', 'ambientes.crear', 'ambientes.editar',
            'titulacion.ver', 'titulacion.crear', 'titulacion.editar',
            'aula.ver', 'unidades.ver', 'unidades.crear', 'unidades.editar',
            'sesiones.ver', 'sesiones.crear', 'sesiones.editar',
            'materiales.ver', 'materiales.crear', 'materiales.editar', 'materiales.eliminar',
            'tareas.ver', 'tareas.crear', 'tareas.editar', 'tareas.eliminar',
            'evaluaciones.ver', 'evaluaciones.crear', 'evaluaciones.editar', 'evaluaciones.eliminar',
            'entregas.ver', 'entregas.calificar',
            'calificaciones.ver', 'calificaciones.crear', 'calificaciones.editar',
            'avisos.ver', 'avisos.crear',
            'asistencias.ver', 'asistencias.registrar',
            'perfil.ver', 'perfil.editar', 'perfil.cambiar_password',
        ];
        $roles['ADMINISTRADOR']->permisos()->sync(
            array_values(array_filter($permisos, fn($c) => in_array($c, $adminCodes), ARRAY_FILTER_USE_KEY))
        );

        // ─────────────────────────────────────────────────────────────────────
        // COORDINADOR — solo su programa, sin CRUD de usuarios, sin roles,
        // sin instituciones, sin períodos CRUD, sin admisión CRUD.
        // Puede ver/vincular docentes y estudiantes a su programa.
        // ─────────────────────────────────────────────────────────────────────
        $coordinadorCodes = [
            'programas.ver',
            'docentes.ver', 'docentes.crear', 'docentes.editar',   // vincular al programa
            'estudiantes.ver', 'estudiantes.crear', 'estudiantes.editar',
            'periodos.ver',
            'cursos.ver', 'cursos.crear', 'cursos.editar',
            'grupos.ver', 'grupos.crear', 'grupos.editar',
            'secciones.ver', 'secciones.crear', 'secciones.editar',
            'matriculas.ver', 'matriculas.crear', 'matriculas.editar',
            'admision.ver',
            'tramites.ver', 'tramites.editar',
            'ambientes.ver',
            'titulacion.ver',
            'aula.ver', 'unidades.ver', 'unidades.crear', 'unidades.editar',
            'sesiones.ver', 'sesiones.crear', 'sesiones.editar',
            'materiales.ver', 'materiales.crear', 'materiales.editar', 'materiales.eliminar',
            'tareas.ver', 'tareas.crear', 'tareas.editar', 'tareas.eliminar',
            'evaluaciones.ver', 'evaluaciones.crear', 'evaluaciones.editar', 'evaluaciones.eliminar',
            'entregas.ver', 'entregas.calificar',
            'calificaciones.ver', 'calificaciones.crear', 'calificaciones.editar',
            'avisos.ver', 'avisos.crear',
            'asistencias.ver', 'asistencias.registrar',
            'perfil.ver', 'perfil.editar', 'perfil.cambiar_password',
        ];
        $roles['COORDINADOR']->permisos()->sync(
            array_values(array_filter($permisos, fn($c) => in_array($c, $coordinadorCodes), ARRAY_FILTER_USE_KEY))
        );

        // ─────────────────────────────────────────────────────────────────────
        // SECRETARIA — gestión institucional de trámites y documentos.
        // ─────────────────────────────────────────────────────────────────────
        $secretariaCodes = [
            'estudiantes.ver', 'estudiantes.crear', 'estudiantes.editar',
            'programas.ver',
            'matriculas.ver', 'matriculas.editar',
            'tramites.ver', 'tramites.crear', 'tramites.editar',
            'perfil.ver', 'perfil.editar', 'perfil.cambiar_password',
        ];
        $roles['SECRETARIA']->permisos()->sync(
            array_values(array_filter($permisos, fn($c) => in_array($c, $secretariaCodes), ARRAY_FILTER_USE_KEY))
        );

        // ─────────────────────────────────────────────────────────────────────
        // CAJA — consulta y control operativo de matrículas / cobro sin módulo
        // de pago real. Reutiliza la superficie de matrículas existente.
        // ─────────────────────────────────────────────────────────────────────
        $cajaCodes = [
            'estudiantes.ver',
            'matriculas.ver', 'matriculas.editar',
            'tramites.ver',
            'perfil.ver', 'perfil.editar', 'perfil.cambiar_password',
        ];
        $roles['CAJA']->permisos()->sync(
            array_values(array_filter($permisos, fn($c) => in_array($c, $cajaCodes), ARRAY_FILTER_USE_KEY))
        );

        // ─────────────────────────────────────────────────────────────────────
        // MESA_DE_PARTES — consulta documental y seguimiento sin aprobación.
        // ─────────────────────────────────────────────────────────────────────
        $mesaDePartesCodes = [
            'estudiantes.ver',
            'tramites.ver',
            'perfil.ver', 'perfil.editar', 'perfil.cambiar_password',
        ];
        $roles['MESA_DE_PARTES']->permisos()->sync(
            array_values(array_filter($permisos, fn($c) => in_array($c, $mesaDePartesCodes), ARRAY_FILTER_USE_KEY))
        );

        // ─────────────────────────────────────────────────────────────────────
        // DOCENTE — solo sus grupos asignados. Sin gestión administrativa.
        // ─────────────────────────────────────────────────────────────────────
        $docenteCodes = [
            'grupos.ver',
            'cursos.ver',
            'aula.ver',
            'unidades.ver', 'unidades.crear', 'unidades.editar',
            'sesiones.ver', 'sesiones.crear', 'sesiones.editar',
            'materiales.ver', 'materiales.crear', 'materiales.editar', 'materiales.eliminar',
            'tareas.ver', 'tareas.crear', 'tareas.editar', 'tareas.eliminar',
            'evaluaciones.ver', 'evaluaciones.crear', 'evaluaciones.editar', 'evaluaciones.eliminar',
            'entregas.ver', 'entregas.calificar',
            'calificaciones.ver', 'calificaciones.crear', 'calificaciones.editar',
            'avisos.ver', 'avisos.crear',
            'asistencias.ver', 'asistencias.registrar',
            'perfil.ver', 'perfil.editar', 'perfil.cambiar_password',
        ];
        $roles['DOCENTE']->permisos()->sync(
            array_values(array_filter($permisos, fn($c) => in_array($c, $docenteCodes), ARRAY_FILTER_USE_KEY))
        );

        // ─────────────────────────────────────────────────────────────────────
        // ESTUDIANTE — solo lectura de su contenido. Puede entregar tareas
        // y hacer evaluaciones.
        // ─────────────────────────────────────────────────────────────────────
        $estudianteCodes = [
            'cursos.ver',
            'grupos.ver',
            'aula.ver',
            'unidades.ver',
            'sesiones.ver',
            'materiales.ver',
            'tareas.ver',
            'evaluaciones.ver',
            'evaluaciones.rendir',
            'entregas.ver', 'entregas.crear',
            'calificaciones.ver',
            'avisos.ver',
            'asistencias.ver',
            'matriculas.ver',
            'tramites.ver', 'tramites.crear',
            'titulacion.ver',
            'perfil.ver', 'perfil.editar', 'perfil.cambiar_password',
        ];
        $roles['ESTUDIANTE']->permisos()->sync(
            array_values(array_filter($permisos, fn($c) => in_array($c, $estudianteCodes), ARRAY_FILTER_USE_KEY))
        );

        // ─────────────────────────────────────────────────────────────────────
        // APODERADO — solo seguimiento de hijos.
        // ─────────────────────────────────────────────────────────────────────
        $apoderadoCodes = [
            'calificaciones.ver',
            'asistencias.ver',
            'avisos.ver',
            'perfil.ver', 'perfil.editar', 'perfil.cambiar_password',
        ];
        $roles['APODERADO']->permisos()->sync(
            array_values(array_filter($permisos, fn($c) => in_array($c, $apoderadoCodes), ARRAY_FILTER_USE_KEY))
        );
    }
}
