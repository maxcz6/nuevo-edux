<?php

namespace App\Http\Controllers\Api\Aula;

use App\Http\Controllers\Controller;
use App\Models\Calificacion;
use App\Models\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CalificacionController extends Controller
{
    use AuthorizesAulaAccess;

    /**
     * Bug #3: Un estudiante solo puede ver sus propias calificaciones.
     * El consolidado completo solo lo ven docentes, coordinadores y admins.
     */
    public function index(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        $query = Calificacion::where('grupo_curso_id', $grupoCurso->id);

        // Estudiante SOLO ve sus propias calificaciones
        if ($user->hasRole('ESTUDIANTE') && $user->estudiante) {
            $query->where('estudiante_id', $user->estudiante->id);
        }

        $calificaciones = $query->with(['estudiante.usuario', 'registradoPor.usuario'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $calificaciones->items(),
            'meta' => [
                'current_page' => $calificaciones->currentPage(),
                'last_page'    => $calificaciones->lastPage(),
                'per_page'     => $calificaciones->perPage(),
                'total'        => $calificaciones->total(),
            ],
            'message' => 'OK'
        ]);
    }

    /**
     * Bug #4: Verificar que el estudiante pertenece a la institución y está matriculado en el grupo.
     */
    public function store(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $this->ensureActaAbierta($grupoCurso);
        $user = auth()->user();

        $validated = $request->validate([
            'estudiante_id'   => 'required|integer',
            'tipo_origen'     => 'required|in:TAREA,EVALUACION,PARTICIPACION,MANUAL',
            'origen_id'       => 'nullable|integer',
            'nombre_rubro'    => 'required|string|max:100',
            'nota'            => 'required|numeric|between:0,20',
            'peso_porcentual' => 'nullable|numeric|between:0,100'
        ]);

        // Bug #4: Verificar que el estudiante pertenece a la institución y está matriculado en el grupo
        $this->verifyEstudianteInGroup($validated['estudiante_id'], $grupoCurso);

        $validated['grupo_curso_id'] = $grupoCurso->id;
        $validated['registrado_por'] = $user->docente?->id ?? $grupoCurso->docente_id;

        $calificacion = Calificacion::updateOrCreate(
            [
                'grupo_curso_id' => $grupoCurso->id,
                'estudiante_id'  => $validated['estudiante_id'],
                'nombre_rubro'   => $validated['nombre_rubro'],
            ],
            $validated
        );

        Audit::create([
            'institucion_id'    => $grupoCurso->institucion_id,
            'usuario_id'        => $request->user()->id,
            'accion'            => 'CALIFICACION_REGISTRADA',
            'entidad_tipo'      => Calificacion::class,
            'entidad_id'        => $calificacion->id,
            'valores_nuevos'    => $calificacion->only(['nombre_rubro', 'nota', 'peso_porcentual']),
            'ip'                => $request->ip(),
        ]);

        return response()->json(['data' => $calificacion, 'message' => 'Calificación registrada exitosamente.'], 201);
    }

    /**
     * Registro y actualización de calificaciones en lote por actividad o rubro.
     */
    public function guardarLote(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $this->ensureActaAbierta($grupoCurso);
        $user = auth()->user();

        $validated = $request->validate([
            'nombre_rubro'          => 'required|string|max:100',
            'tipo_origen'           => 'required|in:TAREA,EVALUACION,PARTICIPACION,MANUAL',
            'origen_id'             => 'nullable|integer',
            'peso_porcentual'       => 'nullable|numeric|between:0,100',
            'notas'                 => 'required|array',
            'notas.*.estudiante_id' => 'required|integer',
            'notas.*.nota'          => 'required|numeric|between:0,20',
        ]);

        $docenteId = $user->docente?->id ?? $grupoCurso->docente_id;
        $guardadas = [];

        DB::transaction(function () use ($validated, $grupoCurso, $docenteId, &$guardadas, $request) {
            foreach ($validated['notas'] as $item) {
                $this->verifyEstudianteInGroup($item['estudiante_id'], $grupoCurso);

                $calificacion = Calificacion::updateOrCreate(
                    [
                        'grupo_curso_id' => $grupoCurso->id,
                        'estudiante_id'  => $item['estudiante_id'],
                        'nombre_rubro'   => $validated['nombre_rubro'],
                    ],
                    [
                        'tipo_origen'     => $validated['tipo_origen'],
                        'origen_id'       => $validated['origen_id'] ?? null,
                        'nota'            => $item['nota'],
                        'peso_porcentual' => $validated['peso_porcentual'] ?? null,
                        'registrado_por'  => $docenteId,
                    ]
                );

                $guardadas[] = $calificacion;
            }

            Audit::create([
                'institucion_id'    => $grupoCurso->institucion_id,
                'usuario_id'        => $request->user()->id,
                'accion'            => 'CALIFICACIONES_LOTE_GUARDADAS',
                'entidad_tipo'      => Calificacion::class,
                'entidad_id'        => $grupoCurso->id,
                'valores_nuevos'    => [
                    'nombre_rubro' => $validated['nombre_rubro'],
                    'total_notas'  => count($guardadas),
                ],
                'ip'                => $request->ip(),
            ]);
        });

        return response()->json([
            'data'    => $guardadas,
            'message' => 'Calificaciones registradas exitosamente.',
            'total'   => count($guardadas),
        ], 200);
    }

    public function show($grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        $calificacion = Calificacion::where('grupo_curso_id', $grupoCurso->id)
            ->with(['estudiante.usuario', 'registradoPor.usuario'])
            ->findOrFail($id);

        // Estudiante solo puede ver sus propias calificaciones
        if ($user->hasRole('ESTUDIANTE') && $user->estudiante && $calificacion->estudiante_id !== $user->estudiante->id) {
            abort(response()->json(['message' => 'No tienes acceso a esta calificación.', 'error' => 'forbidden'], 403));
        }

        return response()->json(['data' => $calificacion, 'message' => 'OK']);
    }

    public function update(Request $request, $grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $this->ensureActaAbierta($grupoCurso);
        $calificacion = Calificacion::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        $validated = $request->validate([
            'nombre_rubro'    => 'sometimes|string|max:100',
            'nota'            => 'sometimes|numeric|between:0,20',
            'peso_porcentual' => 'nullable|numeric|between:0,100'
        ]);

        $anterior = $calificacion->only(['nombre_rubro', 'nota', 'peso_porcentual']);
        $calificacion->update($validated);
        Audit::create([
            'institucion_id'    => $grupoCurso->institucion_id,
            'usuario_id'        => $request->user()->id,
            'accion'            => 'CALIFICACION_ACTUALIZADA',
            'entidad_tipo'      => Calificacion::class,
            'entidad_id'        => $calificacion->id,
            'valores_anteriores'=> $anterior,
            'valores_nuevos'    => $calificacion->fresh()->only(['nombre_rubro', 'nota', 'peso_porcentual']),
            'ip'                => $request->ip(),
        ]);

        return response()->json(['data' => $calificacion, 'message' => 'Calificación actualizada exitosamente.']);
    }

    public function destroy($grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $this->ensureActaAbierta($grupoCurso);
        $calificacion = Calificacion::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        $calificacion->delete();
        return response()->json(null, 204);
    }

    private function ensureActaAbierta(\App\Models\GrupoCurso $grupo): void
    {
        if ($grupo->estado === 'FINALIZADO' || $grupo->cerrado_at !== null) {
            abort(response()->json(['message' => 'El acta está cerrada y las notas no pueden modificarse.'], 422));
        }
    }

    /**
     * Bug #3: Consolidado restringido — ESTUDIANTE recibe 403.
     * Solo DOCENTE titular del grupo, COORDINADOR y ADMIN pueden ver el consolidado.
     */
    public function consolidado(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        // Estudiante no puede ver el consolidado del grupo
        if ($user->hasRole('ESTUDIANTE')) {
            abort(response()->json([
                'message' => 'No tienes acceso al consolidado del grupo. Consulta tus propias calificaciones.',
                'error'   => 'forbidden'
            ], 403));
        }

        $matriculas = \App\Models\Matricula::where('grupo_curso_id', $grupoCurso->id)
            ->where('estado', 'MATRICULADO')
            ->with(['estudiante.usuario'])
            ->get();

        $calificaciones = Calificacion::where('grupo_curso_id', $grupoCurso->id)->get();
        $rubros = $calificaciones->pluck('nombre_rubro')->unique()->values();

        $data = $matriculas->map(function ($mat) use ($calificaciones, $rubros) {
            $est = $mat->estudiante;
            $notasAlumno = $calificaciones->where('estudiante_id', $est->id);

            $detalle = [];
            $detalleRubros = [];
            $sumaPonderada = 0;
            $sumaPesos = 0;

            foreach ($rubros as $rubro) {
                $c = $notasAlumno->firstWhere('nombre_rubro', $rubro);
                $nota = $c ? (float)$c->nota : null;
                $peso = $c ? (float)($c->peso_porcentual ?? 0) : 0;

                $detalle[$rubro] = $nota ?? 0;
                $detalleRubros[$rubro] = [
                    'id'              => $c?->id,
                    'nota'            => $nota,
                    'peso_porcentual' => $c ? (float)$c->peso_porcentual : null,
                    'tipo_origen'     => $c?->tipo_origen,
                    'origen_id'       => $c?->origen_id,
                ];

                if ($nota !== null && $peso > 0) {
                    $sumaPonderada += ($nota * ($peso / 100));
                    $sumaPesos += $peso;
                }
            }

            $promedio = $sumaPesos > 0
                ? round($sumaPonderada / ($sumaPesos / 100), 2)
                : ($notasAlumno->isNotEmpty() ? round($notasAlumno->avg('nota'), 2) : 0);

            return [
                'estudiante_id'    => $est->id,
                'codigo'           => $est->codigo_estudiante,
                'nombre_completo'  => $est->usuario ? "{$est->usuario->apellido_paterno} {$est->usuario->apellido_materno}, {$est->usuario->nombres}" : 'N/A',
                'tipo_documento'   => $est->usuario?->tipo_documento ?? 'DNI',
                'numero_documento' => $est->usuario?->numero_documento ?? 'N/A',
                'notas_por_rubro'  => $detalle,
                'detalle_rubros'   => $detalleRubros,
                'promedio_final'   => $promedio,
                'estado'           => $promedio >= 11 ? 'APROBADO' : 'DESAPROBADO',
            ];
        });

        return response()->json([
            'data'    => $data,
            'rubros'  => $rubros,
            'message' => 'OK'
        ]);
    }

    /**
     * Exportación de acta consolidada a CSV con codificación UTF-8 BOM compatible con Microsoft Excel.
     */
    public function exportar(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        if ($user->hasRole('ESTUDIANTE')) {
            abort(response()->json([
                'message' => 'No tienes permiso para exportar el consolidado del grupo.',
                'error'   => 'forbidden'
            ], 403));
        }

        $grupoCurso->load(['curso', 'docente.usuario', 'periodo']);

        $matriculas = \App\Models\Matricula::where('grupo_curso_id', $grupoCurso->id)
            ->where('estado', 'MATRICULADO')
            ->with(['estudiante.usuario'])
            ->get();

        $calificaciones = Calificacion::where('grupo_curso_id', $grupoCurso->id)->get();
        $rubros = $calificaciones->pluck('nombre_rubro')->unique()->values();

        $filename = 'acta_calificaciones_' . Str::slug($grupoCurso->curso?->nombre ?? 'curso') . '_seccion_' . ($grupoCurso->seccion ?? 'U') . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($grupoCurso, $matriculas, $calificaciones, $rubros) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM para soporte nativo en Microsoft Excel en Windows
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ['EDUX - PLATAFORMA DE GESTION ACADEMICA']);
            fputcsv($file, ['ACTA CONSOLIDADA DE CALIFICACIONES']);
            fputcsv($file, ['Curso:', $grupoCurso->curso?->nombre ?? 'N/A', 'Codigo:', $grupoCurso->curso?->codigo ?? 'N/A']);
            fputcsv($file, ['Docente:', $grupoCurso->docente?->usuario ? "{$grupoCurso->docente->usuario->apellido_paterno} {$grupoCurso->docente->usuario->apellido_materno}, {$grupoCurso->docente->usuario->nombres}" : 'N/A']);
            fputcsv($file, ['Periodo:', $grupoCurso->periodo?->nombre ?? 'N/A', 'Seccion / Grupo:', $grupoCurso->seccion ?? 'N/A']);
            fputcsv($file, ['Fecha de Emision:', now()->format('d/m/Y H:i:s')]);
            fputcsv($file, []);

            $columnas = ['N°', 'Codigo Estudiante', 'Tipo Doc.', 'N° Documento', 'Apellidos y Nombres'];
            foreach ($rubros as $rubro) {
                $columnas[] = $rubro;
            }
            $columnas[] = 'Promedio Final';
            $columnas[] = 'Condicion';
            fputcsv($file, $columnas);

            $num = 1;
            foreach ($matriculas as $mat) {
                $est = $mat->estudiante;
                $usuario = $est?->usuario;
                $notasAlumno = $calificaciones->where('estudiante_id', $est->id);

                $sumaPonderada = 0;
                $sumaPesos = 0;
                $row = [
                    $num++,
                    $est->codigo_estudiante ?? 'N/A',
                    $usuario?->tipo_documento ?? 'DNI',
                    $usuario?->numero_documento ?? 'N/A',
                    $usuario ? "{$usuario->apellido_paterno} {$usuario->apellido_materno}, {$usuario->nombres}" : 'N/A',
                ];

                foreach ($rubros as $rubro) {
                    $c = $notasAlumno->firstWhere('nombre_rubro', $rubro);
                    $nota = $c ? (float)$c->nota : null;
                    $peso = $c ? (float)($c->peso_porcentual ?? 0) : 0;

                    $row[] = $nota !== null ? number_format($nota, 2, '.', '') : '-';

                    if ($nota !== null && $peso > 0) {
                        $sumaPonderada += ($nota * ($peso / 100));
                        $sumaPesos += $peso;
                    }
                }

                $promedio = $sumaPesos > 0
                    ? round($sumaPonderada / ($sumaPesos / 100), 2)
                    : ($notasAlumno->isNotEmpty() ? round($notasAlumno->avg('nota'), 2) : 0);

                $estado = $promedio >= 11 ? 'APROBADO' : 'DESAPROBADO';

                $row[] = number_format($promedio, 2, '.', '');
                $row[] = $estado;

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
