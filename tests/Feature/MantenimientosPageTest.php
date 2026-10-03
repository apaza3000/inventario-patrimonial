<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Bien;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\Mantenimiento;
use App\Models\Rol;
use App\Models\Sede;
use App\Models\TipoEquipo;
use App\Models\TipoMantenimiento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MantenimientosPageTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role, bool $active = true, ?int $specialty = null): Usuario
    {
        $user = new Usuario(['nombres' => 'Prueba', 'activo' => $active, 'especialidad_id' => $specialty, 'password_hash' => 'test-only-session-hash']);
        $user->setRelation('rol', new Rol(['nombre' => $role]));

        return $user;
    }

    public function test_page_requires_an_active_session_and_maintenance_permission(): void
    {
        $this->get('/mantenimientos')->assertRedirect('/login');
        $this->actingAs($this->user('asistente'), 'web')->get('/mantenimientos')->assertForbidden();
        $this->get('/home')->assertOk()->assertDontSee('href="'.route('mantenimientos.index').'"', false);
        $this->actingAs($this->user('superadmin', false), 'web')->get('/mantenimientos')->assertForbidden();
    }

    public function test_full_access_roles_have_real_endpoints_and_management_forms(): void
    {
        foreach (['superadmin', 'administrador', 'director'] as $role) {
            $this->actingAs($this->user($role), 'web')->get('/mantenimientos')->assertOk()
                ->assertSee('Registro de Mantenimientos')->assertSee('La búsqueda se limita a la página visible')
                ->assertSee('data-maintenance-form', false)->assertSee('data-maintenance-delete-form', false)
                ->assertSee('data-maintenance-detail-dialog', false)->assertSee('Sin asignar')
                ->assertSee('definitiva y no se puede deshacer')
                ->assertSee('data-types-url="'.url('/api/tipos-mantenimiento').'"', false)
                ->assertSee('data-bienes-url="'.url('/api/bienes').'"', false)
                ->assertSee('data-users-url="'.url('/api/usuarios').'"', false)
                ->assertSee('data-conditions-url="'.url('/api/condiciones-bien').'"', false)
                ->assertSee('Cambiar condición (opcional)')->assertSee('Mantener la condición actual')
                ->assertSee('data-condition-section hidden', false)
                ->assertSee('data-export-url="'.url('/api/reportes/mantenimientos/pdf').'"', false)
                ->assertSee('data-export-url="'.url('/api/reportes/mantenimientos/excel').'"', false)
                ->assertSee('href="'.route('mantenimientos.index').'" class="menu-item active"', false);
        }
    }

    public function test_coordinator_has_only_detail_and_scoped_exports_without_management_controls(): void
    {
        $this->actingAs($this->user('coordinador'), 'web')->get('/mantenimientos')->assertOk()
            ->assertSee('Solo consulta: mantenimientos de equipos ubicados actualmente en ambientes de tu especialidad.')
            ->assertSee('data-maintenance-detail-dialog', false)
            ->assertSee('data-export-url="'.url('/api/reportes/mantenimientos/pdf').'"', false)
            ->assertSee('data-export-url="'.url('/api/reportes/mantenimientos/excel').'"', false)
            ->assertDontSee('data-new-maintenance', false)->assertDontSee('data-maintenance-form-dialog', false)
            ->assertDontSee('data-maintenance-delete-form', false)->assertDontSee('data-users-url', false)
            ->assertDontSee('data-bienes-url', false)->assertDontSee('data-condition-section', false)
            ->assertDontSee('data-conditions-url', false);
    }

    public function test_real_api_accepts_nullable_technician_and_supports_detail_edit_delete_and_validation(): void
    {
        $bien = Bien::create(['descripcion' => 'Bien temporal mantenimiento frontend', 'activo' => true]);
        $tipo = TipoMantenimiento::firstOrFail();
        $this->actingAs($this->user('superadmin'), 'web')->withHeader('Origin', 'http://localhost:3000');
        $payload = [
            'bien_id' => $bien->id, 'tipo_mantenimiento_id' => $tipo->id, 'tecnico_id' => null,
            'fecha_mantenimiento' => '2026-10-02', 'descripcion' => 'Registro temporal para prueba',
            'diagnostico' => null, 'trabajo_realizado' => null, 'observaciones' => null,
        ];
        $created = $this->postJson('/api/mantenimientos', $payload)->assertCreated()
            ->assertJsonPath('data.tecnico', null)->assertJsonPath('data.bien.id', $bien->id)
            ->assertJsonPath('data.tipo_mantenimiento.id', $tipo->id);
        $id = $created->json('data.id');
        $this->getJson("/api/mantenimientos/$id")->assertOk()->assertJsonPath('data.descripcion', $payload['descripcion']);
        $this->patchJson("/api/mantenimientos/$id", ['diagnostico' => 'Diagnóstico temporal', 'observaciones' => null])
            ->assertOk()->assertJsonPath('data.diagnostico', 'Diagnóstico temporal')
            ->assertJsonPath('data.bien_id', $bien->id)->assertJsonPath('data.tecnico_id', null);
        $this->postJson('/api/mantenimientos', [])->assertUnprocessable()->assertJsonValidationErrors(['bien_id', 'tipo_mantenimiento_id', 'fecha_mantenimiento', 'descripcion']);
        $this->deleteJson("/api/mantenimientos/$id")->assertOk();
        $this->getJson("/api/mantenimientos/$id")->assertNotFound();
        $this->assertDatabaseMissing('mantenimientos', ['id' => $id]);
    }

    public function test_api_keeps_pagination_and_coordinator_scope_and_blocks_writes(): void
    {
        $own = Especialidad::create(['nombre' => uniqid('Mantenimiento propia ')]);
        $other = Especialidad::create(['nombre' => uniqid('Mantenimiento ajena ')]);
        $tipoEquipo = TipoEquipo::firstOrFail();
        $tipo = TipoMantenimiento::firstOrFail();
        $ownIds = [];
        $foreign = null;
        foreach ([$own, $other] as $specialty) {
            $ambiente = Ambiente::create(['nombre' => uniqid('Ambiente prueba '), 'sede_id' => Sede::firstOrFail()->id, 'especialidad_id' => $specialty->id]);
            $bien = Bien::create(['descripcion' => 'Equipo temporal mantenimiento', 'ambiente_id' => $ambiente->id, 'activo' => true]);
            Equipo::create(['bien_id' => $bien->id, 'tipo_equipo_id' => $tipoEquipo->id]);
            for ($i = 0; $i < ($specialty->id === $own->id ? 16 : 1); $i++) {
                $record = Mantenimiento::create(['bien_id' => $bien->id, 'tipo_mantenimiento_id' => $tipo->id, 'fecha_mantenimiento' => '2026-10-02', 'descripcion' => 'Temporal para alcance']);
                if ($specialty->id === $own->id) $ownIds[] = $record->id;
                else $foreign = $record->id;
            }
        }
        $this->actingAs($this->user('coordinador', true, $own->id), 'web')->withHeader('Origin', 'http://localhost:3000');
        $this->getJson('/api/mantenimientos?page=1')->assertOk()->assertJsonPath('per_page', 15)
            ->assertJsonPath('total', 16)->assertJsonCount(15, 'data');
        $this->getJson('/api/mantenimientos?page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/mantenimientos/'.$ownIds[0])->assertOk();
        $this->getJson('/api/mantenimientos/'.$foreign)->assertNotFound();
        $this->postJson('/api/mantenimientos', [])->assertForbidden();
        $this->patchJson('/api/mantenimientos/'.$ownIds[0], [])->assertForbidden();
        $this->deleteJson('/api/mantenimientos/'.$ownIds[0])->assertForbidden();
        $this->actingAs($this->user('asistente'), 'web')->getJson('/api/mantenimientos')->assertForbidden();
    }
}
