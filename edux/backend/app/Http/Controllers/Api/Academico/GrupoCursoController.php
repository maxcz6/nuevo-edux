<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\GrupoCurso;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\PeriodoAcademico;
use App\Models\Seccion;
use App\Models\Audit;
use Illuminate\Http\Request;

class GrupoCursoController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = GrupoCurso::with(['curso', 'docente.usuario', 'periodo'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id));

        if ($user->hasRole('COORDINADOR')) {
            $query->whereHas('curso', fn ($q) => $q->where('unidad_academica_id', $user->unidad_academica_id ?? 0));
        }

        $query->when($request->periodo_id, fn ($q, $v) => $q->where('periodo_id', $v))
            ->when($request->docente_id, fn ($q, $v) => $q->where('docente_id', $v))
            ->when($request->estado, fn ($q, $v) => $q->where('estado', $v))
            ->when($request->curso_id, fn ($q, $v) => $q->where('curso_id', $v));

        $grupos = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data'    => $grupos->items(),
            'meta'    => [
                'current_page' => $grupos->currentPage(),
                'last_page'    => $grupos->lastPage(),
                'per_page'     => $grupos->perPage(),
                'total'        => $grupos->total(),
            ],
            'message' => 'OK',
        ]);
    }

    /**
     * Grupos del usuario autenticado:
     * - Docente: sus grupos asignados.
     * - Estudiante: grupos en los que está matriculado.
     * - Coordinador: grupos de su programa de estudio.
     * - Admin/Superadmin: todos los grupos.
     */
    public function misGrupos(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('DOCENTE') && $user->docente) {
            $grupos = GrupoCurso::with(['curso', 'periodo'])
                ->where('docente_id', $user->docente->id)
                ->where('estado', 'ACTIVO')
                ->get();
        } elseif ($user->hasRole('ESTUDIANTE') && $user->estudiante) {
            $grupos = GrupoCurso::with(['curso', 'periodo', 'docente.usuario'])
                ->whereHas('matriculas', function ($q) use ($user) {
                    $q->where('estudiante_id', $user->estudiante->id)
                      ->where('estado', 'MATRICULADO');
                })
                ->where('estado', 'ACTIVO')
                ->get();
        } elseif ($user->hasRole('COORDINADOR')) {
            $grupos = GrupoCurso::with(['curso', 'periodo', 'docente.usuario'])
                ->whereHas('curso', fn ($q) => $q->where('unidad_academica_id', $user->unidad_academica_id ?? 0))
                ->where('estado', 'ACTIVO')
                ->get();
        } else {
            $grupos = GrupoCurso::with(['curso', 'periodo', 'docente.usuario'])
                ->where('estado', 'ACTIVO')
                ->get();
        }

        return response()->json(['data' => $grupos, 'message' => 'OK']);
    }

    private function ensureCoordinadorBelongsToGrupo(GrupoCurso $grupo, $user): void
    {
        if ($user->hasRole('COORDINADOR')) {
            $cursoUnidad = $grupo->curso?->unidad_academica_id;
            if (!$user->unidad_academica_id || !$cursoUnidad || $cursoUnidad !== $user->unidad_academica_id) {
                abort(response()->json(['message' => 'Este grupo no pertenece a tu programa de estudios.', 'error' => 'forbidden'], 403));
            }
        }
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $institucionId = $user->institucion_id;

        $validated = $request->validate([
            'curso_id'    => [
                'required', 'integer',
                function ($attr, $val, $fail) use ($institucionId) {
                    if (!Curso::withoutGlobalScopes()->where('id', $val)->where('institucion_id', $institucionId)->exists()) {
                        $fail('El curso no pertenece a tu institución.');
                    }
                },
            ],
            'periodo_id'  => [
                'required', 'integer',
                function ($attr, $val, $fail) use ($institucionId) {
                    $periodo = PeriodoAcademico::withoutGlobalScopes()->where('id', $val)->where('institucion_id', $institucionId)->first();
                    if (!$periodo) {
                        $fail('El período no pertenece a tu institución.');
                    } elseif ($periodo->estado === 'FINALIZADO') {
                        $fail('No se puede crear un grupo en un período finalizado.');
                    }
                },
            ],
            'docente_id'  => [
                'required', 'integer',
                function ($attr, $val, $fail) use ($institucionId) {
                    if (!Docente::withoutGlobalScopes()->where('id', $val)->where('institucion_id', $institucionId)->exists()) {
                        $fail('El docente no pertenece a tu institución.');
                    }
                },
            ],
            'seccion_id'  => [
                'nullable', 'integer',
                function ($attr, $val, $fail) use ($institucionId) {
                    if ($val && !Seccion::withoutGlobalScopes()->where('id', $val)->where('institucion_id', $institucionId)->exists()) {
                        $fail('La sección no pertenece a tu institución.');
                    }
                },
            ],
            'codigo_grupo' => 'nullable|string|max:30',
            'turno'       => 'nullable|in:MANANA,TARDE,NOCHE,VIRTUAL',
            'aula'        => 'nullable|string|max:50',
            'cupo'        => 'nullable|integer|min:1|max:500',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'   => 'nullable|date|after_or_equal:fecha_inicio',
        ]);

        if ($user->hasRole('COORDINADOR') && $user->unidad_academica_id) {
            $cursoPertenece = Curso::where('id', $validated['curso_id'])
                ->where('unidad_academica_id', $user->unidad_academica_id)
                ->exists();
            if (!$cursoPertenece) {
                abort(response()->json(['message' => 'No puedes crear grupos para cursos fuera de tu programa.'], 403));
            }
        }

        $validated['institucion_id'] = $institucionId;
        $validated['estado']         = 'ACTIVO';

        $grupo = GrupoCurso::create($validated);

        return response()->json([
            'data'    => $grupo->load(['curso', 'docente.usuario', 'periodo']),
            'message' => 'Grupo de curso creado exitosamente.',
        ], 201);
    }

    public function show(GrupoCurso $grupo)
    {
        $this->ensureCoordinadorBelongsToGrupo($grupo, auth()->user());

        return response()->json([
            'data'    => $grupo->load(['curso', 'docente.usuario', 'periodo', 'seccion']),
            'message' => 'OK',
        ]);
    }

    public function update(Request $request, GrupoCurso $grupo)
    {
        $user = auth()->user();
        $this->ensureCoordinadorBelongsToGrupo($grupo, $user);
        $institucionId = $user->institucion_id;

        $validated = $request->validate([
            'docente_id'  => [
                'sometimes', 'integer',
                function ($attr, $val, $fail) use ($institucionId) {
                    // Bug #4: Validar que el docente pertenece a la institución
                    if (!Docente::withoutGlobalScopes()->where('id', $val)->where('institucion_id', $institucionId)->exists()) {
                        $fail('El docente no pertenece a tu institución.');
                    }
                },
            ],
            'codigo_grupo' => 'sometimes|string|max:30',
            'turno'        => 'sometimes|in:MANANA,TARDE,NOCHE,VIRTUAL',
            'aula'         => 'nullable|string|max:50',
            'cupo'         => 'nullable|integer|min:1',
            'estado'       => 'sometimes|in:ACTIVO,INACTIVO,FINALIZADO',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date',
        ]);

        $grupo->update($validated);

        return response()->json([
            'data'    => $grupo->fresh()->load(['curso', 'docente.usuario', 'periodo']),
            'message' => 'Grupo de curso actualizado.',
        ]);
    }

    public function destroy(GrupoCurso $grupo)
    {
        $this->ensureCoordinadorBelongsToGrupo($grupo, auth()->user());

        $forceDelete = request()->boolean('force') || request()->input('action') === 'eliminar';
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');

        if ($forceDelete && !$isSuperAdmin) {
            return response()->json([
                'message' => 'El rol ADMINISTRADOR solo puede desactivar registros, no eliminarlos permanentemente.',
                'error'   => 'forbidden_action'
            ], 403);
        }

        // Verificar que no tiene matrículas activas
        if ($grupo->matriculas()->where('estado', 'MATRICULADO')->exists()) {
            return response()->json([
                'message' => 'No se puede desactivar ni eliminar un grupo con estudiantes matriculados.',
            ], 422);
        }

        if ($forceDelete && $isSuperAdmin) {
            $grupo->delete();
            return response()->json(['message' => 'Grupo eliminado definitivamente.']);
        }

        $grupo->update(['estado' => 'INACTIVO']);
        return response()->json(['message' => 'Grupo desactivado exitosamente.']);
    }

    /**
     * Cierra el acta del grupo; las notas quedan inmutables.
     * Bug #2: Solo el docente TITULAR del grupo puede cerrarla (además de ADMIN/COORD/SA).
     */
    public function cerrarActa(Request $request, GrupoCurso $grupo)
    {
        $user = auth()->user();
        $this->ensureCoordinadorBelongsToGrupo($grupo, $user);

        // Verificar propiedad del grupo para DOCENTE
        $isAdmin = $user->hasRole('SUPERADMIN') || $user->hasRole('ADMINISTRADOR') || $user->hasRole('COORDINADOR');
        if (!$isAdmin) {
            if (!($user->hasRole('DOCENTE') && $user->docente && $grupo->docente_id === $user->docente->id)) {
                return response()->json([
                    'message' => 'Solo el docente titular del grupo puede cerrar el acta.',
                    'error'   => 'forbidden',
                ], 403);
            }
        }

        if ($grupo->estado === 'FINALIZADO') {
            return response()->json(['message' => 'El acta ya se encuentra cerrada.'], 422);
        }

        $anterior = $grupo->only(['estado', 'cerrado_at', 'cerrado_por']);
        $grupo->update([
            'estado'      => 'FINALIZADO',
            'cerrado_at'  => now(),
            'cerrado_por' => $user->id,
        ]);

        Audit::create([
            'institucion_id'     => $grupo->institucion_id,
            'usuario_id'         => $user->id,
            'accion'             => 'ACTA_CERRADA',
            'entidad_tipo'       => GrupoCurso::class,
            'entidad_id'         => $grupo->id,
            'valores_anteriores' => $anterior,
            'valores_nuevos'     => $grupo->fresh()->only(['estado', 'cerrado_at', 'cerrado_por']),
            'ip'                 => $request->ip(),
        ]);

        return response()->json(['data' => $grupo->fresh(), 'message' => 'Acta cerrada; las notas ya no pueden modificarse.']);
    }
}
