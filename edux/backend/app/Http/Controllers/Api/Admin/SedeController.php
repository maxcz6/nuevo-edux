<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sede;
use Illuminate\Http\Request;

class SedeController extends Controller
{
    public function index(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = Sede::query()->orderBy('nombre', 'asc');

        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        } elseif ($request->filled('institucion_id')) {
            $query->where('institucion_id', $request->institucion_id);
        }

        $sedes = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $sedes->items(),
            'meta' => [
                'current_page' => $sedes->currentPage(),
                'last_page'    => $sedes->lastPage(),
                'per_page'     => $sedes->perPage(),
                'total'        => $sedes->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $validated = $request->validate([
            'nombre'         => 'required|string|max:150',
            'direccion'      => 'nullable|string|max:250',
            'estado'         => 'nullable|in:ACTIVO,INACTIVO',
            'institucion_id' => $isSuperAdmin ? 'nullable|exists:instituciones,id' : 'prohibited',
        ]);

        $validated['institucion_id'] = $isSuperAdmin && $request->filled('institucion_id')
            ? $request->institucion_id
            : auth()->user()->institucion_id;
        $validated['estado'] = $validated['estado'] ?? 'ACTIVO';

        $sede = Sede::create($validated);

        return response()->json([
            'data'    => $sede,
            'message' => 'Sede creada exitosamente.'
        ], 201);
    }

    public function show($id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = Sede::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $sede = $query->findOrFail($id);
        return response()->json(['data' => $sede, 'message' => 'OK']);
    }

    public function update(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = Sede::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $sede = $query->findOrFail($id);

        $validated = $request->validate([
            'nombre'         => 'sometimes|string|max:150',
            'direccion'      => 'nullable|string|max:250',
            'estado'         => 'sometimes|in:ACTIVO,INACTIVO',
            'institucion_id' => $isSuperAdmin ? 'sometimes|exists:instituciones,id' : 'prohibited',
        ]);

        $sede->update($validated);

        return response()->json([
            'data'    => $sede,
            'message' => 'Sede actualizada exitosamente.'
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = Sede::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $sede = $query->findOrFail($id);

        $forceDelete = $request->boolean('force') || $request->input('action') === 'eliminar';

        // ADMINISTRADOR solo puede desactivar
        if ($forceDelete && !$isSuperAdmin) {
            return response()->json([
                'message' => 'El rol ADMINISTRADOR solo puede desactivar registros, no eliminarlos permanentemente.',
                'error'   => 'forbidden_action'
            ], 403);
        }

        if ($forceDelete && $isSuperAdmin) {
            $sede->delete();
            return response()->json(['message' => 'Sede eliminada definitivamente.']);
        }

        $sede->update(['estado' => 'INACTIVO']);
        return response()->json(['message' => 'Sede desactivada exitosamente.']);
    }
}
