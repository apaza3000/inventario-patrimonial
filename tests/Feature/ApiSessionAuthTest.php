<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiSessionAuthTest extends TestCase
{
    use DatabaseTransactions;

    private function createUsuario(bool $activo = true): Usuario
    {
        return Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Autenticación',
            'correo' => uniqid('auth-prueba-', true).'@example.test',
            'password_hash' => Hash::make('ClaveSegura123!'),
            'rol_id' => Rol::query()->firstOrFail()->id,
            'activo' => $activo,
            'fecha_registro' => now(),
        ]);
    }

    private function asSpa(): static
    {
        return $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ]);
    }

    private function useSessionFrom($response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === config('session.cookie')) {
                $this->withCookie($cookie->getName(), $cookie->getValue());

                return;
            }
        }

        $this->fail('No se recibió la cookie de sesión.');
    }

    public function test_spa_can_obtain_csrf_and_session_cookies(): void
    {
        $this->asSpa()->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN')
            ->assertCookie(config('session.cookie'))
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_rejects_invalid_credentials_inactive_users_and_requests_without_session(): void
    {
        $active = $this->createUsuario();
        $inactive = $this->createUsuario(false);

        $this->postJson('/api/login', ['correo' => $active->correo, 'password' => 'ClaveSegura123!'])->assertForbidden();
        $this->get('/api/bienes')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
        $this->asSpa()->getJson('/api/bienes')->assertUnauthorized();
        $this->asSpa()->withToken('token-invalido')->getJson('/api/bienes')->assertUnauthorized();
        $this->asSpa()->postJson('/api/login', ['correo' => $active->correo, 'password' => 'incorrecta'])->assertUnauthorized();
        $this->asSpa()->postJson('/api/login', ['correo' => 'no-existe@example.test', 'password' => 'ClaveSegura123!'])->assertUnauthorized();
        $this->asSpa()->postJson('/api/login', ['correo' => $inactive->correo, 'password' => 'ClaveSegura123!'])->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->asSpa()->postJson('/api/login', [
                'correo' => 'no-existe@example.test',
                'password' => 'incorrecta',
            ])->assertUnauthorized();
        }

        $this->asSpa()->postJson('/api/login', [
            'correo' => 'no-existe@example.test',
            'password' => 'incorrecta',
        ])->assertStatus(429);
    }

    public function test_login_protected_routes_and_logout_use_only_the_session(): void
    {
        $usuario = $this->createUsuario();

        $login = $this->asSpa()->postJson('/api/login', [
            'correo' => $usuario->correo,
            'password' => 'ClaveSegura123!',
        ]);
        $login->assertOk()->assertJsonPath('data.id', $usuario->id);
        $this->assertArrayNotHasKey('password_hash', $login->json('data'));
        $this->useSessionFrom($login);

        $currentUser = $this->asSpa()->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.rol.id', $usuario->rol_id);
        $this->assertArrayNotHasKey('password_hash', $currentUser->json('data'));
        $this->asSpa()->getJson('/api/bienes')->assertOk();

        $this->asSpa()->postJson('/api/logout')->assertOk();
        Auth::forgetGuards();
        $this->asSpa()->getJson('/api/user')->assertUnauthorized();
    }

    public function test_deactivated_user_loses_access_to_existing_session(): void
    {
        $usuario = $this->createUsuario();
        $login = $this->asSpa()->postJson('/api/login', [
            'correo' => $usuario->correo,
            'password' => 'ClaveSegura123!',
        ]);
        $login->assertOk();
        $this->useSessionFrom($login);

        $usuario->update(['activo' => false]);
        Auth::forgetGuards();

        $this->asSpa()->getJson('/api/bienes')->assertForbidden();
        Auth::forgetGuards();
        $this->asSpa()->getJson('/api/user')->assertUnauthorized();
    }
}
