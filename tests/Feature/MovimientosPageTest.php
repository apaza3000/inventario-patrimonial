<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Tests\TestCase;

class MovimientosPageTest extends TestCase
{
    private function user(string $role, bool $active = true): Usuario
    {
        $user = new Usuario(['activo' => $active, 'password_hash' => 'test-only-session-hash']);
        $user->setRelation('rol', new Rol(['nombre' => $role]));

        return $user;
    }

    public function test_movements_require_an_active_session_and_allowed_role(): void
    {
        $this->get('/movimientos')->assertRedirect('/login');
        $this->actingAs($this->user('coordinador'), 'web')->get('/movimientos')->assertForbidden();
        $this->actingAs($this->user('asistente', false), 'web')->get('/movimientos')->assertForbidden();
    }

    public function test_full_access_roles_have_list_registration_and_pdf_flow(): void
    {
        foreach (['superadmin', 'administrador', 'director'] as $role) {
            $this->actingAs($this->user($role), 'web')->get('/movimientos')->assertOk()
                ->assertSee('data-movements-list', false)
                ->assertSee('La búsqueda se limita a la página visible')
                ->assertSee('data-movement-create-form', false)
                ->assertDontSee('data-movement-lookup-form', false)
                ->assertDontSee('Consultar por ID')
                ->assertSee('data-bien-search', false)
                ->assertSee('La búsqueda incluye todos los bienes recibidos')
                ->assertSee('data-movement-upload-form', false)
                ->assertSee('Subir PDF firmado y finalizar')
                ->assertSee('href="'.route('movimientos.index').'" class="menu-item active"', false);
        }
    }

    public function test_assistant_has_lookup_registration_and_pdf_actions_without_general_list(): void
    {
        $this->actingAs($this->user('asistente'), 'web')->get('/movimientos')->assertOk()
            ->assertSee('data-movement-lookup-form', false)
            ->assertSee('<details class="catalog-filters movement-lookup-secondary">', false)
            ->assertSee('data-bien-search', false)
            ->assertSee('data-movement-create-form', false)
            ->assertSee('data-movement-detail-dialog', false)
            ->assertSee('data-movement-upload-form', false)
            ->assertSee('data-download-generated', false)
            ->assertSee('data-download-signed', false)
            ->assertSee('data-options-url="'.url('/api/movimientos/opciones').'"', false)
            ->assertSee('La ubicación del bien cambia únicamente al subir el PDF firmado')
            ->assertDontSee('data-movements-list', false)
            ->assertDontSee('data-movements-search', false)
            ->assertDontSee('data-movements-rows', false);
    }

    public function test_assistant_api_can_read_options_but_not_general_list(): void
    {
        $this->actingAs($this->user('asistente'), 'web')->withHeader('Origin', 'http://localhost:3000');
        $this->getJson('/api/movimientos')->assertForbidden();
        $this->getJson('/api/movimientos/opciones')->assertOk()
            ->assertJsonStructure(['data' => ['bienes', 'ambientes', 'responsables']]);
    }

    public function test_coordinador_has_no_active_movements_link(): void
    {
        $this->actingAs($this->user('coordinador'), 'web')->get('/home')->assertOk()
            ->assertDontSee('href="'.route('movimientos.index').'"', false);
    }

    public function test_required_field_titles_group_the_asterisk_with_the_label(): void
    {
        $response = $this->actingAs($this->user('superadmin'), 'web')->get('/movimientos')->assertOk();
        foreach (['Bien', 'Destino', 'Ordenado por', 'Ejecutado por', 'Fecha y hora', 'Motivo', 'PDF firmado (máximo 10 MB)'] as $label) {
            $response->assertSee('<span class="movement-field-label">'.$label.' <span aria-hidden="true">*</span></span>', false);
        }
    }
}
