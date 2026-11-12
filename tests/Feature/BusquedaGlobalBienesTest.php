<?php

namespace Tests\Feature;

use App\Models\Bien;
use App\Models\Rol;
use App\Models\Usuario;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusquedaGlobalBienesTest extends TestCase
{
    private function asRole(string $role, bool $active = true, ?int $specialty = 1): static
    {
        $user = new Usuario(['nombres' => 'Prueba', 'activo' => $active, 'especialidad_id' => $specialty, 'password_hash' => 'test-session-only']);
        $user->setRelation('rol', new Rol(['nombre' => $role]));
        return $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000');
    }

    private function asset(string $cbi, string $description, int $environment = 1, ?string $serial = null): int
    {
        $bien = Bien::create(['cbi' => $cbi, 'descripcion' => $description, 'ambiente_id' => $environment]);
        if ($serial !== null) DB::table('equipos')->insert(['bien_id' => $bien->id, 'tipo_equipo_id' => 1, 'serie' => $serial]);
        return $bien->id;
    }

    public function test_search_matches_cbi_serial_and_description_and_preserves_leading_zeros(): void
    {
        $cbi = $this->asset('0243831', 'Computadora', 1, 'SER-001');
        $description = $this->asset('X-22', 'Microscopio institucional');
        $this->asRole('superadmin');
        foreach (['0243831', '02438', 'SER-001'] as $q) {
            $this->getJson('/api/busqueda/bienes?'.http_build_query(['q' => $q]))->assertOk()
                ->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $cbi)->assertJsonPath('data.0.cbi', '0243831');
        }
        $this->getJson('/api/busqueda/bienes?q=Microscopio')->assertOk()->assertJsonPath('data.0.id', $description);
        $this->getJson('/api/busqueda/bienes?q=noexiste')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/busqueda/bienes?q=0243831')->assertJsonPath('data.0.equipo.serie', 'SER-001')
            ->assertJsonPath('data.0.ambiente.sede.nombre', 'Sede simulada');
    }

    public function test_search_uses_server_pagination_and_treats_wildcards_literally(): void
    {
        for ($i = 0; $i < 17; $i++) $this->asset("PAG-$i", 'Paginado global');
        $literal = $this->asset('SPECIAL', 'Literal %_!');
        $this->asRole('director')->getJson('/api/busqueda/bienes?q=Paginado')->assertOk()
            ->assertJsonPath('total', 17)->assertJsonPath('per_page', 15)->assertJsonCount(15, 'data');
        $this->getJson('/api/busqueda/bienes?q=Paginado&page=2')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/busqueda/bienes?'.http_build_query(['q' => '%_!']))->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $literal);
    }

    public function test_scopes_apply_to_every_search_alternative_and_direct_detail_requests(): void
    {
        $own = $this->asset('MATCH-01', 'Coincidencia', 1, 'MATCH-SER');
        $foreign = $this->asset('MATCH-02', 'Coincidencia', 2, 'MATCH-SER');
        $nonEquipment = $this->asset('MATCH-03', 'Coincidencia', 1);
        foreach (['superadmin', 'director', 'administrador'] as $role) {
            $this->asRole($role)->getJson('/api/busqueda/bienes?q=MATCH')->assertOk()->assertJsonPath('total', 3);
            $this->getJson("/api/busqueda/bienes/$foreign")->assertOk();
        }
        foreach (['MATCH', 'Coincidencia', 'MATCH-SER'] as $q) {
            $this->asRole('coordinador')->getJson('/api/busqueda/bienes?q='.$q)->assertOk()
                ->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $own);
        }
        $this->getJson("/api/busqueda/bienes/$foreign")->assertNotFound();
        $this->getJson("/api/busqueda/bienes/$nonEquipment")->assertNotFound();
        $this->getJson("/api/busqueda/bienes/$own")->assertOk();
        $this->asRole('coordinador', true, null)->getJson('/api/busqueda/bienes?q=MATCH')->assertJsonPath('total', 0);
        $this->getJson("/api/busqueda/bienes/$own")->assertNotFound();
        $this->asRole('asistente')->getJson('/api/busqueda/bienes?q=MATCH')->assertOk()->assertJsonPath('total', 2);
        $this->getJson("/api/busqueda/bienes/$foreign")->assertNotFound();
        $this->getJson("/api/busqueda/bienes/$nonEquipment")->assertOk();
        DB::table('bienes')->where('id', $own)->update(['ambiente_id' => 2]);
        $this->getJson("/api/busqueda/bienes/$own")->assertNotFound();
    }

    public function test_session_active_user_role_and_query_validation_are_enforced(): void
    {
        $this->getJson('/api/busqueda/bienes?q=00')->assertUnauthorized();
        $this->getJson('/api/busqueda/bienes/1')->assertUnauthorized();
        foreach ([['otro', true], ['superadmin', false]] as [$role, $active]) {
            $this->asRole($role, $active)->getJson('/api/busqueda/bienes?q=00')->assertForbidden();
            $this->asRole($role, $active)->getJson('/api/busqueda/bienes/1')->assertForbidden();
        }
        $this->asRole('superadmin');
        foreach (['', 'a', '  ', str_repeat('a', 151)] as $q) $this->getJson('/api/busqueda/bienes?'.http_build_query(['q' => $q]))->assertUnprocessable();
        $this->getJson('/api/busqueda/bienes?q=00&page=0')->assertUnprocessable();
        $this->getJson('/api/busqueda/bienes?q[]=00')->assertUnprocessable();
        $this->getJson('/api/busqueda/bienes/999999')->assertNotFound();
    }

    public function test_detail_returns_existing_technical_relations_without_write_routes(): void
    {
        $id = $this->asset('PC-00', 'PC de prueba', 1, 'SERIE-00');
        DB::table('equipos_computo')->insert(['bien_id' => $id, 'procesador' => 'CPU de prueba', 'ram' => '16 GB', 'almacenamiento' => '512 GB']);
        $this->asRole('asistente')->getJson("/api/busqueda/bienes/$id")->assertOk()
            ->assertJsonPath('data.equipo.equipo_computo.ram', '16 GB')
            ->assertJsonPath('data.equipo.equipo_computo.almacenamiento', '512 GB')
            ->assertJsonPath('data.equipo.marca', null)->assertJsonPath('data.mueble', null);
        $this->postJson('/api/busqueda/bienes', [])->assertStatus(405);
        $this->patchJson("/api/busqueda/bienes/$id", [])->assertStatus(405);
        $this->deleteJson("/api/busqueda/bienes/$id")->assertStatus(405);
    }

    public function test_shared_header_and_detail_are_available_without_duplicate_ids_on_every_module(): void
    {
        $this->asRole('superadmin');
        foreach (['/home', '/inventario/bienes', '/inventario/estaciones', '/inventario/toma-inventario', '/movimientos', '/mantenimientos', '/mantenimientos/registrar', '/reportes', '/configuracion'] as $url) {
            $html = $this->get($url)->assertOk()->assertSee('data-global-search-input', false)
                ->assertSee('data-global-detail-root', false)->assertDontSee('notification-icon', false)->getContent();
            $doc = new DOMDocument(); @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            $xp = new DOMXPath($doc); $ids = [];
            foreach ($xp->query('//*[@id]') as $element) $ids[] = $element->getAttribute('id');
            $this->assertSame(count($ids), count(array_unique($ids)), $url);
            $this->assertSame(0, $xp->query('//*[@data-global-detail-root]//button[@data-edit-bien]')->length);
        }
    }
}
