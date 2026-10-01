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
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class EvaluacionController extends Controller
{
    use AuthorizesAulaAccess;

    public function index(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        $query = Evaluacion::where('grupo_curso_id', $grupoCurso->id)
            ->withCount(['preguntas', 'intentos']);

        if ($user->hasRole('ESTUDIANTE')) {
            $query->where('estado', 'PUBLICADA');
        }

        $evaluaciones = $query->orderBy('fecha_inicio', 'asc')
            ->paginate($request->get('per_page', 50));

        // Si es estudiante, adjuntar información de sus intentos y disponibilidad
        if ($user->hasRole('ESTUDIANTE') && $user->estudiante) {
            $now = Carbon::now();
            $evaluaciones->getCollection()->transform(function ($eval) use ($user, $now) {
                $intentos = IntentoEvaluacion::where('evaluacion_id', $eval->id)
                    ->where('estudiante_id', $user->estudiante->id)
                    ->get();

                $ultimoIntento = $intentos->sortByDesc('id')->first();

                $eval->intentos_realizados = $intentos->count();
                $eval->mejor_nota = $intentos->max('nota');
                $eval->tiene_intento_activo = $intentos->where('estado', 'EN_CURSO')->isNotEmpty();
                $eval->intento_activo_id = $intentos->where('estado', 'EN_CURSO')->first()?->id;
                $eval->esta_expirada = $now->gt($eval->fecha_fin);
                $eval->ha_iniciado = $now->gte($eval->fecha_inicio);
                $eval->revision_disponible = $eval->esta_expirada;
                $eval->ultimo_intento_estado = $ultimoIntento?->estado;
                $eval->ultimo_intento_archivo = $ultimoIntento?->archivo_estudiante_nombre;
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
                    if (!\App\Models\Docente::withoutGlobalScopes()->where('id', $val)->where('institucion_id', $institucionId)->exists()) {
                        $fail('El docente no pertenece a tu institución.');
                    }
                },
            ],
            'titulo'                     => 'required|string|max:150',
            'instrucciones'              => 'nullable|string',
            'tipo_evaluacion'            => 'nullable|in:QUIZ,ARCHIVO,MIXTO',
            'permite_archivo_estudiante' => 'nullable|boolean',
            'archivo_adjunto'            => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,jpg,png|max:25600',
            'puntaje_maximo'             => 'nullable|numeric|between:1,20',
            'duracion_minutos'           => 'nullable|integer|min:1|max:300',
            'intentos_permitidos'        => 'nullable|integer|min:1|max:10',
            'fecha_inicio'               => 'required|date',
            'fecha_fin'                  => 'required|date|after:fecha_inicio',
            'estado'                     => 'nullable|in:BORRADOR,PROGRAMADA,PUBLICADA,CERRADA,OCULTA',
            'preguntas'                  => 'nullable|array',
            'preguntas.*.tipo'           => 'required|in:ALTERNATIVA,VERDADERO_FALSO,RESPUESTA_CORTA,ABIERTA',
            'preguntas.*.enunciado'      => 'required|string',
            'preguntas.*.puntaje'        => 'required|numeric|min:0.5',
            'preguntas.*.opciones'       => 'nullable|array',
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

        $archivoPath = null;
        $archivoNombreOriginal = null;
        if ($request->hasFile('archivo_adjunto')) {
            $file = $request->file('archivo_adjunto');
            $archivoPath = Storage::disk('local')->putFile('evaluaciones', $file);
            $archivoNombreOriginal = $file->getClientOriginalName();
        }

        $tipoEval = $validated['tipo_evaluacion'] ?? ($archivoPath ? 'ARCHIVO' : 'QUIZ');
        $permiteArchivo = filter_var($request->input('permite_archivo_estudiante', $tipoEval === 'ARCHIVO' || $tipoEval === 'MIXTO'), FILTER_VALIDATE_BOOLEAN);

        $evaluacion = DB::transaction(function () use ($grupoCurso, $docenteId, $validated, $archivoPath, $archivoNombreOriginal, $tipoEval, $permiteArchivo) {
            $eval = Evaluacion::create([
                'grupo_curso_id'             => $grupoCurso->id,
                'unidad_id'                  => $validated['unidad_id'] ?? null,
                'docente_id'                 => $docenteId,
                'titulo'                     => $validated['titulo'],
                'instrucciones'              => $validated['instrucciones'] ?? null,
                'archivo_adjunto'            => $archivoPath,
                'archivo_nombre_original'    => $archivoNombreOriginal,
                'tipo_evaluacion'            => $tipoEval,
                'permite_archivo_estudiante' => $permiteArchivo,
                'puntaje_maximo'             => $validated['puntaje_maximo'] ?? 20,
                'duracion_minutos'           => $validated['duracion_minutos'] ?? null,
                'intentos_permitidos'        => $validated['intentos_permitidos'] ?? 1,
                'fecha_inicio'               => $validated['fecha_inicio'],
                'fecha_fin'                  => $validated['fecha_fin'],
                'estado'                     => $validated['estado'] ?? 'PUBLICADA',
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

            $now = Carbon::now();
            $intentos = IntentoEvaluacion::where('evaluacion_id', $evaluacion->id)
                ->where('estudiante_id', $user->estudiante?->id)
                ->get();

            $estaExpirada = $now->gt($evaluacion->fecha_fin);

            return response()->json([
                'data' => [
                    'id'                          => $evaluacion->id,
                    'titulo'                      => $evaluacion->titulo,
                    'instrucciones'               => $evaluacion->instrucciones,
                    'tipo_evaluacion'             => $evaluacion->tipo_evaluacion,
                    'tiene_archivo_adjunto'       => $evaluacion->tiene_archivo_adjunto,
                    'archivo_nombre_original'     => $evaluacion->archivo_nombre_original,
                    'permite_archivo_estudiante'  => $evaluacion->permite_archivo_estudiante,
                    'puntaje_maximo'              => $evaluacion->puntaje_maximo,
                    'duracion_minutos'            => $evaluacion->duracion_minutos,
                    'intentos_permitidos'         => $evaluacion->intentos_permitidos,
                    'fecha_inicio'                => $evaluacion->fecha_inicio,
                    'fecha_fin'                   => $evaluacion->fecha_fin,
                    'esta_expirada'               => $estaExpirada,
                    'revision_disponible'         => $estaExpirada,
                    'intentos_realizados'         => $intentos->count(),
                    'intentos'                    => $intentos,
                ],
                'message' => 'OK'
            ]);
        }

        // Docente / Admin: ver todo con respuestas correctas e intentos de estudiantes
        return response()->json([
            'data'    => $evaluacion->load(['preguntas.opciones', 'intentos.estudiante.usuario']),
            'message' => 'OK'
        ]);
    }

    public function update(Request $request, $grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $evaluacion = Evaluacion::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        $validated = $request->validate([
            'unidad_id'                  => 'nullable|exists:unidades,id',
            'titulo'                     => 'sometimes|string|max:150',
            'instrucciones'              => 'nullable|string',
            'tipo_evaluacion'            => 'sometimes|in:QUIZ,ARCHIVO,MIXTO',
            'permite_archivo_estudiante' => 'sometimes|boolean',
            'archivo_adjunto'            => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,jpg,png|max:25600',
            'puntaje_maximo'             => 'sometimes|numeric|between:1,20',
            'duracion_minutos'           => 'nullable|integer|min:1|max:300',
            'intentos_permitidos'        => 'sometimes|integer|min:1|max:10',
            'fecha_inicio'               => 'sometimes|date',
            'fecha_fin'                  => 'sometimes|date',
            'estado'                     => 'sometimes|in:BORRADOR,PROGRAMADA,PUBLICADA,CERRADA,OCULTA',
        ]);

        if ($request->hasFile('archivo_adjunto')) {
            if ($evaluacion->archivo_adjunto && Storage::disk('local')->exists($evaluacion->archivo_adjunto)) {
                Storage::disk('local')->delete($evaluacion->archivo_adjunto);
            }
            $file = $request->file('archivo_adjunto');
            $validated['archivo_adjunto'] = Storage::disk('local')->putFile('evaluaciones', $file);
            $validated['archivo_nombre_original'] = $file->getClientOriginalName();
        }

        $evaluacion->update($validated);

        return response()->json([
            'data'    => $evaluacion->fresh(['preguntas.opciones']),
            'message' => 'Evaluación actualizada exitosamente.'
        ]);
    }

    public function destroy($grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $evaluacion = Evaluacion::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        if ($evaluacion->archivo_adjunto && Storage::disk('local')->exists($evaluacion->archivo_adjunto)) {
            Storage::disk('local')->delete($evaluacion->archivo_adjunto);
        }

        $evaluacion->delete();

        return response()->json(['message' => 'Evaluación eliminada exitosamente.']);
    }

    public function descargarAdjunto($evaluacionId)
    {
        $evaluacion = $this->verifyEvaluacionAccess($evaluacionId, false);
        $user = auth()->user();

        if ($user->hasRole('ESTUDIANTE') && $evaluacion->estado !== 'PUBLICADA') {
            abort(response()->json(['message' => 'La evaluación no está disponible para descarga.', 'error' => 'forbidden'], 403));
        }

        if (!$evaluacion->archivo_adjunto || !Storage::disk('local')->exists($evaluacion->archivo_adjunto)) {
            abort(response()->json(['message' => 'La evaluación no cuenta con un archivo adjunto disponible.', 'error' => 'not_found'], 404));
        }

        return Storage::disk('local')->download(
            $evaluacion->archivo_adjunto,
            $evaluacion->archivo_nombre_original ?? 'evaluacion_' . $evaluacion->id . '.pdf'
        );
    }

    public function agregarPregunta(Request $request, $evaluacionId)
    {
        $evaluacion = $this->verifyEvaluacionAccess($evaluacionId, true);

        $validated = $request->validate([
            'tipo'                   => 'required|in:ALTERNATIVA,VERDADERO_FALSO,RESPUESTA_CORTA,ABIERTA',
            'enunciado'              => 'required|string',
            'puntaje'                => 'required|numeric|min:0.5',
            'opciones'               => 'nullable|array',
            'opciones.*.texto'       => 'required|string',
            'opciones.*.es_correcta' => 'required|boolean',
        ]);

        $orden = Pregunta::where('evaluacion_id', $evaluacion->id)->max('orden') + 1;

        $pregunta = DB::transaction(function () use ($evaluacion, $validated, $orden) {
            $preg = Pregunta::create([
                'evaluacion_id' => $evaluacion->id,
                'tipo'          => $validated['tipo'],
                'enunciado'     => $validated['enunciado'],
                'puntaje'       => $validated['puntaje'],
                'orden'         => $orden,
            ]);

            if (!empty($validated['opciones'])) {
                foreach ($validated['opciones'] as $oIdx => $oData) {
                    OpcionPregunta::create([
                        'pregunta_id' => $preg->id,
                        'texto'       => $oData['texto'],
                        'es_correcta' => $oData['es_correcta'],
                        'orden'       => $oIdx + 1,
                    ]);
                }
            }

            return $preg;
        });

        return response()->json([
            'data'    => $pregunta->load('opciones'),
            'message' => 'Pregunta agregada exitosamente.'
        ], 201);
    }

    public function eliminarPregunta($evaluacionId, $preguntaId)
    {
        $this->verifyEvaluacionAccess($evaluacionId, true);

        $pregunta = Pregunta::where('evaluacion_id', $evaluacionId)->findOrFail($preguntaId);
        $pregunta->delete();

        return response()->json(['message' => 'Pregunta eliminada exitosamente.']);
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

        if ($evaluacion->estado !== 'PUBLICADA') {
            abort(response()->json(['message' => 'La evaluación no está disponible para los estudiantes.', 'error' => 'forbidden'], 403));
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

        // Cargar preguntas y opciones SIN revelar es_correcta (Google Forms style)
        $preguntas = Pregunta::where('evaluacion_id', $evaluacion->id)
            ->with(['opciones' => function ($q) {
                $q->select('id', 'pregunta_id', 'texto', 'orden');
            }])
            ->orderBy('orden', 'asc')
            ->get();

        return response()->json([
            'intento'    => $intento,
            'evaluacion' => [
                'id'                         => $evaluacion->id,
                'titulo'                     => $evaluacion->titulo,
                'instrucciones'              => $evaluacion->instrucciones,
                'tipo_evaluacion'            => $evaluacion->tipo_evaluacion,
                'tiene_archivo_adjunto'      => $evaluacion->tiene_archivo_adjunto,
                'archivo_nombre_original'    => $evaluacion->archivo_nombre_original,
                'permite_archivo_estudiante' => $evaluacion->permite_archivo_estudiante,
                'duracion_minutos'           => $evaluacion->duracion_minutos,
                'puntaje_maximo'             => $evaluacion->puntaje_maximo,
                'fecha_fin'                  => $evaluacion->fecha_fin,
            ],
            'preguntas'  => $preguntas,
            'message'    => 'Intento iniciado correctamente.'
        ], 200);
    }

    /**
     * Enviar respuestas del quiz (formulario de Google).
     */
    public function enviarRespuestas(Request $request, $evaluacionId, $intentoId)
    {
        $evaluacion = $this->verifyEvaluacionAccess($evaluacionId, false);
        $user = auth()->user();

        if ($user->hasRole('ESTUDIANTE') && $evaluacion->estado !== 'PUBLICADA') {
            abort(response()->json(['message' => 'La evaluación no está disponible para los estudiantes.', 'error' => 'forbidden'], 403));
        }

        $intento = IntentoEvaluacion::where('evaluacion_id', $evaluacion->id)
            ->where('id', $intentoId)
            ->where('estudiante_id', $user->estudiante?->id)
            ->firstOrFail();

        $now = Carbon::now();
        if ($now->gt($evaluacion->fecha_fin->addMinutes(5))) { // 5 min margen de latencia de red
            return response()->json(['message' => 'El plazo para enviar la evaluación ha expirado. No se aceptan envíos fuera de hora límite.'], 422);
        }

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

        DB::transaction(function () use ($intento, $evaluacion, $validated, $preguntas, &$puntajeTotal, $user, $now) {
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

            $notaFinal = min((float)$evaluacion->puntaje_maximo, round($puntajeTotal, 2));

            $intento->update([
                'fecha_fin' => $now,
                'nota'      => $notaFinal,
                'estado'    => 'FINALIZADO',
            ]);

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

            Notificacion::create([
                'usuario_id'       => $user->id,
                'tipo'             => 'NOTA_PUBLICADA',
                'titulo'           => 'Evaluación enviada con éxito',
                'mensaje'          => "Has completado la evaluación '{$evaluacion->titulo}'. Las respuestas correctas estarán disponibles una vez finalizado el horario del examen.",
                'referencia_tipo'  => 'EVALUACION',
                'referencia_id'    => $evaluacion->id,
            ]);
        }, 3);

        $revisionDisponible = $now->gte($evaluacion->fecha_fin);

        return response()->json([
            'message'             => 'Evaluación enviada y registrada exitosamente.',
            'nota'                => round($puntajeTotal, 2),
            'intento_id'          => $intento->id,
            'revision_disponible' => $revisionDisponible,
            'mensaje_seguridad'   => $revisionDisponible
                ? 'Ya puedes revisar las respuestas correctas de la evaluación.'
                : 'Por seguridad académica, las respuestas correctas y el desglose de preguntas se mostrarán una vez concluido el horario de la evaluación (' . $evaluacion->fecha_fin->format('d/m/Y H:i') . ').'
        ], 200);
    }

    /**
     * Subir archivo de solución por parte del estudiante (evaluación con archivo).
     * Regla estricta: NO se puede enviar pasada la hora y fecha límite.
     */
    public function subirArchivoIntento(Request $request, $evaluacionId, $intentoId)
    {
        $evaluacion = $this->verifyEvaluacionAccess($evaluacionId, false);
        $user = auth()->user();

        if (!$user->hasRole('ESTUDIANTE') || !$user->estudiante) {
            abort(response()->json(['message' => 'Solo estudiantes pueden enviar archivos de evaluación.'], 403));
        }

        if ($evaluacion->estado !== 'PUBLICADA') {
            abort(response()->json(['message' => 'La evaluación no está disponible para los estudiantes.', 'error' => 'forbidden'], 403));
        }

        $now = Carbon::now();
        // BLOQUEO ESTRICTO: Pasada la fecha y hora límite, NO se puede enviar el archivo
        if ($now->gt($evaluacion->fecha_fin)) {
            return response()->json([
                'message' => 'El plazo para enviar la evaluación ha expirado. No se aceptan entregas fuera de fecha y hora límite (' . $evaluacion->fecha_fin->format('d/m/Y H:i') . ').'
            ], 422);
        }

        $intento = IntentoEvaluacion::where('evaluacion_id', $evaluacion->id)
            ->where('id', $intentoId)
            ->where('estudiante_id', $user->estudiante->id)
            ->firstOrFail();

        $request->validate([
            'archivo'    => 'required|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,jpg,png|max:25600',
            'comentario' => 'nullable|string|max:500',
        ]);

        $file = $request->file('archivo');
        $archivoPath = Storage::disk('local')->putFile('evaluaciones_entregas', $file);

        if ($intento->archivo_estudiante && Storage::disk('local')->exists($intento->archivo_estudiante)) {
            Storage::disk('local')->delete($intento->archivo_estudiante);
        }

        $intento->update([
            'archivo_estudiante'        => $archivoPath,
            'archivo_estudiante_nombre' => $file->getClientOriginalName(),
            'comentario_estudiante'     => $request->input('comentario'),
            'fecha_fin'                 => $now,
            'estado'                    => 'FINALIZADO',
        ]);

        Notificacion::create([
            'usuario_id'       => $user->id,
            'tipo'             => 'SISTEMA',
            'titulo'           => 'Archivo de evaluación entregado',
            'mensaje'          => "Has entregado con éxito el archivo para la evaluación '{$evaluacion->titulo}'.",
            'referencia_tipo'  => 'EVALUACION',
            'referencia_id'    => $evaluacion->id,
        ]);

        return response()->json([
            'data'    => $intento,
            'message' => 'Archivo de evaluación entregado exitosamente dentro del plazo.'
        ], 200);
    }

    public function descargarArchivoEstudiante($evaluacionId, $intentoId)
    {
        $evaluacion = $this->verifyEvaluacionAccess($evaluacionId, false);
        $user = auth()->user();

        $intento = IntentoEvaluacion::where('evaluacion_id', $evaluacion->id)
            ->findOrFail($intentoId);

        // Si es estudiante, solo puede descargar su propio archivo
        if ($user->hasRole('ESTUDIANTE') && $intento->estudiante_id !== $user->estudiante?->id) {
            abort(response()->json(['message' => 'No tienes permiso para descargar este archivo.'], 403));
        }

        if (!$intento->archivo_estudiante || !Storage::disk('local')->exists($intento->archivo_estudiante)) {
            abort(response()->json(['message' => 'No se encontró el archivo entregado por el estudiante.'], 404));
        }

        return Storage::disk('local')->download(
            $intento->archivo_estudiante,
            $intento->archivo_estudiante_nombre ?? 'entrega_estudiante_' . $intento->id . '.pdf'
        );
    }

    /**
     * Revisión detallada de preguntas y respuestas.
     * Regla estricta: NO se muestran respuestas correctas hasta que concluya la fecha/hora de fin.
     */
    /**
     * Calificar intento por parte del docente o coordinador (colocar nota y retroalimentación).
     */
    public function calificarIntento(Request $request, $evaluacionId, $intentoId)
    {
        $evaluacion = $this->verifyEvaluacionAccess($evaluacionId, true);
        $user = auth()->user();

        $validated = $request->validate([
            'nota'              => 'required|numeric|min:0|max:' . $evaluacion->puntaje_maximo,
            'retroalimentacion' => 'nullable|string|max:1000',
        ]);

        $intento = IntentoEvaluacion::where('evaluacion_id', $evaluacion->id)
            ->with('estudiante.usuario')
            ->findOrFail($intentoId);

        $notaFinal = round((float)$validated['nota'], 2);

        $intento->update([
            'nota'              => $notaFinal,
            'retroalimentacion' => $validated['retroalimentacion'] ?? null,
            'estado'            => 'FINALIZADO',
        ]);

        // Sincronizar con el consolidado oficial de notas (Calificacion)
        Calificacion::updateOrCreate(
            [
                'grupo_curso_id' => $evaluacion->grupo_curso_id,
                'estudiante_id'  => $intento->estudiante_id,
                'tipo_origen'    => 'EVALUACION',
                'origen_id'      => $evaluacion->id,
            ],
            [
                'nombre_rubro'   => $evaluacion->titulo,
                'nota'           => $notaFinal,
                'registrado_por' => $user->docente?->id ?? $evaluacion->docente_id,
            ]
        );

        if ($intento->estudiante?->usuario_id) {
            Notificacion::create([
                'usuario_id'      => $intento->estudiante->usuario_id,
                'tipo'            => 'NOTA_PUBLICADA',
                'titulo'          => 'Calificación de evaluación asignada',
                'mensaje'         => "Tu evaluación '{$evaluacion->titulo}' ha sido calificada con nota {$notaFinal} / {$evaluacion->puntaje_maximo}.",
                'referencia_tipo' => 'EVALUACION',
                'referencia_id'   => $evaluacion->id,
            ]);
        }

        return response()->json([
            'data'    => $intento->fresh(['estudiante.usuario']),
            'message' => 'Calificación asignada y registrada exitosamente.'
        ]);
    }

    /**
     * Revisión detallada de preguntas y respuestas.
     * Regla estricta: para el estudiante, NO se muestran respuestas correctas hasta que concluya fecha_fin.
     * Para el docente, puede revisar los intentos de sus alumnos en cualquier momento.
     */
    public function revisionIntento($evaluacionId, $intentoId)
    {
        $evaluacion = $this->verifyEvaluacionAccess($evaluacionId, false);
        $user = auth()->user();

        $intentoQuery = IntentoEvaluacion::where('evaluacion_id', $evaluacion->id)
            ->where('id', $intentoId);

        if ($user->hasRole('ESTUDIANTE')) {
            $intentoQuery->where('estudiante_id', $user->estudiante?->id);
            $now = Carbon::now();
            if ($now->lt($evaluacion->fecha_fin)) {
                return response()->json([
                    'message'             => 'Por seguridad académica, las respuestas correctas se mostrarán una vez concluido el horario de la evaluación (' . $evaluacion->fecha_fin->format('d/m/Y H:i') . ').',
                    'revision_disponible' => false,
                ], 403);
            }
        }

        $intento = $intentoQuery->with('estudiante.usuario')->firstOrFail();

        $preguntas = Pregunta::where('evaluacion_id', $evaluacion->id)
            ->with('opciones')
            ->orderBy('orden')
            ->get();

        $respuestas = RespuestaEstudiante::where('intento_id', $intento->id)
            ->get()
            ->keyBy('pregunta_id');

        $revision = $preguntas->map(function ($p) use ($respuestas) {
            $resp = $respuestas->get($p->id);
            return [
                'id'               => $p->id,
                'enunciado'        => $p->enunciado,
                'tipo'             => $p->tipo,
                'puntaje'          => $p->puntaje,
                'puntaje_obtenido' => $resp?->puntaje_obtenido ?? 0,
                'opcion_elegida'   => $resp?->opcion_id,
                'respuesta_texto'  => $resp?->respuesta_texto,
                'opciones'         => $p->opciones->map(fn($o) => [
                    'id'          => $o->id,
                    'texto'       => $o->texto,
                    'es_correcta' => $o->es_correcta,
                ]),
            ];
        });

        return response()->json([
            'intento'             => $intento,
            'revision'            => $revision,
            'revision_disponible' => true,
        ]);
    }
}
