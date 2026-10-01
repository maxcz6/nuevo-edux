<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnidadAcademica;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnidadAcademicaController extends Controller
{
    public function index(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = UnidadAcademica::with(['nivelEducativo', 'padre'])
            ->withCount(['docentes', 'estudiantes', 'cursos']);

        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        } elseif ($request->filled('institucion_id')) {
            $query->where('institucion_id', $request->institucion_id);
        }

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $perPage = $request->get('per_page', 50);
        $unidades = $query->orderBy('nombre', 'asc')->paginate($perPage);

        return response()->json([
            'data' => $unidades->items(),
            'meta' => [
                'current_page' => $unidades->currentPage(),
                'last_page'    => $unidades->lastPage(),
                'per_page'     => $unidades->perPage(),
                'total'        => $unidades->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $targetInstId = $isSuperAdmin && $request->filled('institucion_id')
            ? (int) $request->institucion_id
            : (int) auth()->user()->institucion_id;
        $validated = $request->validate([
            'nivel_educativo_id' => 'nullable|exists:niveles_educativos,id',
            'padre_id'           => [
                'nullable', 'integer',
                Rule::exists('unidades_academicas', 'id')->where('institucion_id', $targetInstId),
            ],
            'codigo'             => [
                'required', 'string', 'max:30',
                Rule::unique('unidades_academicas', 'codigo')
                    ->where(fn ($query) => $query->where('institucion_id', $targetInstId)),
            ],
            'nombre'             => 'required|string|max:200',
            'tipo'               => 'required|in:FACULTAD,PROGRAMA_ESTUDIOS,DEPARTAMENTO_ACADEMICO,NIVEL_ESCOLAR',
            'estado'             => 'nullable|in:ACTIVO,INACTIVO',
            'institucion_id'     => $isSuperAdmin ? 'nullable|exists:instituciones,id' : 'prohibited',
        ]);

        $validated['institucion_id'] = $targetInstId;
        $validated['estado'] = $validated['estado'] ?? 'ACTIVO';

        $unidad = UnidadAcademica::create($validated);

        return response()->json([
            'data'    => $unidad->load(['nivelEducativo', 'padre']),
            'message' => 'Unidad académica creada exitosamente.'
        ], 201);
    }

    public function show($id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = UnidadAcademica::with(['nivelEducativo', 'padre', 'hijos']);
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $unidad = $query->findOrFail($id);

        return response()->json(['data' => $unidad, 'message' => 'OK']);
    }

    public function update(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = UnidadAcademica::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $unidad = $query->findOrFail($id);
        $targetInstId = $isSuperAdmin && $request->filled('institucion_id')
            ? (int) $request->institucion_id
            : (int) $unidad->institucion_id;

        $validated = $request->validate([
            'nivel_educativo_id' => 'nullable|exists:niveles_educativos,id',
            'padre_id'           => [
                'nullable', 'integer',
                Rule::exists('unidades_academicas', 'id')->where('institucion_id', $targetInstId),
            ],
            'codigo'             => [
                'sometimes', 'string', 'max:30',
                Rule::unique('unidades_academicas', 'codigo')
                    ->where(fn ($query) => $query->where('institucion_id', $targetInstId))
                    ->ignore($unidad->id),
            ],
            'nombre'             => 'sometimes|string|max:200',
            'tipo'               => 'sometimes|in:FACULTAD,PROGRAMA_ESTUDIOS,DEPARTAMENTO_ACADEMICO,NIVEL_ESCOLAR',
            'estado'             => 'sometimes|in:ACTIVO,INACTIVO',
            'institucion_id'     => $isSuperAdmin ? 'sometimes|exists:instituciones,id' : 'prohibited',
        ]);

        $unidad->update($validated);

        return response()->json([
            'data'    => $unidad->load(['nivelEducativo', 'padre']),
            'message' => 'Unidad académica actualizada exitosamente.'
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = UnidadAcademica::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $unidad = $query->findOrFail($id);

        $forceDelete = $request->boolean('force') || $request->input('action') === 'eliminar';

        // ADMIN solo puede desactivar, nunca eliminar permanentemente
        if ($forceDelete && !$isSuperAdmin) {
            return response()->json([
                'message' => 'El rol ADMINISTRADOR solo puede desactivar registros, no eliminarlos permanentemente.',
                'error'   => 'forbidden_action'
            ], 403);
        }

        if ($forceDelete && $isSuperAdmin) {
            if ($unidad->cursos()->exists() || $unidad->hijos()->exists()) {
                return response()->json(['message' => 'No se puede eliminar una unidad académica con cursos o dependencias vinculadas.'], 400);
            }
            $unidad->delete();
            return response()->json(['message' => 'Unidad académica eliminada definitivamente.']);
        }

        // Desactivación
        $unidad->update(['estado' => 'INACTIVO']);

        return response()->json(['message' => 'Unidad académica desactivada exitosamente.']);
    }
}
