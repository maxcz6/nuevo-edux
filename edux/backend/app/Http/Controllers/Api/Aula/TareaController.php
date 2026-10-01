<?php

namespace App\Http\Controllers\Api\Aula;

use App\Http\Controllers\Controller;
use App\Models\Tarea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TareaController extends Controller
{
    use AuthorizesAulaAccess;

    public function index(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        $query = Tarea::where('grupo_curso_id', $grupoCurso->id);

        if ($user->hasRole('ESTUDIANTE')) {
            $query->where('estado', 'PUBLICADA');
            if ($user->estudiante) {
                $estudianteId = $user->estudiante->id;
                $query->with(['entregas' => function ($q) use ($estudianteId) {
                    $q->where('estudiante_id', $estudianteId);
                }]);
            }
        }

        $tareas = $query->orderBy('fecha_limite', 'asc')->paginate($request->get('per_page', 15));

        $items = collect($tareas->items())->map(function ($tarea) use ($user) {
            $data = $tarea->toArray();
            if ($user->hasRole('ESTUDIANTE') && $user->estudiante) {
                $entrega = $tarea->entregas->first();
                $data['mi_entrega'] = $entrega ? [
                    'id'                    => $entrega->id,
                    'estado'                => $entrega->estado,
                    'fecha_entrega'         => $entrega->fecha_entrega,
                    'es_tardia'             => $entrega->es_tardia,
                    'tiene_archivo'         => $entrega->tiene_archivo,
                    'nota'                  => $entrega->nota,
                    'comentario_estudiante' => $entrega->comentario_estudiante,
                    'comentario_docente'    => $entrega->comentario_docente,
                ] : null;
            }
            return $data;
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $tareas->currentPage(),
                'last_page'    => $tareas->lastPage(),
                'per_page'     => $tareas->perPage(),
                'total'        => $tareas->total(),
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
            'unidad_id'    => [
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
            'docente_id'             => [
                'nullable', 'integer',
                function ($attr, $val, $fail) use ($institucionId) {
                    if (!$val) return;
                    if (!\App\Models\Docente::withoutGlobalScopes()->where('id', $val)->where('institucion_id', $institucionId)->exists()) {
                        $fail('El docente no pertenece a tu institución.');
                    }
                },
            ],
            'titulo'                    => 'required|string|max:150',
            'descripcion'               => 'nullable|string',
            'comentarios_instrucciones' => 'nullable|string',
            'enlace_recurso'            => 'nullable|url|max:255',
            'archivo'                   => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,jpg,png|max:20480',
            'puntaje_maximo'            => 'nullable|numeric|between:1,20',
            'fecha_inicio'              => 'nullable|date',
            'fecha_limite'              => 'required|date',
            'permite_entrega_tardia'    => 'nullable|boolean',
            'estado'                    => 'nullable|in:BORRADOR,PUBLICADA'
        ]);

        $docenteId = $user->docente?->id ?? $validated['docente_id'] ?? $grupoCurso->docente_id;
        if (!$docenteId) {
            abort(response()->json([
                'message' => 'No se puede crear la tarea: el grupo no tiene un docente asignado y no se especificó docente_id.',
                'error'   => 'unprocessable_entity'
            ], 422));
        }

        $validated['grupo_curso_id']         = $grupoCurso->id;
        $validated['docente_id']             = $docenteId;
        $validated['puntaje_maximo']         = $validated['puntaje_maximo'] ?? 20;
        $validated['permite_entrega_tardia'] = $validated['permite_entrega_tardia'] ?? false;
        $validated['estado']                 = $validated['estado'] ?? 'PUBLICADA';

        // Manejo de archivo adjunto para la actividad
        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $ext = strtolower($file->getClientOriginalExtension());
            $tipoMap = [
                'pdf'  => 'PDF',
                'doc'  => 'DOCX',
                'docx' => 'DOCX',
                'ppt'  => 'PPTX',
                'pptx' => 'PPTX',
                'xls'  => 'XLSX',
                'xlsx' => 'XLSX',
                'txt'  => 'TXT',
                'zip'  => 'ZIP',
                'jpg'  => 'IMAGEN',
                'jpeg' => 'IMAGEN',
                'png'  => 'IMAGEN',
            ];
            $validated['archivo_adjunto_url']      = Storage::disk('local')->putFile('tareas_adjuntos', $file);
            $validated['archivo_nombre_original']  = $file->getClientOriginalName();
            $validated['archivo_tipo']             = $tipoMap[$ext] ?? 'DOCUMENTO';
        }

        $tarea = Tarea::create($validated);

        return response()->json(['data' => $tarea, 'message' => 'Actividad creada exitosamente.'], 201);
    }

    public function show($grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $user = auth()->user();

        $tarea = Tarea::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        if ($user->hasRole('ESTUDIANTE') && $tarea->estado !== 'PUBLICADA') {
            abort(response()->json(['message' => 'Tarea no disponible.', 'error' => 'forbidden'], 403));
        }

        return response()->json(['data' => $tarea, 'message' => 'OK']);
    }

    public function update(Request $request, $grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $tarea = Tarea::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        $validated = $request->validate([
            'unidad_id'              => [
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
            'titulo'                    => 'sometimes|string|max:150',
            'descripcion'               => 'nullable|string',
            'comentarios_instrucciones' => 'nullable|string',
            'enlace_recurso'            => 'nullable|url|max:255',
            'archivo'                   => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,jpg,png|max:20480',
            'puntaje_maximo'            => 'sometimes|numeric|between:1,20',
            'fecha_inicio'              => 'nullable|date',
            'fecha_limite'              => 'sometimes|date',
            'permite_entrega_tardia'    => 'nullable|boolean',
            'estado'                    => 'sometimes|in:BORRADOR,PUBLICADA'
        ]);

        if ($request->hasFile('archivo')) {
            // Eliminar archivo previo si existía
            if ($tarea->archivo_adjunto_url && Storage::disk('local')->exists($tarea->archivo_adjunto_url)) {
                Storage::disk('local')->delete($tarea->archivo_adjunto_url);
            }

            $file = $request->file('archivo');
            $ext = strtolower($file->getClientOriginalExtension());
            $tipoMap = [
                'pdf'  => 'PDF',
                'doc'  => 'DOCX',
                'docx' => 'DOCX',
                'ppt'  => 'PPTX',
                'pptx' => 'PPTX',
                'xls'  => 'XLSX',
                'xlsx' => 'XLSX',
                'txt'  => 'TXT',
                'zip'  => 'ZIP',
                'jpg'  => 'IMAGEN',
                'jpeg' => 'IMAGEN',
                'png'  => 'IMAGEN',
            ];
            $validated['archivo_adjunto_url']     = Storage::disk('local')->putFile('tareas_adjuntos', $file);
            $validated['archivo_nombre_original'] = $file->getClientOriginalName();
            $validated['archivo_tipo']            = $tipoMap[$ext] ?? 'DOCUMENTO';
        }

        $tarea->update($validated);

        return response()->json(['data' => $tarea, 'message' => 'Actividad actualizada exitosamente.']);
    }

    public function destroy($grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $tarea = Tarea::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        if ($tarea->entregas()->exists()) {
            return response()->json(['message' => 'No se puede eliminar una tarea con entregas registradas.'], 422);
        }

        if ($tarea->archivo_adjunto_url && Storage::disk('local')->exists($tarea->archivo_adjunto_url)) {
            Storage::disk('local')->delete($tarea->archivo_adjunto_url);
        }

        $tarea->delete();
        return response()->json(null, 204);
    }

    /**
     * Descargar el archivo adjunto (PDF, Word, etc.) colocado por el docente.
     */
    public function descargarAdjunto($id)
    {
        $tarea = Tarea::findOrFail($id);
        $this->verifyGroupAccess($tarea->grupo_curso_id, false);

        $user = auth()->user();
        if ($user->hasRole('ESTUDIANTE') && $tarea->estado !== 'PUBLICADA') {
            abort(response()->json(['message' => 'Actividad no disponible para descarga.', 'error' => 'forbidden'], 403));
        }

        if (!$tarea->archivo_adjunto_url || !Storage::disk('local')->exists($tarea->archivo_adjunto_url)) {
            abort(response()->json(['message' => 'La actividad no tiene un archivo adjunto disponible.', 'error' => 'not_found'], 404));
        }

        $nombreDescarga = $tarea->archivo_nombre_original ?: ($tarea->titulo . '.' . strtolower($tarea->archivo_tipo ?? 'pdf'));

        return Storage::disk('local')->download($tarea->archivo_adjunto_url, $nombreDescarga);
    }
}
