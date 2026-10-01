<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConvocatoriaAdmision;
use App\Models\VacanteAdmision;
use App\Models\Postulante;
use App\Models\Usuario;
use App\Models\Rol;
use App\Models\Estudiante;
use App\Models\EstudiantePrograma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdmisionController extends Controller
{
    public function indexConvocatorias(Request $request)
    {
        $user = auth()->user();
        $query = ConvocatoriaAdmision::with(['periodo'])
            ->withCount(['vacantes', 'postulantes'])
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->orderBy('created_at', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->has('page') || $request->has('per_page')) {
            $perPage = min((int) $request->input('per_page', 25), 100);
            $paginated = $query->paginate($perPage);
            return response()->json([
                'data' => $paginated->items(),
                'meta' => [
                    'total'        => $paginated->total(),
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                ],
                'message' => 'OK'
            ]);
        }

        return response()->json([
            'data' => $query->limit(50)->get(),
            'message' => 'OK'
        ]);
    }

    public function storeConvocatoria(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $institucionId = $isSuperAdmin && $request->filled('institucion_id')
            ? $request->integer('institucion_id')
            : $user->institucion_id;
        $validated = $request->validate([
            'institucion_id' => $isSuperAdmin
                ? (($user->institucion_id ? 'nullable' : 'required') . '|integer|exists:instituciones,id')
                : 'prohibited',
            'codigo' => 'required|string|max:30',
            'nombre' => 'required|string|max:150',
'periodo_id' => [
                'nullable', 'integer',
                Rule::exists('periodos_academicos', 'id')->where('institucion_id', $institucionId),
            ],
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'fecha_examen' => 'nullable|date',
            'puntaje_minimo_aprobatorio' => 'nullable|numeric|between:0,20',
            'estado' => 'nullable|in:BORRADOR,PUBLICADA,EN_EVALUACION,FINALIZADA'
        ]);

        $validated['institucion_id'] = $institucionId;
        $validated['puntaje_minimo_aprobatorio'] = $validated['puntaje_minimo_aprobatorio'] ?? 11.00;

        $convocatoria = ConvocatoriaAdmision::create($validated);

        return response()->json([
            'data' => $convocatoria,
            'message' => 'Convocatoria de admisión creada con éxito.'
        ], 201);
    }

    public function showConvocatoria($id)
    {
        $user = auth()->user();
        $convocatoria = ConvocatoriaAdmision::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->with(['periodo', 'vacantes.programa'])
            ->withCount(['postulantes'])
            ->findOrFail($id);

        $resumenPostulantes = Postulante::where('convocatoria_id', $id)
            ->select('condicion', DB::raw('count(*) as total'))
            ->groupBy('condicion')
            ->pluck('total', 'condicion');

        return response()->json([
            'data' => [
                'convocatoria' => $convocatoria,
                'resumen_postulantes' => $resumenPostulantes
            ],
            'message' => 'OK'
        ]);
    }

    public function updateConvocatoria(Request $request, $id)
    {
        $user = auth()->user();
        $convocatoria = ConvocatoriaAdmision::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:150',
'periodo_id' => [
                'nullable', 'integer',
                Rule::exists('periodos_academicos', 'id')->where('institucion_id', $convocatoria->institucion_id),
            ],
            'fecha_inicio' => 'sometimes|required|date',
            'fecha_fin' => 'sometimes|required|date',
            'fecha_examen' => 'nullable|date',
            'puntaje_minimo_aprobatorio' => 'nullable|numeric|between:0,20',
            'estado' => 'sometimes|required|in:BORRADOR,PUBLICADA,EN_EVALUACION,FINALIZADA'
        ]);

        $convocatoria->update($validated);

        return response()->json([
            'data' => $convocatoria,
            'message' => 'Convocatoria actualizada.'
        ]);
    }

    public function guardarVacantes(Request $request, $convocatoriaId)
    {
        $user = auth()->user();
        $convocatoria = ConvocatoriaAdmision::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($convocatoriaId);

        $validated = $request->validate([
            'vacantes' => 'required|array',
            'vacantes.*.programa_id' => [
                'required', 'integer',
                Rule::exists('unidades_academicas', 'id')->where('institucion_id', $convocatoria->institucion_id),
            ],
            'vacantes.*.turno' => 'required|in:DIURNO,NOCTURNO',
            'vacantes.*.modalidad' => 'required|in:ORDINARIO,EXONERADO,PRIMEROS_PUESTOS,DEPORTISTA',
            'vacantes.*.vacantes_ofertadas' => 'required|integer|min:1'
        ]);

        $saved = [];
        foreach ($validated['vacantes'] as $item) {
            $vacante = VacanteAdmision::updateOrCreate(
                [
                    'institucion_id' => $convocatoria->institucion_id,
                    'convocatoria_id' => $convocatoria->id,
                    'programa_id' => $item['programa_id'],
                    'turno' => $item['turno'],
                    'modalidad' => $item['modalidad'],
                ],
                [
                    'vacantes_ofertadas' => $item['vacantes_ofertadas']
                ]
            );
            $saved[] = $vacante->load('programa');
        }

        return response()->json([
            'data' => $saved,
            'message' => 'Vacantes configuradas exitosamente.'
        ]);
    }

    public function indexPostulantes(Request $request, $convocatoriaId)
    {
        $user = auth()->user();
        $convocatoria = ConvocatoriaAdmision::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($convocatoriaId);

        $query = Postulante::with(['programa', 'estudiante'])
            ->where('convocatoria_id', $convocatoria->id);

        if ($user->hasRole('COORDINADOR') && $user->unidad_academica_id) {
            $query->where('programa_id', $user->unidad_academica_id);
        } elseif ($request->filled('programa_id')) {
            $query->where('programa_id', $request->input('programa_id'));
        }

        if ($request->filled('turno')) {
            $query->where('turno', $request->input('turno'));
        }

        if ($request->filled('condicion')) {
            $query->where('condicion', $request->input('condicion'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('codigo_postulante', 'ILIKE', "%{$search}%")
                  ->orWhere('documento_numero', 'ILIKE', "%{$search}%")
                  ->orWhere('nombres', 'ILIKE', "%{$search}%")
                  ->orWhere('apellido_paterno', 'ILIKE', "%{$search}%")
                  ->orWhere('apellido_materno', 'ILIKE', "%{$search}%");
            });
        }

        $postulantes = $query->orderBy('orden_merito', 'asc')
            ->orderBy('puntaje_examen', 'desc')
            ->paginate($request->get('per_page', 25));

        return response()->json([
            'data' => $postulantes->items(),
            'meta' => [
                'current_page' => $postulantes->currentPage(),
                'last_page' => $postulantes->lastPage(),
                'per_page' => $postulantes->perPage(),
                'total' => $postulantes->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function storePostulante(Request $request, $convocatoriaId)
    {
        $user = auth()->user();
        $convocatoria = ConvocatoriaAdmision::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($convocatoriaId);

        $validated = $request->validate([
            'programa_id' => 'required|exists:unidades_academicas,id',
            'turno' => 'required|in:DIURNO,NOCTURNO',
            'modalidad' => 'required|in:ORDINARIO,EXONERADO,PRIMEROS_PUESTOS,DEPORTISTA',
            'nombres' => 'required|string|max:100',
            'apellido_paterno' => 'required|string|max:100',
            'apellido_materno' => 'nullable|string|max:100',
            'documento_tipo' => 'required|string|max:20',
            'documento_numero' => 'required|string|max:20',
            'email' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:30',
        ]);

        $exists = Postulante::where('convocatoria_id', $convocatoria->id)
            ->where('documento_numero', $validated['documento_numero'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'El postulante con este documento ya está inscrito en la convocatoria.'], 422);
        }

        $validated['institucion_id'] = $user->institucion_id;
        $validated['convocatoria_id'] = $convocatoria->id;
        $validated['codigo_postulante'] = 'POST-' . $convocatoria->codigo . '-' . $validated['documento_numero'];
        $validated['condicion'] = 'INSCRITO';

        $postulante = Postulante::create($validated);

        return response()->json([
            'data' => $postulante->load('programa'),
            'message' => 'Postulante registrado con éxito.'
        ], 201);
    }

    public function registrarCalificaciones(Request $request, $convocatoriaId)
    {
        $user = auth()->user();
        $convocatoria = ConvocatoriaAdmision::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($convocatoriaId);

        $validated = $request->validate([
            'calificaciones' => 'required|array',
            'calificaciones.*.postulante_id' => 'required|exists:postulantes,id',
            'calificaciones.*.puntaje_examen' => 'required|numeric|between:0,20',
        ]);

        return DB::transaction(function () use ($validated, $convocatoria) {
            $updated = 0;
            foreach ($validated['calificaciones'] as $item) {
                $postulante = Postulante::where('convocatoria_id', $convocatoria->id)
                    ->where('id', $item['postulante_id'])
                    ->first();

                if ($postulante) {
                    $postulante->update([
                        'puntaje_examen' => $item['puntaje_examen'],
                        'condicion' => 'EVALUADO',
                    ]);
                    $updated++;
                }
            }

            if ($convocatoria->estado === 'PUBLICADA') {
                $convocatoria->update(['estado' => 'EN_EVALUACION']);
            }

            return response()->json([
                'updated_count' => $updated,
                'message' => "Se registraron {$updated} notas de examen con éxito."
            ]);
        });
    }

    public function calcularOrdenMerito(Request $request, $convocatoriaId)
    {
        $user = auth()->user();
        $convocatoria = ConvocatoriaAdmision::query()
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($convocatoriaId);

        return DB::transaction(function () use ($convocatoria) {
            $vacantes = VacanteAdmision::where('convocatoria_id', $convocatoria->id)->get();
            $resumen = [];

            foreach ($vacantes as $vacante) {
                $postulantes = Postulante::where('convocatoria_id', $convocatoria->id)
                    ->where('programa_id', $vacante->programa_id)
                    ->where('turno', $vacante->turno)
                    ->where('modalidad', $vacante->modalidad)
                    ->whereNotNull('puntaje_examen')
                    ->orderBy('puntaje_examen', 'desc')
                    ->get();

                $cubiertas = 0;
                $puesto = 1;

                foreach ($postulantes as $postulante) {
                    $postulante->orden_merito = $puesto;

                    if ($postulante->puntaje_examen >= $convocatoria->puntaje_minimo_aprobatorio && $cubiertas < $vacante->vacantes_ofertadas) {
                        $postulante->condicion = 'ALCANZO_VACANTE';
                        $cubiertas++;
                    } else {
                        $postulante->condicion = 'NO_INGRESO';
                    }

                    $postulante->save();
                    $puesto++;
                }

                $vacante->update(['vacantes_cubiertas' => $cubiertas]);
                $resumen[] = [
                    'programa_id' => $vacante->programa_id,
                    'turno' => $vacante->turno,
                    'modalidad' => $vacante->modalidad,
                    'ofertadas' => $vacante->vacantes_ofertadas,
                    'cubiertas' => $cubiertas,
                    'total_evaluados' => $postulantes->count(),
                ];
            }

            return response()->json([
                'resumen' => $resumen,
                'message' => 'Cálculo de orden de mérito completado con éxito.'
            ]);
        });
    }

    public function matricularIngresantes(Request $request, $convocatoriaId)
    {
        $user = auth()->user();
        $convocatoria = ConvocatoriaAdmision::query()
            ->with('institucion')
            ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
            ->findOrFail($convocatoriaId);

        $rolEstudiante = Rol::where('nombre', 'ESTUDIANTE')->first();
        $inst = $convocatoria->institucion;
        $instSlug = \Illuminate\Support\Str::slug($inst?->nombre_corto ?: ($inst?->codigo ?: 'sam'));
        $instPrefix = strtoupper($inst?->nombre_corto ?: (\Illuminate\Support\Str::after($inst?->codigo ?? '', '-') ?: 'SAM'));

        return DB::transaction(function () use ($convocatoria, $user, $rolEstudiante, $instSlug, $instPrefix) {
            $ingresantes = Postulante::where('convocatoria_id', $convocatoria->id)
                ->where('condicion', 'ALCANZO_VACANTE')
                ->whereNull('estudiante_id')
                ->get();

            $matriculados = 0;
            $year = date('Y');

            foreach ($ingresantes as $ingresante) {
                // 1. Obtener o crear Usuario
                $usuario = Usuario::query()
                    ->when($user->institucion_id, fn ($query) => $query->where('institucion_id', $user->institucion_id))
                    ->where(function ($q) use ($ingresante) {
                        $q->where('documento_numero', $ingresante->documento_numero)
                          ->orWhere('username', $ingresante->documento_numero);
                    })->first();

                if (!$usuario) {
                    $email = $ingresante->email ?: "est.{$ingresante->documento_numero}@{$instSlug}.edu.pe";
                    $usuario = Usuario::create([
                        'institucion_id' => $convocatoria->institucion_id,
                        'username' => $ingresante->documento_numero,
                        'email' => $email,
                        'password' => Hash::make($ingresante->documento_numero),
                        'nombres' => $ingresante->nombres,
                        'apellido_paterno' => $ingresante->apellido_paterno,
                        'apellido_materno' => $ingresante->apellido_materno,
                        'documento_tipo' => $ingresante->documento_tipo,
                        'documento_numero' => $ingresante->documento_numero,
                        'telefono' => $ingresante->telefono,
                        'estado' => 'ACTIVO',
                    ]);

                    if ($rolEstudiante) {
                        $usuario->roles()->syncWithoutDetaching([$rolEstudiante->id]);
                    }
                }

                // 2. Crear Estudiante
                $codigoEstudiante = "{$instPrefix}-{$year}-" . str_pad($ingresante->id, 4, '0', STR_PAD_LEFT);
                $estudiante = Estudiante::firstOrCreate(
                    [
                        'institucion_id' => $convocatoria->institucion_id,
                        'usuario_id' => $usuario->id,
                    ],
                    [
                        'unidad_academica_id' => $ingresante->programa_id,
                        'codigo_estudiante' => $codigoEstudiante,
                        'fecha_ingreso' => now()->toDateString(),
                        'estado' => 'ACTIVO',
                    ]
                );

                // 3. Vincular programa en EstudiantePrograma
                $plan = \App\Models\PlanEstudio::withoutGlobalScopes()->where('programa_id', $ingresante->programa_id)->first();
                if ($plan) {
                    EstudiantePrograma::firstOrCreate(
                        [
                            'estudiante_id' => $estudiante->id,
                            'programa_id'   => $ingresante->programa_id,
                        ],
                        [
                            'plan_estudio_id' => $plan->id,
                            'fecha_ingreso'   => now()->toDateString(),
                            'estado'          => 'ACTIVO',
                        ]
                    );
                }

                // 4. Actualizar Postulante
                $ingresante->update([
                    'condicion' => 'MATRICULADO',
                    'usuario_id' => $usuario->id,
                    'estudiante_id' => $estudiante->id,
                ]);

                $matriculados++;
            }

            $convocatoria->update(['estado' => 'FINALIZADA']);

            return response()->json([
                'matriculados' => $matriculados,
                'message' => "Se matricularon exitosamente {$matriculados} ingresantes como estudiantes oficiales."
            ]);
        });
    }
}
