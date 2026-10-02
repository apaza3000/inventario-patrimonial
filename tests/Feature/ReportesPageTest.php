<?php

namespace Tests\Feature;

use App\Models\Especialidad;
use App\Models\Rol;
use App\Models\Usuario;
use Tests\TestCase;

class ReportesPageTest extends TestCase
{
    private function user(string $role, bool $active = true): Usuario
    {
        $user = new Usuario(['nombres' => 'Prueba', 'activo' => $active, 'password_hash' => 'test-only-session-hash']);
        $user->setRelation('rol', new Rol(['nombre' => $role]));

        return $user;
    }

    public function test_page_requires_active_session_and_allowed_role(): void
    {
        $this->get('/reportes')->assertRedirect('/login');
        $this->actingAs($this->user('otro'), 'web')->get('/reportes')->assertForbidden();
        $this->actingAs($this->user('superadmin', false), 'web')->get('/reportes')->assertForbidden();
    }

    public function test_full_roles_have_exactly_six_downloads_and_active_navigation(): void
    {
        foreach (['superadmin', 'administrador', 'director'] as $role) {
            $response = $this->actingAs($this->user($role), 'web')->get('/reportes')->assertOk()
                ->assertSee('Información completa de inventarios, equipos y mantenimientos.')
                ->assertSee('todos los registros permitidos por tu alcance')
                ->assertSee('href="'.route('reportes.index').'" class="menu-item active"', false);
            foreach (['inventario', 'equipos', 'mantenimientos'] as $type) {
                foreach (['pdf', 'excel'] as $format) {
                    $response->assertSee('data-report-url="'.url("/api/reportes/$type/$format").'"', false);
                }
            }
            $this->assertSame(6, substr_count($response->getContent(), 'data-report-url='));
            $response->assertDontSee('<select', false)->assertDontSee('<form', false);
        }
    }

    public function test_coordinator_has_three_reports_and_real_specialty_scope(): void
    {
        $user = $this->user('coordinador');
        $this->actingAs($user, 'web')->get('/reportes')->assertOk()
            ->assertSee('No tienes una especialidad asignada.')
            ->assertSee('data-report-url="'.url('/api/reportes/inventario/pdf').'"', false)
            ->assertSee('data-report-url="'.url('/api/reportes/equipos/pdf').'"', false)
            ->assertSee('data-report-url="'.url('/api/reportes/mantenimientos/pdf').'"', false);
        $user->especialidad_id = 123;
        $user->setRelation('especialidad', new Especialidad(['nombre' => 'Especialidad de prueba']));
        $response = $this->actingAs($user, 'web')->get('/reportes')->assertOk()
            ->assertSee('Especialidad de prueba')->assertSee('ubicados actualmente en ambientes de tu especialidad')
            ->assertDontSee('No tienes una especialidad asignada.');
        $this->assertSame(6, substr_count($response->getContent(), 'data-report-url='));
    }

    public function test_assistant_only_has_laboratory_inventory_downloads(): void
    {
        $response = $this->actingAs($this->user('asistente'), 'web')->get('/reportes')->assertOk()
            ->assertSee('LABORATORIO')
            ->assertSee('data-report-url="'.url('/api/reportes/inventario/pdf').'"', false)
            ->assertSee('data-report-url="'.url('/api/reportes/inventario/excel').'"', false)
            ->assertDontSee('/api/reportes/equipos')->assertDontSee('/api/reportes/mantenimientos');
        $this->assertSame(2, substr_count($response->getContent(), 'data-report-url='));
    }
}
