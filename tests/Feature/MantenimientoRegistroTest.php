<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MantenimientoRegistroTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'maintenance_memory', 'database.connections.maintenance_memory' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::listen(function ($query): void { $this->assertSame('maintenance_memory', $query->connectionName); });
        foreach (['roles', 'especialidades', 'sedes', 'estados_bien', 'condiciones_bien', 'marcas', 'tipos_equipo', 'tipos_mueble', 'tipos_mantenimiento'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->increments('id'); $table->string('nombre'); $table->string('descripcion')->nullable();
            });
        }
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id'); $table->string('nombres'); $table->string('apellidos'); $table->string('correo');
            $table->string('password_hash'); $table->integer('rol_id'); $table->integer('especialidad_id')->nullable(); $table->boolean('activo');
        });
        Schema::create('ambientes', function (Blueprint $table): void {
            $table->increments('id'); $table->string('nombre'); $table->integer('especialidad_id')->nullable(); $table->integer('sede_id');
        });
        Schema::create('bienes', function (Blueprint $table): void {
            $table->increments('id'); $table->string('cbi')->nullable()->unique(); $table->string('descripcion');
            $table->integer('estado_id')->nullable(); $table->integer('condicion_id')->nullable(); $table->integer('ambiente_id')->nullable();
            $table->boolean('activo')->default(true); $table->dateTime('fecha_registro')->nullable(); $table->string('observaciones')->nullable();
        });
        Schema::create('equipos', function (Blueprint $table): void {
            $table->integer('bien_id')->primary(); $table->integer('tipo_equipo_id'); $table->integer('marca_id')->nullable();
            $table->string('modelo')->nullable(); $table->string('serie')->nullable();
        });
        Schema::create('equipos_computo', function (Blueprint $table): void {
            $table->integer('bien_id')->primary();
            foreach (['procesador', 'ram', 'almacenamiento', 'detalle_adicional'] as $name) $table->string($name)->nullable();
        });
        Schema::create('monitores', function (Blueprint $table): void {
            $table->integer('bien_id')->primary(); $table->string('tamanio_pantalla')->nullable(); $table->string('detalle_pantalla')->nullable();
        });
        Schema::create('muebles', function (Blueprint $table): void {
            $table->integer('bien_id')->primary(); $table->integer('tipo_mueble_id'); $table->string('material')->nullable(); $table->string('color')->nullable();
        });
        Schema::create('mantenimientos', function (Blueprint $table): void {
            $table->increments('id'); $table->integer('bien_id'); $table->integer('tipo_mantenimiento_id'); $table->integer('tecnico_id')->nullable();
            $table->date('fecha_mantenimiento'); $table->string('descripcion');
            foreach (['diagnostico', 'trabajo_realizado', 'observaciones'] as $name) $table->string($name)->nullable();
            $table->foreign('bien_id')->references('id')->on('bienes');
            $table->foreign('tipo_mantenimiento_id')->references('id')->on('tipos_mantenimiento');
            $table->foreign('tecnico_id')->references('id')->on('usuarios');
        });
        foreach (['superadmin', 'director', 'administrador', 'coordinador', 'asistente'] as $i => $role) {
            DB::table('roles')->insert(['id' => $i + 1, 'nombre' => $role]);
            DB::table('usuarios')->insert(['id' => $i + 1, 'nombres' => 'Prueba', 'apellidos' => 'Memoria', 'correo' => "$i@example.test", 'password_hash' => 'test-session-only', 'rol_id' => $i + 1, 'especialidad_id' => $i === 3 ? 1 : null, 'activo' => true]);
        }
        DB::table('usuarios')->insert(['id' => 6, 'nombres' => 'Opcional', 'apellidos' => 'Inactivo', 'correo' => 'inactivo@example.test', 'password_hash' => 'test-session-only', 'rol_id' => 5, 'especialidad_id' => null, 'activo' => false]);
        DB::table('especialidades')->insert([['id' => 1, 'nombre' => 'Propia'], ['id' => 2, 'nombre' => 'Ajena']]);
        DB::table('sedes')->insert(['id' => 1, 'nombre' => 'Sede de prueba']);
        DB::table('ambientes')->insert([['id' => 1, 'nombre' => 'Propio', 'sede_id' => 1, 'especialidad_id' => 1], ['id' => 2, 'nombre' => 'Ajeno', 'sede_id' => 1, 'especialidad_id' => 2]]);
        DB::table('condiciones_bien')->insert([['id' => 1, 'nombre' => 'OPERATIVO'], ['id' => 2, 'nombre' => 'AUSENTE']]);
        DB::table('tipos_mantenimiento')->insert([['id' => 43, 'nombre' => 'Preventivo'], ['id' => 44, 'nombre' => 'Correctivo']]);
        DB::table('tipos_equipo')->insert(['id' => 41, 'nombre' => 'CPU']);
        DB::table('marcas')->insert(['id' => 17, 'nombre' => 'Marca de prueba']);
        for ($id = 1; $id <= 20; $id++) {
            DB::table('bienes')->insert(['id' => $id, 'cbi' => $id === 20 ? '0243831' : str_pad((string) $id, 7, '0', STR_PAD_LEFT), 'descripcion' => "Bien simulado $id", 'condicion_id' => 1, 'ambiente_id' => $id === 19 ? 2 : 1]);
        }
        foreach ([19, 20] as $id) {
            DB::table('equipos')->insert(['bien_id' => $id, 'tipo_equipo_id' => 41, 'marca_id' => 17]);
            DB::table('equipos_computo')->insert(['bien_id' => $id, 'procesador' => 'CPU de prueba', 'ram' => '16 GB', 'almacenamiento' => '512 GB']);
        }
    }

    private function asRole(int $id): static
    {
        return $this->actingAs(Usuario::findOrFail($id), 'web')->withHeader('Origin', 'http://localhost:3000');
    }

    private function payload(array $extra = []): array
    {
        return ['bien_id' => 20, 'tipo_mantenimiento_id' => 44, 'tecnico_id' => null, 'fecha_mantenimiento' => '2026-11-11', 'descripcion' => 'Prueba en memoria', ...$extra];
    }

    public function test_exact_cbi_search_finds_goods_beyond_page_one_and_preserves_existing_pagination(): void
    {
        $this->asRole(1);
        $this->getJson('/api/bienes')->assertOk()->assertJsonPath('total', 20)->assertJsonPath('per_page', 15)->assertJsonCount(15, 'data');
        $this->getJson('/api/bienes?page=2')->assertOk()->assertJsonCount(5, 'data');
        $this->getJson('/api/bienes?cbi=0243831')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', 20)->assertJsonPath('data.0.cbi', '0243831');
        $this->getJson('/api/bienes?cbi=243831')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/bienes?cbi=02438%25')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/bienes?cbi=')->assertUnprocessable();
        $this->getJson('/api/bienes?cbi='.str_repeat('0', 26))->assertUnprocessable();
        $this->getJson('/api/bienes?categoria=computadoras&cbi=0243831')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/bienes?categoria=mobiliario&cbi=0243831')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/bienes/20')->assertOk()->assertJsonPath('data.equipo.equipo_computo.ram', '16 GB')->assertJsonPath('data.equipo.modelo', null);
    }

    public function test_registration_preserves_condition_unless_explicitly_changed_and_edit_delete_do_not_revert_it(): void
    {
        foreach ([1, 2, 3] as $role) {
            $this->asRole($role)->postJson('/api/mantenimientos', $this->payload())->assertCreated()->assertJsonPath('data.tecnico_id', null);
            $this->assertDatabaseHas('bienes', ['id' => 20, 'condicion_id' => 1]);
        }
        $id = $this->postJson('/api/mantenimientos', $this->payload(['condicion_id' => 2, 'tecnico_id' => 6]))->assertCreated()
            ->assertJsonPath('data.bien.condicion.id', 2)->assertJsonPath('data.tecnico_id', 6)->json('data.id');
        $this->patchJson("/api/mantenimientos/$id", ['condicion_id' => null])->assertUnprocessable()->assertJsonValidationErrors('condicion_id');
        $this->patchJson("/api/mantenimientos/$id", ['diagnostico' => 'Actualizado'])->assertOk();
        $this->deleteJson("/api/mantenimientos/$id")->assertOk();
        $this->assertDatabaseHas('bienes', ['id' => 20, 'condicion_id' => 2, 'ambiente_id' => 1]);
    }

    public function test_validation_and_permissions_reject_invalid_writes_without_changing_goods(): void
    {
        $this->postJson('/api/mantenimientos', $this->payload())->assertUnauthorized();
        foreach ([4, 5, 6] as $role) {
            $this->asRole($role)->get('/mantenimientos/registrar')->assertForbidden();
            $this->asRole($role)->postJson('/api/mantenimientos', $this->payload())->assertForbidden();
            $this->asRole($role)->getJson('/api/bienes?cbi=0243831')->assertForbidden();
        }
        $this->asRole(1);
        $this->postJson('/api/mantenimientos', [])->assertUnprocessable();
        foreach ([['condicion_id' => null], ['condicion_id' => 999], ['bien_id' => 999], ['tecnico_id' => 999], ['tipo_mantenimiento_id' => 999], ['fecha_mantenimiento' => 'incorrecta'], ['descripcion' => str_repeat('x', 256)]] as $extra) {
            $this->postJson('/api/mantenimientos', $this->payload($extra))->assertUnprocessable();
        }
        $this->assertDatabaseCount('mantenimientos', 0);
        $this->assertDatabaseHas('bienes', ['id' => 20, 'condicion_id' => 1]);
    }

    public function test_list_detail_and_exports_remain_available_with_coordinator_scope(): void
    {
        $this->asRole(1);
        $own = $this->postJson('/api/mantenimientos', $this->payload())->assertCreated()->json('data.id');
        $foreign = $this->postJson('/api/mantenimientos', $this->payload(['bien_id' => 19]))->assertCreated()->json('data.id');
        $this->getJson('/api/mantenimientos')->assertOk()->assertJsonPath('per_page', 15)->assertJsonPath('total', 2);
        $this->asRole(4)->getJson('/api/mantenimientos')->assertOk()->assertJsonPath('total', 1);
        $this->getJson("/api/mantenimientos/$own")->assertOk(); $this->getJson("/api/mantenimientos/$foreign")->assertNotFound();
        $this->patchJson("/api/mantenimientos/$own", ['descripcion' => 'NO'])->assertForbidden();
        $this->deleteJson("/api/mantenimientos/$own")->assertForbidden();
        foreach ([1, 4] as $role) {
            $this->asRole($role);
            $pdf = $this->get('/api/reportes/mantenimientos/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $pdf->getContent());
            $excel = $this->get('/api/reportes/mantenimientos/excel')->assertOk();
            $this->assertStringStartsWith('PK', $excel->getContent());
        }
    }
}
