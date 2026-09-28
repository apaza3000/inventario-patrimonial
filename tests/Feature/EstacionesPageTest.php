<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Tests\TestCase;

class EstacionesPageTest extends TestCase
{
    private function userWithRole(string $role, bool $active = true): Usuario
    {
        $user = new Usuario(['activo' => $active]);
        $user->setRelation('rol', new Rol(['nombre' => $role]));

        return $user;
    }

    public function test_stations_require_an_active_session_and_existing_permissions(): void
    {
        $this->get('/inventario/estaciones')->assertRedirect('/login');

        foreach (['asistente', 'coordinador'] as $role) {
            $this->actingAs($this->userWithRole($role), 'web')
                ->get('/inventario/estaciones')->assertForbidden();
        }

        $this->actingAs($this->userWithRole('superadmin', false), 'web')
            ->get('/inventario/estaciones')->assertForbidden();
    }

    public function test_full_access_roles_can_open_stations_with_inventory_active(): void
    {
        foreach (['superadmin', 'administrador', 'director'] as $role) {
            $this->actingAs($this->userWithRole($role), 'web')
                ->get('/inventario/estaciones')
                ->assertOk()
                ->assertSee('class="menu-item active"', false)
                ->assertSee('data-stations-root', false)
                ->assertSee('La búsqueda se limita a la página visible')
                ->assertSee('Historial de asignaciones')
                ->assertSee('data-withdraw-form', false)
                ->assertSee('data-assignment-form', false);
        }
    }

    public function test_inventory_tabs_link_both_pages_and_inventory_taking_stays_disabled(): void
    {
        $this->actingAs($this->userWithRole('superadmin'), 'web');

        $this->get('/inventario/bienes')->assertOk()
            ->assertSee('href="'.route('inventario.estaciones').'" class="catalog-tab">Estaciones', false);

        $this->get('/inventario/estaciones')->assertOk()
            ->assertSee('href="'.route('inventario.bienes').'" class="catalog-tab">Catálogo de Bienes', false)
            ->assertSee('href="'.route('inventario.estaciones').'" class="catalog-tab active" aria-current="page"', false)
            ->assertSee('aria-disabled="true">Toma de Inventario', false);
    }
}
