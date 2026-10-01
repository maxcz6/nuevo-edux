<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    public function index(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = Rol::query();

        if (!$isSuperAdmin) {
            $userInstId = auth()->user()->institucion_id;
            $query->where(function ($q) use ($userInstId) {
                $q->where('institucion_id', $userInstId)
                  ->orWhere('es_sistema', true);
            });
        } elseif ($request->filled('institucion_id')) {
            $query->where('institucion_id', $request->institucion_id);
        }

        $roles = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $roles->items(),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $validated = $request->validate([
            'nombre'         => 'required|string|max:100',
            'descripcion'    => 'nullable|string',
            'institucion_id' => $isSuperAdmin ? 'nullable|exists:instituciones,id' : 'prohibited',
        ]);

        $validated['institucion_id'] = $isSuperAdmin && $request->filled('institucion_id')
            ? $request->institucion_id
            : auth()->user()->institucion_id;
        $validated['es_sistema'] = false;
        $validated['estado'] = true;

        $rol = Rol::create($validated);

        return response()->json(['data' => $rol, 'message' => 'OK'], 201);
    }

    public function show($id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = Rol::with('permisos');

        if (!$isSuperAdmin) {
            $query->where(function($q) {
                $q->where('institucion_id', auth()->user()->institucion_id)
                  ->orWhere('es_sistema', true);
            });
        }

        $rol = $query->findOrFail($id);

        return response()->json(['data' => $rol, 'message' => 'OK']);
    }

    public function update(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = Rol::query();

        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id)
                  ->where('es_sistema', false);
        }

        $rol = $query->findOrFail($id);

        $validated = $request->validate([
            'nombre'      => 'sometimes|string|max:100',
            'descripcion' => 'nullable|string',
            'estado'      => 'sometimes|boolean'
        ]);

        $rol->update($validated);

        return response()->json(['data' => $rol, 'message' => 'OK']);
    }

    public function destroy(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = Rol::query();

        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id)
                  ->where('es_sistema', false);
        }

        $rol = $query->findOrFail($id);

        if ($rol->es_sistema) {
            return response()->json(['message' => 'No se pueden modificar ni eliminar roles del sistema.'], 400);
        }

        $forceDelete = $request->boolean('force') || $request->input('action') === 'eliminar';

        // ADMIN solo puede desactivar
        if ($forceDelete && !$isSuperAdmin) {
            return response()->json([
                'message' => 'El rol ADMINISTRADOR solo puede desactivar registros, no eliminarlos permanentemente.',
                'error'   => 'forbidden_action'
            ], 403);
        }

        if ($forceDelete && $isSuperAdmin) {
            if ($rol->usuarios()->exists()) {
                return response()->json(['message' => 'No se puede eliminar un rol con usuarios asociados.'], 400);
            }
            $rol->permisos()->detach();
            $rol->delete();
            return response()->json(['message' => 'Rol eliminado definitivamente.']);
        }

        // Desactivación
        $rol->update(['estado' => false]);
        return response()->json(['message' => 'Rol desactivado exitosamente.']);
    }

    public function assignPermissions(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = Rol::query();

        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id)
                  ->where('es_sistema', false);
        }

        $rol = $query->findOrFail($id);

        $validated = $request->validate([
            'permisos' => 'required|array',
            'permisos.*' => 'exists:permisos,id'
        ]);

        $rol->permisos()->sync($validated['permisos']);

        return response()->json(['data' => $rol->load('permisos'), 'message' => 'OK']);
    }
}
