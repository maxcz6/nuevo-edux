<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institucion;
use Illuminate\Http\Request;

class InstitucionController extends Controller
{
    public function index(Request $request)
    {
        // Requires SUPERADMIN
        $instituciones = Institucion::paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $instituciones->items(),
            'meta' => [
                'current_page' => $instituciones->currentPage(),
                'last_page' => $instituciones->lastPage(),
                'per_page' => $instituciones->perPage(),
                'total' => $instituciones->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo' => 'required|unique:instituciones,codigo',
            'nombre' => 'required|string|max:255',
            'tipo_institucion' => 'required|in:COLEGIO,ESCUELA,INSTITUTO,UNIVERSIDAD'
        ]);

        $institucion = Institucion::create($validated);

        return response()->json(['data' => $institucion, 'message' => 'OK'], 201);
    }

    public function show($id)
    {
        $institucion = Institucion::findOrFail($id);
        return response()->json(['data' => $institucion, 'message' => 'OK']);
    }

    public function update(Request $request, $id)
    {
        $institucion = Institucion::findOrFail($id);

        $validated = $request->validate([
            'codigo' => 'sometimes|unique:instituciones,codigo,'.$id,
            'nombre' => 'sometimes|string|max:255',
            'tipo_institucion' => 'sometimes|in:COLEGIO,ESCUELA,INSTITUTO,UNIVERSIDAD',
            'estado' => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        $institucion->update($validated);

        return response()->json(['data' => $institucion, 'message' => 'OK']);
    }

    public function destroy($id)
    {
        $institucion = Institucion::findOrFail($id);
        // Soft-check before delete
        if ($institucion->usuarios()->exists()) {
            return response()->json(['message' => 'Cannot delete institution with active users.'], 400);
        }
        $institucion->delete();
        return response()->json(null, 204);
    }
}
