<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\Estudiante;
use App\Models\GrupoCurso;
use App\Models\Matricula;
use App\Models\Notificacion;
use App\Models\SolicitudInclusionGrupo;
use App\Models\Usuario;
use Illuminate\Http\Request;

class SolicitudInclusionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = SolicitudInclusionGrupo::with([
            'grupoCurso.curso',
            'grupoCurso.periodo',
            'docente.usuario',
            'estudiante.usuario',
            'coordinador',
        ]);

        if ($user->hasRole('DOCENTE') && $user->docente) {
            $query->where('docente_id', $user->docente->id);
        } elseif ($user->hasRole('COORDINADOR')) {
            $query->whereHas('grupoCurso.curso', fn ($q) => $q->where('unidad_academica_id', $user->unidad_academica_id ?? 0));
        }

        $query->when($request->grupo_curso_id, fn ($q, $v) => $q->where('grupo_curso_id', $v))
            ->when($request->estado, fn ($q, $v) => $q->where('estado', $v));

        $solicitudes = $query->orderByDesc('id')->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $solicitudes->items(),
            'meta' => [
                'current_page' => $solicitudes->currentPage(),
                'last_page'    => $solicitudes->lastPage(),
                'per_page'     => $solicitudes->perPage(),
                'total'        => $solicitudes->total(),
            ],
            'message' => 'OK',
        ]);
    }

    public function estudiantesDisponibles(Request $request, GrupoCurso $grupo)
    {
        $user = auth()->user();

        // Validar que el docente sea el asignado o tenga rol de gestión
        if ($user->hasRole('DOCENTE') && $grupo->docente_id !== $user->docente?->id) {
            abort(response()->json(['message' => 'No tienes permiso para gestionar este grupo.'], 403));
        }

        $matriculadosIds = Matricula::where('grupo_curso_id', $grupo->id)
            ->where('estado', 'MATRICULADO')
            ->pluck('estudiante_id')
            ->toArray();

        $search = trim((string) $request->get('q', ''));

        $estudiantes = Estudiante::with('usuario')
            ->where('estado', 'ACTIVO')
            ->whereNotIn('id', $matriculadosIds)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('codigo_estudiante', 'ilike', "%{$search}%")
                        ->orWhereHas('usuario', function ($uq) use ($search) {
                            $uq->where('nombres', 'ilike', "%{$search}%")
                               ->orWhere('apellido_paterno', 'ilike', "%{$search}%")
                               ->orWhere('apellido_materno', 'ilike', "%{$search}%")
                               ->orWhere('documento_numero', 'ilike', "%{$search}%");
                        });
                });
            })
            ->limit(30)
            ->get();

        return response()->json([
            'data' => $estudiantes->map(function ($est) {
                $nombreCompleto = trim("{$est->usuario?->nombres} {$est->usuario?->apellido_paterno} {$est->usuario?->apellido_materno}");
                return [
                    'id'               => $est->id,
                    'codigo_estudiante'=> $est->codigo_estudiante,
                    'documento_numero' => $est->usuario?->documento_numero,
                    'nombre_completo'  => $nombreCompleto ?: 'Estudiante #' . $est->id,
                    'email'            => $est->usuario?->email,
                ];
            }),
            'message' => 'OK',
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $institucionId = $user->institucion_id;

        $validated = $request->validate([
            'grupo_curso_id' => 'required|integer',
            'estudiante_id'  => 'required|integer',
            'motivo'         => 'required|string|min:5|max:1000',
        ], [
            'grupo_curso_id.required' => 'El grupo de curso es obligatorio.',
            'estudiante_id.required'  => 'Debes seleccionar al estudiante a incluir.',
            'motivo.required'         => 'Debes indicar el motivo de la inclusión.',
            'motivo.min'              => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $grupo = GrupoCurso::with(['curso', 'periodo'])->findOrFail($validated['grupo_curso_id']);

        if ($user->hasRole('DOCENTE')) {
            if (!$user->docente || $grupo->docente_id !== $user->docente->id) {
                abort(response()->json(['message' => 'No tienes permiso para solicitar inclusión en un grupo que no tienes asignado.'], 403));
            }
            $docenteId = $user->docente->id;
        } else {
            $docenteId = $grupo->docente_id;
        }

        $estudiante = Estudiante::with('usuario')->findOrFail($validated['estudiante_id']);
        if ($estudiante->estado !== 'ACTIVO') {
            abort(response()->json(['message' => 'El estudiante no se encuentra en estado ACTIVO.'], 422));
        }

        // Validar que no esté ya matriculado
        $yaMatriculado = Matricula::where('grupo_curso_id', $grupo->id)
            ->where('estudiante_id', $estudiante->id)
            ->where('estado', 'MATRICULADO')
            ->exists();
        if ($yaMatriculado) {
            abort(response()->json(['message' => 'El estudiante ya se encuentra matriculado en este grupo.'], 422));
        }

        // Validar que no tenga solicitud pendiente
        $yaSolicitado = SolicitudInclusionGrupo::where('grupo_curso_id', $grupo->id)
            ->where('estudiante_id', $estudiante->id)
            ->where('estado', 'PENDIENTE')
            ->exists();
        if ($yaSolicitado) {
            abort(response()->json(['message' => 'Ya existe una solicitud pendiente de inclusión para este estudiante en este grupo.'], 422));
        }

        $solicitud = SolicitudInclusionGrupo::create([
            'institucion_id' => $institucionId,
            'grupo_curso_id' => $grupo->id,
            'docente_id'     => $docenteId,
            'estudiante_id'  => $estudiante->id,
            'motivo'         => $validated['motivo'],
            'estado'         => 'PENDIENTE',
        ]);

        // Notificar a los coordinadores del programa o administradores
        $curso = $grupo->curso;
        $coordinadores = Usuario::where('institucion_id', $institucionId)
            ->where('estado', 'ACTIVO')
            ->where(function ($q) use ($curso) {
                if ($curso?->unidad_academica_id) {
                    $q->where(function ($sub) use ($curso) {
                        $sub->where('unidad_academica_id', $curso->unidad_academica_id)
                            ->whereHas('roles', fn ($r) => $r->where('nombre', 'COORDINADOR'));
                    });
                }
            })
            ->get();

        if ($coordinadores->isEmpty()) {
            $coordinadores = Usuario::where('institucion_id', $institucionId)
                ->where('estado', 'ACTIVO')
                ->whereHas('roles', fn ($r) => $r->whereIn('nombre', ['COORDINADOR', 'ADMINISTRADOR']))
                ->get();
        }

        $docenteNombre = $user->nombre_completo ?? "Prof. {$user->nombres}";
        $estudianteNombre = $estudiante->usuario?->nombre_completo ?? "Estudiante #{$estudiante->id}";
        $cursoNombre = $curso?->nombre ?? 'Curso';

        foreach ($coordinadores as $coord) {
            Notificacion::create([
                'usuario_id'      => $coord->id,
                'titulo'          => 'Nueva solicitud de inclusión de estudiante',
                'mensaje'         => "El docente {$docenteNombre} ha solicitado incluir al alumno {$estudianteNombre} en {$cursoNombre} (Grupo {$grupo->codigo_grupo}). Motivo: {$solicitud->motivo}",
                'tipo'            => 'SOLICITUD_INCLUSION',
                'referencia_tipo' => 'solicitudes_inclusion',
                'referencia_id'   => $solicitud->id,
                'leido'           => false,
            ]);
        }

        return response()->json([
            'data'    => $solicitud->load(['grupoCurso.curso', 'estudiante.usuario', 'docente.usuario']),
            'message' => 'Solicitud de inclusión enviada exitosamente a Coordinación.',
        ], 201);
    }

    public function aprobar(Request $request, SolicitudInclusionGrupo $solicitud)
    {
        $user = auth()->user();

        if ($user->hasRole('COORDINADOR')) {
            $cursoUnidad = $solicitud->grupoCurso?->curso?->unidad_academica_id;
            if (!$user->unidad_academica_id || !$cursoUnidad || $cursoUnidad !== $user->unidad_academica_id) {
                abort(response()->json(['message' => 'Esta solicitud no pertenece a tu programa de estudios.'], 403));
            }
        }

        if ($solicitud->estado !== 'PENDIENTE') {
            abort(response()->json(['message' => 'Esta solicitud ya ha sido procesada previamente.'], 422));
        }

        // Crear la matrícula oficial
        $matricula = Matricula::firstOrCreate([
            'institucion_id' => $solicitud->institucion_id,
            'grupo_curso_id' => $solicitud->grupo_curso_id,
            'estudiante_id'  => $solicitud->estudiante_id,
        ], [
            'origen'          => 'INCLUSION_DOCENTE',
            'fecha_matricula' => now()->toDateString(),
            'estado'          => 'MATRICULADO',
        ]);

        if ($matricula->estado !== 'MATRICULADO') {
            $matricula->update(['estado' => 'MATRICULADO']);
        }

        $solicitud->update([
            'estado'           => 'APROBADO',
            'coordinador_id'   => $user->id,
            'fecha_resolucion' => now(),
        ]);

        $solicitud->load(['grupoCurso.curso', 'estudiante.usuario', 'docente.usuario']);

        $cursoNombre = $solicitud->grupoCurso?->curso?->nombre ?? 'Curso';
        $estudianteNombre = $solicitud->estudiante?->usuario?->nombre_completo ?? 'Estudiante';
        $grupoCodigo = $solicitud->grupoCurso?->codigo_grupo ?? 'A';

        // Notificar al Docente
        if ($solicitud->docente?->usuario_id) {
            Notificacion::create([
                'usuario_id'      => $solicitud->docente->usuario_id,
                'titulo'          => 'Solicitud de inclusión APROBADA',
                'mensaje'         => "Tu solicitud para incluir a {$estudianteNombre} en {$cursoNombre} (Grupo {$grupoCodigo}) fue aprobada por Coordinación. El alumno ya está matriculado en el aula virtual.",
                'tipo'            => 'SOLICITUD_INCLUSION_APROBADA',
                'referencia_tipo' => 'solicitudes_inclusion',
                'referencia_id'   => $solicitud->id,
                'leido'           => false,
            ]);
        }

        // Notificar al Estudiante
        if ($solicitud->estudiante?->usuario_id) {
            Notificacion::create([
                'usuario_id'      => $solicitud->estudiante->usuario_id,
                'titulo'          => 'Matrícula de curso asignada',
                'mensaje'         => "Has sido incluido oficialmente en el curso {$cursoNombre} (Grupo {$grupoCodigo}). Ya puedes acceder a tus clases y materiales.",
                'tipo'            => 'MATRICULA_ASIGNADA',
                'referencia_tipo' => 'grupos_curso',
                'referencia_id'   => $solicitud->grupo_curso_id,
                'leido'           => false,
            ]);
        }

        Audit::create([
            'institucion_id' => $solicitud->institucion_id,
            'usuario_id'     => $user->id,
            'accion'         => 'APROBAR_INCLUSION_GRUPO',
            'entidad_tipo'   => SolicitudInclusionGrupo::class,
            'entidad_id'     => $solicitud->id,
            'valores_nuevos' => [
                'grupo_curso_id' => $solicitud->grupo_curso_id,
                'estudiante_id'  => $solicitud->estudiante_id,
                'matricula_id'   => $matricula->id,
            ],
            'ip'             => $request->ip(),
        ]);

        return response()->json([
            'data'    => $solicitud,
            'message' => 'Solicitud aprobada y estudiante matriculado exitosamente en el grupo.',
        ]);
    }

    public function rechazar(Request $request, SolicitudInclusionGrupo $solicitud)
    {
        $user = auth()->user();

        if ($user->hasRole('COORDINADOR')) {
            $cursoUnidad = $solicitud->grupoCurso?->curso?->unidad_academica_id;
            if (!$user->unidad_academica_id || !$cursoUnidad || $cursoUnidad !== $user->unidad_academica_id) {
                abort(response()->json(['message' => 'Esta solicitud no pertenece a tu programa de estudios.'], 403));
            }
        }

        if ($solicitud->estado !== 'PENDIENTE') {
            abort(response()->json(['message' => 'Esta solicitud ya ha sido procesada previamente.'], 422));
        }

        $validated = $request->validate([
            'observaciones_coordinador' => 'required|string|min:3|max:1000',
        ], [
            'observaciones_coordinador.required' => 'Debes indicar el motivo del rechazo.',
            'observaciones_coordinador.min'      => 'El motivo debe tener al menos 3 caracteres.',
        ]);

        $solicitud->update([
            'estado'                    => 'RECHAZADO',
            'observaciones_coordinador' => $validated['observaciones_coordinador'],
            'coordinador_id'            => $user->id,
            'fecha_resolucion'          => now(),
        ]);

        $solicitud->load(['grupoCurso.curso', 'estudiante.usuario', 'docente.usuario']);

        $cursoNombre = $solicitud->grupoCurso?->curso?->nombre ?? 'Curso';
        $estudianteNombre = $solicitud->estudiante?->usuario?->nombre_completo ?? 'Estudiante';

        // Notificar al Docente
        if ($solicitud->docente?->usuario_id) {
            Notificacion::create([
                'usuario_id'      => $solicitud->docente->usuario_id,
                'titulo'          => 'Solicitud de inclusión RECHAZADA',
                'mensaje'         => "Tu solicitud para incluir a {$estudianteNombre} en {$cursoNombre} fue rechazada por Coordinación. Motivo: {$validated['observaciones_coordinador']}",
                'tipo'            => 'SOLICITUD_INCLUSION_RECHAZADA',
                'referencia_tipo' => 'solicitudes_inclusion',
                'referencia_id'   => $solicitud->id,
                'leido'           => false,
            ]);
        }

        Audit::create([
            'institucion_id' => $solicitud->institucion_id,
            'usuario_id'     => $user->id,
            'accion'         => 'RECHAZAR_INCLUSION_GRUPO',
            'entidad_tipo'   => SolicitudInclusionGrupo::class,
            'entidad_id'     => $solicitud->id,
            'valores_nuevos' => [
                'motivo_rechazo' => $validated['observaciones_coordinador'],
            ],
            'ip'             => $request->ip(),
        ]);

        return response()->json([
            'data'    => $solicitud,
            'message' => 'Solicitud rechazada.',
        ]);
    }

    public function destroy(SolicitudInclusionGrupo $solicitud)
    {
        $user = auth()->user();

        if ($user->hasRole('DOCENTE')) {
            if ($solicitud->docente_id !== $user->docente?->id) {
                abort(response()->json(['message' => 'No puedes cancelar una solicitud de otro docente.'], 403));
            }
        }

        if ($solicitud->estado !== 'PENDIENTE') {
            abort(response()->json(['message' => 'Solo se pueden cancelar solicitudes en estado PENDIENTE.'], 422));
        }

        $solicitud->update(['estado' => 'CANCELADO']);

        return response()->json([
            'message' => 'Solicitud cancelada exitosamente.',
        ]);
    }
}
