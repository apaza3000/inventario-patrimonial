<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActividadFiltrosTest extends TestCase
{
    private function asRole(string $role, bool $active = true): static
    {
        $user = new Usuario(['nombres' => 'Prueba', 'activo' => $active, 'especialidad_id' => 1, 'password_hash' => 'session-test-only']);
        $user->setRelation('rol', new Rol(['nombre' => $role]));
        return $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000');
    }

    private function equipment(): void
    {
        foreach ([1, 2] as $id) DB::table('equipos')->insert(['bien_id' => $id, 'tipo_equipo_id' => 1, 'serie' => 'SERIE-00'.$id]);
    }

    private function movement(array $data = []): int
    {
        return DB::table('movimientos')->insertGetId(array_merge([
            'bien_id' => 1, 'ambiente_origen_id' => 1, 'ambiente_destino_id' => 2, 'ordenado_por' => 1, 'ejecutado_por' => 1,
            'fecha_movimiento' => '2026-11-11 23:59:59', 'motivo' => 'Solo SQLite', 'estado' => 'pendiente_firma',
        ], $data));
    }

    private function maintenance(array $data = []): int
    {
        return DB::table('mantenimientos')->insertGetId(array_merge([
            'bien_id' => 1, 'tipo_mantenimiento_id' => 1, 'tecnico_id' => 1, 'fecha_mantenimiento' => '2026-11-11', 'descripcion' => 'Solo SQLite',
        ], $data));
    }

    public function test_movement_picker_is_partial_paginated_and_preserves_the_existing_assistant_scope(): void
    {
        $this->equipment();
        $this->getJson('/api/movimientos/bienes?q=00')->assertUnauthorized();
        $this->asRole('coordinador')->getJson('/api/movimientos/bienes?q=00')->assertForbidden();
        foreach (['superadmin', 'administrador', 'director', 'asistente'] as $role) {
            $this->asRole($role)->getJson('/api/movimientos/bienes?q=0000002')->assertOk()
                ->assertJsonPath('data.0.cbi', '0000002')->assertJsonPath('data.0.ambiente.nombre', 'Ambiente simulado 2');
            $this->getJson('/api/movimientos/bienes?q=SERIE-002')->assertJsonPath('total', 1);
            $this->getJson('/api/movimientos/bienes?q=Bien')->assertJsonPath('total', 2);
            $this->getJson('/api/movimientos/opciones?incluir_bienes=0')->assertJsonCount(0, 'data.bienes');
        }
        $this->asRole('asistente')->getJson('/api/movimientos')->assertForbidden();
        for ($i = 0; $i < 18; $i++) DB::table('bienes')->insert(['cbi' => "02-$i", 'descripcion' => 'Bien de prueba', 'ambiente_id' => 1]);
        $this->getJson('/api/movimientos/bienes?q=02')->assertJsonPath('total', 19)->assertJsonPath('per_page', 15)->assertJsonCount(15, 'data');
        $this->getJson('/api/movimientos/bienes?q=02&page=2')->assertJsonCount(4, 'data');
        $this->getJson('/api/movimientos/bienes?q=x')->assertUnprocessable();
        $this->getJson('/api/movimientos/bienes?q=no-existe')->assertJsonPath('total', 0);
        $this->getJson('/api/movimientos/bienes?bien_id=2')->assertJsonPath('data.0.id', 2);
        $this->asRole('asistente', false)->getJson('/api/movimientos/bienes?q=00')->assertForbidden();
    }

    public function test_movement_filters_combine_before_real_pagination_and_include_the_whole_day(): void
    {
        $this->equipment();
        $this->asRole('superadmin')->getJson('/api/movimientos')->assertOk()->assertJsonPath('total', 0);
        for ($i = 0; $i < 18; $i++) $this->movement();
        $this->movement(['estado' => 'finalizado']);
        $this->movement(['ambiente_origen_id' => 2]);
        $this->movement(['fecha_movimiento' => '2026-11-12 00:00:00']);
        $query = '/api/movimientos?q=SERIE-001&bien_id=1&desde=2026-11-11&hasta=2026-11-11&ambiente_origen_id=1&ambiente_destino_id=2&estado=pendiente_firma';
        foreach (['superadmin', 'director', 'administrador'] as $role) {
            $this->asRole($role)->getJson($query)->assertOk()->assertJsonPath('total', 18)->assertJsonCount(15, 'data');
            $this->getJson($query.'&page=2')->assertJsonCount(3, 'data');
        }
        $this->getJson('/api/movimientos?hasta=2026-11-11')->assertOk()->assertJsonPath('total', 20);
        $this->getJson('/api/movimientos?desde=2026-11-12&hasta=2026-11-11')->assertUnprocessable();
        $this->getJson('/api/movimientos?estado=inventado')->assertUnprocessable();
        $this->asRole('coordinador')->getJson($query)->assertForbidden();
        $this->asRole('asistente')->getJson($query)->assertForbidden();
    }

    public function test_maintenance_filters_preserve_specialty_scope_even_with_or_search_matches(): void
    {
        $this->equipment();
        $this->asRole('director')->getJson('/api/mantenimientos')->assertOk()->assertJsonPath('total', 0);
        for ($i = 0; $i < 18; $i++) $this->maintenance();
        $this->maintenance(['bien_id' => 2]);
        $this->maintenance(['tecnico_id' => null]);
        $this->maintenance(['fecha_mantenimiento' => '2026-11-12']);
        $query = '/api/mantenimientos?q=Bien&tipo_mantenimiento_id=1&tecnico_id=1&tecnico=Usuario%20Simulado&desde=2026-11-11&hasta=2026-11-11';
        $this->asRole('coordinador')->getJson($query)->assertOk()->assertJsonPath('total', 18)->assertJsonCount(15, 'data');
        $this->getJson($query.'&page=2')->assertJsonCount(3, 'data');
        $this->getJson('/api/mantenimientos?q=SERIE-002')->assertJsonPath('total', 0);
        foreach (['superadmin', 'director', 'administrador'] as $role) $this->asRole($role)->getJson($query)->assertOk()->assertJsonPath('total', 19);
        $this->getJson('/api/mantenimientos?hasta=2026-11-11')->assertOk()->assertJsonPath('total', 20);
        $this->getJson('/api/mantenimientos?desde=2026-11-12&hasta=2026-11-11')->assertUnprocessable();
        $this->getJson('/api/mantenimientos?tipo_mantenimiento_id=99999')->assertUnprocessable();
        $this->getJson('/api/mantenimientos?tecnico_id=99999')->assertUnprocessable();
        $this->getJson('/api/mantenimientos?q=sin-coincidencias')->assertJsonPath('total', 0);
        $this->asRole('asistente')->getJson($query)->assertForbidden();
    }

    public function test_ids_returned_by_suggestions_register_the_chosen_asset_without_changing_the_existing_flows(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $disk = Storage::fake('local');
        try {
            $this->asRole('director');
            $suggestion = $this->getJson('/api/movimientos/bienes?q=0000001')->assertOk()->json('data.0');
            $movement = $this->postJson('/api/movimientos', [
                'bien_id' => $suggestion['id'], 'ambiente_origen_id' => $suggestion['ambiente_id'],
                'ambiente_destino_id' => 2, 'ordenado_por' => 1, 'ejecutado_por' => 1,
                'fecha_movimiento' => '2026-11-12 10:30:00', 'motivo' => 'Solo SQLite y almacenamiento simulado',
            ])->assertCreated()->assertJsonPath('data.bien_id', $suggestion['id'])
                ->assertJsonPath('data.estado', 'pendiente_firma');
            $disk->assertExists('movimientos/'.$movement->json('data.id').'/generado.pdf');
            $this->assertDatabaseHas('bienes', ['id' => $suggestion['id'], 'ambiente_id' => $suggestion['ambiente_id']]);

            $suggestion = $this->getJson('/api/busqueda/bienes?q=0000002')->assertOk()->json('data.0');
            $this->postJson('/api/mantenimientos', [
                'bien_id' => $suggestion['id'], 'tipo_mantenimiento_id' => 1, 'tecnico_id' => null,
                'fecha_mantenimiento' => '2026-11-12', 'descripcion' => 'Registro desde una sugerencia simulada',
            ])->assertCreated()->assertJsonPath('data.bien_id', $suggestion['id'])->assertJsonPath('data.tecnico_id', null);
            $this->assertDatabaseHas('bienes', ['id' => $suggestion['id'], 'condicion_id' => $suggestion['condicion_id']]);
        } finally {
            $disk->deleteDirectory('movimientos');
        }
    }
}
