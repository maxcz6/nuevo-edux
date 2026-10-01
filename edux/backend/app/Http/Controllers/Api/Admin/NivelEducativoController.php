<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NivelEducativo;
use Illuminate\Http\Request;

class NivelEducativoController extends Controller
{
    public function index(Request $request)
    {
        $niveles = NivelEducativo::where('institucion_id', auth()->user()->institucion_id)
            ->orderBy('orden', 'asc')
            ->get();

        return response()->json([
            'data'    => $niveles,
            'message' => 'OK'
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:20',
            'nombre' => 'required|string|max:100',
            'orden'  => 'nullable|integer|min:0',
            'estado' => 'nullable|in:ACTIVO,INACTIVO',
        ]);

        $validated['institucion_id'] = auth()->user()->institucion_id;
        $validated['orden']          = $validated['orden'] ?? 0;

        $nivel = NivelEducativo::create($validated);

        return response()->json([
            'data'    => $nivel,
            'message' => 'Nivel educativo creado exitosamente.'
        ], 201);
    }

    public function show($id)
    {
        $nivel = NivelEducativo::where('institucion_id', auth()->user()->institucion_id)->findOrFail($id);
        return response()->json(['data' => $nivel, 'message' => 'OK']);
    }

    public function update(Request $request, $id)
    {
        $nivel = NivelEducativo::where('institucion_id', auth()->user()->institucion_id)->findOrFail($id);

        $validated = $request->validate([
            'codigo' => 'sometimes|string|max:20',
            'nombre' => 'sometimes|string|max:100',
            'orden'  => 'nullable|integer|min:0',
            'estado' => 'sometimes|in:ACTIVO,INACTIVO',
        ]);

        $nivel->update($validated);

        return response()->json([
            'data'    => $nivel,
            'message' => 'Nivel educativo actualizado exitosamente.'
        ]);
    }

    public function destroy($id)
    {
        $nivel = NivelEducativo::where('institucion_id', auth()->user()->institucion_id)->findOrFail($id);
        $nivel->delete();

        return response()->json(['message' => 'Nivel educativo eliminado exitosamente.']);
    }
}
