<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsuarioController extends Controller
{
    // ── Helpers de autorización ───────────────────────────────────────────────

    /**
     * Verifica que el actor (ADMINISTRADOR) no intente operar sobre un SUPERADMIN
     * ni asignarle el rol SUPERADMIN a ningún usuario.
     */
    private function ensureAdminCannotEscalate(string $action, ?Usuario $target = null, ?int $rolId = null): void
    {
        $actor = auth()->user();

        if ($actor->hasRole('SUPERADMIN')) {
            return; // SUPERADMIN no tiene restricción
        }

        // ADMINISTRADOR no puede operar sobre un SUPERADMIN existente
        if ($target !== null) {
            $esSuperadmin = $target->roles()->where('nombre', 'SUPERADMIN')->exists();
            if ($esSuperadmin) {
                abort(response()->json([
                    'message' => "Un Administrador no puede {$action} a un Superadministrador.",
                ], 403));
            }
        }

        // ADMINISTRADOR no puede asignar el rol SUPERADMIN a ningún usuario
        if ($rolId !== null) {
            $rol = Rol::find($rolId);
            if ($rol && $rol->nombre === 'SUPERADMIN') {
                abort(response()->json([
                    'message' => 'Un Administrador no puede asignar el rol Superadministrador.',
                ], 403));
            }
        }
    }

    // ── Listar usuarios ───────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = Usuario::with('roles')->latest('id');

        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        } elseif ($request->filled('institucion_id')) {
            $query->where('institucion_id', $request->institucion_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('rol')) {
            $rolBuscado = $request->rol;
            $query->whereHas('roles', fn($q) => $q->where('nombre', $rolBuscado));
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('nombres', 'ilike', "%{$search}%")
                  ->orWhere('apellido_paterno', 'ilike', "%{$search}%")
                  ->orWhere('apellido_materno', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('username', 'ilike', "%{$search}%")
                  ->orWhere('documento_numero', 'ilike', "%{$search}%")
                  ->orWhereRaw("CONCAT(nombres, ' ', apellido_paterno, ' ', COALESCE(apellido_materno, '')) ilike ?", ["%{$search}%"]);
            });
        }

        $usuarios = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $usuarios->items(),
            'meta' => [
                'current_page' => $usuarios->currentPage(),
                'last_page'    => $usuarios->lastPage(),
                'per_page'     => $usuarios->perPage(),
                'total'        => $usuarios->total(),
            ],
            'message' => 'OK'
        ]);
    }

    // ── Crear usuario ─────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $instId = $isSuperAdmin && $request->filled('institucion_id')
            ? $request->institucion_id
            : auth()->user()->institucion_id;

        $validated = $request->validate([
            'username'         => ['required', 'string', \Illuminate\Validation\Rule::unique('usuarios', 'username')->where('institucion_id', $instId)],
            'email'            => ['required', 'email', \Illuminate\Validation\Rule::unique('usuarios', 'email')->where('institucion_id', $instId)],
            'password'         => 'required|min:8',
            'nombres'          => 'required|string',
            'apellido_paterno' => 'required|string',
            'apellido_materno' => 'nullable|string',
            'institucion_id'   => $isSuperAdmin ? 'sometimes|exists:instituciones,id' : 'prohibited',
            'rol_id'           => [
                'required', 'integer',
                function ($attr, $val, $fail) use ($instId, $isSuperAdmin) {
                    $rol = \App\Models\Rol::find($val);
                    if (!$rol) {
                        $fail('El rol seleccionado no existe.');
                        return;
                    }
                    if (!$isSuperAdmin && $rol->institucion_id !== null && $rol->institucion_id !== $instId) {
                        $fail('El rol no pertenece a tu institución.');
                    }
                },
            ],
        ]);

        $rolId = $validated['rol_id'];
        unset($validated['rol_id']);

        // Crítico: ADMINISTRADOR no puede crear usuarios con el rol SUPERADMIN
        $this->ensureAdminCannotEscalate('asignar el rol', null, $rolId);

        $validated['institucion_id'] = $instId;
        $validated['estado']         = 'ACTIVO';
        $validated['password']       = Hash::make($validated['password']);

        $usuario = DB::transaction(function () use ($validated, $rolId) {
            $user = Usuario::create($validated);
            $user->roles()->attach($rolId);
            return $user;
        });

        $usuario->load('roles');

        return response()->json(['data' => $usuario, 'message' => 'OK'], 201);
    }

    // ── Ver usuario ───────────────────────────────────────────────────────────
    public function show($id)
    {
        $query = Usuario::with('roles');
        if (!auth()->user()->hasRole('SUPERADMIN')) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $usuario = $query->findOrFail($id);

        return response()->json(['data' => $usuario, 'message' => 'OK']);
    }

    // ── Editar usuario ────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = Usuario::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $usuario = $query->findOrFail($id);

        // Crítico: ADMINISTRADOR no puede editar un SUPERADMIN
        $this->ensureAdminCannotEscalate('editar', $usuario);

        $targetInstId = $isSuperAdmin ? ($request->institucion_id ?? $usuario->institucion_id) : auth()->user()->institucion_id;

        $validated = $request->validate([
            'username'         => ['sometimes', 'string', \Illuminate\Validation\Rule::unique('usuarios', 'username')->where('institucion_id', $targetInstId)->ignore($id)],
            'email'            => ['sometimes', 'email', \Illuminate\Validation\Rule::unique('usuarios', 'email')->where('institucion_id', $targetInstId)->ignore($id)],
            'nombres'          => 'sometimes|string',
            'apellido_paterno' => 'sometimes|string',
            'apellido_materno' => 'nullable|string',
            'estado'           => 'sometimes|in:ACTIVO,INACTIVO',
            'institucion_id'   => [
                'sometimes',
                function ($attr, $val, $fail) use ($isSuperAdmin, $usuario) {
                    if (!$isSuperAdmin && (int)$val !== (int)$usuario->institucion_id) {
                        $fail('No puedes cambiar la institución del usuario.');
                    }
                },
            ],
            'rol_id'           => [
                'nullable', 'integer',
                function ($attr, $val, $fail) use ($targetInstId, $isSuperAdmin) {
                    if (!$val) {
                        return;
                    }
                    $rol = \App\Models\Rol::find($val);
                    if (!$rol) {
                        $fail('El rol seleccionado no existe.');
                        return;
                    }
                    if (!$isSuperAdmin && $rol->institucion_id !== null && $rol->institucion_id !== $targetInstId) {
                        $fail('El rol no pertenece a tu institución.');
                    }
                },
            ],
        ]);

        $rolId = $validated['rol_id'] ?? null;

        // Crítico: ADMINISTRADOR no puede asignar el rol SUPERADMIN
        $this->ensureAdminCannotEscalate('asignar ese rol a', $usuario, $rolId);

        unset($validated['rol_id']);
        if (!$isSuperAdmin) {
            unset($validated['institucion_id']);
        }

        DB::transaction(function () use ($usuario, $validated, $rolId) {
            $usuario->update($validated);

            if ($rolId !== null) {
                // Verificación extra: el rol no puede ser SUPERADMIN si el actor es ADMIN
                $this->ensureAdminCannotEscalate('asignar el rol', null, $rolId);
                $usuario->roles()->sync([$rolId]);
            }
        });

        $usuario->load('roles');

        return response()->json(['data' => $usuario, 'message' => 'OK']);
    }

    // ── Desactivar o Eliminar usuario ─────────────────────────────────────────
    public function destroy(Request $request, $id)
    {
        $isSuperAdmin = auth()->user()->hasRole('SUPERADMIN');
        $query = Usuario::query();
        if (!$isSuperAdmin) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $usuario = $query->findOrFail($id);

        // Crítico: ADMINISTRADOR no puede desactivar ni eliminar un SUPERADMIN
        $this->ensureAdminCannotEscalate('desactivar o eliminar', $usuario);

        $forceDelete = $request->boolean('force') || $request->input('action') === 'eliminar';

        // ADMINISTRADOR solo puede desactivar, nunca eliminar permanentemente
        if ($forceDelete && !$isSuperAdmin) {
            return response()->json([
                'message' => 'El rol ADMINISTRADOR solo puede desactivar registros, no eliminarlos permanentemente.',
                'error'   => 'forbidden_action'
            ], 403);
        }

        // SUPERADMIN puede eliminar permanentemente si se especifica force/eliminar
        if ($forceDelete && $isSuperAdmin) {
            if ($usuario->id === auth()->id()) {
                return response()->json(['message' => 'No puedes eliminar tu propia cuenta de superadministrador.'], 400);
            }
            DB::transaction(function () use ($usuario) {
                $usuario->roles()->detach();
                $usuario->delete();
            });
            return response()->json(['message' => 'Usuario eliminado definitivamente.']);
        }

        // Desactivación (por defecto para ADMIN, o cuando SUPERADMIN lo solicite)
        $usuario->update(['estado' => 'INACTIVO']);

        return response()->json(['message' => 'Usuario desactivado exitosamente.']);
    }

    // ── Cambiar contraseña de cualquier usuario (ADMIN+) ─────────────────────
    public function cambiarPassword(Request $request, $id)
    {
        $query = Usuario::query();
        if (!auth()->user()->hasRole('SUPERADMIN')) {
            $query->where('institucion_id', auth()->user()->institucion_id);
        }
        $usuario = $query->findOrFail($id);

        // Crítico: ADMINISTRADOR no puede cambiar la contraseña de un SUPERADMIN
        $this->ensureAdminCannotEscalate('cambiar la contraseña de', $usuario);

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $usuario->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    // ── Perfil propio (todos los roles) ───────────────────────────────────────
    public function miPerfil(Request $request)
    {
        $usuario = auth()->user()->load('roles');
        return response()->json(['data' => $this->perfilSeguro($usuario), 'message' => 'OK']);
    }

    public function actualizarMiPerfil(Request $request)
    {
        $usuario = auth()->user();

        $validated = $request->validate([
            'nombres'          => 'sometimes|string|max:120',
            'apellido_paterno' => 'sometimes|string|max:80',
            'apellido_materno' => 'nullable|string|max:80',
            'telefono'         => 'nullable|string|max:30',
        ]);

        $usuario->update($validated);
        $usuario->load('roles');

        return response()->json(['data' => $this->perfilSeguro($usuario), 'message' => 'Perfil actualizado correctamente.']);
    }

    /**
     * Guarda la foto del usuario autenticado en el disco privado local.
     */
    public function actualizarMiFoto(Request $request)
    {
        $validated = $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=4096,max_height=4096',
        ]);

        /** @var Usuario $usuario */
        $usuario = $request->user();
        $archivo = $validated['foto'];
        $nuevaRuta = Storage::disk('local')->putFile(
            "fotos-perfil/{$usuario->institucion_id}",
            $archivo
        );

        $rutaAnterior = $usuario->foto;
        $usuario->update(['foto' => $nuevaRuta]);

        if ($rutaAnterior && $rutaAnterior !== $nuevaRuta) {
            Storage::disk('local')->delete($rutaAnterior);
        }

        $usuario->load('roles');

        return response()->json([
            'data' => $this->perfilSeguro($usuario),
            'message' => 'Foto de perfil actualizada correctamente.',
        ]);
    }

    /**
     * Sirve únicamente la foto del usuario autenticado; nunca expone su ruta.
     */
    public function miFoto(Request $request): StreamedResponse
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        abort_unless(
            $usuario->foto && Storage::disk('local')->exists($usuario->foto),
            404,
            'Foto de perfil no encontrada.'
        );

        return Storage::disk('local')->response($usuario->foto, null, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Type' => Storage::disk('local')->mimeType($usuario->foto) ?: 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function eliminarMiFoto(Request $request)
    {
        /** @var Usuario $usuario */
        $usuario = $request->user();

        if ($usuario->foto) {
            Storage::disk('local')->delete($usuario->foto);
            $usuario->update(['foto' => null]);
        }

        $usuario->load('roles');

        return response()->json([
            'data' => $this->perfilSeguro($usuario),
            'message' => 'Se restauró el avatar con iniciales.',
        ]);
    }

    private function perfilSeguro(Usuario $usuario): array
    {
        $data = $usuario->toArray();
        $data['tiene_foto'] = filled($usuario->foto);

        return $data;
    }

    // ── Cambiar mi propia contraseña ──────────────────────────────────────────
    public function cambiarMiPassword(Request $request)
    {
        $usuario = auth()->user();

        $request->validate([
            'password_actual'  => 'required|string',
            'password'         => 'required|string|min:8|confirmed|different:password_actual',
        ]);

        if (!Hash::check($request->password_actual, $usuario->password)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta.',
                'errors'  => ['password_actual' => ['La contraseña actual no coincide.']]
            ], 422);
        }

        $usuario->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Contraseña cambiada correctamente.']);
    }
}
