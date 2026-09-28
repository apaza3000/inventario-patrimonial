<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/home');
        $this->get('/home')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_dashboard_is_the_active_menu_item_for_an_authenticated_user(): void
    {
        $this->actingAs(new Usuario(['activo' => true]));

        $this->get('/home')
            ->assertOk()
            ->assertSee('class="menu-item active"', false)
            ->assertSee('aria-current="page"', false);
    }
}
