<?php

namespace App\Http\Controllers\Api\Aula;

use App\Http\Controllers\Controller;
use App\Models\Sesion;
use Illuminate\Http\Request;

class SesionController extends Controller
{
    use AuthorizesAulaAccess;

    public function index(Request $request, $unidad)
    {
        $unidadModel = $this->verifyUnidadAccess($unidad, false);

        $sesiones = Sesion::where('unidad_id', $unidadModel->id)
            ->orderBy('orden')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $sesiones->items(),
            'meta' => [
                'current_page' => $sesiones->currentPage(),
                'last_page'    => $sesiones->lastPage(),
                'per_page'     => $sesiones->perPage(),
                'total'        => $sesiones->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request, $unidad)
    {
        $unidadModel = $this->verifyUnidadAccess($unidad, true);

        $validated = $request->validate([
            'titulo'          => 'required|string|max:150',
            'descripcion'     => 'nullable|string',
            'fecha_sesion'    => 'nullable|date',
            'hora_inicio'     => 'nullable|date_format:H:i',
            'hora_fin'        => 'nullable|date_format:H:i',
            'enlace_virtual'  => 'nullable|string|max:255',
            'orden'           => 'required|integer|min:1'
        ]);

        $validated['unidad_id'] = $unidadModel->id;
        $validated['estado']    = 'ACTIVO';

        $sesion = Sesion::create($validated);

        return response()->json(['data' => $sesion, 'message' => 'Sesión creada exitosamente.'], 201);
    }

    public function show($unidad, $id)
    {
        $unidadModel = $this->verifyUnidadAccess($unidad, false);
        $sesion = Sesion::where('unidad_id', $unidadModel->id)->findOrFail($id);

        return response()->json(['data' => $sesion, 'message' => 'OK']);
    }

    public function update(Request $request, $unidad, $id)
    {
        $unidadModel = $this->verifyUnidadAccess($unidad, true);
        $sesion = Sesion::where('unidad_id', $unidadModel->id)->findOrFail($id);

        $validated = $request->validate([
            'titulo'          => 'sometimes|string|max:150',
            'descripcion'     => 'nullable|string',
            'fecha_sesion'    => 'nullable|date',
            'hora_inicio'     => 'nullable|date_format:H:i',
            'hora_fin'        => 'nullable|date_format:H:i',
            'enlace_virtual'  => 'nullable|string|max:255',
            'orden'           => 'sometimes|integer|min:1',
            'estado'          => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        $sesion->update($validated);

        return response()->json(['data' => $sesion, 'message' => 'Sesión actualizada exitosamente.']);
    }

    public function destroy($unidad, $id)
    {
        $unidadModel = $this->verifyUnidadAccess($unidad, true);
        $sesion = Sesion::where('unidad_id', $unidadModel->id)->findOrFail($id);

        if ($sesion->materiales()->exists()) {
            return response()->json(['message' => 'No se puede eliminar una sesión con materiales asociados.'], 422);
        }

        $sesion->delete();
        return response()->json(null, 204);
    }
}
