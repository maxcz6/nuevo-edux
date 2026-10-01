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

        $unidades = Unidad::where('grupo_curso_id', $grupoCurso->id)
            ->orderBy('orden')
            ->paginate($request->get('per_page', 15));

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
            'titulo'      => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'orden'       => 'required|integer|min:1'
        ]);

        $validated['grupo_curso_id'] = $grupoCurso->id;
        $validated['estado']         = 'ACTIVO';

        $unidad = Unidad::create($validated);

        return response()->json(['data' => $unidad, 'message' => 'Unidad creada exitosamente.'], 201);
    }

    public function show($grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $unidad = Unidad::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        return response()->json(['data' => $unidad, 'message' => 'OK']);
    }

    public function update(Request $request, $grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $unidad = Unidad::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        $validated = $request->validate([
            'titulo'      => 'sometimes|string|max:150',
            'descripcion' => 'nullable|string',
            'orden'       => 'sometimes|integer|min:1',
            'estado'      => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        $unidad->update($validated);

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
