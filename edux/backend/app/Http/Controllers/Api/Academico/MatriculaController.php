<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\Matricula;
use App\Models\MatriculaSeccion;
use App\Models\GrupoCurso;
use App\Models\Estudiante;
use App\Models\Seccion;
use App\Models\Curso;
use App\Models\CursoPrerrequisito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatriculaController extends Controller
{
    public function index(Request $request)
    {
        $query = Matricula::with(['grupoCurso.curso', 'estudiante.usuario'])
            ->when($request->grupo_curso_id, fn($q, $v) => $q->where('grupo_curso_id', $v))
            ->when($request->estudiante_id, fn($q, $v) => $q->where('estudiante_id', $v))
            ->when($request->estado, fn($q, $v) => $q->where('estado', $v));

        $matriculas = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data'    => $matriculas->items(),
            'meta'    => [
                'current_page' => $matriculas->currentPage(),
                'last_page'    => $matriculas->lastPage(),
                'per_page'     => $matriculas->perPage(),
                'total'        => $matriculas->total(),
            ],
            'message' => 'OK',
        ]);
    }

    public function store(Request $request)
    {
        $institucionId = auth()->user()->institucion_id;

        $validated = $request->validate([
            'grupo_curso_id' => [
                'required', 'integer',
                function ($attr, $val, $fail) use ($institucionId) {
                    $grupo = GrupoCurso::withoutGlobalScopes()
                        ->with('periodo')
                        ->where('id', $val)->where('institucion_id', $institucionId)->first();
                    if (!$grupo) {
                        $fail('El grupo no pertenece a tu institución.');
                    } elseif ($grupo->estado === 'FINALIZADO') {
                        $fail('No se puede matricular en un grupo finalizado.');
                    } elseif ($grupo->periodo && in_array($grupo->periodo->estado, ['FINALIZADO', 'CERRADO', 'INACTIVO'])) {
                        $fail('No se puede matricular en un periodo académico finalizado o cerrado.');
                    }
                },
            ],
            'estudiante_id' => [
                'required', 'integer',
                function ($attr, $val, $fail) use ($institucionId) {
                    if (!Estudiante::withoutGlobalScopes()->where('id', $val)->where('institucion_id', $institucionId)->exists()) {
                        $fail('El estudiante no pertenece a tu institución.');
                    }
                },
            ],
        ]);

        return DB::transaction(function () use ($validated, $institucionId) {
            // Lock the grupo row to prevent concurrent over-enrollment
            $grupo = GrupoCurso::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($validated['grupo_curso_id']);

            if ($grupo->cupo !== null) {
                $enrolled = Matricula::withoutGlobalScopes()
                    ->where('grupo_curso_id', $grupo->id)
                    ->where('estado', 'MATRICULADO')
                    ->count();

                if ($enrolled >= $grupo->cupo) {
                    abort(422, 'El grupo ya alcanzó su cupo máximo.');
                }
            }

            // Validación de Prerrequisitos de la Unidad Didáctica (MINEDU LAG)
            $curso = Curso::withoutGlobalScopes()->find($grupo->curso_id);
            if ($curso) {
                $prerrequisitoIds = CursoPrerrequisito::where('curso_id', $curso->id)->pluck('prerrequisito_curso_id');
                if ($prerrequisitoIds->isNotEmpty()) {
                    foreach ($prerrequisitoIds as $reqId) {
                        $prerreqCurso = Curso::withoutGlobalScopes()->find($reqId);
                        $aprobado = Matricula::withoutGlobalScopes()
                            ->where('estudiante_id', $validated['estudiante_id'])
                            ->where('estado', 'FINALIZADO')
                            ->whereHas('grupoCurso', function ($q) use ($reqId) {
                                $q->where('curso_id', $reqId);
                            })
                            ->exists();

                        if (!$aprobado) {
                            $nombreReq = $prerreqCurso ? "{$prerreqCurso->nombre} ({$prerreqCurso->codigo})" : "UD #{$reqId}";
                            abort(422, "No cumple con el prerrequisito obligatorio: {$nombreReq}. Requiere haber aprobado previamente la Unidad Didáctica.");
                        }
                    }
                }

                // Límite de créditos semestrales (MINEDU LAG: máximo 24 créditos)
                if ($grupo->periodo_id && $curso->creditos) {
                    $creditosActuales = (int) DB::table('matriculas')
                        ->join('grupos_curso', 'matriculas.grupo_curso_id', '=', 'grupos_curso.id')
                        ->join('cursos', 'grupos_curso.curso_id', '=', 'cursos.id')
                        ->where('matriculas.estudiante_id', $validated['estudiante_id'])
                        ->where('matriculas.estado', 'MATRICULADO')
                        ->where('grupos_curso.periodo_id', $grupo->periodo_id)
                        ->where('matriculas.grupo_curso_id', '!=', $grupo->id)
                        ->sum('cursos.creditos');

                    if (($creditosActuales + $curso->creditos) > 24) {
                        abort(422, "Supera el límite máximo de 24 créditos académicos permitidos por semestre ({$creditosActuales} matriculados + {$curso->creditos} a matricular).");
                    }
                }
            }

            // Prevent duplicate enrollment
            $existing = Matricula::withoutGlobalScopes()
                ->where('grupo_curso_id', $validated['grupo_curso_id'])
                ->where('estudiante_id', $validated['estudiante_id'])
                ->first();

            if ($existing && $existing->estado === 'MATRICULADO') {
                abort(422, 'El estudiante ya está matriculado en este grupo.');
            }

            if ($existing) {
                $existing->update(['estado' => 'MATRICULADO', 'fecha_matricula' => now()->toDateString()]);
                $matricula = $existing;
            } else {
                $matricula = Matricula::withoutGlobalScopes()->create([
                    'institucion_id'  => $institucionId,
                    'grupo_curso_id'  => $validated['grupo_curso_id'],
                    'estudiante_id'   => $validated['estudiante_id'],
                    'origen'          => 'MANUAL',
                    'fecha_matricula'  => now()->toDateString(),
                    'estado'          => 'MATRICULADO',
                ]);
            }

            return response()->json([
                'data'    => $matricula->load(['grupoCurso.curso', 'estudiante.usuario']),
                'message' => 'Estudiante matriculado exitosamente.',
            ], 201);
        });
    }

    public function show($id)
    {
        $matricula = Matricula::with(['grupoCurso.curso', 'estudiante.usuario'])->findOrFail($id);
        return response()->json(['data' => $matricula, 'message' => 'OK']);
    }

    public function update(Request $request, $id)
    {
        $matricula = Matricula::findOrFail($id);

        $validated = $request->validate([
            'estado' => 'required|in:RETIRADO,ANULADO,FINALIZADO',
        ]);

        $matricula->update($validated);
        return response()->json(['data' => $matricula, 'message' => 'Matrícula actualizada.']);
    }

    public function destroy($id)
    {
        $matricula = Matricula::findOrFail($id);
        $matricula->update(['estado' => 'ANULADO']);
        return response()->json(null, 204);
    }

    /**
     * Matrícula por sección (flujo colegio).
     * Crea MatriculaSeccion + genera matriculas en todos los grupos de la sección de forma transaccional.
     */
    public function matricularSeccion(Request $request)
    {
        $institucionId = auth()->user()->institucion_id;

        $validated = $request->validate([
            'seccion_id'    => [
                'required', 'integer',
                function ($attr, $val, $fail) use ($institucionId) {
                    $seccion = Seccion::withoutGlobalScopes()
                        ->with('periodo')
                        ->where('id', $val)
                        ->where('institucion_id', $institucionId)
                        ->first();
                    if (!$seccion) {
                        $fail('La sección no pertenece a tu institución.');
                    } elseif ($seccion->periodo && in_array($seccion->periodo->estado, ['FINALIZADO', 'CERRADO', 'INACTIVO'])) {
                        $fail('No se puede matricular en una sección de un periodo académico finalizado o cerrado.');
                    }
                },
            ],
            'estudiante_id' => [
                'required', 'integer',
                function ($attr, $val, $fail) use ($institucionId) {
                    if (!Estudiante::withoutGlobalScopes()->where('id', $val)->where('institucion_id', $institucionId)->exists()) {
                        $fail('El estudiante no pertenece a tu institución.');
                    }
                },
            ],
        ]);

        $result = DB::transaction(function () use ($validated, $institucionId) {
            // Create or reactivate section enrollment
            $matriculaSeccion = MatriculaSeccion::withoutGlobalScopes()->updateOrCreate(
                [
                    'seccion_id'    => $validated['seccion_id'],
                    'estudiante_id' => $validated['estudiante_id'],
                ],
                [
                    'institucion_id'  => $institucionId,
                    'fecha_matricula'  => now()->toDateString(),
                    'estado'          => 'MATRICULADO',
                ]
            );

            // Auto-enroll in all active groups of the section
            $seccion = Seccion::withoutGlobalScopes()->findOrFail($validated['seccion_id']);
            $grupos  = GrupoCurso::withoutGlobalScopes()
                ->where('seccion_id', $seccion->id)
                ->where('estado', 'ACTIVO')
                ->get();

            $created = 0;
            foreach ($grupos as $grupo) {
                Matricula::withoutGlobalScopes()->updateOrCreate(
                    [
                        'grupo_curso_id' => $grupo->id,
                        'estudiante_id'  => $validated['estudiante_id'],
                    ],
                    [
                        'institucion_id'  => $institucionId,
                        'origen'          => 'AUTOMATICA_SECCION',
                        'fecha_matricula'  => now()->toDateString(),
                        'estado'          => 'MATRICULADO',
                    ]
                );
                $created++;
            }

            return ['matricula_seccion' => $matriculaSeccion, 'matriculas_creadas' => $created];
        });

        return response()->json([
            'data'    => $result,
            'message' => 'Matrícula por sección completada exitosamente.',
        ], 201);
    }
}
