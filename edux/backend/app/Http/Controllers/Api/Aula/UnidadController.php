<?php

namespace App\Http\Controllers\Api\Aula;

use App\Http\Controllers\Controller;
use App\Models\Unidad;
use Illuminate\Http\Request;

class UnidadController extends Controller
{
    use AuthorizesAulaAccess;

    public function index(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        $query = Unidad::where('grupo_curso_id', $grupoCurso->id);

        if ($user->hasRole('ESTUDIANTE')) {
            $query->where('estado', 'ACTIVO')
                  ->where(function ($q) {
                      $q->whereNull('visible_desde')
                        ->orWhere('visible_desde', '<=', now());
                  })
                  ->where(function ($q) {
                      $q->whereNull('visible_hasta')
                        ->orWhere('visible_hasta', '>=', now());
                  });
        }

        $unidades = $query
            ->with(['materiales' => function ($q) use ($user) {
                if ($user->hasRole('ESTUDIANTE')) {
                    $q->where('estado', 'ACTIVO')
                      ->where(function ($sq) {
                          $sq->whereNull('visible_desde')
                             ->orWhere('visible_desde', '<=', now());
                      })
                      ->where(function ($sq) {
                          $sq->whereNull('visible_hasta')
                             ->orWhere('visible_hasta', '>=', now());
                      });
                }
                $q->orderBy('created_at', 'desc');
            }, 'sesiones'])
            ->orderBy('orden')
            ->paginate($request->get('per_page', 50));

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

    public function store(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);

        $validated = $request->validate([
            'titulo'        => 'required|string|max:150',
            'descripcion'   => 'nullable|string',
            'orden'         => 'required|integer|min:1',
            'fecha_inicio'  => 'nullable|date',
            'fecha_fin'     => 'nullable|date',
            'visible_desde' => 'nullable|date',
            'visible_hasta' => 'nullable|date',
            'estado'        => 'nullable|in:ACTIVO,INACTIVO',
        ]);

        $validated['grupo_curso_id'] = $grupoCurso->id;
        $validated['estado']         = $validated['estado'] ?? 'ACTIVO';

        $unidad = Unidad::create($validated);
        $unidad->load(['materiales', 'sesiones']);

        return response()->json(['data' => $unidad, 'message' => 'Unidad creada exitosamente.'], 201);
    }

    public function show($grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        $query = Unidad::where('grupo_curso_id', $grupoCurso->id);

        if ($user->hasRole('ESTUDIANTE')) {
            $query->where('estado', 'ACTIVO')
                  ->where(function ($q) {
                      $q->whereNull('visible_desde')
                        ->orWhere('visible_desde', '<=', now());
                  })
                  ->where(function ($q) {
                      $q->whereNull('visible_hasta')
                        ->orWhere('visible_hasta', '>=', now());
                  });
        }

        $unidad = $query
            ->with(['materiales' => function ($q) use ($user) {
                if ($user->hasRole('ESTUDIANTE')) {
                    $q->where('estado', 'ACTIVO')
                      ->where(function ($sq) {
                          $sq->whereNull('visible_desde')
                             ->orWhere('visible_desde', '<=', now());
                      })
                      ->where(function ($sq) {
                          $sq->whereNull('visible_hasta')
                             ->orWhere('visible_hasta', '>=', now());
                      });
                }
                $q->orderBy('created_at', 'desc');
            }, 'sesiones'])
            ->findOrFail($id);

        return response()->json(['data' => $unidad, 'message' => 'OK']);
    }

    public function update(Request $request, $grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $unidad = Unidad::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        $validated = $request->validate([
            'titulo'        => 'sometimes|string|max:150',
            'descripcion'   => 'nullable|string',
            'orden'         => 'sometimes|integer|min:1',
            'fecha_inicio'  => 'nullable|date',
            'fecha_fin'     => 'nullable|date',
            'visible_desde' => 'nullable|date',
            'visible_hasta' => 'nullable|date',
            'estado'        => 'sometimes|in:ACTIVO,INACTIVO',
        ]);

        $unidad->update($validated);
        $unidad->load(['materiales', 'sesiones']);

        return response()->json(['data' => $unidad, 'message' => 'Unidad actualizada exitosamente.']);
    }

    public function destroy($grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $unidad = Unidad::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        if ($unidad->sesiones()->exists() || $unidad->materiales()->exists() || $unidad->tareas()->exists()) {
            return response()->json(['message' => 'No se puede eliminar una unidad con sesiones, materiales o tareas asociadas.'], 422);
        }

        $unidad->delete();
        return response()->json(null, 204);
    }
}
