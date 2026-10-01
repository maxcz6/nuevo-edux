<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\PeriodoAcademico;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeriodoController extends Controller
{
    public function index(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = PeriodoAcademico::query();

        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        } elseif ($request->filled('institucion_id')) {
            $query->where('institucion_id', $request->institucion_id);
        }

        $periodos = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $periodos->items(),
            'meta' => [
                'current_page' => $periodos->currentPage(),
                'last_page' => $periodos->lastPage(),
                'per_page' => $periodos->perPage(),
                'total' => $periodos->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $institucionId = $isSuperAdmin && $request->filled('institucion_id')
            ? $request->institucion_id
            : auth()->user()->institucion_id;

        $validated = $request->validate([
            'codigo' => [
                'required',
                'string',
                Rule::unique('periodos_academicos')->where(function ($query) use ($institucionId) {
                    return $query->where('institucion_id', $institucionId);
                })
            ],
            'nombre' => 'required|string|max:100',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'estado' => 'sometimes|in:ACTIVO,INACTIVO,CERRADO',
            'institucion_id' => $isSuperAdmin ? 'nullable|exists:instituciones,id' : 'prohibited',
        ]);

        $validated['institucion_id'] = $institucionId;
        $validated['estado'] = $validated['estado'] ?? 'ACTIVO';

        $periodo = PeriodoAcademico::create($validated);

        return response()->json(['data' => $periodo, 'message' => 'OK'], 201);
    }

    public function show($id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = PeriodoAcademico::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $periodo = $query->findOrFail($id);

        return response()->json(['data' => $periodo, 'message' => 'OK']);
    }

    public function update(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = PeriodoAcademico::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $periodo = $query->findOrFail($id);
        $institucionId = $periodo->institucion_id;

        $validated = $request->validate([
            'codigo' => [
                'sometimes',
                'string',
                Rule::unique('periodos_academicos')->where(function ($query) use ($institucionId) {
                    return $query->where('institucion_id', $institucionId);
                })->ignore($periodo->id)
            ],
            'nombre' => 'sometimes|string|max:100',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date|after:fecha_inicio',
            'estado' => 'sometimes|in:ACTIVO,INACTIVO,CERRADO'
        ]);

        $periodo->update($validated);

        return response()->json(['data' => $periodo, 'message' => 'OK']);
    }

    public function destroy(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = PeriodoAcademico::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $periodo = $query->findOrFail($id);

        $forceDelete = $request->boolean('force') || $request->input('action') === 'eliminar';

        // ADMINISTRADOR solo puede desactivar, nunca eliminar permanentemente
        if ($forceDelete && !$isSuperAdmin) {
            return response()->json([
                'message' => 'El rol ADMINISTRADOR solo puede desactivar registros, no eliminarlos permanentemente.',
                'error'   => 'forbidden_action'
            ], 403);
        }

        if ($forceDelete && $isSuperAdmin) {
            if ($periodo->gruposCurso()->exists()) {
                return response()->json(['message' => 'No se puede eliminar un periodo con grupos de curso asociados.'], 400);
            }
            $periodo->delete();
            return response()->json(['message' => 'Periodo eliminado definitivamente.']);
        }

        // Desactivación
        $periodo->update(['estado' => 'INACTIVO']);

        return response()->json(['message' => 'Periodo desactivado exitosamente.']);
    }
}
