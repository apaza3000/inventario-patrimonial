<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UltimoSuperadminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // A dedicated in-memory connection; no migrations, seeders or MySQL writes.
        config([
            'database.default' => 'superadmin_test_memory',
            'database.connections.superadmin_test_memory' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::listen(function ($query): void {
            $this->assertSame('superadmin_test_memory', $query->connectionName);
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('nombre');
        });
        Schema::create('especialidades', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('nombre');
        });
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('correo')->unique();
            $table->string('password_hash');
            $table->integer('rol_id');
            $table->integer('especialidad_id')->nullable();
            $table->boolean('activo');
            $table->dateTime('fecha_registro')->nullable();
            $table->foreign('rol_id')->references('id')->on('roles');
        });
        DB::table('roles')->insert([
            ['id' => 1, 'nombre' => 'superadmin'], ['id' => 2, 'nombre' => 'administrador'],
            ['id' => 3, 'nombre' => 'director'], ['id' => 4, 'nombre' => 'asistente'],
        ]);
        $this->user(1, 1);
        $actor = $this->user(9, 2);
        $this->actingAs($actor, 'web')->withHeader('Origin', 'http://localhost:3000');
    }

    private function user(int $id, int $role, bool $active = true): Usuario
    {
        $usuario = new Usuario([
            'nombres' => 'Prueba', 'apellidos' => 'Memoria',
            'correo' => "usuario-$id@example.test", 'password_hash' => 'test-only-session-hash',
            'rol_id' => $role, 'activo' => $active,
        ]);
        $usuario->id = $id;
        $usuario->save();

        return $usuario;
    }

    public static function methods(): array
    {
        return [['PUT'], ['PATCH']];
    }

    /** @dataProvider methods */
    public function test_last_active_superadmin_cannot_change_role_even_with_an_inactive_superadmin(string $method): void
    {
        $this->user(2, 1, false);
        $this->json($method, '/api/usuarios/1', ['rol_id' => 2, 'activo' => true, 'nombres' => 'Bloqueado'])
            ->assertConflict()->assertJsonPath('message', 'No se puede cambiar el rol del último superadmin activo.');
        $this->assertDatabaseHas('usuarios', ['id' => 1, 'rol_id' => 1, 'activo' => true, 'nombres' => 'Prueba']);
    }

    /** @dataProvider methods */
    public function test_role_change_is_allowed_when_another_superadmin_remains_active(string $method): void
    {
        $this->user(2, 1);
        $this->json($method, '/api/usuarios/1', ['rol_id' => 3])
            ->assertOk()->assertJsonPath('data.rol_id', 3)->assertJsonPath('data.activo', true);
        $this->assertSame(1, Usuario::where('rol_id', 1)->where('activo', true)->count());
    }

    /** @dataProvider methods */
    public function test_self_role_change_is_blocked_only_when_no_other_active_superadmin_exists(string $method): void
    {
        $this->actingAs(Usuario::findOrFail(1), 'web');
        $this->json($method, '/api/usuarios/1', ['rol_id' => 2])->assertConflict();
        $this->user(2, 1);
        $this->json($method, '/api/usuarios/1', ['rol_id' => 2])->assertOk();
    }

    public function test_existing_deactivation_and_normal_update_behaviors_are_preserved(): void
    {
        $this->patchJson('/api/usuarios/1', ['activo' => false])->assertConflict()
            ->assertJsonPath('message', 'No se puede desactivar al último superadmin activo.');
        $this->patchJson('/api/usuarios/9', ['activo' => false])->assertConflict()
            ->assertJsonPath('message', 'No puede desactivar su propia cuenta.');
        $this->patchJson('/api/usuarios/1', ['rol_id' => '1', 'nombres' => 'Actualizado'])->assertOk();
        $this->user(2, 1);
        $this->patchJson('/api/usuarios/1', ['rol_id' => 4, 'activo' => false])->assertOk();
        $this->assertDatabaseHas('usuarios', ['id' => 2, 'rol_id' => 1, 'activo' => true]);
        $this->patchJson('/api/usuarios/1', ['rol_id' => 3, 'activo' => true])->assertOk();
    }
}
