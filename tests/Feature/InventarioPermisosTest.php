<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureRol;
use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventarioPermisosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'inventory_test_memory',
            'database.connections.inventory_test_memory' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::listen(function ($query): void {
            $this->assertSame('inventory_test_memory', $query->connectionName);
        });
        // Only disposable in-memory tables; no migrations or seeders.
        foreach (['roles', 'especialidades', 'tipos_ambiente', 'estados_bien', 'condiciones_bien'] as $table) {
            Schema::create($table, function (Blueprint $table): void {
                $table->increments('id'); $table->string('nombre');
            });
        }
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id'); $table->string('nombres'); $table->string('apellidos');
            $table->string('correo')->unique(); $table->string('password_hash'); $table->integer('rol_id');
            $table->integer('especialidad_id')->nullable(); $table->boolean('activo'); $table->dateTime('fecha_registro')->nullable();
        });
        Schema::create('ambientes', function (Blueprint $table): void {
            $table->increments('id'); $table->string('nombre'); $table->integer('tipo_ambiente_id')->nullable();
            $table->integer('sede_id')->nullable(); $table->integer('especialidad_id')->nullable();
        });
        Schema::create('bienes', function (Blueprint $table): void {
            $table->increments('id'); $table->string('cbi')->nullable(); $table->string('descripcion');
            $table->integer('ambiente_id')->nullable(); $table->integer('estado_id')->nullable();
            $table->integer('condicion_id')->nullable(); $table->boolean('activo')->default(true);
        });
        Schema::create('inventarios', function (Blueprint $table): void {
            $table->integer('bien_id'); $table->integer('anio'); $table->string('inventario', 20);
            $table->primary(['bien_id', 'anio']); $table->foreign('bien_id')->references('id')->on('bienes');
        });
        DB::table('especialidades')->insert(['id' => 1, 'nombre' => 'Carrera simulada']);
        foreach (['superadmin', 'director', 'administrador', 'asistente', 'coordinador'] as $i => $role) {
            DB::table('roles')->insert(['id' => $i + 1, 'nombre' => $role]);
            DB::table('usuarios')->insert([
                'id' => $i + 1, 'nombres' => 'Prueba', 'apellidos' => 'Memoria',
                'correo' => "rol-$i@example.test", 'password_hash' => 'test-only-session-hash',
                'rol_id' => $i + 1, 'especialidad_id' => null, 'activo' => true,
            ]);
        }
        DB::table('tipos_ambiente')->insert([['id' => 1, 'nombre' => 'LABORATORIO'], ['id' => 2, 'nombre' => 'AULA']]);
        DB::table('ambientes')->insert([
            ['id' => 1, 'nombre' => 'Laboratorio de prueba', 'tipo_ambiente_id' => 1],
            ['id' => 2, 'nombre' => 'Aula de prueba', 'tipo_ambiente_id' => 2],
            ['id' => 3, 'nombre' => 'Sin tipo', 'tipo_ambiente_id' => null],
        ]);
        foreach (array_merge(range(1, 17), [20, 21, 22]) as $id) {
            DB::table('bienes')->insert([
                'id' => $id, 'cbi' => str_pad((string) $id, 7, '0', STR_PAD_LEFT), 'descripcion' => "Bien simulado $id",
                'ambiente_id' => $id === 20 ? 2 : ($id === 21 ? null : ($id === 22 ? 3 : 1)),
            ]);
            DB::table('inventarios')->insert(['bien_id' => $id, 'anio' => 2025, 'inventario' => "INV-$id"]);
        }
    }

    private function asRole(int $id, bool $active = true): static
    {
        $user = Usuario::findOrFail($id);
        $user->activo = $active; // In-memory only.
        return $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_assistant_selector_and_inventory_reads_are_paginated_and_laboratory_only(): void
    {
        $this->asRole(4);
        foreach (['/api/inventarios', '/api/inventarios/bienes'] as $url) {
            $this->getJson($url)->assertOk()->assertJsonPath('per_page', 15)->assertJsonPath('total', 17)->assertJsonCount(15, 'data');
            $this->getJson($url.'?page=2')->assertOk()->assertJsonCount(2, 'data');
        }
        $this->getJson('/api/inventarios/1/2025')->assertOk();
        foreach ([20, 21, 22] as $id) $this->getJson("/api/inventarios/$id/2025")->assertNotFound();
        $this->getJson('/api/bienes')->assertForbidden();
        $this->getJson('/api/usuarios')->assertForbidden();
        $this->getJson('/api/roles')->assertForbidden();
    }

    public function test_assistant_can_register_only_laboratory_goods_and_composite_identity_stays_unique(): void
    {
        $this->asRole(4);
        $payload = ['bien_id' => 1, 'anio' => 2026, 'inventario' => 'INV-NUEVO'];
        $this->postJson('/api/inventarios', $payload)->assertCreated()->assertJsonPath('data.bien_id', 1)->assertJsonPath('data.anio', 2026);
        $this->getJson('/api/inventarios/1/2026')->assertOk();
        $this->postJson('/api/inventarios', $payload)->assertUnprocessable()->assertJsonValidationErrors('anio');
        foreach ([20, 21, 22] as $id) {
            $this->postJson('/api/inventarios', [...$payload, 'bien_id' => $id])->assertForbidden();
            $this->assertDatabaseMissing('inventarios', ['bien_id' => $id, 'anio' => 2026]);
        }
        $this->postJson('/api/inventarios', [...$payload, 'bien_id' => 999])->assertUnprocessable()->assertJsonValidationErrors('bien_id');
        $this->postJson('/api/inventarios', [])->assertUnprocessable()->assertJsonValidationErrors(['bien_id', 'anio', 'inventario']);
        $this->postJson('/api/inventarios', [...$payload, 'anio' => 'incorrecto', 'inventario' => str_repeat('x', 21)])
            ->assertUnprocessable()->assertJsonValidationErrors(['anio', 'inventario']);
        $this->patchJson('/api/inventarios/1/2026', ['inventario' => 'CAMBIO'])->assertForbidden();
        $this->putJson('/api/inventarios/1/2026', ['inventario' => 'CAMBIO'])->assertForbidden();
        $this->deleteJson('/api/inventarios/1/2026')->assertForbidden();
        $this->assertDatabaseHas('inventarios', $payload);
    }

    public function test_registration_rechecks_location_even_if_the_good_previously_appeared_in_the_selector(): void
    {
        $this->asRole(4)->getJson('/api/inventarios/bienes')->assertOk()->assertJsonPath('data.0.id', 1);
        DB::table('bienes')->where('id', 1)->update(['ambiente_id' => 2]);
        $this->postJson('/api/inventarios', ['bien_id' => 1, 'anio' => 2026, 'inventario' => 'FUERA'])->assertForbidden();
        $this->assertDatabaseMissing('inventarios', ['bien_id' => 1, 'anio' => 2026]);
    }

    public function test_existing_full_roles_retain_unrestricted_inventory_crud_and_user_management(): void
    {
        foreach ([1, 2, 3] as $role) {
            $this->asRole($role)->getJson('/api/inventarios')->assertOk()->assertJsonPath('total', 20);
            $this->getJson('/api/inventarios/bienes')->assertOk()->assertJsonPath('total', 20);
            $this->getJson('/api/usuarios')->assertOk()->assertJsonPath('per_page', 15);
            $this->postJson('/api/inventarios', ['bien_id' => 20, 'anio' => 2026, 'inventario' => 'FUERA-LAB'])->assertCreated();
            $this->patchJson('/api/inventarios/20/2026', ['anio' => 2027, 'inventario' => 'NO'])->assertUnprocessable();
            $this->putJson('/api/inventarios/20/2026', ['bien_id' => 1, 'inventario' => 'NO'])->assertUnprocessable();
            $this->patchJson('/api/inventarios/20/2026', ['inventario' => 'ACTUALIZADO'])->assertOk();
            $this->deleteJson('/api/inventarios/20/2026')->assertOk();
            $this->postJson('/api/usuarios', [
                'nombres' => 'Nuevo', 'apellidos' => 'Simulado', 'correo' => "nuevo-$role@example.test",
                'password' => 'ClaveTemporal123!', 'rol_id' => 4, 'especialidad_id' => 1, 'activo' => true,
            ])->assertCreated();
            $this->assertDatabaseHas('bienes', ['id' => 20, 'ambiente_id' => 2]);
        }
    }

    public function test_guests_inactive_users_and_coordinators_cannot_register_or_use_the_selector(): void
    {
        $payload = ['bien_id' => 1, 'anio' => 2026, 'inventario' => 'NO'];
        $this->getJson('/api/inventarios/bienes')->assertUnauthorized();
        $this->postJson('/api/inventarios', $payload)->assertUnauthorized();
        $this->asRole(4, false)->postJson('/api/inventarios', $payload)->assertForbidden();
        $this->asRole(4, false)->getJson('/api/inventarios/bienes')->assertForbidden();
        $this->asRole(5)->postJson('/api/inventarios', $payload)->assertForbidden();
        $this->getJson('/api/inventarios/bienes')->assertForbidden();
        $this->get('/inventario/toma-inventario')->assertForbidden();
        $this->get('/mantenimientos')->assertOk();
        $this->get('/reportes')->assertOk();
        $this->get('/configuracion')->assertForbidden();
        $this->get('/movimientos')->assertForbidden();
        foreach ([1, 2] as $role) {
            $this->asRole($role, false)->get('/configuracion')->assertForbidden();
            $this->asRole($role, false)->postJson('/api/inventarios', $payload)->assertForbidden();
        }
        $this->assertDatabaseMissing('inventarios', ['anio' => 2026]);
    }

    public function test_director_and_superadmin_keep_access_to_every_business_route_and_report(): void
    {
        $middleware = app(EnsureRol::class);
        foreach ([1, 2] as $role) {
            $user = Usuario::findOrFail($role);
            $this->asRole($role);
            foreach (['/inventario/bienes', '/inventario/estaciones', '/inventario/toma-inventario', '/movimientos', '/mantenimientos', '/reportes', '/configuracion'] as $url) {
                $this->get($url)->assertOk();
            }
            $this->get('/configuracion')->assertSee('data-config-module="usuarios"', false)->assertSee('data-config-module="roles"', false);
            $this->get('/reportes')->assertSee('/api/reportes/inventario/pdf')->assertSee('/api/reportes/equipos/excel')->assertSee('/api/reportes/mantenimientos/pdf');
            foreach (app('router')->getRoutes() as $route) {
                if (! str_starts_with($route->uri(), 'api/') || $route->uri() === 'api/login') continue;
                $protection = $route->gatherMiddleware();
                $this->assertContains('auth:web', $protection);
                $this->assertContains('usuario.activo', $protection);
                foreach ($protection as $item) {
                    if (! str_starts_with($item, 'rol:')) continue;
                    $request = Request::create('/'); $request->setUserResolver(fn () => $user);
                    $this->assertSame(200, $middleware->handle($request, fn () => response('ok'), ...explode(',', substr($item, 4)))->getStatusCode());
                }
            }
        }
    }
}
