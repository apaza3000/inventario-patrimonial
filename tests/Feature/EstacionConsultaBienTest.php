<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstacionConsultaBienTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::table('bienes')->insert(['id' => 3, 'cbi' => null, 'descripcion' => 'Mueble simulado']);
        DB::table('equipos')->insert([
            ['bien_id' => 1, 'tipo_equipo_id' => 1, 'marca_id' => 1, 'modelo' => 'Modelo de prueba', 'serie' => '000SERIE'],
            ['bien_id' => 2, 'tipo_equipo_id' => 1, 'marca_id' => null, 'modelo' => null, 'serie' => null],
        ]);
        DB::table('equipos_computo')->insert(['bien_id' => 1, 'procesador' => 'Procesador simulado', 'ram' => '16 GB', 'almacenamiento' => '512 GB SSD', 'detalle_adicional' => 'Datos actuales']);
        DB::table('monitores')->insert(['bien_id' => 2, 'tamanio_pantalla' => '24 pulgadas', 'detalle_pantalla' => null]);
        DB::table('muebles')->insert(['bien_id' => 3, 'tipo_mueble_id' => 1, 'material' => 'Madera', 'color' => null]);
        DB::table('estaciones_pc')->insert(['id' => 7, 'codigo' => 'EST-07', 'descripcion' => 'Estacion simulada', 'activo' => true]);
        DB::table('estacion_componentes')->insert([
            ['id' => 11, 'estacion_id' => 7, 'bien_id' => 1, 'activo' => true, 'fecha_asignacion' => '2026-01-02', 'fecha_retiro' => null],
            ['id' => 12, 'estacion_id' => 7, 'bien_id' => 2, 'activo' => false, 'fecha_asignacion' => null, 'fecha_retiro' => '2026-02-01'],
            ['id' => 13, 'estacion_id' => 7, 'bien_id' => 3, 'activo' => true, 'fecha_asignacion' => null, 'fecha_retiro' => null],
        ]);
    }

    private function asRole(string $role, bool $active = true): static
    {
        $user = new Usuario(['activo' => $active, 'especialidad_id' => 1, 'password_hash' => 'test-session-only']);
        $user->setRelation('rol', new Rol(['nombre' => $role]));
        return $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_station_assignments_point_to_assets_whose_existing_detail_contains_current_technical_data(): void
    {
        foreach (['superadmin', 'administrador', 'director'] as $role) {
            $this->asRole($role)->getJson('/api/estaciones-pc/7')->assertOk()->assertJsonCount(3, 'data.componentes')
                ->assertJsonPath('data.componentes.0.bien.id', 1)->assertJsonPath('data.componentes.0.bien.cbi', '0000001')
                ->assertJsonPath('data.componentes.1.activo', false)->assertJsonPath('data.componentes.2.bien.cbi', null)
                ->assertJsonMissingPath('data.componentes.0.ram');
            foreach (['/api/bienes/1', '/api/busqueda/bienes/1'] as $endpoint) {
                $this->getJson($endpoint)->assertOk()->assertJsonPath('data.id', 1)
                    ->assertJsonPath('data.equipo.marca.nombre', 'Marca simulada')
                    ->assertJsonPath('data.equipo.modelo', 'Modelo de prueba')->assertJsonPath('data.equipo.serie', '000SERIE')
                    ->assertJsonPath('data.equipo.equipo_computo.procesador', 'Procesador simulado')
                    ->assertJsonPath('data.equipo.equipo_computo.ram', '16 GB')
                    ->assertJsonPath('data.equipo.equipo_computo.almacenamiento', '512 GB SSD')
                    ->assertJsonPath('data.equipo.equipo_computo.detalle_adicional', 'Datos actuales');
            }
            $this->getJson('/api/equipos/1')->assertOk()->assertJsonPath('data.equipo_computo.ram', '16 GB');
            $this->getJson('/api/equipos-computo/1')->assertOk()->assertJsonPath('data.procesador', 'Procesador simulado');
            $this->getJson('/api/busqueda/bienes/2')->assertOk()->assertJsonPath('data.equipo.monitor.tamanio_pantalla', '24 pulgadas')
                ->assertJsonPath('data.equipo.marca', null)->assertJsonPath('data.equipo.serie', null)->assertJsonPath('data.equipo.equipo_computo', null);
            $this->getJson('/api/busqueda/bienes/3')->assertOk()->assertJsonPath('data.cbi', null)->assertJsonPath('data.equipo', null)
                ->assertJsonPath('data.mueble.material', 'Madera')->assertJsonPath('data.mueble.color', null);
        }
    }

    public function test_consulting_a_station_and_its_assets_does_not_write_any_assignment_or_technical_record(): void
    {
        $this->asRole('director');
        $tables = ['bienes', 'equipos', 'equipos_computo', 'monitores', 'muebles', 'estaciones_pc', 'estacion_componentes'];
        $before = [];
        foreach ($tables as $table) $before[$table] = DB::table($table)->get()->toArray();
        $station = $this->getJson('/api/estaciones-pc/7')->assertOk()->json('data');
        foreach ($station['componentes'] as $component) {
            $this->getJson('/api/estacion-componentes/'.$component['id'])->assertOk()->assertJsonPath('data.bien.id', $component['bien_id']);
            $this->getJson('/api/busqueda/bienes/'.$component['bien_id'])->assertOk();
        }
        foreach ($tables as $table) $this->assertEquals($before[$table], DB::table($table)->get()->toArray(), $table);
    }

    public function test_station_access_and_asset_detail_scopes_are_not_expanded_for_assistant_or_coordinator(): void
    {
        $this->getJson('/api/estaciones-pc/7')->assertUnauthorized();
        foreach (['asistente', 'coordinador'] as $role) {
            $this->asRole($role)->get('/inventario/estaciones')->assertForbidden();
            $this->getJson('/api/estaciones-pc/7')->assertForbidden();
            $this->getJson('/api/estacion-componentes/11')->assertForbidden();
            $this->getJson('/api/busqueda/bienes/1')->assertOk()->assertJsonPath('data.equipo.equipo_computo.ram', '16 GB');
            $this->getJson('/api/busqueda/bienes/2')->assertNotFound();
        }
        $this->asRole('superadmin', false)->getJson('/api/estaciones-pc/7')->assertForbidden();
    }

    public function test_existing_assignment_and_withdrawal_validations_remain_intact(): void
    {
        $this->asRole('superadmin');
        $this->postJson('/api/estacion-componentes', ['estacion_id' => 7, 'bien_id' => 1])->assertConflict();
        $this->patchJson('/api/estacion-componentes/11', ['bien_id' => 2])->assertUnprocessable();
        $this->patchJson('/api/estacion-componentes/11', ['activo' => false])->assertUnprocessable();
        $this->patchJson('/api/estacion-componentes/11', ['activo' => false, 'fecha_retiro' => '2025-01-01'])->assertUnprocessable();
        $this->patchJson('/api/estacion-componentes/11', ['activo' => false, 'fecha_retiro' => '2026-11-14'])->assertOk()->assertJsonPath('data.activo', false);
        $this->patchJson('/api/estacion-componentes/11', ['activo' => true, 'fecha_retiro' => null])->assertUnprocessable();
        $this->getJson('/api/estaciones-pc/7')->assertOk()->assertJsonCount(3, 'data.componentes');
        $this->deleteJson('/api/estaciones-pc/7')->assertConflict();
        $this->assertDatabaseHas('equipos_computo', ['bien_id' => 1, 'ram' => '16 GB']);
        $this->assertDatabaseHas('bienes', ['id' => 1, 'ambiente_id' => 1]);
        $this->assertDatabaseHas('estacion_componentes', ['id' => 11, 'fecha_asignacion' => '2026-01-02', 'activo' => false]);
        $this->assertSame('2026-11-14', \App\Models\EstacionComponente::findOrFail(11)->fecha_retiro->format('Y-m-d'));
    }
}
