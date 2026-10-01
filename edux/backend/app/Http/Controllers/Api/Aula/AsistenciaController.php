<?php

namespace App\Http\Controllers\Api\Aula;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Matricula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AsistenciaController extends Controller
{
    use AuthorizesAulaAccess;

    /**
     * Listar asistencias de una fecha para los alumnos matriculados del grupo.
     * Bug #3: Un estudiante solo puede ver sus propias asistencias.
     * La vista colectiva (con datos de otros) solo es para DOCENTE titular, COORDINADOR y ADMIN.
     */
    public function index(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();
        $fecha = $request->get('fecha', Carbon::today()->format('Y-m-d'));

        // Estudiante solo ve su propia fila
        if ($user->hasRole('ESTUDIANTE') && $user->estudiante) {
            $asistencia = Asistencia::where('grupo_curso_id', $grupoCurso->id)
                ->where('estudiante_id', $user->estudiante->id)
                ->where('fecha', $fecha)
                ->first();

            return response()->json([
                'data'    => $asistencia ? [$asistencia] : [],
                'fecha'   => $fecha,
                'message' => 'OK'
            ]);
        }

        // Docente: solo puede ver su propio grupo (ya garantizado por verifyGroupAccess)
        // Admin/Coordinador: vista completa del grupo
        $matriculas = Matricula::where('grupo_curso_id', $grupoCurso->id)
            ->where('estado', 'MATRICULADO')
            ->with(['estudiante.usuario'])
            ->get();

        $asistenciasExistentes = Asistencia::where('grupo_curso_id', $grupoCurso->id)
            ->where('fecha', $fecha)
            ->get()
            ->keyBy('estudiante_id');

        $data = $matriculas->map(function ($mat) use ($asistenciasExistentes, $fecha) {
            $est = $mat->estudiante;
            $asistencia = $asistenciasExistentes->get($est->id);

            return [
                'estudiante_id'     => $est->id,
                'codigo'            => $est->codigo_estudiante,
                'nombre_completo'   => $est->usuario ? "{$est->usuario->apellido_paterno} {$est->usuario->apellido_materno}, {$est->usuario->nombres}" : 'N/A',
                'tiene_foto'        => filled($est->usuario?->foto),
                'fecha'             => $fecha,
                'asistencia_id'     => $asistencia?->id,
                'estado_asistencia' => $asistencia?->estado_asistencia ?? 'PRESENTE',
                'observacion'       => $asistencia?->observacion ?? '',
            ];
        });

        return response()->json([
            'data'    => $data,
            'fecha'   => $fecha,
            'message' => 'OK'
        ]);
    }

