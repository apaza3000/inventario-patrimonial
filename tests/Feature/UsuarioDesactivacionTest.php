<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioDesactivacionTest extends TestCase
{
    use DatabaseTransactions;

    /** @dataProvider selfDeactivationCases */
    public function test_users_cannot_deactivate_their_own_account(string $method, string $rol): void
    {
        $actor = $this->createUsuario($rol);
        $this->authenticate($actor);

        $this->json($method, "/api/usuarios/{$actor->id}", [
            'activo' => false,
            'nombres' => 'Cambio bloqueado',
        ])->assertConflict()->assertJsonPath('message', 'No puede desactivar su propia cuenta.');

        $this->assertTrue($actor->fresh()->activo);
        $this->assertSame('Prueba', $actor->fresh()->nombres);
    }

    public static function selfDeactivationCases(): array
    {
        $cases = [];
        foreach (['PUT', 'PATCH'] as $method) {
            foreach (['superadmin', 'administrador', 'director'] as $rol) {
                $cases["$method $rol"] = [$method, $rol];
            }
        }

        return $cases;
    }

    /** @dataProvider updateMethods */
    public function test_last_active_superadmin_cannot_be_deactivated(string $method): void
    {
        $rolId = Rol::where('nombre', 'superadmin')->firstOrFail()->id;
        $activos = DB::table('usuarios')->where('rol_id', $rolId)->where('activo', true)
            ->get(['id', 'nombres', 'activo']);
        $this->assertCount(1, $activos);
        $last = $activos->first();
        $this->createUsuario('superadmin', false);
        $this->authenticate($this->createUsuario('administrador'));

        $this->json($method, "/api/usuarios/{$last->id}", [
            'activo' => 0,
            'nombres' => 'Cambio bloqueado',
        ])->assertConflict()->assertJsonPath('message', 'No se puede desactivar al último superadmin activo.');

        $this->assertDatabaseHas('usuarios', [
            'id' => $last->id,
            'nombres' => $last->nombres,
            'activo' => true,
        ]);
    }

    /** @dataProvider updateMethods */
    public function test_superadmin_can_be_deactivated_when_another_remains_active(string $method): void
    {
        $target = $this->createUsuario('superadmin');
        $this->authenticate($this->createUsuario('administrador'));

        $this->json($method, "/api/usuarios/{$target->id}", ['activo' => '0'])
            ->assertOk()->assertJsonPath('data.activo', false);

        $this->assertFalse($target->fresh()->activo);
        $this->assertSame(1, Usuario::where('rol_id', $target->rol_id)->where('activo', true)->count());
    }

    public function test_other_user_deactivation_reactivation_and_normal_updates_are_preserved(): void
    {
        $actor = $this->createUsuario('administrador');
        $target = $this->createUsuario('asistente');
        $originalHash = $target->getAuthPassword();
        $this->authenticate($actor);

        $this->patchJson("/api/usuarios/{$target->id}", ['activo' => false])
            ->assertOk()->assertJsonPath('data.activo', false);
        $this->putJson("/api/usuarios/{$target->id}", ['activo' => true])
            ->assertOk()->assertJsonPath('data.activo', true);
        $this->patchJson("/api/usuarios/{$actor->id}", ['nombres' => 'Nombre actualizado'])
            ->assertOk()->assertJsonPath('data.nombres', 'Nombre actualizado');
        $this->assertSame($originalHash, $target->fresh()->getAuthPassword());
    }

    public static function updateMethods(): array
    {
        return [['PUT'], ['PATCH']];
    }

    private function authenticate(Usuario $usuario): void
    {
        $this->actingAs($usuario, 'web')->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ]);
    }

    private function createUsuario(string $rol, bool $activo = true): Usuario
    {
        return Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Desactivación',
            'correo' => uniqid('desactivacion-', true).'@example.test',
            'password_hash' => Hash::make('ClaveSegura123!'),
            'rol_id' => Rol::where('nombre', $rol)->firstOrFail()->id,
            'activo' => $activo,
            'fecha_registro' => now(),
        ]);
    }
}
