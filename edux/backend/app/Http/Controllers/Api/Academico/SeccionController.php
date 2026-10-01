<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\Seccion;
use App\Models\Docente;
use App\Models\PeriodoAcademico;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeccionController extends Controller
{
    public function index(Request $request)
    {
        $secciones = Seccion::with(['periodo', 'tutorDocente.usuario'])
            ->when($request->periodo_id, fn($q, $v) => $q->where('periodo_id', $v))
            ->when($request->estado, fn($q, $v) => $q->where('estado', $v))
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $secciones->items(),
            'meta' => [
                'current_page' => $secciones->currentPage(),
                'last_page'    => $secciones->lastPage(),
                'per_page'     => $secciones->perPage(),
                'total'        => $secciones->total(),
            ],
            'message' => 'OK',
        ]);
    }

    public function store(Request $request)
    {
        $institucionId = auth()->user()->institucion_id;

        $validated = $request->validate([
            'periodo_id'         => [
                'required',
                function ($attr, $val, $fail) use ($institucionId) {
                    if (!PeriodoAcademico::where('id', $val)->where('institucion_id', $institucionId)->exists()) {
                        $fail('El período no pertenece a tu institución.');
                    }
                },
            ],
            'nombre'             => 'required|string|max:50',
            'grado'              => 'nullable|string|max:20',
            'unidad_academica_id' => [
                'nullable', 'integer',
                Rule::exists('unidades_academicas', 'id')->where('institucion_id', $institucionId),
            ],
            'tutor_docente_id'   => [
                'nullable',
                function ($attr, $val, $fail) use ($institucionId) {
                    if ($val && !Docente::where('id', $val)->where('institucion_id', $institucionId)->exists()) {
                        $fail('El docente tutor no pertenece a tu institución.');
                    }
                },
            ],
            'capacidad'          => 'nullable|integer|min:1|max:500',
        ]);

        $validated['institucion_id'] = $institucionId;
        $validated['estado'] = 'ACTIVO';

        $seccion = Seccion::create($validated);

        return response()->json([
            'data'    => $seccion->load(['periodo', 'tutorDocente.usuario']),
            'message' => 'Sección creada exitosamente.',
        ], 201);
    }

    public function show(Seccion $seccion)
    {
        return response()->json([
            'data'    => $seccion->load(['periodo', 'tutorDocente.usuario', 'gruposCurso.curso']),
            'message' => 'OK',
        ]);
    }

    public function update(Request $request, Seccion $seccion)
    {
        $institucionId = auth()->user()->institucion_id;

        if ($seccion->institucion_id !== $institucionId) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'nombre'   => 'sometimes|string|max:50',
            'grado'    => 'nullable|string|max:20',
            'capacidad' => 'nullable|integer|min:1',
            'estado'   => 'sometimes|in:ACTIVO,INACTIVO',
        ]);

        $seccion->update($validated);

        return response()->json(['data' => $seccion->fresh(), 'message' => 'Sección actualizada.']);
    }

    public function destroy(Request $request, Seccion $seccion)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        if (!$isSuperAdmin && $seccion->institucion_id !== auth()->user()->institucion_id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $forceDelete = $request->boolean('force') || $request->input('action') === 'eliminar';

        if ($forceDelete && !$isSuperAdmin) {
            return response()->json([
                'message' => 'El rol ADMINISTRADOR solo puede desactivar registros, no eliminarlos permanentemente.',
                'error'   => 'forbidden_action'
            ], 403);
        }

        if ($forceDelete && $isSuperAdmin) {
            $seccion->delete();
            return response()->json(['message' => 'Sección eliminada definitivamente.']);
        }

        $seccion->update(['estado' => 'INACTIVO']);

        return response()->json(['message' => 'Sección desactivada exitosamente.']);
    }
}
