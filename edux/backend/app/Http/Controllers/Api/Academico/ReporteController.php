<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\GrupoCurso;
use App\Models\PeriodoAcademico;
use App\Models\Matricula;
use App\Models\Calificacion;
use App\Models\Institucion;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteController extends Controller
{
    /**
     * Boleta Oficial de Calificaciones del Periodo
     */
    public function boletaNotas(Request $request, $estudianteId, $periodoId)
    {
        $user = auth()->user();
        $estudiante = Estudiante::query()
            ->when($user->institucion_id, fn ($q) => $q->where('institucion_id', $user->institucion_id))
            ->with(['usuario'])
            ->findOrFail($estudianteId);

        // Si es estudiante, solo puede consultar su propia boleta
        if ($user->hasRole('ESTUDIANTE')) {
            if (!$user->estudiante || $user->estudiante->id !== $estudiante->id) {
                abort(response()->json(['message' => 'No tienes acceso a la boleta de otro estudiante.', 'error' => 'forbidden'], 403));
            }
        }

        // Si es apoderado, solo puede consultar las boletas de sus hijos vinculados
        if ($user->hasRole('APODERADO')) {
            $hijosIds = $user->apoderado ? $user->apoderado->estudiantes()->pluck('estudiantes.id')->toArray() : [];
            if (!in_array($estudiante->id, $hijosIds)) {
                abort(response()->json(['message' => 'No tienes acceso a la boleta de este estudiante.', 'error' => 'forbidden'], 403));
            }
        }

        $periodo = PeriodoAcademico::query()
            ->when($user->institucion_id, fn ($q) => $q->where('institucion_id', $user->institucion_id))
            ->findOrFail($periodoId);
        $institucion = Institucion::query()
            ->when($user->institucion_id, fn ($q) => $q->where('id', $user->institucion_id))
            ->firstOrFail();

        $matriculas = Matricula::where('estudiante_id', $estudiante->id)
            ->whereHas('grupoCurso', function ($q) use ($periodo) {
                $q->where('periodo_id', $periodo->id);
            })
            ->with(['grupoCurso.curso', 'grupoCurso.docente.usuario'])
            ->get();

        $cursosDetalle = $matriculas->map(function ($mat) use ($estudiante) {
            $grupo = $mat->grupoCurso;
            $calificaciones = Calificacion::where('grupo_curso_id', $grupo->id)
                ->where('estudiante_id', $estudiante->id)
                ->get();

            // Cálculo ponderado
            $sumaPonderada = 0;
            $sumaPesos = 0;
            foreach ($calificaciones as $cal) {
                $peso = (float)($cal->peso_porcentual ?? 0);
                if ($peso > 0) {
                    $sumaPonderada += ((float)$cal->nota * ($peso / 100));
                    $sumaPesos += $peso;
                }
            }

            if ($sumaPesos > 0) {
                $promedioFinal = round($sumaPonderada / ($sumaPesos / 100), 2);
            } else {
                $promedioFinal = $calificaciones->isNotEmpty() ? round($calificaciones->avg('nota'), 2) : 0;
            }

            return [
                'codigo_curso'   => $grupo->curso?->codigo ?? 'S/C',
                'nombre_curso'   => $grupo->curso?->nombre ?? 'Curso',
                'creditos'       => $grupo->curso?->creditos ?? 0,
                'docente'        => $grupo->docente?->usuario ? "{$grupo->docente->usuario->nombres} {$grupo->docente->usuario->apellido_paterno}" : 'Docente asignado',
                'promedio_final' => $promedioFinal,
                'estado'         => $promedioFinal >= 11 ? 'APROBADO' : 'DESAPROBADO',
                'rubros'         => $calificaciones->map(fn($c) => [
                    'rubro' => $c->nombre_rubro,
                    'nota'  => (float)$c->nota,
                    'peso'  => (float)($c->peso_porcentual ?? 0)
                ])
            ];
        });

        $totalCreditos = $cursosDetalle->sum('creditos');
        $creditosAprobados = $cursosDetalle->where('estado', 'APROBADO')->sum('creditos');
        $promedioPonderadoSemestral = $totalCreditos > 0
            ? round($cursosDetalle->sum(fn($c) => $c['promedio_final'] * $c['creditos']) / $totalCreditos, 2)
            : 0;

        if ($request->query('formato') === 'pdf') {
            return $this->pdf('Boleta de calificaciones', [
                'Estudiante' => "{$estudiante->usuario->nombres} {$estudiante->usuario->apellido_paterno}",
                'Periodo' => $periodo->nombre,
                'Promedio ponderado' => $promedioPonderadoSemestral,
            ], ['Curso', 'Créditos', 'Promedio', 'Condición'], $cursosDetalle->map(fn ($curso) => [
                $curso['nombre_curso'], $curso['creditos'], $curso['promedio_final'], $curso['estado'],
            ])->all(), "boleta-{$estudiante->codigo_estudiante}-{$periodo->codigo}.pdf");
        }

        return response()->json([
            'data' => [
                'institucion' => [
                    'nombre'       => $institucion->nombre,
                    'nombre_corto' => $institucion->nombre_corto,
                    'ruc'          => $institucion->ruc,
                    'logo'         => $institucion->logo,
                    'direccion'    => $institucion->direccion,
                ],
                'estudiante' => [
                    'id'              => $estudiante->id,
                    'codigo'          => $estudiante->codigo_estudiante,
                    'nombre_completo' => "{$estudiante->usuario->apellido_paterno} {$estudiante->usuario->apellido_materno}, {$estudiante->usuario->nombres}",
                    'documento'       => $estudiante->usuario->documento_numero,
                ],
                'periodo' => [
                    'id'     => $periodo->id,
                    'nombre' => $periodo->nombre,
                    'codigo' => $periodo->codigo,
                ],
                'resumen' => [
                    'total_cursos'                 => $cursosDetalle->count(),
                    'total_creditos'               => $totalCreditos,
                    'creditos_aprobados'           => $creditosAprobados,
                    'promedio_ponderado_semestral' => $promedioPonderadoSemestral,
                    'condicion'                    => $promedioPonderadoSemestral >= 11 ? 'REGULAR' : 'OBSERVADO',
                ],
                'cursos' => $cursosDetalle,
                'fecha_emision' => now()->format('Y-m-d H:i:s'),
            ],
            'message' => 'OK'
        ]);
    }

    /**
     * Acta Oficial Consolidada de Evaluación por Grupo / Curso
     * Bug #7: Solo docente TITULAR del grupo, coordinador y admin pueden ver el acta.
     */
    public function actaConsolidada(Request $request, $grupoId)
    {
        $user = auth()->user();
        $grupo = GrupoCurso::query()
            ->when($user->institucion_id, fn ($q) => $q->where('institucion_id', $user->institucion_id))
            ->with(['curso', 'docente.usuario', 'periodo'])
            ->findOrFail($grupoId);

        // Bug #7: Solo docente TITULAR del grupo, coordinador y admin pueden ver el acta.
        if ($user->hasRole('DOCENTE')) {
            if (!$user->docente || $grupo->docente_id !== $user->docente->id) {
                abort(response()->json([
                    'message' => 'Solo puedes ver el acta de los grupos que tienes asignados.',
                    'error'   => 'forbidden',
                ], 403));
            }
        }

        // Estudiantes y apoderados no acceden al acta consolidada
        if ($user->hasRole('ESTUDIANTE') || $user->hasRole('APODERADO')) {
            abort(response()->json([
                'message' => 'No tienes acceso a esta sección.',
                'error'   => 'forbidden',
            ], 403));
        }

        $institucion = Institucion::findOrFail($user->institucion_id);

        $matriculas = Matricula::where('grupo_curso_id', $grupo->id)
            ->where('estado', 'MATRICULADO')
            ->with(['estudiante.usuario'])
            ->get();

        $calificacionesGrupo = Calificacion::where('grupo_curso_id', $grupo->id)->get();
        $rubrosDisponibles = $calificacionesGrupo->pluck('nombre_rubro')->unique()->values();

        $alumnosNotas = $matriculas->map(function ($mat) use ($calificacionesGrupo, $rubrosDisponibles) {
            $est = $mat->estudiante;
            $notasAlumno = $calificacionesGrupo->where('estudiante_id', $est->id);

            $detalleRubros = [];
            $sumaPonderada = 0;
            $sumaPesos = 0;

            foreach ($rubrosDisponibles as $rubro) {
                $cal = $notasAlumno->firstWhere('nombre_rubro', $rubro);
                $nota = $cal ? (float)$cal->nota : 0;
                $peso = $cal ? (float)($cal->peso_porcentual ?? 0) : 0;

                $detalleRubros[$rubro] = $nota;

                if ($peso > 0) {
                    $sumaPonderada += ($nota * ($peso / 100));
                    $sumaPesos += $peso;
                }
            }

            if ($sumaPesos > 0) {
                $promedioFinal = round($sumaPonderada / ($sumaPesos / 100), 2);
            } else {
                $promedioFinal = $notasAlumno->isNotEmpty() ? round($notasAlumno->avg('nota'), 2) : 0;
            }

            return [
                'estudiante_id'   => $est->id,
                'codigo'          => $est->codigo_estudiante,
                'nombre_completo' => $est->usuario ? "{$est->usuario->apellido_paterno} {$est->usuario->apellido_materno}, {$est->usuario->nombres}" : 'Estudiante',
                'rubros'          => $detalleRubros,
                'promedio_final'  => $promedioFinal,
                'condicion'       => $promedioFinal >= 11 ? 'APROBADO' : 'DESAPROBADO',
            ];
        });

        $aprobados    = $alumnosNotas->where('condicion', 'APROBADO')->count();
        $desaprobados = $alumnosNotas->where('condicion', 'DESAPROBADO')->count();

        if ($request->query('formato') === 'pdf') {
            return $this->pdf('Acta consolidada de evaluación', [
                'Curso'   => $grupo->curso?->nombre,
                'Periodo' => $grupo->periodo?->nombre,
                'Docente' => $grupo->docente?->usuario?->nombres . ' ' . $grupo->docente?->usuario?->apellido_paterno,
            ], ['Código', 'Estudiante', 'Promedio', 'Condición'], $alumnosNotas->map(fn ($alumno) => [
                $alumno['codigo'], $alumno['nombre_completo'], $alumno['promedio_final'], $alumno['condicion'],
            ])->all(), "acta-grupo-{$grupo->id}.pdf");
        }

        return response()->json([
            'data' => [
                'institucion' => [
                    'nombre' => $institucion->nombre,
                    'ruc'    => $institucion->ruc,
                ],
                'curso' => [
                    'codigo'   => $grupo->curso?->codigo,
                    'nombre'   => $grupo->curso?->nombre,
                    'creditos' => $grupo->curso?->creditos,
                ],
                'docente' => [
                    'nombre' => $grupo->docente?->usuario ? "{$grupo->docente->usuario->nombres} {$grupo->docente->usuario->apellido_paterno}" : 'Docente titular',
                ],
                'periodo' => [
                    'nombre' => $grupo->periodo?->nombre,
                ],
                'rubros_evaluacion' => $rubrosDisponibles,
                'alumnos'           => $alumnosNotas,
                'estadisticas' => [
                    'total_matriculados' => $alumnosNotas->count(),
                    'aprobados'          => $aprobados,
                    'desaprobados'       => $desaprobados,
                    'promedio_grupal'    => $alumnosNotas->isNotEmpty() ? round($alumnosNotas->avg('promedio_final'), 2) : 0,
                ],
                'fecha_cierre' => now()->format('Y-m-d H:i:s'),
            ],
            'message' => 'OK'
        ]);
    }

    /**
     * Ficha Formal de Matrícula
     * Bug #7: Un ESTUDIANTE solo puede ver su propia ficha de matrícula.
     */
    public function fichaMatricula(Request $request, $matriculaId)
    {
        $user = auth()->user();
        $matricula = Matricula::query()
        ->when($user->institucion_id, fn ($q) => $q->whereHas('grupoCurso', function ($q2) use ($user) {
            $q2->where('institucion_id', $user->institucion_id);
        }))
        ->with(['estudiante.usuario', 'grupoCurso.curso', 'grupoCurso.periodo', 'grupoCurso.seccion'])
        ->findOrFail($matriculaId);

        // Bug #7: ESTUDIANTE solo puede ver su propia ficha
        if ($user->hasRole('ESTUDIANTE')) {
            if (!$user->estudiante || $matricula->estudiante_id !== $user->estudiante->id) {
                abort(response()->json([
                    'message' => 'No tienes acceso a la ficha de matrícula de otro estudiante.',
                    'error'   => 'forbidden',
                ], 403));
            }
        }

        // Apoderado: solo puede ver fichas de sus hijos
        if ($user->hasRole('APODERADO')) {
            $hijosIds = $user->apoderado ? $user->apoderado->estudiantes()->pluck('estudiantes.id')->toArray() : [];
            if (!in_array($matricula->estudiante_id, $hijosIds)) {
                abort(response()->json([
                    'message' => 'No tienes acceso a la ficha de matrícula de este estudiante.',
                    'error'   => 'forbidden',
                ], 403));
            }
        }

        $institucion = Institucion::findOrFail($user->institucion_id);

        if ($request->query('formato') === 'pdf') {
            return $this->pdf('Ficha de matrícula', [
                'Institución'          => $institucion->nombre,
                'Estudiante'           => "{$matricula->estudiante->usuario->nombres} {$matricula->estudiante->usuario->apellido_paterno}",
                'Código de estudiante' => $matricula->estudiante->codigo_estudiante,
                'Curso'                => $matricula->grupoCurso->curso?->nombre,
                'Periodo'              => $matricula->grupoCurso->periodo?->nombre,
                'Fecha de matrícula'   => (string) $matricula->fecha_matricula,
                'Estado'               => $matricula->estado,
            ], [], [], "matricula-{$matricula->id}.pdf");
        }

        return response()->json([
            'data' => [
                'institucion' => [
                    'nombre'       => $institucion->nombre,
                    'nombre_corto' => $institucion->nombre_corto,
                    'direccion'    => $institucion->direccion,
                    'telefono'     => $institucion->telefono,
                ],
                'matricula' => [
                    'numero'          => str_pad((string)$matricula->id, 8, '0', STR_PAD_LEFT),
                    'fecha_matricula' => $matricula->fecha_matricula,
                    'tipo'            => $matricula->origen,
                    'estado'          => $matricula->estado,
                ],
                'estudiante' => [
                    'codigo'          => $matricula->estudiante->codigo_estudiante,
                    'nombre_completo' => "{$matricula->estudiante->usuario->apellido_paterno} {$matricula->estudiante->usuario->apellido_materno}, {$matricula->estudiante->usuario->nombres}",
                    'documento'       => $matricula->estudiante->usuario->documento_numero,
                    'email'           => $matricula->estudiante->usuario->email,
                ],
                'curso' => [
                    'nombre'   => $matricula->grupoCurso->curso?->nombre,
                    'codigo'   => $matricula->grupoCurso->curso?->codigo,
                    'creditos' => $matricula->grupoCurso->curso?->creditos,
                    'periodo'  => $matricula->grupoCurso->periodo?->nombre,
                    'seccion'  => $matricula->grupoCurso->seccion?->nombre ?? 'General',
                ],
                'constancia_validez' => 'Documento emitido electrónicamente por el sistema académico institucional Edux.',
            ],
            'message' => 'OK'
        ]);
    }


    /** @param array<string, mixed> $datos @param array<int, string> $cabeceras @param array<int, array<int, mixed>> $filas */
    private function pdf(string $titulo, array $datos, array $cabeceras, array $filas, string $filename)
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $detalle = collect($datos)->map(fn ($valor, $clave) => '<p><strong>' . $escape($clave) . ':</strong> ' . $escape($valor) . '</p>')->implode('');
        $tabla = '';
        if ($cabeceras !== []) {
            $tabla .= '<table><thead><tr>' . collect($cabeceras)->map(fn ($valor) => '<th>' . $escape($valor) . '</th>')->implode('') . '</tr></thead><tbody>';
            $tabla .= collect($filas)->map(fn ($fila) => '<tr>' . collect($fila)->map(fn ($valor) => '<td>' . $escape($valor) . '</td>')->implode('') . '</tr>')->implode('');
            $tabla .= '</tbody></table>';
        }
        $html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:11px}h1{color:#1d4ed8;font-size:20px;border-bottom:2px solid #1d4ed8;padding-bottom:8px}table{width:100%;border-collapse:collapse;margin-top:18px}th{background:#1d4ed8;color:#fff}th,td{border:1px solid #cbd5e1;padding:7px;text-align:left}footer{position:fixed;bottom:0;color:#64748b;font-size:9px}</style></head><body><h1>' . $escape($titulo) . '</h1>' . $detalle . $tabla . '<footer>Documento emitido electrónicamente por Edux · ' . now()->format('Y-m-d H:i') . '</footer></body></html>';
        return Pdf::loadHTML($html)->setPaper('a4')->download($filename);
    }
}
