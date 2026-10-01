<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apoderado;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\Matricula;
use App\Models\Calificacion;
use App\Models\Asistencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ApoderadoController extends Controller
{
    public function index(Request $request)
    {
        $apoderados = Apoderado::where('institucion_id', auth()->user()->institucion_id)
            ->with(['usuario', 'estudiantes.usuario'])
            ->orderBy('apellidos', 'asc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $apoderados->items(),
            'meta' => [
                'current_page' => $apoderados->currentPage(),
                'last_page'    => $apoderados->lastPage(),
                'per_page'     => $apoderados->perPage(),
                'total'        => $apoderados->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombres'          => 'required|string|max:120',
            'apellidos'        => 'required|string|max:150',
            'documento_numero' => 'nullable|string|max:20',
            'telefono'         => 'nullable|string|max:30',
            'correo'           => 'nullable|email|max:150',
            'crear_usuario'    => 'nullable|boolean',
            'password'         => 'nullable|required_if:crear_usuario,true|min:8',
        ]);

        $institucionId = auth()->user()->institucion_id;

        $apoderado = DB::transaction(function () use ($validated, $institucionId) {
            $usuarioId = null;

            if (!empty($validated['crear_usuario']) && !empty($validated['correo'])) {
                $usuario = Usuario::create([
                    'institucion_id'   => $institucionId,
                    'username'         => $validated['correo'],
                    'email'            => $validated['correo'],
                    'password'         => Hash::make($validated['password']),
                    'nombres'          => $validated['nombres'],
                    'apellido_paterno' => explode(' ', $validated['apellidos'])[0] ?? $validated['apellidos'],
                    'apellido_materno' => explode(' ', $validated['apellidos'])[1] ?? null,
                    'documento_numero' => $validated['documento_numero'] ?? null,
                    'telefono'         => $validated['telefono'] ?? null,
                ]);

                $rol = Rol::where('nombre', 'APODERADO')->first();
                if ($rol) {
                    $usuario->roles()->attach($rol->id);
                }

                $usuarioId = $usuario->id;
            }

            return Apoderado::create([
                'institucion_id'   => $institucionId,
                'usuario_id'       => $usuarioId,
                'nombres'          => $validated['nombres'],
                'apellidos'        => $validated['apellidos'],
                'documento_numero' => $validated['documento_numero'] ?? null,
                'telefono'         => $validated['telefono'] ?? null,
                'correo'           => $validated['correo'] ?? null,
            ]);
        });

        return response()->json([
            'data'    => $apoderado->load('usuario'),
            'message' => 'Apoderado registrado exitosamente.'
        ], 201);
    }

    public function show($id)
    {
        $apoderado = Apoderado::where('institucion_id', auth()->user()->institucion_id)
            ->with(['usuario', 'estudiantes.usuario'])
            ->findOrFail($id);

        return response()->json(['data' => $apoderado, 'message' => 'OK']);
    }

    public function update(Request $request, $id)
    {
        $apoderado = Apoderado::where('institucion_id', auth()->user()->institucion_id)->findOrFail($id);

        $validated = $request->validate([
            'nombres'          => 'sometimes|string|max:120',
            'apellidos'        => 'sometimes|string|max:150',
            'documento_numero' => 'nullable|string|max:20',
            'telefono'         => 'nullable|string|max:30',
            'correo'           => 'nullable|email|max:150',
        ]);

        $apoderado->update($validated);

        return response()->json([
            'data'    => $apoderado->load('usuario'),
            'message' => 'Apoderado actualizado exitosamente.'
        ]);
    }

    public function destroy($id)
    {
        $apoderado = Apoderado::where('institucion_id', auth()->user()->institucion_id)->findOrFail($id);
        $apoderado->delete();

        return response()->json(['message' => 'Apoderado eliminado exitosamente.']);
    }

    /**
     * Vincular un estudiante con el apoderado.
     */
    public function vincularEstudiante(Request $request, $id)
    {
        $apoderado = Apoderado::where('institucion_id', auth()->user()->institucion_id)->findOrFail($id);

        $validated = $request->validate([
            'estudiante_id' => [
                'required', 'integer',
                Rule::exists('estudiantes', 'id')->where('institucion_id', $apoderado->institucion_id),
            ],
            'parentesco'    => 'nullable|string|max:50',
            'es_principal'  => 'nullable|boolean',
        ]);

        $apoderado->estudiantes()->syncWithoutDetaching([
            $validated['estudiante_id'] => [
                'parentesco'   => $validated['parentesco'] ?? 'TUTOR',
                'es_principal' => $validated['es_principal'] ?? false,
            ]
        ]);

        return response()->json([
            'data'    => $apoderado->load('estudiantes.usuario'),
            'message' => 'Estudiante vinculado al apoderado exitosamente.'
        ]);
    }

    /**
     * Desvincular un estudiante del apoderado.
     */
    public function desvincularEstudiante($id, $estudianteId)
    {
        $apoderado = Apoderado::where('institucion_id', auth()->user()->institucion_id)->findOrFail($id);
        $apoderado->estudiantes()->detach($estudianteId);

        return response()->json(['message' => 'Estudiante desvinculado exitosamente.']);
    }

    /**
     * Portal del Apoderado: Retorna los hijos vinculados y su seguimiento académico consolidado.
     */
    public function misHijos(Request $request)
    {
        $user = auth()->user();
        $apoderado = Apoderado::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->where('usuario_id', $user->id)
            ->with(['estudiantes.usuario'])
            ->first();

        if (!$apoderado) {
            abort(response()->json(['message' => 'No se encontró un perfil de apoderado vinculado a tu cuenta.', 'error' => 'not_found'], 404));
        }

        $hijosData = $apoderado->estudiantes->map(function ($est) {
            $matriculas = Matricula::where('estudiante_id', $est->id)
                ->where('estado', 'MATRICULADO')
                ->with(['grupoCurso.curso', 'grupoCurso.docente.usuario'])
                ->get();

            $cursos = $matriculas->map(function ($mat) use ($est) {
                $grupo = $mat->grupoCurso;

                $calificaciones = Calificacion::where('grupo_curso_id', $grupo->id)
                    ->where('estudiante_id', $est->id)
                    ->get();

                $asistencias = Asistencia::where('grupo_curso_id', $grupo->id)
                    ->where('estudiante_id', $est->id)
                    ->get();

                $totalClases = $asistencias->count();
                $presentes = $asistencias->where('estado_asistencia', 'PRESENTE')->count();
                $ausentes = $asistencias->where('estado_asistencia', 'AUSENTE')->count();

                return [
                    'grupo_id'            => $grupo->id,
                    'curso_nombre'        => $grupo->curso?->nombre ?? 'Curso',
                    'docente'             => $grupo->docente?->usuario ? "{$grupo->docente->usuario->nombres} {$grupo->docente->usuario->apellido_paterno}" : 'Docente asignado',
                    'promedio'            => $calificaciones->avg('nota') ? round($calificaciones->avg('nota'), 1) : null,
                    'total_calificaciones'=> $calificaciones->count(),
                    'asistencia_pct'      => $totalClases > 0 ? round(($presentes / $totalClases) * 100, 1) : 100,
                    'faltas'              => $ausentes,
                ];
            });

            return [
                'estudiante_id'    => $est->id,
                'codigo'           => $est->codigo_estudiante,
                'nombre_completo'  => $est->usuario ? "{$est->usuario->apellido_paterno} {$est->usuario->apellido_materno}, {$est->usuario->nombres}" : 'Estudiante',
                'parentesco'       => $est->pivot->parentesco,
                'es_principal'     => $est->pivot->es_principal,
                'cursos'           => $cursos,
            ];
        });

        return response()->json([
            'data'    => $hijosData,
            'message' => 'OK'
        ]);
    }
}
