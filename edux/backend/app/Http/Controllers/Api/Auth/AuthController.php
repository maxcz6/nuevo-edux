<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AuthController extends Controller
{
    /**
     * Login multi-institución seguro.
     *
     * Si se envía institucion_id, se busca el usuario dentro de esa institución.
     * Si no se envía institucion_id, se buscan todos los usuarios activos con ese email
     * y se autentica contra el usuario cuya contraseña coincida. Si existe ambigüedad
     * (mismo password en múltiples instituciones), se solicita institucion_id.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'          => 'required|email|max:150',
            'password'       => 'required|string|min:6',
            'institucion_id' => 'nullable|integer|exists:instituciones,id',
        ]);

        $query = Usuario::withoutGlobalScopes()
            ->where('email', $request->email)
            ->where('estado', 'ACTIVO');

        if ($request->filled('institucion_id')) {
            $query->where('institucion_id', $request->institucion_id);
            $usuario = $query->first();

            if (!$usuario || !Hash::check($request->password, $usuario->password)) {
                return response()->json([
                    'message' => 'Credenciales incorrectas.',
                    'errors'  => ['email' => ['Las credenciales proporcionadas son incorrectas.']],
                ], 401);
            }
        } else {
            $usuarios = $query->get();

            if ($usuarios->isEmpty()) {
                return response()->json([
                    'message' => 'Credenciales incorrectas.',
                    'errors'  => ['email' => ['Las credenciales proporcionadas son incorrectas.']],
                ], 401);
            }

            // Validar cuál usuario coincide con la contraseña proporcionada
            $matches = [];
            foreach ($usuarios as $u) {
                if (Hash::check($request->password, $u->password)) {
                    $matches[] = $u;
                }
            }

            if (count($matches) === 1) {
                $usuario = $matches[0];
            } elseif (count($matches) > 1) {
                return response()->json([
                    'message' => 'El correo pertenece a más de una institución. Por favor especifique la institución.',
                    'errors'  => ['institucion_id' => ['Debe indicar la institución correspondiente para iniciar sesión.']],
                ], 422);
            } else {
                return response()->json([
                    'message' => 'Credenciales incorrectas.',
                    'errors'  => ['email' => ['Las credenciales proporcionadas son incorrectas.']],
                ], 401);
            }
        }

        if ($usuario->estado !== 'ACTIVO') {
            return response()->json([
                'message' => 'Tu cuenta se encuentra inactiva.',
                'errors'  => ['email' => ['El usuario se encuentra inactivo.']],
            ], 401);
        }

        $usuario->update(['ultimo_acceso_at' => now()]);
        $usuario->loadMissing(['roles.permisos', 'institucion']);

        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'data' => [
                'user'       => $this->formatUser($usuario),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
            'message' => 'Sesión iniciada correctamente.',
        ]);
    }

    /**
     * Cierra la sesión invalidando el token actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        auth()->guard('sanctum')->forgetUser();

        return response()->json(['message' => 'Sesión cerrada exitosamente.']);
    }

    /**
     * Retorna el perfil del usuario autenticado.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['roles.permisos', 'institucion', 'docente', 'estudiante']);

        return response()->json([
            'data'    => $this->formatUser($user),
            'message' => 'OK',
        ]);
    }

    /**
     * Formato canónico de usuario para respuestas de API.
     * No expone la contraseña ni datos internos.
     */
    private function formatUser(Usuario $usuario): array
    {
        return [
            'id'               => $usuario->id,
            'institucion_id'   => $usuario->institucion_id,
            'unidad_academica_id' => $usuario->unidad_academica_id,
            'username'         => $usuario->username,
            'email'            => $usuario->email,
            'nombres'          => $usuario->nombres,
            'apellido_paterno' => $usuario->apellido_paterno,
            'apellido_materno' => $usuario->apellido_materno,
            'tiene_foto'       => filled($usuario->foto),
            'estado'           => $usuario->estado,
            'ultimo_acceso_at' => $usuario->ultimo_acceso_at,
            'institucion'      => $usuario->relationLoaded('institucion') ? $usuario->institucion : null,
            'roles'            => $usuario->relationLoaded('roles') ? $usuario->roles->map(fn ($r) => [
                'id'        => $r->id,
                'nombre'    => $r->nombre,
                'permisos'  => $r->relationLoaded('permisos') ? $r->permisos->pluck('codigo') : [],
            ]) : [],
            'docente'          => $usuario->relationLoaded('docente') ? $usuario->docente : null,
            'estudiante'       => $usuario->relationLoaded('estudiante') ? $usuario->estudiante : null,
        ];
    }

    /**
     * Solicitud de restablecimiento de contraseña multi-institución.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'          => 'required|email',
            'institucion_id' => 'nullable|integer|exists:instituciones,id',
        ]);

        $query = Usuario::withoutGlobalScopes()
            ->with('institucion')
            ->where('email', $request->email)
            ->where('estado', 'ACTIVO');

        if ($request->filled('institucion_id')) {
            $query->where('institucion_id', $request->institucion_id);
        }

        $usuarios = $query->get();

        if ($usuarios->isEmpty()) {
            return response()->json([
                'message' => 'Si el correo electrónico existe en nuestra plataforma, recibirás instrucciones para restablecer tu contraseña.'
            ], 200);
        }

        foreach ($usuarios as $usuario) {
            $token = bin2hex(random_bytes(20));
            // Usar clave única con identificador de institución para evitar colisiones entre instituciones
            $key = $usuario->institucion_id ? "{$usuario->email}:{$usuario->institucion_id}" : $usuario->email;

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $key],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                    'expires_at' => now()->addMinutes(60),
                ]
            );

            $url = rtrim(config('app.url'), '/') . '/reset-password?email=' . urlencode($usuario->email) . '&token=' . urlencode($token);
            if ($usuario->institucion_id) {
                $url .= '&institucion_id=' . $usuario->institucion_id;
            }

            $instNombre = $usuario->institucion?->nombre ?? 'Modux';
            $userEmail = $usuario->email;
            $userNombres = $usuario->nombres;

            dispatch(function () use ($userEmail, $userNombres, $instNombre, $url) {
                Mail::raw(
                    "Hola {$userNombres},\n\nSolicitaste restablecer tu contraseña en {$instNombre}. Usa este enlace dentro de los próximos 60 minutos:\n{$url}\n\nSi no realizaste esta solicitud, puedes ignorar este mensaje.",
                    fn ($message) => $message->to($userEmail)->subject("Restablecimiento de contraseña - {$instNombre}")
                );
            });
        }

        return response()->json([
            'message' => 'Si el correo electrónico existe en nuestra plataforma, recibirás instrucciones para restablecer tu contraseña.'
        ], 200);
    }

    /**
     * Restablecer contraseña con token multi-institución.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'          => 'required|email',
            'token'          => 'required|string',
            'institucion_id' => 'nullable|integer|exists:instituciones,id',
            'password'       => 'required|string|min:8|confirmed',
        ]);

        $keyWithInst = $request->filled('institucion_id') ? "{$request->email}:{$request->institucion_id}" : null;

        // El token debe ligarse estrictamente a correo + institución: si se recibe
        // institucion_id, nunca debe hacerse fallback al token genérico por email.
        $record = DB::table('password_reset_tokens')
            ->where('email', $keyWithInst ?? $request->email)
            ->first();

        $expiresAt = $record?->expires_at
            ? Carbon::parse($record->expires_at)
            : ($record?->created_at ? Carbon::parse($record->created_at)->addMinutes(60) : null);
        $expired = !$record || !$expiresAt || now()->greaterThan($expiresAt);
        if ($expired || !Hash::check($request->token, $record->token)) {
            if ($record && $expired) {
                DB::table('password_reset_tokens')->where('email', $record->email)->delete();
            }
            return response()->json([
                'message' => 'El enlace de recuperación es inválido o ha expirado.',
                'error'   => 'invalid_token'
            ], 422);
        }

        $query = Usuario::withoutGlobalScopes()->where('email', $request->email)->where('estado', 'ACTIVO');
        if ($request->filled('institucion_id')) {
            $query->where('institucion_id', $request->institucion_id);
        }

        $usuario = $query->first();
        if ($usuario) {
            $usuario->update(['password' => Hash::make($request->password)]);
            DB::table('password_reset_tokens')->where('email', $record->email)->delete();
        }

        return response()->json(['message' => 'Tu contraseña ha sido restablecida exitosamente. Ya puedes iniciar sesión.']);
    }
}
