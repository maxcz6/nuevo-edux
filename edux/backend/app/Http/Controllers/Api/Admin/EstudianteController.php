<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Usuario;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EstudianteController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $query = Estudiante::with(['usuario', 'programa']);

        if (!$isSuperAdmin) {
            $query->where('institucion_id', $user->institucion_id);
            if ($user->hasRole('COORDINADOR')) {
                $query->where('unidad_academica_id', $user->unidad_academica_id ?? 0);
            } elseif ($request->filled('programa_id')) {
                $query->where('unidad_academica_id', $request->input('programa_id'));
            }
        } else {
            if ($request->filled('institucion_id')) {
                $query->where('institucion_id', $request->input('institucion_id'));
            }
            if ($request->filled('programa_id')) {
                $query->where('unidad_academica_id', $request->input('programa_id'));
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('codigo_estudiante', 'ILIKE', "%{$search}%")
                  ->orWhereHas('usuario', function ($uq) use ($search) {
                      $uq->where('nombres', 'ILIKE', "%{$search}%")
                         ->orWhere('apellido_paterno', 'ILIKE', "%{$search}%")
                         ->orWhere('apellido_materno', 'ILIKE', "%{$search}%")
                         ->orWhere('documento_numero', 'ILIKE', "%{$search}%")
                         ->orWhere('email', 'ILIKE', "%{$search}%");
                  });
            });
        }

        if ($request->filled("estado")) {
            $query->where("estado", $request->input("estado"));
        }

        $estudiantes = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $estudiantes->items(),
            'meta' => [
                'current_page' => $estudiantes->currentPage(),
                'last_page' => $estudiantes->lastPage(),
                'per_page' => $estudiantes->perPage(),
                'total' => $estudiantes->total(),
            ],
            'message' => 'OK'
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('SUPERADMIN');

        $instId = $isSuperAdmin && $request->filled('institucion_id')
            ? $request->input('institucion_id')
            : $user->institucion_id;

        $validated = $request->validate([
            'nombres' => 'required|string',
            'apellido_paterno' => 'required|string',
            'apellido_materno' => 'nullable|string',
            'documento_numero' => 'nullable|string|max:20',
            'email' => [
                'required',
                'email',
                \Illuminate\Validation\Rule::unique('usuarios', 'email')->where('institucion_id', $instId),
            ],
            'username' => [
                'required',
                'string',
                \Illuminate\Validation\Rule::unique('usuarios', 'username')->where('institucion_id', $instId),
            ],
            'password' => 'required|string|min:8',
            'codigo_estudiante' => 'nullable|string',
            'sexo' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'unidad_academica_id' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists('unidades_academicas', 'id')->where('institucion_id', $instId),
            ],
            'institucion_id' => $isSuperAdmin ? 'nullable|exists:instituciones,id' : 'prohibited',
        ]);

        if ($user->hasRole('COORDINADOR') && $user->unidad_academica_id) {
            $validated['unidad_academica_id'] = $user->unidad_academica_id;
        }

        $rolEstudiante = Rol::where('nombre', 'ESTUDIANTE')->first();

        $estudiante = DB::transaction(function () use ($validated, $instId, $rolEstudiante) {
            $usuario = Usuario::create([
                'institucion_id' => $instId,
                'unidad_academica_id' => $validated['unidad_academica_id'] ?? null,
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'nombres' => $validated['nombres'],
                'apellido_paterno' => $validated['apellido_paterno'],
                'apellido_materno' => $validated['apellido_materno'] ?? null,
                'documento_tipo' => !empty($validated['documento_numero']) ? 'DNI' : null,
                'documento_numero' => $validated['documento_numero'] ?? null,
                'estado' => 'ACTIVO',
            ]);

            if ($rolEstudiante) {
                $usuario->roles()->attach($rolEstudiante->id);
            }

            return Estudiante::create([
                'institucion_id' => $instId,
                'usuario_id' => $usuario->id,
                'unidad_academica_id' => $validated['unidad_academica_id'] ?? null,
                'codigo_estudiante' => $validated['codigo_estudiante'] ?? null,
                'sexo' => $validated['sexo'] ?? null,
                'fecha_nacimiento' => $validated['fecha_nacimiento'] ?? null,
                'estado' => 'ACTIVO',
            ]);
        });

        return response()->json(['data' => $estudiante->load(['usuario', 'programa']), 'message' => 'OK'], 201);
    }

    public function show($id)
    {
        $user = auth()->user();
        $query = Estudiante::with(['usuario', 'programa']);
        if (!$user->hasRole('SUPERADMIN')) {
            $query->where('institucion_id', $user->institucion_id);
            if ($user->hasRole('COORDINADOR') && $user->unidad_academica_id) {
                $query->where('unidad_academica_id', $user->unidad_academica_id);
            }
        }

        $estudiante = $query->findOrFail($id);

        return response()->json(['data' => $estudiante, 'message' => 'OK']);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $query = Estudiante::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', $user->institucion_id);
            if ($user->hasRole('COORDINADOR') && $user->unidad_academica_id) {
                $query->where('unidad_academica_id', $user->unidad_academica_id);
            }
        }

        $estudiante = $query->findOrFail($id);

        $validated = $request->validate([
            'nombres' => 'sometimes|string',
            'apellido_paterno' => 'sometimes|string',
            'apellido_materno' => 'nullable|string',
            'codigo_estudiante' => 'nullable|string',
            'unidad_academica_id' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists('unidades_academicas', 'id')->where('institucion_id', $estudiante->institucion_id),
            ],
            'estado' => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        if ($user->hasRole('COORDINADOR') && $user->unidad_academica_id) {
            $validated['unidad_academica_id'] = $user->unidad_academica_id;
        }

        DB::transaction(function () use ($estudiante, $validated) {
            if (isset($validated['nombres']) || isset($validated['apellido_paterno']) || array_key_exists('apellido_materno', $validated) || isset($validated['estado']) || array_key_exists('unidad_academica_id', $validated)) {
                $userData = [];
                if (isset($validated['nombres'])) $userData['nombres'] = $validated['nombres'];
                if (isset($validated['apellido_paterno'])) $userData['apellido_paterno'] = $validated['apellido_paterno'];
                if (array_key_exists('apellido_materno', $validated)) $userData['apellido_materno'] = $validated['apellido_materno'];
                if (isset($validated['estado'])) $userData['estado'] = $validated['estado'];
                if (array_key_exists('unidad_academica_id', $validated)) $userData['unidad_academica_id'] = $validated['unidad_academica_id'];
                
                $estudiante->usuario?->update($userData);
            }

            $estudianteData = [];
            if (array_key_exists('codigo_estudiante', $validated)) $estudianteData['codigo_estudiante'] = $validated['codigo_estudiante'];
            if (array_key_exists('unidad_academica_id', $validated)) $estudianteData['unidad_academica_id'] = $validated['unidad_academica_id'];
            if (isset($validated['estado'])) $estudianteData['estado'] = $validated['estado'];
            
            if (!empty($estudianteData)) {
                $estudiante->update($estudianteData);
            }
        });

        return response()->json(['data' => $estudiante->fresh(['usuario', 'programa']), 'message' => 'OK']);
    }

    public function destroy(Request $request, $id)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('SUPERADMIN');
        $query = Estudiante::query();

        if (!$isSuperAdmin) {
            $query->where('institucion_id', $user->institucion_id);
            if ($user->hasRole('COORDINADOR') && $user->unidad_academica_id) {
                $query->where('unidad_academica_id', $user->unidad_academica_id);
            }
        }

        $estudiante = $query->findOrFail($id);

        $forceDelete = $request->boolean('force') || $request->input('action') === 'eliminar';

        // ADMINISTRADOR solo puede desactivar, nunca eliminar permanentemente
        if ($forceDelete && !$isSuperAdmin) {
            return response()->json([
                'message' => 'El rol ADMINISTRADOR solo puede desactivar registros, no eliminarlos permanentemente.',
                'error'   => 'forbidden_action'
            ], 403);
        }

        if ($forceDelete && $isSuperAdmin) {
            DB::transaction(function () use ($estudiante) {
                $usuario = $estudiante->usuario;
                $estudiante->delete();
                if ($usuario) {
                    $usuario->roles()->detach();
                    $usuario->delete();
                }
            });
            return response()->json(['message' => 'Estudiante eliminado definitivamente.']);
        }
        
        DB::transaction(function () use ($estudiante) {
            $estudiante->update(['estado' => 'INACTIVO']);
            if ($estudiante->usuario) {
                $estudiante->usuario->update(['estado' => 'INACTIVO']);
            }
        });

        return response()->json(['message' => 'Estudiante desactivado exitosamente.']);
    }
}
