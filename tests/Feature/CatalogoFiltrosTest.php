<?php

namespace Tests\Feature;

use App\Models\Bien;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogoFiltrosTest extends TestCase
{
    private function asRole(string $role, bool $active = true): static
    {
        $user = new Usuario(['activo' => $active, 'password_hash' => 'test-session-only']);
        $user->setRelation('rol', new Rol(['nombre' => $role]));
        return $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000');
    }

    private function asset(string $cbi, int $environment = 1, int $condition = 1, string $category = 'computadoras'): int
    {
        $bien = Bien::create(['cbi' => $cbi, 'descripcion' => 'Equipo de prueba', 'ambiente_id' => $environment, 'estado_id' => 1, 'condicion_id' => $condition]);
        if ($category === 'mobiliario') {
            DB::table('muebles')->insert(['bien_id' => $bien->id, 'tipo_mueble_id' => 1]);
        } else {
            DB::table('equipos')->insert(['bien_id' => $bien->id, 'tipo_equipo_id' => 1]);
            if ($category === 'computadoras') DB::table('equipos_computo')->insert(['bien_id' => $bien->id]);
            if ($category === 'monitores') DB::table('monitores')->insert(['bien_id' => $bien->id]);
        }
        return $bien->id;
    }

    public function test_partial_search_and_all_filters_combine_before_pagination(): void
    {
        DB::table('sedes')->insert(['id' => 2, 'nombre' => 'Otra sede']);
        DB::table('ambientes')->insert(['id' => 3, 'nombre' => 'Otro laboratorio', 'sede_id' => 2, 'tipo_ambiente_id' => 1]);
        $ids = [];
        for ($i = 0; $i < 18; $i++) $ids[] = $this->asset('02'.str_pad((string) $i, 5, '0', STR_PAD_LEFT));
        $this->asset('02-OTHER-SITE', 3); $this->asset('02-CONDITION', 1, 2);
        $this->asset('02-OTHER-ENVIRONMENT', 2);
        DB::table('estados_bien')->insert(['id' => 2, 'nombre' => 'Otro estado']);
        $wrongState = $this->asset('02-OTHER-STATE');
        DB::table('bienes')->where('id', $wrongState)->update(['estado_id' => 2]);
        $this->asset('02-FURNITURE', 1, 1, 'mobiliario'); $this->asset('OTHER-CBI');
        $query = '/api/bienes?q=02&categoria=computadoras&sede_id=1&ambiente_id=1&estado_id=1&condicion_id=1';
        foreach (['superadmin', 'director', 'administrador'] as $role) {
            $this->asRole($role)->getJson($query)->assertOk()->assertJsonPath('total', 18)
                ->assertJsonPath('per_page', 15)->assertJsonCount(15, 'data')->assertJsonPath('data.0.cbi', '0200000');
            $this->getJson($query.'&page=2')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.id', $ids[15]);
        }
        $this->getJson('/api/bienes?q=prueba&sede_id=2')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/bienes?sede_id=2&ambiente_id=1')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_all_categories_work_with_filters_and_exact_cbi_remains_compatible(): void
    {
        foreach (['computadoras', 'monitores', 'mobiliario', 'otros-equipos'] as $category) $this->asset('02-'.$category, 1, 1, $category);
        $this->asRole('superadmin');
        foreach (['computadoras', 'monitores', 'mobiliario', 'otros-equipos'] as $category) {
            $this->getJson('/api/bienes?q=02&ambiente_id=1&categoria='.$category)->assertOk()->assertJsonPath('total', 1);
        }
        $this->getJson('/api/bienes?q=02-&categoria=todos')->assertJsonPath('total', 4);
        $this->getJson('/api/bienes?cbi=02-computadoras')->assertJsonPath('total', 1);
        $this->getJson('/api/bienes?cbi=02')->assertJsonPath('total', 0);
        $this->getJson('/api/bienes?q=no-existe')->assertJsonPath('total', 0);
        $this->asset('LITERAL%_');
        $this->getJson('/api/bienes?'.http_build_query(['q' => '%_']))->assertOk()->assertJsonPath('total', 1);
    }

    public function test_filters_do_not_expand_roles_and_reject_invalid_parameters(): void
    {
        $url = '/api/bienes?q=02&sede_id=1';
        $this->getJson($url)->assertUnauthorized();
        foreach (['coordinador', 'asistente', 'otro'] as $role) $this->asRole($role)->getJson($url)->assertForbidden();
        $this->asRole('superadmin', false)->getJson($url)->assertForbidden();
        $this->asRole('superadmin');
        foreach (['sede_id', 'ambiente_id', 'estado_id', 'condicion_id'] as $field) {
            foreach (['0', 'texto', '9999999'] as $value) $this->getJson('/api/bienes?'.$field.'='.$value)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->getJson('/api/bienes?q[]=02')->assertUnprocessable();
        $this->getJson('/api/bienes?q='.str_repeat('a', 151))->assertUnprocessable();
        $this->getJson('/api/bienes?page=0')->assertUnprocessable();
    }
}
