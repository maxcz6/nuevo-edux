<?php

namespace App\Http\Controllers\Api\Aula;

use App\Http\Controllers\Controller;
use App\Models\Aviso;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AvisoController extends Controller
{
    use AuthorizesAulaAccess;

    public function index(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);

        $avisos = Aviso::where('grupo_curso_id', $grupoCurso->id)
            ->with('autorUsuario:id,nombres,apellido_paterno')
            ->orderBy('fijado', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $avisos->items(),
            'meta' => [
                'current_page' => $avisos->currentPage(),
                'last_page'    => $avisos->lastPage(),
                'per_page'     => $avisos->perPage(),
                'total'        => $avisos->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request, $grupo)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $user = auth()->user();

        $validated = $request->validate([
            'titulo'     => 'required|string|max:150',
            'contenido'  => 'required|string',
            'tipo_aviso' => 'nullable|in:INFORMATIVO,URGENTE,RECORDATORIO',
            'fijado'     => 'nullable|boolean'
        ]);

        $aviso = DB::transaction(function () use ($validated, $grupoCurso, $user) {
            $aviso = Aviso::create([
                'institucion_id'    => $user->institucion_id,
                'grupo_curso_id'    => $grupoCurso->id,
                'autor_usuario_id'  => $user->id,
                'titulo'            => $validated['titulo'],
                'contenido'         => $validated['contenido'],
                'tipo_aviso'        => $validated['tipo_aviso'] ?? 'INFORMATIVO',
                'fijado'            => $validated['fijado'] ?? false,
                'estado'            => 'PUBLICADO'
            ]);

            $notificaciones = [];
            $matriculas = $grupoCurso->matriculas()->with('estudiante')->where('estado', 'MATRICULADO')->get();

            foreach ($matriculas as $matricula) {
                if ($matricula->estudiante?->usuario_id) {
                    $notificaciones[] = [
                        'usuario_id'      => $matricula->estudiante->usuario_id,
                        'tipo'            => 'AVISO',
                        'titulo'          => 'Nuevo aviso: ' . $aviso->titulo,
                        'mensaje'         => 'Se ha publicado un nuevo aviso en el curso.',
                        'referencia_tipo' => 'AVISO',
                        'referencia_id'   => $aviso->id,
                        'leido'           => false,
                        'created_at'      => now()
                    ];
                }
            }

            if (!empty($notificaciones)) {
                Notificacion::insert($notificaciones);
            }

            return $aviso;
        });

        return response()->json(['data' => $aviso, 'message' => 'Aviso publicado exitosamente.'], 201);
    }

    public function show($grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, false);
        $aviso = Aviso::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        return response()->json(['data' => $aviso, 'message' => 'OK']);
    }

    public function update(Request $request, $grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $aviso = Aviso::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        $validated = $request->validate([
            'titulo'     => 'sometimes|string|max:150',
            'contenido'  => 'sometimes|string',
            'tipo_aviso' => 'sometimes|in:INFORMATIVO,URGENTE,RECORDATORIO',
            'fijado'     => 'sometimes|boolean'
        ]);

        $aviso->update($validated);

        return response()->json(['data' => $aviso, 'message' => 'Aviso actualizado exitosamente.']);
    }

    public function destroy($grupo, $id)
    {
        $grupoCurso = $this->verifyGroupAccess($grupo, true);
        $aviso = Aviso::where('grupo_curso_id', $grupoCurso->id)->findOrFail($id);

        $aviso->delete();
        return response()->json(null, 204);
    }
}
