<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ambiente;
use App\Models\HorarioGrupo;
use Illuminate\Http\Request;

class AmbienteController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Ambiente::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->withCount('horarios')
            ->orderBy('codigo', 'asc');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'ILIKE', "%{$search}%")
                  ->orWhere('nombre', 'ILIKE', "%{$search}%")
                  ->orWhere('pabellon', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->has('page') || $request->has('per_page')) {
            $perPage = min((int) $request->input('per_page', 25), 100);
            $paginated = $query->paginate($perPage);
            return response()->json([
                'data' => $paginated->items(),
                'meta' => [
                    'total' => $paginated->total(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                ],
                'message' => 'OK'
            ]);
        }

        return response()->json([
            'data' => $query->limit(100)->get(),
            'message' => 'OK'
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'codigo' => 'required|string|max:30',
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|in:AULA,TALLER,LABORATORIO,CAMPO',
            'pabellon' => 'nullable|string|max:50',
            'capacidad' => 'required|integer|min:1',
            'estado' => 'nullable|in:ACTIVO,INACTIVO,MANTENIMIENTO'
        ]);

        $validated['institucion_id'] = $user->institucion_id;
        $ambiente = Ambiente::create($validated);

        return response()->json([
            'data' => $ambiente,
            'message' => 'Ambiente registrado exitosamente.'
        ], 201);
    }

    public function show($id)
    {
        $user = auth()->user();
        $ambiente = Ambiente::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->with(['horarios.grupoCurso.curso', 'horarios.grupoCurso.docente.usuario'])
            ->findOrFail($id);

        return response()->json([
            'data' => $ambiente,
            'message' => 'OK'
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $ambiente = Ambiente::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:100',
            'tipo' => 'sometimes|required|in:AULA,TALLER,LABORATORIO,CAMPO',
            'pabellon' => 'nullable|string|max:50',
            'capacidad' => 'sometimes|required|integer|min:1',
            'estado' => 'sometimes|required|in:ACTIVO,INACTIVO,MANTENIMIENTO'
        ]);

        $ambiente->update($validated);

        return response()->json([
            'data' => $ambiente,
            'message' => 'Ambiente actualizado.'
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = auth()->user();
        $query = Ambiente::query();
        if (!$user->hasRole('SUPERADMIN')) {
            $query->where('institucion_id', $user->institucion_id);
        }
        $ambiente = $query->findOrFail($id);

        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $forceDelete  = $request->boolean('force') || $request->input('action') === 'eliminar';

        if ($forceDelete && !$isSuperAdmin) {
            return response()->json([
                'message' => 'Solo el SUPERADMIN puede eliminar permanentemente registros.',
                'error'   => 'forbidden_action',
            ], 403);
        }

        if ($forceDelete && $isSuperAdmin) {
            if ($ambiente->horarios()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar permanentemente el ambiente porque tiene horarios de clases vinculados. Desactívelo en su lugar.',
                    'error'   => 'integrity_violation',
                ], 422);
            }
            $ambiente->delete();
            return response()->json(['message' => 'Ambiente eliminado permanentemente.'], 200);
        }

        // Por defecto: desactivar
        $ambiente->update(['estado' => 'INACTIVO']);
        return response()->json([
            'message' => 'Ambiente desactivado correctamente.',
            'data'    => $ambiente,
        ], 200);
    }
}
