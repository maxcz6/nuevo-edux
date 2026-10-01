<?php

namespace App\Http\Controllers\Api\Aula;

use App\Models\GrupoCurso;
use App\Models\Unidad;
use App\Models\Tarea;
use App\Models\Evaluacion;
use App\Models\Estudiante;

trait AuthorizesAulaAccess
{
    /**
     * Verifica acceso al grupo validando institución y permisos por rol:
     * - Admin/Superadmin: acceso total dentro de su institución.
     * - Coordinador: acceso total solo si el grupo pertenece a su unidad.
     * - Docente: debe ser el docente asignado al grupo.
     * - Estudiante: debe estar matriculado (solo lectura si $forManagement es false).
     */
    protected function verifyGroupAccess($grupoId, bool $forManagement = false): GrupoCurso
    {
        $user = auth()->user();
        $user->loadMissing(['roles', 'docente', 'estudiante']);

        $grupoCurso = GrupoCurso::query()
            ->when($user->institucion_id, fn ($q) => $q->where('institucion_id', $user->institucion_id))
            ->findOrFail($grupoId);

        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $isAdmin      = $user->hasRole('ADMINISTRADOR');

        if ($isSuperAdmin || $isAdmin) {
            return $grupoCurso;
        }

        // COORDINADOR: acceso al grupo si pertenece a su programa (unidad_academica_id)
        if ($user->hasRole('COORDINADOR') && $user->unidad_academica_id) {
            $belongsToPrograma = GrupoCurso::where('id', $grupoCurso->id)
                ->whereHas('curso', fn($q) => $q->where('unidad_academica_id', $user->unidad_academica_id))
                ->exists();
            if ($belongsToPrograma) {
                return $grupoCurso;
            }
            abort(response()->json([
                'message' => 'Este grupo no pertenece a tu programa de estudios.',
                'error'   => 'forbidden'
            ], 403));
        }

        $isAssignedDocente = $user->hasRole('DOCENTE')
            && $user->docente
            && $grupoCurso->docente_id === $user->docente->id;

        if ($forManagement) {
            if ($isAssignedDocente) {
                return $grupoCurso;
            }
            abort(response()->json([
                'message' => 'No tienes permisos de gestión en este grupo.',
                'error'   => 'forbidden'
            ], 403));
        }

        // Lectura (index, show)
        if ($isAssignedDocente) {
            return $grupoCurso;
        }

        if ($user->hasRole('ESTUDIANTE') && $user->estudiante) {
            $isEnrolled = $grupoCurso->matriculas()
                ->where('estudiante_id', $user->estudiante->id)
                ->where('estado', 'MATRICULADO')
                ->exists();

            if ($isEnrolled) {
                return $grupoCurso;
            }
        }

        abort(response()->json([
            'message' => 'No estás asignado ni matriculado en este grupo.',
            'error'   => 'forbidden'
        ], 403));
    }

    /**
     * Verifica que el estudiante enviado pertenece a la misma institución y
     * está matriculado en el grupo dado.
     */
    protected function verifyEstudianteInGroup(int $estudianteId, GrupoCurso $grupo): void
    {
        // Verificar que el estudiante pertenece a la institución
        $estudiante = Estudiante::where('id', $estudianteId)
            ->where('institucion_id', $grupo->institucion_id)
            ->first();

        if (!$estudiante) {
            abort(response()->json([
                'message' => 'El estudiante no pertenece a esta institución.',
                'error'   => 'forbidden'
            ], 403));
        }

        // Verificar que está matriculado en el grupo
        $matriculado = $grupo->matriculas()
            ->where('estudiante_id', $estudianteId)
            ->where('estado', 'MATRICULADO')
            ->exists();

        if (!$matriculado) {
            abort(response()->json([
                'message' => 'El estudiante no está matriculado en este grupo.',
                'error'   => 'forbidden'
            ], 403));
        }
    }

    /**
     * Verifica acceso a una unidad mediante su GrupoCurso.
     */
    protected function verifyUnidadAccess($unidadId, bool $forManagement = false): Unidad
    {
        $unidad = Unidad::with('grupoCurso')->findOrFail($unidadId);
        $this->verifyGroupAccess($unidad->grupo_curso_id, $forManagement);
        return $unidad;
    }

    /**
     * Verifica acceso a una tarea mediante su GrupoCurso.
     */
    protected function verifyTareaAccess($tareaId, bool $forManagement = false): Tarea
    {
        $tarea = Tarea::with('grupoCurso')->findOrFail($tareaId);
        $this->verifyGroupAccess($tarea->grupo_curso_id, $forManagement);
        return $tarea;
    }

    /**
     * Verifica acceso a una evaluación mediante su GrupoCurso y que esté PUBLICADA si es estudiante.
     */
    protected function verifyEvaluacionAccess($evaluacionId, bool $forManagement = false): Evaluacion
    {
        $evaluacion = Evaluacion::with('grupoCurso')->findOrFail($evaluacionId);
        $this->verifyGroupAccess($evaluacion->grupo_curso_id, $forManagement);

        // Bug #5: Un estudiante solo puede iniciar evaluaciones PUBLICADAS
        $user = auth()->user();
        if ($user->hasRole('ESTUDIANTE') && $evaluacion->estado !== 'PUBLICADA') {
            abort(response()->json([
                'message' => 'La evaluación no está disponible.',
                'error'   => 'forbidden'
            ], 403));
        }

        return $evaluacion;
    }
}
