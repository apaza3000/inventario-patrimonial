<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConfiguracionPageTest extends TestCase
{
    use DatabaseTransactions;
    private function user(string $role, bool $active = true): Usuario
    {
        $user = new Usuario(['nombres' => 'Prueba', 'activo' => $active, 'password_hash' => 'test-only-session-hash']);
        $user->id = 987654;
        $user->setRelation('rol', new Rol(['nombre' => $role]));

        return $user;
    }

    public function test_configuration_requires_an_active_authorized_session(): void
    {
        $this->get('/configuracion')->assertRedirect('/login');
        foreach (['coordinador', 'asistente', 'otro'] as $role) {
            $this->actingAs($this->user($role), 'web')->get('/configuracion')->assertForbidden();
        }
        $this->actingAs($this->user('superadmin', false), 'web')->get('/configuracion')->assertForbidden();
    }

    public function test_full_roles_get_only_real_modules_and_shared_modals(): void
    {
        foreach (['superadmin', 'administrador', 'director'] as $role) {
            $response = $this->actingAs($this->user($role), 'web')->get('/configuracion')->assertOk()
                ->assertSee('Usuarios y roles')->assertSee('Ubicación y organización')->assertSee('Catálogos del sistema')
                ->assertSee('Solo consulta')->assertSee('data-user-id="987654"', false)
                ->assertSee('href="'.route('configuracion.index').'" class="menu-item active"', false)
                ->assertSee('data-csrf-url="'.url('/sanctum/csrf-cookie').'"', false)
                ->assertSee('data-detail-dialog', false)->assertSee('data-form-dialog', false)->assertSee('data-confirm-dialog', false);
            foreach (['usuarios', 'roles', 'sedes', 'ambientes', 'especialidades', 'tipos-ambiente', 'estados-bien', 'condiciones-bien', 'tipos-mantenimiento'] as $module) {
                $response->assertSee('data-config-module="'.$module.'"', false);
            }
            $this->assertSame(9, substr_count($response->getContent(), 'data-config-module='));
            $response->assertDontSee('data-config-module="responsables"', false)
                ->assertDontSee('data-config-module="areas"', false)->assertDontSee('data-config-module="categorias"', false);
        }
    }

    public function test_restricted_roles_do_not_get_configuration_navigation(): void
    {
        foreach (['coordinador', 'asistente'] as $role) {
            $this->actingAs($this->user($role), 'web')->get('/home')->assertOk()
                ->assertDontSee('href="'.route('configuracion.index').'"', false);
        }
    }

    public function test_initial_view_has_no_loaded_catalog_and_no_outbound_requests(): void
    {
        Http::preventStrayRequests();
        $response = $this->actingAs($this->user('superadmin'), 'web')->get('/configuracion')->assertOk();
        $response->assertSee('data-workspace hidden', false)
            ->assertSee('<tbody data-rows></tbody>', false)->assertSee('Abre una sección para consultar sus datos.');
        Http::assertNothingSent();
    }

    public function test_api_preserves_pagination_and_readonly_maintenance_types(): void
    {
        $this->actingAs($this->user('superadmin'), 'web')->withHeader('Origin', 'http://localhost:3000');
        $this->getJson('/api/usuarios?page=1')->assertOk()->assertJsonPath('per_page', 15);
        $this->getJson('/api/roles?page=1')->assertOk()->assertJsonPath('per_page', 15);
        $this->getJson('/api/tipos-mantenimiento')->assertOk()->assertJsonStructure(['data' => [['id', 'nombre', 'descripcion']]]);
        $this->postJson('/api/tipos-mantenimiento', [])->assertStatus(405);
        foreach (['coordinador', 'asistente'] as $role) {
            $this->actingAs($this->user($role), 'web');
            foreach (['usuarios', 'roles', 'sedes', 'ambientes', 'especialidades', 'tipos-ambiente', 'estados-bien', 'condiciones-bien'] as $module) {
                $this->getJson('/api/'.$module)->assertForbidden();
            }
        }
    }

    public function test_location_forms_use_real_fields_and_report_dependency_and_validation_errors(): void
    {
        $this->actingAs($this->user('superadmin'), 'web')->withHeader('Origin', 'http://localhost:3000');
        $created = $this->postJson('/api/sedes', ['nombre' => uniqid('Sede prueba configuración '), 'direccion' => null, 'activo' => true])
            ->assertCreated()->assertJsonPath('data.direccion', null);
        $sedeId = $created->json('data.id');
        $created = $this->postJson('/api/ambientes', ['nombre' => 'Ambiente temporal configuración', 'sede_id' => $sedeId, 'tipo_ambiente_id' => null, 'especialidad_id' => null, 'area' => null, 'ancho' => null, 'largo' => null, 'activo' => true])
            ->assertCreated()->assertJsonPath('data.sede.id', $sedeId)->assertJsonPath('data.area', null);
        $id = $created->json('data.id');
        $this->patchJson('/api/ambientes/'.$id, ['area' => -1])->assertUnprocessable()->assertJsonValidationErrors('area');
        $this->patchJson('/api/ambientes/'.$id, ['ancho' => 4.25, 'activo' => false])->assertOk()->assertJsonPath('data.activo', false);
        $this->getJson('/api/ambientes/'.$id)->assertOk()->assertJsonPath('data.sede.id', $sedeId);
        $this->deleteJson('/api/sedes/'.$sedeId)->assertConflict();
        $this->deleteJson('/api/ambientes/'.$id)->assertOk();
        $this->getJson('/api/ambientes/'.$id)->assertNotFound();
        $this->deleteJson('/api/sedes/'.$sedeId)->assertOk();
    }
}
