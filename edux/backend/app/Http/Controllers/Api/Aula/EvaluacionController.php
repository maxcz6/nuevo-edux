<?php

namespace App\Http\Controllers\Api\Aula;

use App\Http\Controllers\Controller;
use App\Models\Evaluacion;
use App\Models\Pregunta;
use App\Models\OpcionPregunta;
use App\Models\IntentoEvaluacion;
use App\Models\RespuestaEstudiante;
use App\Models\Calificacion;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EvaluacionController extends Controller
{
    use AuthorizesAulaAccess;

    public function index(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        $query = Evaluacion::where('grupo_curso_id', $grupoCurso->id)
            ->withCount('preguntas');

        if ($user->hasRole('ESTUDIANTE')) {
            $query->where('estado', 'PUBLICADA');
        }

        $evaluaciones = $query->orderBy('fecha_inicio', 'asc')
            ->paginate($request->get('per_page', 15));

        // Si es estudiante, adjuntar información de sus intentos
        if ($user->hasRole('ESTUDIANTE') && $user->estudiante) {
            $evaluaciones->getCollection()->transform(function ($eval) use ($user) {
                $intentos = IntentoEvaluacion::where('evaluacion_id', $eval->id)
                    ->where('estudiante_id', $user->estudiante->id)
                    ->get();

                $eval->intentos_realizados = $intentos->count();
                $eval->mejor_nota = $intentos->max('nota');
                $eval->tiene_intento_activo = $intentos->where('estado', 'EN_CURSO')->isNotEmpty();
                return $eval;
            });
        }

        return response()->json([
            'data' => $evaluaciones->items(),
            'meta' => [
                'current_page' => $evaluaciones->currentPage(),
                'last_page'    => $evaluaciones->lastPage(),
                'per_page'     => $evaluaciones->perPage(),
                'total'        => $evaluaciones->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $user = auth()->user();
        $institucionId = $user->institucion_id;

        $validated = $request->validate([
            'unidad_id' => [
                'nullable', 'integer',
                function ($attr, $val, $fail) use ($grupoCurso) {
                    if (!$val) return;
                    // Bug #4: La unidad debe pertenecer al mismo grupo de curso
                    $existe = \App\Models\Unidad::where('id', $val)
                        ->where('grupo_curso_id', $grupoCurso->id)
                        ->exists();
                    if (!$existe) {
                        $fail('La unidad no pertenece a este grupo de curso.');
                    }
                },
            ],
            'docente_id' => [
                'nullable', 'integer',
                function ($attr, $val, $fail) use ($institucionId) {
                    if (!$val) return;
                    // Bug #4: El docente debe pertenecer a la institución
                    if (!\App\Models\Docente::withoutGlobalScopes()->where('id', $val)->where('institucion_id', $institucionId)->exists()) {
                        $fail('El docente no pertenece a tu institución.');
                    }
                },
            ],
            'titulo'                 => 'required|string|max:150',
            'instrucciones'          => 'nullable|string',
            'puntaje_maximo'         => 'nullable|numeric|between:1,20',
            'duracion_minutos'       => 'nullable|integer|min:1|max:300',
            'intentos_permitidos'    => 'nullable|integer|min:1|max:10',
            'fecha_inicio'           => 'required|date',
            'fecha_fin'              => 'required|date|after:fecha_inicio',
            'estado'                 => 'nullable|in:BORRADOR,PROGRAMADA,PUBLICADA,CERRADA',
            'preguntas'              => 'nullable|array',
            'preguntas.*.tipo'       => 'required|in:ALTERNATIVA,VERDADERO_FALSO,RESPUESTA_CORTA,ABIERTA',
            'preguntas.*.enunciado'  => 'required|string',
            'preguntas.*.puntaje'    => 'required|numeric|min:0.5',
            'preguntas.*.opciones'   => 'nullable|array',
            'preguntas.*.opciones.*.texto'       => 'required|string',
            'preguntas.*.opciones.*.es_correcta' => 'required|boolean',
        ]);

        $docenteId = $user->docente?->id ?? $validated['docente_id'] ?? $grupoCurso->docente_id;
        if (!$docenteId) {
            abort(response()->json([
                'message' => 'No se puede crear la evaluación: el grupo no tiene docente asignado.',
                'error'   => 'unprocessable_entity'
            ], 422));
        }

        $evaluacion = DB::transaction(function () use ($grupoCurso, $docenteId, $validated) {
            $eval = Evaluacion::create([
                'grupo_curso_id'      => $grupoCurso->id,
                'unidad_id'           => $validated['unidad_id'] ?? null,
                'docente_id'          => $docenteId,
                'titulo'              => $validated['titulo'],
                'instrucciones'       => $validated['instrucciones'] ?? null,
                'puntaje_maximo'      => $validated['puntaje_maximo'] ?? 20,
                'duracion_minutos'    => $validated['duracion_minutos'] ?? null,
                'intentos_permitidos' => $validated['intentos_permitidos'] ?? 1,
                'fecha_inicio'        => $validated['fecha_inicio'],
                'fecha_fin'           => $validated['fecha_fin'],
                'estado'              => $validated['estado'] ?? 'PROGRAMADA',
            ]);

            if (!empty($validated['preguntas'])) {
                foreach ($validated['preguntas'] as $pIndex => $pData) {
                    $pregunta = Pregunta::create([
                        'evaluacion_id' => $eval->id,
                        'tipo'          => $pData['tipo'],
                        'enunciado'     => $pData['enunciado'],
                        'puntaje'       => $pData['puntaje'],
                        'orden'         => $pIndex + 1,
                    ]);

                    if (!empty($pData['opciones'])) {
                        foreach ($pData['opciones'] as $oIndex => $oData) {
                            OpcionPregunta::create([
                                'pregunta_id' => $pregunta->id,
                                'texto'       => $oData['texto'],
                                'es_correcta' => $oData['es_correcta'],
                                'orden'       => $oIndex + 1,
                            ]);
                        }
                    }
                }
            }

            return $eval;
        });

        return response()->json([
            'data'    => $evaluacion->load('preguntas.opciones'),
            'message' => 'Evaluación creada exitosamente.'
        ], 201);
    }

    public function show($grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        $evaluacion = Evaluacion::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        if ($user->hasRole('ESTUDIANTE')) {
            if ($evaluacion->estado !== 'PUBLICADA') {
                abort(response()->json(['message' => 'La evaluación no está disponible.', 'error' => 'forbidden'], 403));
            }

            $intentos = IntentoEvaluacion::where('evaluacion_id', $evaluacion->id)
                ->where('estudiante_id', $user->estudiante?->id)
                ->get();

            return response()->json([
                'data' => [
                    'id'                  => $evaluacion->id,
                    'titulo'              => $evaluacion->titulo,
                    'instrucciones'       => $evaluacion->instrucciones,
                    'puntaje_maximo'      => $evaluacion->puntaje_maximo,
                    'duracion_minutos'    => $evaluacion->duracion_minutos,
                    'intentos_permitidos' => $evaluacion->intentos_permitidos,
                    'fecha_inicio'        => $evaluacion->fecha_inicio,
                    'fecha_fin'           => $evaluacion->fecha_fin,
                    'intentos_realizados' => $intentos->count(),
                    'intentos'            => $intentos,
                ],
                'message' => 'OK'
            ]);
        }

        // Docente / Admin: ver todo con respuestas correctas
        return response()->json([
            'data'    => $evaluacion->load('preguntas.opciones', 'intentos.estudiante.usuario'),
            'message' => 'OK'
        ]);
    }

    public function update(Request $request, $grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $evaluacion = Evaluacion::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        $validated = $request->validate([
            'unidad_id'              => 'nullable|exists:unidades,id',
            'titulo'                 => 'sometimes|string|max:150',
            'instrucciones'          => 'nullable|string',
            'puntaje_maximo'         => 'sometimes|numeric|between:1,20',
            'duracion_minutos'       => 'nullable|integer|min:1|max:300',
            'intentos_permitidos'    => 'sometimes|integer|min:1|max:10',
            'fecha_inicio'           => 'sometimes|date',
            'fecha_fin'              => 'sometimes|date',
            'estado'                 => 'sometimes|in:BORRADOR,PROGRAMADA,PUBLICADA,CERRADA',
        ]);

        $evaluacion->update($validated);

        return response()->json([
            'data'    => $evaluacion,
            'message' => 'Evaluación actualizada exitosamente.'
        ]);
    }

    public function destroy($grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $evaluacion = Evaluacion::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        $evaluacion->delete();

        return response()->json(['message' => 'Evaluación eliminada exitosamente.']);
    }

    /**
     * Iniciar un nuevo intento de examen (solo estudiantes matriculados).
     */
    public function iniciarIntento(Request $request, $evaluacionId)
    {
        $evaluacion = $this->verifyEvaluacionAccess($evaluacionId, false);
        $user = auth()->user();

        if (!$user->hasRole('ESTUDIANTE') || !$user->estudiante) {
            abort(response()->json(['message' => 'Solo estudiantes matriculados pueden rendir la evaluación.', 'error' => 'forbidden'], 403));
        }

        $now = Carbon::now();
        if ($now->lt($evaluacion->fecha_inicio)) {
            return response()->json(['message' => 'La evaluación aún no ha iniciado.'], 422);
        }
        if ($now->gt($evaluacion->fecha_fin)) {
            return response()->json(['message' => 'El plazo para rendir la evaluación ha expirado.'], 422);
        }

        // Verificar si tiene un intento activo (EN_CURSO)
        $intentoActivo = IntentoEvaluacion::where('evaluacion_id', $evaluacion->id)
            ->where('estudiante_id', $user->estudiante->id)
            ->where('estado', 'EN_CURSO')
            ->first();

        if ($intentoActivo) {
            $intento = $intentoActivo;
        } else {
            // Verificar si superó el número de intentos permitidos
            $totalIntentos = IntentoEvaluacion::where('evaluacion_id', $evaluacion->id)
                ->where('estudiante_id', $user->estudiante->id)
                ->count();

            if ($totalIntentos >= $evaluacion->intentos_permitidos) {
                return response()->json(['message' => 'Has alcanzado el límite de intentos permitidos para esta evaluación.'], 422);
            }

            $intento = IntentoEvaluacion::create([
                'evaluacion_id'  => $evaluacion->id,
                'estudiante_id'  => $user->estudiante->id,
                'numero_intento' => $totalIntentos + 1,
                'fecha_inicio'   => $now,
                'estado'         => 'EN_CURSO',
            ]);
        }

        // Cargar preguntas y opciones SIN el campo es_correcta
        $preguntas = Pregunta::where('evaluacion_id', $evaluacion->id)
            ->with(['opciones' => function ($q) {
                $q->select('id', 'pregunta_id', 'texto', 'orden');
            }])
            ->orderBy('orden', 'asc')
            ->get();

        return response()->json([
            'intento'    => $intento,
            'evaluacion' => [
                'id'               => $evaluacion->id,
                'titulo'           => $evaluacion->titulo,
                'instrucciones'    => $evaluacion->instrucciones,
                'duracion_minutos' => $evaluacion->duracion_minutos,
                'puntaje_maximo'   => $evaluacion->puntaje_maximo,
            ],
            'preguntas'  => $preguntas,
            'message'    => 'Intento iniciado correctamente.'
        ], 200);
    }

    /**
     * Enviar respuestas del examen, autocalificar y guardar en calificaciones.
     */
    public function enviarRespuestas(Request $request, $evaluacionId, $intentoId)
    {
        $evaluacion = $this->verifyEvaluacionAccess($evaluacionId, false);
        $user = auth()->user();

        $intento = IntentoEvaluacion::where('evaluacion_id', $evaluacion->id)
            ->where('id', $intentoId)
            ->where('estudiante_id', $user->estudiante?->id)
            ->firstOrFail();

        $validated = $request->validate([
            'respuestas'                   => 'required|array',
            'respuestas.*.pregunta_id'     => 'required|exists:preguntas,id',
            'respuestas.*.opcion_id'       => 'nullable|exists:opciones_pregunta,id',
            'respuestas.*.respuesta_texto' => 'nullable|string',
        ]);

        $preguntas = Pregunta::where('evaluacion_id', $evaluacion->id)
            ->with('opciones')
            ->get()
            ->keyBy('id');

        $puntajeTotal = 0;

        DB::transaction(function () use ($intento, $evaluacion, $validated, $preguntas, &$puntajeTotal, $user) {
            // Bloquear el intento para evitar doble procesamiento por concurrencia (Bug #6)
            $intentoFresco = IntentoEvaluacion::where('id', $intento->id)->lockForUpdate()->first();

            if (!$intentoFresco || $intentoFresco->estado !== 'EN_CURSO') {
                abort(response()->json(['message' => 'Este intento ya fue finalizado previamente.'], 422));
            }

            foreach ($validated['respuestas'] as $resp) {
                $pregunta = $preguntas->get($resp['pregunta_id']);
                if (!$pregunta) {
                    continue;
                }

                $puntajeObtenido = 0;
                $esRevisado = true;

                if (in_array($pregunta->tipo, ['ALTERNATIVA', 'VERDADERO_FALSO'])) {
                    if (!empty($resp['opcion_id'])) {
                        $opcionCorrecta = $pregunta->opciones->firstWhere('es_correcta', true);
                        if ($opcionCorrecta && $opcionCorrecta->id == $resp['opcion_id']) {
                            $puntajeObtenido = (float) $pregunta->puntaje;
                        }
                    }
                } else {
                    // Preguntas abiertas o de respuesta corta requieren revisión manual posterior
                    $esRevisado = false;
                }

                $puntajeTotal += $puntajeObtenido;

                RespuestaEstudiante::updateOrCreate(
                    [
                        'intento_id'  => $intento->id,
                        'pregunta_id' => $pregunta->id,
                    ],
                    [
                        'opcion_id'            => $resp['opcion_id'] ?? null,
                        'respuesta_texto'      => $resp['respuesta_texto'] ?? null,
                        'puntaje_obtenido'     => $puntajeObtenido,
                        'revisado_por_docente' => $esRevisado,
                    ]
                );
            }

            // Normalizar nota máxima de acuerdo al puntaje de la evaluación (máx 20 por defecto)
            $notaFinal = min((float)$evaluacion->puntaje_maximo, round($puntajeTotal, 2));

            $intento->update([
                'fecha_fin' => Carbon::now(),
                'nota'      => $notaFinal,
                'estado'    => 'FINALIZADO',
            ]);

            // Sincronizar o crear en la tabla calificaciones
            Calificacion::updateOrCreate(
                [
                    'grupo_curso_id' => $evaluacion->grupo_curso_id,
                    'estudiante_id'  => $user->estudiante->id,
                    'tipo_origen'    => 'EVALUACION',
                    'origen_id'      => $evaluacion->id,
                ],
                [
                    'nombre_rubro'   => $evaluacion->titulo,
                    'nota'           => $notaFinal,
                    'registrado_por' => $evaluacion->docente_id,
                ]
            );

            // Emitir notificación al estudiante
            Notificacion::create([
                'usuario_id'       => $user->id,
                'tipo'             => 'NOTA_PUBLICADA',
                'titulo'           => 'Evaluación finalizada',
                'mensaje'          => "Has finalizado la evaluación '{$evaluacion->titulo}' con una calificación de {$notaFinal}.",
                'referencia_tipo'  => 'EVALUACION',
                'referencia_id'    => $evaluacion->id,
            ]);
        }, 3);

        return response()->json([
            'message'      => 'Evaluación enviada y calificada exitosamente.',
            'nota'         => round($puntajeTotal, 2),
            'intento_id'   => $intento->id,
        ], 200);
    }
}
