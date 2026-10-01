<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TramiteEstudiante;
use App\Models\Estudiante;
use App\Models\EstudiantePrograma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TramiteEstudianteController extends Controller
{
    // ── Helpers de validación y autorización ──────────────────────────────────

    /**
     * Verifica que el estudiante_id pertenezca a la institución del usuario autenticado.
     */
    private function ensureEstudianteDeInstitucion(int $estudianteId, ?int $institucionId = null): Estudiante
    {
        $user = auth()->user();
        $institucionId ??= $user->institucion_id;
        $est = Estudiante::withoutGlobalScopes()
            ->where('id', $estudianteId)
            ->when($institucionId, fn ($query) => $query->where('institucion_id', $institucionId))
            ->first();

        if (!$est) {
            abort(response()->json([
                'message' => 'El estudiante no pertenece a tu institución.',
                'error'   => 'forbidden',
            ], 403));
        }
        return $est;
    }

    /**
     * Valida la autorización según el rol del usuario autenticado para acceder/modificar un trámite.
     */
    private function authorizeRoleAccess(int $estudianteId): void
    {
        $user = auth()->user();

        if ($user->hasRole('DOCENTE')) {
            abort(response()->json([
                'message' => 'Los docentes no tienen acceso a trámites académicos de estudiantes.',
                'error'   => 'forbidden',
            ], 403));
        }

        if ($user->hasRole('ESTUDIANTE')) {
            if (!$user->estudiante || $user->estudiante->id !== $estudianteId) {
                abort(response()->json([
                    'message' => 'Solo puedes gestionar tus propios trámites.',
                    'error'   => 'forbidden',
                ], 403));
            }
        } elseif ($user->hasRole('APODERADO')) {
            $hijosIds = $user->apoderado ? $user->apoderado->estudiantes()->pluck('estudiantes.id')->toArray() : [];
            if (!in_array($estudianteId, $hijosIds)) {
                abort(response()->json([
                    'message' => 'Solo puedes gestionar trámites de tus hijos vinculados.',
                    'error'   => 'forbidden',
                ], 403));
            }
        } elseif ($user->hasRole('COORDINADOR')) {
            if (!$user->unidad_academica_id) {
                abort(response()->json([
                    'message' => 'El coordinador no tiene un programa de estudios asignado.',
                    'error'   => 'forbidden',
                ], 403));
            }
            $est = Estudiante::withoutGlobalScopes()->find($estudianteId);
            if (!$est || $est->unidad_academica_id !== $user->unidad_academica_id) {
                abort(response()->json([
                    'message' => 'Solo puedes gestionar trámites de estudiantes de tu programa.',
                    'error'   => 'forbidden',
                ], 403));
            }
        }
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = auth()->user();

        // Docentes no tienen acceso a trámites
        if ($user->hasRole('DOCENTE')) {
            abort(response()->json([
                'message' => 'Los docentes no tienen acceso a trámites académicos.',
                'error'   => 'forbidden',
            ], 403));
        }

        $query = TramiteEstudiante::with(['estudiante.usuario', 'estudiante.programa', 'programaOrigen', 'programaDestino'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->orderBy('created_at', 'desc');

        // Estudiante sólo ve sus propios trámites
        if ($user->hasRole('ESTUDIANTE')) {
            $estId = $user->estudiante?->id ?? 0;
            $query->where('estudiante_id', $estId);
        } elseif ($user->hasRole('APODERADO')) {
            // Apoderado sólo ve trámites de sus hijos vinculados
            $hijosIds = $user->apoderado ? $user->apoderado->estudiantes()->pluck('estudiantes.id')->toArray() : [];
            $query->whereIn('estudiante_id', $hijosIds);
        } elseif ($user->hasRole('COORDINADOR')) {
            // Coordinador sólo ve trámites de estudiantes de su programa
            if (!$user->unidad_academica_id) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('estudiante', function ($eq) use ($user) {
                    $eq->where('unidad_academica_id', $user->unidad_academica_id);
                });
            }
        } elseif ($request->filled('estudiante_id')) {
            // Administrador puede filtrar por estudiante
            $query->where('estudiante_id', $request->input('estudiante_id'));
        }

        if ($request->filled('tipo_tramite')) {
            $query->where('tipo_tramite', $request->input('tipo_tramite'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $tramites = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $tramites->items(),
            'meta' => [
                'current_page' => $tramites->currentPage(),
                'last_page'    => $tramites->lastPage(),
                'per_page'     => $tramites->perPage(),
                'total'        => $tramites->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->hasRole('DOCENTE')) {
            abort(response()->json([
                'message' => 'Los docentes no pueden registrar trámites académicos de estudiantes.',
                'error'   => 'forbidden',
            ], 403));
        }

        // Si es ESTUDIANTE, forzar su propio ID
        if ($user->hasRole('ESTUDIANTE')) {
            if (!$user->estudiante) {
                abort(response()->json([
                    'message' => 'Tu usuario no tiene un perfil de estudiante vinculado.',
                    'error'   => 'forbidden',
                ], 403));
            }
            $request->merge(['estudiante_id' => $user->estudiante->id]);
        }

        if ($user->hasRole('COORDINADOR')) {
            if (!$user->unidad_academica_id) {
                abort(response()->json([
                    'message' => 'El coordinador no tiene un programa de estudios asignado.',
                    'error'   => 'forbidden',
                ], 403));
            }
            $est = Estudiante::withoutGlobalScopes()->find($request->input('estudiante_id'));
            if (!$est || $est->unidad_academica_id !== $user->unidad_academica_id) {
                abort(response()->json([
                    'message' => 'Solo puedes registrar trámites para estudiantes de tu programa.',
                    'error'   => 'forbidden',
                ], 403));
            }
        }

        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $instId = $isSuperAdmin && $request->filled('institucion_id')
            ? $request->integer('institucion_id')
            : $user->institucion_id;

        $validated = $request->validate([
            'institucion_id'       => $isSuperAdmin
                ? (($user->institucion_id ? 'nullable' : 'required') . '|integer|exists:instituciones,id')
                : 'prohibited',
            'estudiante_id'        => 'required|integer',
            'tipo_tramite'         => 'required|in:RESERVA_MATRICULA,REINCORPORACION,TRASLADO_INTERNO,TRASLADO_EXTERNO,CONVALIDACION,RECTIFICACION',
            'programa_origen_id'   => ['nullable', 'integer', Rule::exists('unidades_academicas', 'id')->where('institucion_id', $instId)],
            'programa_destino_id'  => ['nullable', 'integer', Rule::exists('unidades_academicas', 'id')->where('institucion_id', $instId)],
            'motivo'               => 'required|string|min:10',
            'documento_sustento'   => 'nullable|string|max:255',
        ]);

        $this->ensureEstudianteDeInstitucion($validated['estudiante_id'], $instId);
        $this->authorizeRoleAccess($validated['estudiante_id']);

        $tramite = TramiteEstudiante::create([
            'institucion_id'      => $instId,
            'estudiante_id'       => $validated['estudiante_id'],
            'tipo_tramite'        => $validated['tipo_tramite'],
            'programa_origen_id'  => $validated['programa_origen_id'] ?? null,
            'programa_destino_id' => $validated['programa_destino_id'] ?? null,
            'motivo'              => $validated['motivo'],
            'documento_sustento'  => $validated['documento_sustento'] ?? null,
            'estado'              => 'PENDIENTE',
            'fecha_solicitud'     => now()->toDateString(),
        ]);

        return response()->json([
            'data'    => $tramite->load(['estudiante.usuario', 'programaOrigen', 'programaDestino']),
            'message' => 'Trámite registrado con éxito. Estado: PENDIENTE.'
        ], 201);
    }

    public function show($id)
    {
        $user = auth()->user();

        if ($user->hasRole('DOCENTE')) {
            abort(response()->json([
                'message' => 'Los docentes no tienen acceso a trámites académicos.',
                'error'   => 'forbidden',
            ], 403));
        }

        $tramite = TramiteEstudiante::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->with(['estudiante.usuario', 'estudiante.programa', 'programaOrigen', 'programaDestino'])
            ->findOrFail($id);

        $this->authorizeRoleAccess($tramite->estudiante_id);

        return response()->json(['data' => $tramite, 'message' => 'OK']);
    }

    /**
     * Actualizar / procesar / aprobar / rechazar un trámite.
     * Solo SUPERADMIN, ADMINISTRADOR y COORDINADOR (de su programa).
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();

        // Estudiantes, apoderados y docentes no pueden aprobar ni rechazar trámites
        if ($user->hasRole('ESTUDIANTE') || $user->hasRole('APODERADO') || $user->hasRole('DOCENTE')) {
            return response()->json([
                'message' => 'No tienes permisos para procesar trámites. Solo el personal administrativo puede aprobar o rechazar trámites.',
                'error'   => 'forbidden',
            ], 403);
        }

        $tramite = TramiteEstudiante::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($id);

        // Si es coordinador, verificar que el estudiante pertenezca a su programa
        if ($user->hasRole('COORDINADOR')) {
            if (!$user->unidad_academica_id) {
                return response()->json([
                    'message' => 'El coordinador no tiene un programa de estudios asignado.',
                    'error'   => 'forbidden',
                ], 403);
            }
            $est = Estudiante::withoutGlobalScopes()->find($tramite->estudiante_id);
            if (!$est || $est->unidad_academica_id !== $user->unidad_academica_id) {
                return response()->json([
                    'message' => 'Solo puedes procesar trámites de estudiantes de tu programa.',
                    'error'   => 'forbidden',
                ], 403);
            }
        }

        $validated = $request->validate([
            'resolucion_directoral' => 'nullable|string|max:100',
            'fecha_resolucion'      => 'nullable|date',
            'observaciones'         => 'nullable|string',
            'estado'                => 'required|in:PENDIENTE,APROBADO,RECHAZADO',
        ]);

        return DB::transaction(function () use ($tramite, $validated) {
            $estadoAnteriorTramite = $tramite->estado;
            $tramite->update($validated);

            if ($validated['estado'] === 'RECHAZADO') {
                // Revertir los efectos de una aprobación previa del mismo trámite.
                if ($estadoAnteriorTramite === 'APROBADO') {
                    $estudiante = Estudiante::withoutGlobalScopes()->find($tramite->estudiante_id);
                    if ($estudiante) {
                        $this->revertirEfectosDeTrámite($tramite, $estudiante);
                    }
                }

                return response()->json([
                    'data'    => $tramite->fresh(['estudiante.usuario', 'programaOrigen', 'programaDestino']),
                    'message' => 'Trámite rechazado; se revirtieron los efectos académicos si los hubiera.'
                ]);
            }

            if ($validated['estado'] === 'APROBADO') {
                $estudiante = Estudiante::withoutGlobalScopes()->find($tramite->estudiante_id);

                if ($estudiante) {
                    switch ($tramite->tipo_tramite) {
                        case 'RESERVA_MATRICULA':
                            $estudiante->update(['estado' => 'RESERVA']);
                            break;

                        case 'REINCORPORACION':
                            $estudiante->update(['estado' => 'ACTIVO']);
                            break;

                        case 'TRASLADO_INTERNO':
                            if ($tramite->programa_destino_id) {
                                $estudiante->update(['unidad_academica_id' => $tramite->programa_destino_id]);
                                $plan = \App\Models\PlanEstudio::withoutGlobalScopes()
                                    ->where('programa_id', $tramite->programa_destino_id)
                                    ->first();
                                if ($plan) {
                                    EstudiantePrograma::updateOrCreate(
                                        [
                                            'estudiante_id' => $estudiante->id,
                                            'programa_id'   => $tramite->programa_destino_id,
                                        ],
                                        [
                                            'plan_estudio_id' => $plan->id,
                                            'fecha_ingreso'   => now()->toDateString(),
                                            'estado'          => 'ACTIVO'
                                        ]
                                    );
                                }
                            }
                            break;

                        case 'TRASLADO_EXTERNO':
                            // El estudiante se traslada a otra institución: egresa de la actual.
                            $estudiante->update(['estado' => 'RETIRADO']);
                            EstudiantePrograma::where('estudiante_id', $estudiante->id)
                                ->where('estado', 'ACTIVO')
                                ->update(['estado' => 'EGRESADO', 'fecha_egreso' => now()->toDateString()]);
                            break;

                        case 'CONVALIDACION':
                        case 'RECTIFICACION':
                            // Se registra con resolución directoral; no altera el estado del estudiante.
                            break;

                        default:
                            break;
                    }
                }
            }

            return response()->json([
                'data'    => $tramite->fresh(['estudiante.usuario', 'programaOrigen', 'programaDestino']),
                'message' => 'Trámite actualizado y procesado con éxito.'
            ]);
        });
    }

    /**
     * Eliminar / cancelar un trámite.
     */
    public function destroy($id)
    {
        $user    = auth()->user();
        $tramite = TramiteEstudiante::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($id);

        if ($user->hasRole('DOCENTE')) {
            return response()->json(['message' => 'Los docentes no tienen permisos sobre trámites.', 'error' => 'forbidden'], 403);
        }

        // Estudiantes y apoderados sólo pueden cancelar trámites propios en estado PENDIENTE
        if ($user->hasRole('ESTUDIANTE')) {
            if (!$user->estudiante || $tramite->estudiante_id !== $user->estudiante->id) {
                return response()->json(['message' => 'No tienes acceso a este trámite.', 'error' => 'forbidden'], 403);
            }
            if ($tramite->estado !== 'PENDIENTE') {
                return response()->json(['message' => 'Solo puedes cancelar trámites en estado PENDIENTE.'], 422);
            }
        } elseif ($user->hasRole('APODERADO')) {
            $hijosIds = $user->apoderado ? $user->apoderado->estudiantes()->pluck('estudiantes.id')->toArray() : [];
            if (!in_array($tramite->estudiante_id, $hijosIds)) {
                return response()->json(['message' => 'No tienes acceso a este trámite.', 'error' => 'forbidden'], 403);
            }
            if ($tramite->estado !== 'PENDIENTE') {
                return response()->json(['message' => 'Solo puedes cancelar trámites en estado PENDIENTE.'], 422);
            }
        } elseif ($user->hasRole('COORDINADOR')) {
            if (!$user->unidad_academica_id) {
                return response()->json(['message' => 'El coordinador no tiene un programa de estudios asignado.', 'error' => 'forbidden'], 403);
            }
            $est = Estudiante::withoutGlobalScopes()->find($tramite->estudiante_id);
            if (!$est || $est->unidad_academica_id !== $user->unidad_academica_id) {
                return response()->json(['message' => 'Solo puedes eliminar trámites de tu programa.', 'error' => 'forbidden'], 403);
            }
        }

        $tramite->delete();
        return response()->json(null, 204);
    }

    /**
     * Revierte los efectos académicos aplicados por una aprobación previa del trámite.
     */
    private function revertirEfectosDeTrámite(TramiteEstudiante $tramite, Estudiante $estudiante): void
    {
        switch ($tramite->tipo_tramite) {
            case 'RESERVA_MATRICULA':
                // Solo se revierte si el estado actual se debe a este trámite.
                if ($estudiante->estado === 'RESERVA') {
                    $estudiante->update(['estado' => 'ACTIVO']);
                }
                break;

            case 'TRASLADO_INTERNO':
                if ($tramite->programa_origen_id
                    && $estudiante->unidad_academica_id === $tramite->programa_destino_id) {
                    $estudiante->update(['unidad_academica_id' => $tramite->programa_origen_id]);
                }
                break;

            case 'TRASLADO_EXTERNO':
                if ($estudiante->estado === 'RETIRADO') {
                    $estudiante->update(['estado' => 'ACTIVO']);
                }
                EstudiantePrograma::where('estudiante_id', $estudiante->id)
                    ->where('estado', 'EGRESADO')
                    ->where('programa_id', $tramite->programa_origen_id)
                    ->update(['estado' => 'ACTIVO', 'fecha_egreso' => null]);
                break;
        }
    }
}
