<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Tests\TestCase;

class CatalogoBienesPageTest extends TestCase
{
    private function userWithRole(string $role): Usuario
    {
        $user = new Usuario(['activo' => true]);
        $user->setRelation('rol', new Rol(['nombre' => $role]));

        return $user;
    }

    public function test_catalog_requires_a_session_and_the_existing_permission(): void
    {
        $this->get('/inventario/bienes')->assertRedirect('/login');

        $this->actingAs($this->userWithRole('asistente'))
            ->get('/inventario/bienes')
            ->assertForbidden();
    }

    public function test_authorized_user_sees_catalog_with_inventory_active(): void
    {
        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/inventario/bienes')
            ->assertOk()
            ->assertSee('Catálogo General de Bienes')
            ->assertSee('Registrar nuevo bien')
            ->assertSee('class="menu-item active"', false)
            ->assertSee('data-catalog-rows', false);
    }
}
