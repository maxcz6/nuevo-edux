<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CursoController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $query = Curso::query();

        if (!$isSuperAdmin) {
            $query->where('institucion_id', $user->institucion_id);
            if ($user->hasRole('COORDINADOR')) {
                $query->where('unidad_academica_id', $user->unidad_academica_id ?? 0);
            } elseif ($request->has('unidad_academica_id')) {
                $query->where('unidad_academica_id', $request->unidad_academica_id);
            }
        } else {
            if ($request->filled('institucion_id')) {
                $query->where('institucion_id', $request->institucion_id);
            }
            if ($request->has('unidad_academica_id')) {
                $query->where('unidad_academica_id', $request->unidad_academica_id);
            }
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        $cursos = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $cursos->items(),
            'meta' => [
                'current_page' => $cursos->currentPage(),
                'last_page' => $cursos->lastPage(),
                'per_page' => $cursos->perPage(),
                'total' => $cursos->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $institucionId = $isSuperAdmin && $request->filled('institucion_id')
            ? $request->institucion_id
            : $user->institucion_id;

        $validated = $request->validate([
            'codigo' => [
                'required',
                'string',
                Rule::unique('cursos')->where(function ($query) use ($institucionId) {
                    return $query->where('institucion_id', $institucionId);
                })
            ],
            'nombre' => 'required|string|max:255',
            'creditos' => 'nullable|integer|min:0',
            'horas_semanales' => 'nullable|integer|min:0',
            'unidad_academica_id' => [
                'nullable', 'integer',
                Rule::exists('unidades_academicas', 'id')->where('institucion_id', $institucionId),
            ],
            'estado' => 'nullable|in:ACTIVO,INACTIVO',
            'institucion_id' => $isSuperAdmin ? 'nullable|exists:instituciones,id' : 'prohibited',
        ]);

        if ($user->hasRole('COORDINADOR') && $user->unidad_academica_id) {
            $validated['unidad_academica_id'] = $user->unidad_academica_id;
        }

        $validated['institucion_id'] = $institucionId;

        $curso = Curso::create($validated);

        return response()->json(['data' => $curso, 'message' => 'OK'], 201);
    }

    public function show($id)
    {
        $user = auth()->user();
        $query = Curso::query();
        if (!$user->hasRole('SUPERADMIN')) {
            $query->where('institucion_id', $user->institucion_id);
            if ($user->hasRole('COORDINADOR')) {
                $query->where('unidad_academica_id', $user->unidad_academica_id ?? 0);
            }
        }
        $curso = $query->findOrFail($id);
        return response()->json(['data' => $curso, 'message' => 'OK']);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $query = Curso::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', $user->institucion_id);
            if ($user->hasRole('COORDINADOR')) {
                $query->where('unidad_academica_id', $user->unidad_academica_id ?? 0);
            }
        }
        $curso = $query->findOrFail($id);
        $institucionId = $curso->institucion_id;

        $validated = $request->validate([
            'codigo' => [
                'sometimes',
                'string',
                Rule::unique('cursos')->where(function ($query) use ($institucionId) {
                    return $query->where('institucion_id', $institucionId);
                })->ignore($curso->id)
            ],
            'nombre' => 'sometimes|string|max:255',
            'creditos' => 'nullable|integer|min:0',
            'horas_semanales' => 'nullable|integer|min:0',
            'unidad_academica_id' => [
                'nullable', 'integer',
                Rule::exists('unidades_academicas', 'id')->where('institucion_id', $institucionId),
            ],
            'estado' => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        if ($user->hasRole('COORDINADOR') && $user->unidad_academica_id) {
            $validated['unidad_academica_id'] = $user->unidad_academica_id;
        }

        $curso->update($validated);

        return response()->json(['data' => $curso, 'message' => 'OK']);
    }

    public function destroy(Request $request, $id)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $query = Curso::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', $user->institucion_id);
            if ($user->hasRole('COORDINADOR')) {
                $query->where('unidad_academica_id', $user->unidad_academica_id ?? 0);
            }
        }
        $curso = $query->findOrFail($id);

        $forceDelete = $request->boolean('force') || $request->input('action') === 'eliminar';

        // ADMINISTRADOR solo puede desactivar, nunca eliminar permanentemente
        if ($forceDelete && !$isSuperAdmin) {
            return response()->json([
                'message' => 'El rol ADMINISTRADOR solo puede desactivar registros, no eliminarlos permanentemente.',
                'error'   => 'forbidden_action'
            ], 403);
        }

        if ($forceDelete && $isSuperAdmin) {
            if ($curso->gruposCurso()->exists()) {
                return response()->json(['message' => 'No se puede eliminar un curso con grupos académicos vinculados.'], 400);
            }
            $curso->delete();
            return response()->json(['message' => 'Curso eliminado definitivamente.']);
        }

        // Desactivación
        $curso->update(['estado' => 'INACTIVO']);

        return response()->json(['message' => 'Curso desactivado exitosamente.']);
    }
}
