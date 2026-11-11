<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CatalogoCategoriasTest extends TestCase
{
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'catalog_memory', 'database.connections.catalog_memory' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::listen(function ($query): void { $this->assertSame('catalog_memory', $query->connectionName); });
        foreach (['roles', 'especialidades', 'sedes', 'marcas', 'tipos_equipo', 'tipos_mueble', 'estados_bien', 'condiciones_bien'] as $name) {
            Schema::create($name, function (Blueprint $table): void { $table->increments('id'); $table->string('nombre'); });
        }
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id'); $table->string('nombres'); $table->string('apellidos'); $table->string('correo');
            $table->string('password_hash'); $table->integer('rol_id'); $table->integer('especialidad_id')->nullable(); $table->boolean('activo');
        });
        Schema::create('ambientes', function (Blueprint $table): void {
            $table->increments('id'); $table->string('nombre'); $table->integer('especialidad_id')->nullable(); $table->integer('sede_id');
        });
        Schema::create('bienes', function (Blueprint $table): void {
            $table->increments('id'); $table->string('cbi')->nullable()->unique(); $table->string('descripcion', 150);
            $table->integer('estado_id')->nullable(); $table->integer('condicion_id')->nullable(); $table->integer('ambiente_id')->nullable();
            $table->string('observaciones')->nullable(); $table->boolean('activo')->default(true); $table->dateTime('fecha_registro')->nullable();
        });
        Schema::create('equipos', function (Blueprint $table): void {
            $table->integer('bien_id')->primary(); $table->integer('tipo_equipo_id'); $table->integer('marca_id')->nullable();
            $table->string('modelo')->nullable(); $table->string('serie')->nullable();
            $table->foreign('bien_id')->references('id')->on('bienes'); $table->foreign('tipo_equipo_id')->references('id')->on('tipos_equipo');
        });
        Schema::create('equipos_computo', function (Blueprint $table): void {
            $table->integer('bien_id')->primary();
            foreach (['procesador', 'ram', 'almacenamiento', 'detalle_adicional'] as $name) $table->string($name)->nullable();
            $table->foreign('bien_id')->references('bien_id')->on('equipos');
        });
        Schema::create('monitores', function (Blueprint $table): void {
            $table->integer('bien_id')->primary(); $table->string('tamanio_pantalla')->nullable(); $table->string('detalle_pantalla')->nullable();
            $table->foreign('bien_id')->references('bien_id')->on('equipos');
        });
        Schema::create('muebles', function (Blueprint $table): void {
            $table->integer('bien_id')->primary(); $table->integer('tipo_mueble_id'); $table->string('material')->nullable(); $table->string('color')->nullable();
            $table->foreign('bien_id')->references('id')->on('bienes'); $table->foreign('tipo_mueble_id')->references('id')->on('tipos_mueble');
        });
        foreach (['superadmin', 'director', 'administrador', 'coordinador', 'asistente'] as $i => $role) {
            DB::table('roles')->insert(['id' => $i + 1, 'nombre' => $role]);
            DB::table('usuarios')->insert(['id' => $i + 1, 'nombres' => 'Prueba', 'apellidos' => 'Memoria', 'correo' => "$i@example.test", 'password_hash' => 'session-test-only', 'rol_id' => $i + 1, 'activo' => true, 'especialidad_id' => $i === 3 ? 1 : null]);
        }
        DB::table('marcas')->insert(['id' => 17, 'nombre' => 'Marca de prueba']);
        DB::table('tipos_equipo')->insert([['id' => 41, 'nombre' => 'CPU'], ['id' => 57, 'nombre' => 'LAPTOP'], ['id' => 83, 'nombre' => 'MONITOR'], ['id' => 92, 'nombre' => 'EQUIPO']]);
        DB::table('tipos_mueble')->insert(['id' => 33, 'nombre' => 'SILLA']);
        DB::table('especialidades')->insert([['id' => 1, 'nombre' => 'Propia'], ['id' => 2, 'nombre' => 'Ajena']]);
        DB::table('sedes')->insert(['id' => 1, 'nombre' => 'Sede']);
        DB::table('ambientes')->insert([['id' => 1, 'nombre' => 'Propio', 'especialidad_id' => 1, 'sede_id' => 1], ['id' => 2, 'nombre' => 'Ajeno', 'especialidad_id' => 2, 'sede_id' => 1]]);
        $this->asRole(1);
    }

    private function asRole(int $id): static
    {
        return $this->actingAs(Usuario::findOrFail($id), 'web')->withHeader('Origin', 'http://localhost:3000');
    }

    private function payload(string $category, array $extra = []): array
    {
        $type = match ($category) { 'computadoras' => ['tipo_equipo_id' => 41], 'monitores' => ['tipo_equipo_id' => 83], 'mobiliario' => ['tipo_mueble_id' => 33], default => ['tipo_equipo_id' => 92] };
        return [...['descripcion' => 'Bien simulado', 'cbi' => '000'.++$this->sequence, 'ambiente_id' => 1, 'categoria' => $category], ...$type, ...$extra];
    }

    private function create(string $category, array $extra = []): int
    {
        return $this->postJson('/api/bienes', $this->payload($category, $extra))->assertCreated()->json('data.id');
    }

    public function test_category_filter_is_applied_before_real_pagination_and_legacy_registration_still_works(): void
    {
        for ($i = 0; $i < 16; $i++) $this->create('computadoras');
        foreach (['monitores', 'mobiliario', 'otros-equipos'] as $category) $this->create($category);
        $this->postJson('/api/bienes', ['descripcion' => 'Registro general compatible'])->assertCreated();
        $this->getJson('/api/bienes')->assertOk()->assertJsonPath('total', 20)->assertJsonPath('per_page', 15);
        $this->getJson('/api/bienes?categoria=computadoras')->assertOk()->assertJsonPath('total', 16)->assertJsonCount(15, 'data');
        $this->getJson('/api/bienes?categoria=computadoras&page=2')->assertOk()->assertJsonCount(1, 'data');
        foreach (['monitores', 'mobiliario', 'otros-equipos'] as $category) $this->getJson('/api/bienes?categoria='.$category)->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/bienes?categoria=inventada')->assertUnprocessable();
        $this->assertDatabaseCount('bienes', 20);
    }

    public function test_complete_registration_and_detail_include_only_real_technical_relations(): void
    {
        $pc = $this->create('computadoras', ['marca_id' => 17, 'modelo' => 'Modelo', 'serie' => '000SERIE', 'procesador' => 'Procesador', 'ram' => '16 GB', 'almacenamiento' => '512 GB', 'detalle_adicional' => null]);
        $this->getJson("/api/bienes/$pc")->assertOk()->assertJsonPath('data.equipo.marca.id', 17)->assertJsonPath('data.equipo.equipo_computo.ram', '16 GB')->assertJsonPath('data.equipo.equipo_computo.almacenamiento', '512 GB')->assertJsonPath('data.equipo.monitor', null)->assertJsonPath('data.mueble', null);
        $monitor = $this->create('monitores', ['tamano_pantalla' => '24 pulgadas', 'detalle_pantalla' => 'Panel de prueba']);
        $this->getJson("/api/bienes/$monitor")->assertOk()->assertJsonPath('data.equipo.monitor.tamanio_pantalla', '24 pulgadas')->assertJsonPath('data.equipo.equipo_computo', null);
        $this->getJson("/api/monitores/$monitor")->assertOk()->assertJsonPath('data.tamano_pantalla', '24 pulgadas')->assertJsonMissingPath('data.tamanio_pantalla');
        $furniture = $this->create('mobiliario', ['material' => 'Madera', 'color' => 'Natural']);
        $this->getJson("/api/bienes/$furniture")->assertOk()->assertJsonPath('data.mueble.tipo_mueble.id', 33)->assertJsonPath('data.mueble.material', 'Madera')->assertJsonPath('data.equipo', null);
        $other = $this->create('otros-equipos');
        $this->getJson("/api/bienes/$other")->assertOk()->assertJsonPath('data.equipo.modelo', null)->assertJsonPath('data.equipo.marca', null);
    }

    public function test_invalid_classification_or_references_never_leave_partial_records(): void
    {
        foreach ([
            $this->payload('computadoras', ['tipo_equipo_id' => 83]),
            $this->payload('monitores', ['tipo_equipo_id' => 41]),
            $this->payload('otros-equipos', ['tipo_equipo_id' => 57]),
            $this->payload('mobiliario', ['tipo_equipo_id' => 41]),
            $this->payload('computadoras', ['tamano_pantalla' => '24']),
            $this->payload('computadoras', ['marca_id' => 999]),
            $this->payload('computadoras', ['ram' => str_repeat('x', 101)]),
            $this->payload('mobiliario', ['tipo_mueble_id' => null]),
            $this->payload('no-existe'),
        ] as $payload) $this->postJson('/api/bienes', $payload)->assertUnprocessable();
        foreach (['bienes', 'equipos', 'muebles', 'equipos_computo', 'monitores'] as $table) $this->assertDatabaseCount($table, 0);
        $this->create('computadoras', ['cbi' => '000001']);
        $this->postJson('/api/bienes', $this->payload('mobiliario', ['cbi' => '000001']))->assertUnprocessable()->assertJsonValidationErrors('cbi');
        $this->assertDatabaseCount('bienes', 1);
    }

    public function test_failure_in_the_last_related_insert_rolls_back_the_whole_registration(): void
    {
        Monitor::creating(function (): void {
            $this->assertDatabaseCount('bienes', 1); $this->assertDatabaseCount('equipos', 1);
            throw new RuntimeException('Fallo simulado del registro relacionado');
        });
        $this->withoutExceptionHandling();
        try {
            $this->postJson('/api/bienes', $this->payload('monitores'));
            $this->fail('The related insert must fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo simulado del registro relacionado', $e->getMessage());
        } finally { Monitor::flushEventListeners(); }
        foreach (['bienes', 'equipos', 'monitores'] as $table) $this->assertDatabaseCount($table, 0);
    }

    public function test_legacy_endpoints_also_reject_incompatible_classifications(): void
    {
        $pc = $this->create('computadoras'); $monitor = $this->create('monitores'); $mueble = $this->create('mobiliario');
        $this->postJson('/api/monitores', ['bien_id' => $pc])->assertConflict();
        $this->postJson('/api/equipos-computo', ['bien_id' => $monitor])->assertConflict();
        $this->postJson('/api/muebles', ['bien_id' => $pc, 'tipo_mueble_id' => 33])->assertConflict();
        $this->postJson('/api/equipos', ['bien_id' => $mueble, 'tipo_equipo_id' => 41])->assertConflict();
        $legacy = $this->postJson('/api/bienes', ['descripcion' => 'Registro por etapas compatible'])->assertCreated()->json('data.id');
        $this->postJson('/api/equipos', ['bien_id' => $legacy, 'tipo_equipo_id' => 41])->assertCreated();
        $this->postJson('/api/equipos-computo', ['bien_id' => $legacy, 'ram' => '8 GB'])->assertCreated();
    }

    public function test_options_full_roles_and_coordinator_specialty_permissions_are_preserved(): void
    {
        $own = $this->create('computadoras'); $foreign = $this->create('otros-equipos', ['ambiente_id' => 2]);
        foreach ([1, 2, 3] as $id) {
            $this->asRole($id)->getJson('/api/bienes/opciones')->assertOk()->assertJsonPath('data.marcas.0.id', 17);
            $this->getJson('/api/bienes?categoria=computadoras')->assertOk();
            $this->get('/inventario/bienes')->assertOk()->assertSee('data-catalog-category="computadoras"', false)->assertSee('name="categoria"', false);
            $this->create('mobiliario');
        }
        $this->asRole(4)->getJson('/api/equipos')->assertOk()->assertJsonPath('total', 1);
        $this->getJson("/api/equipos/$own")->assertOk(); $this->getJson("/api/equipos/$foreign")->assertNotFound();
        foreach ([4, 5] as $id) {
            $this->asRole($id)->getJson('/api/bienes/opciones')->assertForbidden();
            $this->getJson('/api/bienes?categoria=computadoras')->assertForbidden();
            $this->postJson('/api/bienes', $this->payload('computadoras'))->assertForbidden();
            $this->get('/inventario/bienes')->assertForbidden();
        }
    }

    public function test_new_options_and_registration_keep_session_and_active_user_protection(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/bienes/opciones')->assertUnauthorized();
        $this->postJson('/api/bienes', $this->payload('computadoras'))->assertUnauthorized();
        $inactive = Usuario::findOrFail(1); $inactive->activo = false;
        $this->actingAs($inactive, 'web')->getJson('/api/bienes/opciones')->assertForbidden();
        $this->actingAs($inactive, 'web')->postJson('/api/bienes', $this->payload('computadoras'))->assertForbidden();
        $this->assertDatabaseCount('bienes', 0);
        $this->assertDatabaseHas('usuarios', ['id' => 1, 'activo' => true]);
    }
}
