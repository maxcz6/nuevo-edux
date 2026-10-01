<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Institucion;
use App\Models\Usuario;
use App\Models\Rol;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * AuthTest — Pruebas de autenticación.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private Institucion $institucion;
    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermisosSeeder::class);

        $this->institucion = Institucion::create([
            'codigo'           => 'TEST-001',
            'nombre'           => 'Institución de Prueba',
            'tipo_institucion' => 'INSTITUTO',
            'estado'           => 'ACTIVO',
        ]);

        $adminRol = Rol::where('nombre', 'ADMINISTRADOR')->first();

        $this->usuario = Usuario::withoutGlobalScopes()->create([
            'institucion_id'   => $this->institucion->id,
            'username'         => 'testadmin',
            'email'            => 'admin@test.local',
            'password'         => Hash::make('TestPass2026!'),
            'nombres'          => 'Admin',
            'apellido_paterno' => 'Test',
            'estado'           => 'ACTIVO',
        ]);

        $this->usuario->roles()->attach($adminRol->id);
    }

    public function test_login_valido_retorna_token_y_usuario(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'admin@test.local',
            'password' => 'TestPass2026!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'user' => ['id', 'email', 'nombres', 'roles']],
            ])
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    public function test_login_con_contrasena_incorrecta_retorna_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'admin@test.local',
            'password' => 'ContraseñaWrong',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_con_email_inexistente_retorna_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@nowhere.com',
            'password' => 'cualquier',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_de_usuario_inactivo_retorna_401(): void
    {
        $this->usuario->update(['estado' => 'INACTIVO']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'admin@test.local',
            'password' => 'TestPass2026!',
        ]);

        $response->assertStatus(401);
    }

    public function test_usuario_autenticado_puede_obtener_su_perfil(): void
    {
        $token = $this->usuario->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'admin@test.local')
            ->assertJsonStructure(['data' => ['id', 'email', 'nombres', 'roles']]);
    }

    public function test_usuario_puede_subir_y_eliminar_su_foto_sin_exponer_la_ruta(): void
    {
        Storage::fake('local');
        $token = $this->usuario->createToken('test')->plainTextToken;
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $response = $this->withToken($token)->post('/api/v1/me/foto', [
            'foto' => UploadedFile::fake()->createWithContent('perfil.png', $png),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.tiene_foto', true)
            ->assertJsonMissingPath('data.foto');

        $ruta = $this->usuario->fresh()->foto;
        $this->assertNotNull($ruta);
        Storage::disk('local')->assertExists($ruta);

        $this->withToken($token)->get('/api/v1/me/foto')->assertStatus(200);

        $this->withToken($token)->deleteJson('/api/v1/me/foto')
            ->assertStatus(200)
            ->assertJsonPath('data.tiene_foto', false);

        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_foto_de_perfil_rechaza_archivos_que_no_son_imagen(): void
    {
        Storage::fake('local');
        $token = $this->usuario->createToken('test')->plainTextToken;

        $this->withToken($token)->post('/api/v1/me/foto', [
            'foto' => UploadedFile::fake()->create('archivo.pdf', 100, 'application/pdf'),
        ])->assertStatus(422);

        $this->assertNull($this->usuario->fresh()->foto);
    }

    public function test_usuario_puede_cerrar_sesion(): void
    {
        $token = $this->usuario->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/logout');
        $response->assertStatus(200);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_endpoint_protegido_sin_token_retorna_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->getJson('/api/v1/dashboard/stats')->assertStatus(401);
    }

    public function test_health_endpoint_es_publico(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200)->assertJsonPath('status', 'ok');
    }
}