    /**
     * Registro masivo o individual de asistencias para una fecha dada.
     * Bug #4: Cada estudiante_id validado contra institución y matrícula en el grupo.
     */
    public function store(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $user = auth()->user();

        $validated = $request->validate([
            'fecha'                           => 'required|date',
            'asistencias'                     => 'required|array|min:1',
            'asistencias.*.estudiante_id'     => 'required|integer',
            'asistencias.*.estado_asistencia' => 'required|in:PRESENTE,AUSENTE,TARDANZA,JUSTIFICADO',
            'asistencias.*.observacion'       => 'nullable|string|max:255',
        ]);

        // Bug #4: Verificar cada estudiante_id contra institución y matrícula del grupo
        foreach ($validated['asistencias'] as $item) {
            $this->verifyEstudianteInGroup($item['estudiante_id'], $grupoCurso);
        }

        $fecha = Carbon::parse($validated['fecha'])->format('Y-m-d');
        $docenteId = $user->docente?->id ?? $grupoCurso->docente_id;

        DB::transaction(function () use ($grupoCurso, $fecha, $docenteId, $validated) {
            foreach ($validated['asistencias'] as $item) {
                Asistencia::updateOrCreate(
                    [
                        'grupo_curso_id' => $grupoCurso->id,
                        'estudiante_id'  => $item['estudiante_id'],
                        'fecha'          => $fecha,
                    ],
                    [
                        'seccion_id'        => $grupoCurso->seccion_id,
                        'estado_asistencia' => $item['estado_asistencia'],
                        'observacion'       => $item['observacion'] ?? null,
                        'registrado_por'    => $docenteId,
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Asistencias registradas exitosamente.',
            'fecha'   => $fecha
        ], 200);
    }

    /**
     * Resumen acumulado de asistencias del grupo.
     * Bug #3: Solo DOCENTE titular, COORDINADOR y ADMIN. Estudiante → 403.
     */
    public function resumen(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        // Estudiante no puede ver el resumen del grupo completo
        if ($user->hasRole('ESTUDIANTE')) {
            abort(response()->json([
                'message' => 'No tienes acceso al resumen del grupo. Usa /mis-asistencias para ver tu historial.',
                'error'   => 'forbidden'
            ], 403));
        }

        $matriculas = Matricula::where('grupo_curso_id', $grupoCurso->id)
            ->where('estado', 'MATRICULADO')
            ->with(['estudiante.usuario'])
            ->get();

        $totalFechas = Asistencia::where('grupo_curso_id', $grupoCurso->id)
            ->distinct('fecha')
            ->count('fecha');

        $resumen = $matriculas->map(function ($mat) use ($grupoCurso, $totalFechas) {
            $est = $mat->estudiante;

            $counts = Asistencia::where('grupo_curso_id', $grupoCurso->id)
                ->where('estudiante_id', $est->id)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN estado_asistencia = 'PRESENTE' THEN 1 ELSE 0 END) as presentes,
                    SUM(CASE WHEN estado_asistencia = 'TARDANZA' THEN 1 ELSE 0 END) as tardanzas,
                    SUM(CASE WHEN estado_asistencia = 'AUSENTE' THEN 1 ELSE 0 END) as ausentes,
                    SUM(CASE WHEN estado_asistencia = 'JUSTIFICADO' THEN 1 ELSE 0 END) as justificados
                ")
                ->first();

            $presentes    = (int)($counts->presentes    ?? 0);
            $tardanzas    = (int)($counts->tardanzas    ?? 0);
            $ausentes     = (int)($counts->ausentes     ?? 0);
            $justificados = (int)($counts->justificados ?? 0);

            $faltasEfectivas     = $ausentes + floor($tardanzas / 3);
            $porcentajeAsistencia = $totalFechas > 0
                ? round((($totalFechas - $faltasEfectivas) / $totalFechas) * 100, 1)
                : 100;

            return [
                'estudiante_id'         => $est->id,
                'codigo'                => $est->codigo_estudiante,
                'nombre_completo'       => $est->usuario ? "{$est->usuario->apellido_paterno} {$est->usuario->apellido_materno}, {$est->usuario->nombres}" : 'N/A',
                'presentes'             => $presentes,
                'tardanzas'             => $tardanzas,
                'ausentes'              => $ausentes,
                'justificados'          => $justificados,
                'porcentaje_asistencia' => max(0, min(100, $porcentajeAsistencia)),
                'en_riesgo'             => $porcentajeAsistencia < 70,
            ];
        });

        return response()->json([
            'data'         => $resumen,
            'total_fechas' => $totalFechas,
            'message'      => 'OK'
        ]);
    }

    /**
     * Consulta del historial propio de asistencias para estudiantes.
     * Esta es la única ruta de asistencias disponible para ESTUDIANTE.
     */
    public function misAsistencias(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        if (!$user->estudiante) {
            abort(response()->json(['message' => 'No eres un estudiante registrado.', 'error' => 'forbidden'], 403));
        }

        $asistencias = Asistencia::where('grupo_curso_id', $grupoCurso->id)
            ->where('estudiante_id', $user->estudiante->id)
            ->orderBy('fecha', 'desc')
            ->get();

        $totalFechas  = Asistencia::where('grupo_curso_id', $grupoCurso->id)->distinct('fecha')->count('fecha');
        $presentes    = $asistencias->where('estado_asistencia', 'PRESENTE')->count();
        $tardanzas    = $asistencias->where('estado_asistencia', 'TARDANZA')->count();
        $ausentes     = $asistencias->where('estado_asistencia', 'AUSENTE')->count();
        $justificados = $asistencias->where('estado_asistencia', 'JUSTIFICADO')->count();

        $faltasEfectivas = $ausentes + floor($tardanzas / 3);
        $porcentaje = $totalFechas > 0
            ? round((($totalFechas - $faltasEfectivas) / $totalFechas) * 100, 1)
            : 100;

        return response()->json([
            'data' => [
                'asistencias' => $asistencias,
                'resumen'     => [
                    'total_clases'          => $totalFechas,
                    'presentes'             => $presentes,
                    'tardanzas'             => $tardanzas,
                    'ausentes'              => $ausentes,
                    'justificados'          => $justificados,
                    'porcentaje_asistencia' => max(0, min(100, $porcentaje)),
                ]
            ],
            'message' => 'OK'
        ]);
    }
}
