<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanEstudio;
use App\Models\CicloPlan;
use App\Models\PlanCurso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlanEstudioController extends Controller
{
    public function index(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = PlanEstudio::with(['programa'])
            ->withCount(['planCursos', 'ciclos'])
            ->orderBy('nombre', 'asc');

        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        } elseif ($request->filled('institucion_id')) {
            $query->where('institucion_id', $request->institucion_id);
        }

        $planes = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $planes->items(),
            'meta' => [
                'current_page' => $planes->currentPage(),
                'last_page'    => $planes->lastPage(),
                'per_page'     => $planes->perPage(),
                'total'        => $planes->total(),
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
            'programa_id'        => ['required', 'integer', Rule::exists('unidades_academicas', 'id')->where('institucion_id', $institucionId)],
            'codigo'             => 'required|string|max:30',
            'nombre'             => 'required|string|max:150',
            'anio_inicio'        => 'nullable|integer|min:1950|max:2100',
            'fecha_vigencia'     => 'nullable|date',
            'fecha_fin_vigencia' => 'nullable|date|after_or_equal:fecha_vigencia',
            'total_ciclos'       => 'required|integer|min:1|max:15',
            'total_creditos'     => 'nullable|numeric|min:0',
            'estado'             => 'nullable|in:ACTIVO,INACTIVO',
            'institucion_id'     => $isSuperAdmin ? 'nullable|exists:instituciones,id' : 'prohibited',
        ]);

        $validated['institucion_id'] = $institucionId;

        $plan = DB::transaction(function () use ($validated) {
            $nuevoPlan = PlanEstudio::create($validated);

            // Crear automáticamente los ciclos correspondientes
            for ($i = 1; $i <= $validated['total_ciclos']; $i++) {
                CicloPlan::create([
                    'plan_estudio_id' => $nuevoPlan->id,
                    'numero'          => $i,
                    'nombre'          => "Ciclo {$i}",
                ]);
            }

            return $nuevoPlan;
        });

        return response()->json([
            'data'    => $plan->load('ciclos'),
            'message' => 'Plan de estudios creado exitosamente.'
        ], 201);
    }

    public function show($id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = PlanEstudio::with(['programa', 'ciclos.planCursos.curso']);
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $plan = $query->findOrFail($id);

        return response()->json(['data' => $plan, 'message' => 'OK']);
    }

    public function update(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = PlanEstudio::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $plan = $query->findOrFail($id);

        $validated = $request->validate([
            'programa_id'        => ['sometimes', 'integer', Rule::exists('unidades_academicas', 'id')->where('institucion_id', $plan->institucion_id)],
            'codigo'             => 'sometimes|string|max:30',
            'nombre'             => 'sometimes|string|max:150',
            'anio_inicio'        => 'nullable|integer|min:1950|max:2100',
            'fecha_vigencia'     => 'nullable|date',
            'fecha_fin_vigencia' => 'nullable|date',
            'total_creditos'     => 'nullable|numeric|min:0',
            'estado'             => 'sometimes|in:ACTIVO,INACTIVO',
            'institucion_id'     => $isSuperAdmin ? 'sometimes|exists:instituciones,id' : 'prohibited',
        ]);

        $plan->update($validated);

        return response()->json([
            'data'    => $plan->load('programa'),
            'message' => 'Plan de estudios actualizado exitosamente.'
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = PlanEstudio::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $plan = $query->findOrFail($id);

        $forceDelete = $request->boolean('force') || $request->input('action') === 'eliminar';

        // ADMINISTRADOR solo puede desactivar, nunca eliminar permanentemente
        if ($forceDelete && !$isSuperAdmin) {
            return response()->json([
                'message' => 'El rol ADMINISTRADOR solo puede desactivar registros, no eliminarlos permanentemente.',
                'error'   => 'forbidden_action'
            ], 403);
        }

        if ($forceDelete && $isSuperAdmin) {
            $plan->delete();
            return response()->json(['message' => 'Plan de estudios eliminado definitivamente.']);
        }

        // Desactivación
        $plan->update(['estado' => 'INACTIVO']);

        return response()->json(['message' => 'Plan de estudios desactivado exitosamente.']);
    }

    /**
     * Asignar un curso a un ciclo del plan curricular.
     */
    public function agregarCurso(Request $request, $id)
    {
        $plan = PlanEstudio::where('institucion_id', auth()->user()->institucion_id)->findOrFail($id);

        $validated = $request->validate([
            'curso_id'       => ['required', 'integer', Rule::exists('cursos', 'id')->where('institucion_id', $plan->institucion_id)],
            'ciclo_id'       => ['required', 'integer', Rule::exists('ciclos_plan', 'id')->where('plan_estudio_id', $plan->id)],
            'creditos'       => 'nullable|numeric|min:0',
            'horas_teoria'   => 'nullable|integer|min:0',
            'horas_practica' => 'nullable|integer|min:0',
            'tipo'           => 'nullable|in:OBLIGATORIO,ELECTIVO',
            'orden'          => 'nullable|integer|min:0',
        ]);

        $validated['plan_estudio_id'] = $plan->id;
        $validated['tipo']            = $validated['tipo'] ?? 'OBLIGATORIO';

        $planCurso = PlanCurso::create($validated);

        return response()->json([
            'data'    => $planCurso->load(['curso', 'ciclo']),
            'message' => 'Curso asignado al plan curricular exitosamente.'
        ], 201);
    }

    /**
     * Quitar un curso del plan curricular.
     */
    public function removerCurso($id, $planCursoId)
    {
        $plan = PlanEstudio::where('institucion_id', auth()->user()->institucion_id)->findOrFail($id);
        $planCurso = PlanCurso::where('plan_estudio_id', $plan->id)->findOrFail($planCursoId);

        $planCurso->delete();

        return response()->json(['message' => 'Curso removido del plan curricular exitosamente.']);
    }
}
