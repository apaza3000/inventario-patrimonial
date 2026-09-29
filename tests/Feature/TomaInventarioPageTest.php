<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TomaInventarioPageTest extends TestCase
{
    private function user(string $role, bool $active = true): Usuario
    {
        $user = new Usuario(['activo' => $active, 'password_hash' => 'test-only-session-hash']);
        $user->setRelation('rol', new Rol(['nombre' => $role]));

        return $user;
    }

    public function test_inventory_requires_an_active_session_and_allowed_role(): void
    {
        $this->get('/inventario/toma-inventario')->assertRedirect('/login');
        $this->actingAs($this->user('coordinador'), 'web')->get('/inventario/toma-inventario')->assertForbidden();
        $this->actingAs($this->user('asistente', false), 'web')->get('/inventario/toma-inventario')->assertForbidden();
    }

    public function test_full_access_roles_see_management_forms_and_enabled_tabs(): void
    {
        foreach (['superadmin', 'administrador', 'director'] as $role) {
            $this->actingAs($this->user($role), 'web')->get('/inventario/toma-inventario')
                ->assertOk()
                ->assertSee('Alcance: todos los registros de inventario.')
                ->assertSee('data-create-inventory-form', false)
                ->assertSee('data-edit-inventory-form', false)
                ->assertSee('data-delete-inventory-form', false)
                ->assertSee('href="'.route('inventario.bienes').'" class="catalog-tab"', false)
                ->assertSee('href="'.route('inventario.estaciones').'" class="catalog-tab"', false)
                ->assertSee('href="'.route('inventario.toma-inventario').'" class="catalog-tab active" aria-current="page"', false)
                ->assertSee('class="menu-item active"', false);
        }
    }

    public function test_assistant_has_read_only_view_and_sidebar_entry_with_export_and_detail(): void
    {
        $this->actingAs($this->user('asistente'), 'web')->get('/inventario/toma-inventario')
            ->assertOk()
            ->assertSee('Solo consulta: inventarios de bienes en ambientes de tipo LABORATORIO.')
            ->assertSee('La búsqueda se limita a la página visible')
            ->assertSee('data-inventories-url="'.url('/api/inventarios').'"', false)
            ->assertSee('data-inventory-detail-dialog', false)
            ->assertSee('data-export-url="'.url('/api/reportes/inventario/pdf').'"', false)
            ->assertSee('data-export-url="'.url('/api/reportes/inventario/excel').'"', false)
            ->assertSee('aria-disabled="true" title="Sin permiso para esta sección">Catálogo de Bienes', false)
            ->assertSee('aria-disabled="true" title="Sin permiso para esta sección">Estaciones', false)
            ->assertSee('href="'.route('inventario.toma-inventario').'" class="menu-item active"', false)
            ->assertDontSee('data-create-inventory-form', false)
            ->assertDontSee('data-edit-inventory-form', false)
            ->assertDontSee('data-delete-inventory-form', false)
            ->assertDontSee('data-bienes-url', false);
    }

    public function test_catalog_and_stations_link_to_inventory_taking(): void
    {
        $this->actingAs($this->user('superadmin'), 'web');
        foreach (['/inventario/bienes', '/inventario/estaciones'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('href="'.route('inventario.toma-inventario').'" class="catalog-tab">Toma de Inventario', false);
        }
    }

    public function test_inventory_api_keeps_real_pagination_and_assistant_laboratory_scope(): void
    {
        $this->actingAs($this->user('asistente'), 'web')
            ->withHeader('Origin', 'http://localhost:3000');

        $response = $this->getJson('/api/inventarios?page=1')->assertOk()
            ->assertJsonPath('per_page', 15)->assertJsonPath('current_page', 1);

        $rows = $response->json('data');
        $this->assertNotEmpty($rows);
        $allowedIds = DB::table('bienes as b')
            ->join('ambientes as a', 'a.id', '=', 'b.ambiente_id')
            ->join('tipos_ambiente as t', 't.id', '=', 'a.tipo_ambiente_id')
            ->where('t.nombre', 'LABORATORIO')
            ->whereIn('b.id', array_column($rows, 'bien_id'))
            ->pluck('b.id')->all();

        foreach ($rows as $row) {
            $this->assertContains($row['bien_id'], $allowedIds);
            $this->assertArrayHasKey('bien', $row);
        }

        $first = $rows[0];
        $this->getJson('/api/inventarios/'.$first['bien_id'].'/'.$first['anio'])
            ->assertOk()->assertJsonPath('data.bien_id', $first['bien_id']);

        $outside = DB::table('inventarios as i')
            ->join('bienes as b', 'b.id', '=', 'i.bien_id')
            ->leftJoin('ambientes as a', 'a.id', '=', 'b.ambiente_id')
            ->leftJoin('tipos_ambiente as t', 't.id', '=', 'a.tipo_ambiente_id')
            ->where(fn ($query) => $query->whereNull('t.nombre')->orWhere('t.nombre', '!=', 'LABORATORIO'))
            ->first(['i.bien_id', 'i.anio']);

        $this->assertNotNull($outside);
        $this->getJson('/api/inventarios/'.$outside->bien_id.'/'.$outside->anio)->assertNotFound();

        // Middleware rejects these requests before any database write occurs.
        $this->postJson('/api/inventarios', [])->assertForbidden();
        $this->patchJson('/api/inventarios/'.$first['bien_id'].'/'.$first['anio'], [])->assertForbidden();
        $this->deleteJson('/api/inventarios/'.$first['bien_id'].'/'.$first['anio'])->assertForbidden();
    }

    public function test_full_access_roles_can_read_the_unrestricted_inventory_api(): void
    {
        $total = DB::table('inventarios')->count();

        foreach (['superadmin', 'administrador', 'director'] as $role) {
            $this->actingAs($this->user($role), 'web')->withHeader('Origin', 'http://localhost:3000')
                ->getJson('/api/inventarios?page=1')->assertOk()
                ->assertJsonPath('per_page', 15)->assertJsonPath('total', $total);
        }
    }
}
