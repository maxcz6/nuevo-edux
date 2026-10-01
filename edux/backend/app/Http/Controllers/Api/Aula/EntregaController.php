<?php

namespace App\Http\Controllers\Api\Aula;

use App\Http\Controllers\Controller;
use App\Models\EntregaTarea;
use App\Models\Calificacion;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EntregaController extends Controller
{
    use AuthorizesAulaAccess;

    public function index(Request $request, $tarea)
    {
        $tareaModel = $this->verifyTareaAccess($tarea, false);
        $user = auth()->user();

        $query = EntregaTarea::where('tarea_id', $tareaModel->id);

        if ($user->hasRole('ESTUDIANTE') && $user->estudiante) {
            $query->where('estudiante_id', $user->estudiante->id);
        }

        $entregas = $query->with('estudiante.usuario')->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $entregas->items(),
            'meta' => [
                'current_page' => $entregas->currentPage(),
                'last_page'    => $entregas->lastPage(),
                'per_page'     => $entregas->perPage(),
                'total'        => $entregas->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request, $tarea)
    {
        $tareaModel = $this->verifyTareaAccess($tarea, false);
        $user = auth()->user();

        if (!$user->hasRole('ESTUDIANTE') || !$user->estudiante) {
            abort(response()->json(['message' => 'Solo los estudiantes matriculados pueden enviar entregas.', 'error' => 'forbidden'], 403));
        }

        if ($tareaModel->estado !== 'PUBLICADA') {
            return response()->json(['message' => 'La tarea no está disponible para entregas.'], 403);
        }

        $validated = $request->validate([
            'archivo'               => 'nullable|file|mimes:pdf,doc,docx,zip,jpg,png|max:20480',
            'comentario_estudiante' => 'nullable|string',
            'comentario'            => 'nullable|string',
        ]);

        $esTardia = Carbon::now()->gt($tareaModel->fecha_limite);
        if ($esTardia && !$tareaModel->permite_entrega_tardia) {
            return response()->json(['message' => 'La fecha límite de la tarea ha vencido y no se permiten entregas tardías.'], 422);
        }

        $path = null;
        $archivoOriginal = null;
        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $path = Storage::disk('local')->putFile('entregas', $file);
            $archivoOriginal = $file->getClientOriginalName();
        }

        $entrega = EntregaTarea::updateOrCreate(
            [
                'tarea_id'      => $tareaModel->id,
                'estudiante_id' => $user->estudiante->id,
            ],
            [
                'archivo_url'           => $path,
                'archivo_original'      => $archivoOriginal,
                'archivo_nombre'        => $archivoOriginal,
                'comentario_estudiante' => $validated['comentario_estudiante'] ?? $validated['comentario'] ?? null,
                'fecha_entrega'         => Carbon::now(),
                'es_tardia'             => $esTardia,
                'estado'                => 'ENTREGADA'
            ]
        );

        return response()->json(['data' => $entrega, 'message' => 'Entrega registrada exitosamente.'], 201);
    }

    public function show($tarea, $id)
    {
        $tareaModel = $this->verifyTareaAccess($tarea, false);
        $user = auth()->user();

        // Bug #8: Scoped por tarea_id para evitar acceder a una entrega de otra tarea
        $entrega = EntregaTarea::where('tarea_id', $tareaModel->id)
            ->with('estudiante.usuario')
            ->findOrFail($id);

        if ($user->hasRole('ESTUDIANTE') && $user->estudiante && $entrega->estudiante_id !== $user->estudiante->id) {
            abort(response()->json(['message' => 'No tienes acceso a esta entrega.', 'error' => 'forbidden'], 403));
        }

        return response()->json(['data' => $entrega, 'message' => 'OK']);
    }

    public function calificar(Request $request, $tarea, $id)
    {
        $tareaModel = $this->verifyTareaAccess($tarea, true);
        $entrega = EntregaTarea::where('tarea_id', $tareaModel->id)->findOrFail($id);

        $validated = $request->validate([
            'nota'               => 'required|numeric|min:0|max:' . $tareaModel->puntaje_maximo,
            'comentario_docente' => 'nullable|string'
        ]);

        DB::transaction(function () use ($entrega, $tareaModel, $validated) {
            $entrega->update([
                'nota'               => $validated['nota'],
                'comentario_docente' => $validated['comentario_docente'] ?? null,
                'estado'             => 'CALIFICADA'
            ]);

            Calificacion::updateOrCreate(
                [
                    'grupo_curso_id' => $tareaModel->grupo_curso_id,
                    'estudiante_id'  => $entrega->estudiante_id,
                    'tipo_origen'    => 'TAREA',
                    'origen_id'      => $tareaModel->id,
                ],
                [
                    'nombre_rubro'   => $tareaModel->titulo,
                    'nota'           => $validated['nota'],
                    'registrado_por' => auth()->user()->docente?->id,
                ]
            );

            Notificacion::create([
                'usuario_id'      => $entrega->estudiante->usuario_id,
                'tipo'            => 'NOTA_PUBLICADA',
                'titulo'          => 'Tarea calificada',
                'mensaje'         => "Tu entrega de '{$tareaModel->titulo}' ha sido calificada con {$validated['nota']}.",
                'referencia_tipo' => 'TAREA',
                'referencia_id'   => $tareaModel->id,
                'leido'           => false,
            ]);
        });

        return response()->json(['data' => $entrega->fresh(), 'message' => 'Entrega calificada exitosamente.']);
    }

    public function descargar($id)
    {
        $entrega = EntregaTarea::with(['tarea.grupoCurso', 'estudiante'])->findOrFail($id);
        $user = auth()->user();

        if ($entrega->tarea->grupoCurso->institucion_id !== $user->institucion_id) {
            abort(response()->json(['message' => 'Recurso no encontrado.', 'error' => 'not_found'], 404));
        }

        $isOwnerStudent = $user->hasRole('ESTUDIANTE') && $user->estudiante && $entrega->estudiante_id === $user->estudiante->id;
        $isTeacher = $user->hasRole('DOCENTE') && $user->docente && $entrega->tarea->grupoCurso->docente_id === $user->docente->id;
        $isAdmin = $user->hasRole('ADMINISTRADOR') || $user->hasRole('SUPERADMIN') || $user->hasRole('COORDINADOR');

        if (!$isOwnerStudent && !$isTeacher && !$isAdmin) {
            abort(response()->json(['message' => 'No tienes permiso para descargar esta entrega.', 'error' => 'forbidden'], 403));
        }

        if (!$entrega->archivo_url || !Storage::disk('local')->exists($entrega->archivo_url)) {
            abort(response()->json(['message' => 'Archivo no encontrado.', 'error' => 'not_found'], 404));
        }

        return Storage::disk('local')->download($entrega->archivo_url, $entrega->archivo_original ?? basename($entrega->archivo_url));
    }
}
