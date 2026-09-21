<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Bien;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use DatabaseTransactions;

    private function createUsuario(string $rol): Usuario
    {
        return Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => ucfirst($rol),
            'correo' => uniqid($rol.'-', true).'@example.test',
            'password_hash' => Hash::make('ClaveSegura123!'),
            'rol_id' => Rol::query()->where('nombre', $rol)->firstOrFail()->id,
            'activo' => true,
            'fecha_registro' => now(),
        ]);
    }

    private function asRole(string $rol): static
    {
        return $this->actingAs($this->createUsuario($rol), 'web')->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ]);
    }

    private function inventario(bool $laboratorio): object
    {
        $query = DB::table('inventarios as i')
            ->join('bienes as b', 'b.id', '=', 'i.bien_id')
            ->leftJoin('ambientes as a', 'a.id', '=', 'b.ambiente_id')
            ->leftJoin('tipos_ambiente as t', 't.id', '=', 'a.tipo_ambiente_id');

        if ($laboratorio) {
            $query->where('t.nombre', 'LABORATORIO');
        } else {
            $query->where(function ($query) {
                $query->whereNull('t.nombre')->orWhere('t.nombre', '!=', 'LABORATORIO');
            });
        }

        $inventario = $query->first(['i.bien_id', 'i.anio']);

        $this->assertNotNull($inventario, 'No existe un inventario adecuado para ejecutar la prueba.');

        return $inventario;
    }

    public function test_superadmin_has_access_to_general_routes_and_all_inventory(): void
    {
        $outside = $this->inventario(false);

        $this->asRole('superadmin')->getJson('/api/bienes')->assertOk();
        $this->getJson('/api/roles')->assertOk();
        $this->getJson("/api/inventarios/{$outside->bien_id}/{$outside->anio}")->assertOk();
    }

    public function test_asistente_only_sees_laboratory_inventory(): void
    {
        $laboratory = $this->inventario(true);
        $outside = $this->inventario(false);

        $response = $this->asRole('asistente')->getJson('/api/inventarios')->assertOk();
        $returnedIds = collect($response->json('data'))->pluck('bien_id');

        $this->assertFalse($returnedIds->contains($outside->bien_id));
        $this->assertNotEmpty($returnedIds);
        $this->getJson("/api/inventarios/{$laboratory->bien_id}/{$laboratory->anio}")->assertOk();
        $this->getJson("/api/inventarios/{$outside->bien_id}/{$outside->anio}")->assertNotFound();
    }

    public function test_asistente_can_read_minimal_movement_options_and_register_a_movement(): void
    {
        $bien = Bien::query()->firstOrFail();
        $destino = Ambiente::query()
            ->where('activo', true)
            ->whereKeyNot($bien->ambiente_id)
            ->firstOrFail();
        $asistente = $this->createUsuario('asistente');

        $options = $this->actingAs($asistente, 'web')
            ->withHeaders(['Origin' => 'http://localhost:3000', 'Accept' => 'application/json'])
            ->getJson('/api/movimientos/opciones')
            ->assertOk();
        $this->assertSame(['id', 'nombres', 'apellidos'], array_keys($options->json('data.responsables.0')));

        $payload = [
            'bien_id' => $bien->id,
            'ambiente_origen_id' => $bien->ambiente_id,
            'ambiente_destino_id' => $destino->id,
            'ordenado_por' => $asistente->id,
            'ejecutado_por' => $asistente->id,
            'fecha_movimiento' => now()->format('Y-m-d H:i:s'),
            'motivo' => 'Prueba de permisos',
        ];

        $this->postJson('/api/movimientos', $payload)
            ->assertCreated()
            ->assertJsonPath('data.bien_id', $bien->id);
        $this->assertDatabaseHas('movimientos', ['bien_id' => $bien->id, 'motivo' => 'Prueba de permisos']);
        $this->assertDatabaseHas('bienes', ['id' => $bien->id, 'ambiente_id' => $destino->id]);
    }

    public function test_asistente_cannot_access_roles_users_or_other_crud(): void
    {
        $this->asRole('asistente')->getJson('/api/usuarios')->assertForbidden();
        $this->getJson('/api/roles')->assertForbidden();
        $this->getJson('/api/bienes')->assertForbidden();
        $this->getJson('/api/movimientos')->assertForbidden();
    }

    public function test_director_cannot_access_business_routes(): void
    {
        $this->assertUnconfirmedRoleIsForbidden('director');
    }

    public function test_coordinador_cannot_access_business_routes(): void
    {
        $this->assertUnconfirmedRoleIsForbidden('coordinador');
    }

    public function test_administrador_cannot_access_business_routes(): void
    {
        $this->assertUnconfirmedRoleIsForbidden('administrador');
    }

    private function assertUnconfirmedRoleIsForbidden(string $rol): void
    {
        $this->asRole($rol)->getJson('/api/user')->assertOk();
        $this->getJson('/api/inventarios')->assertForbidden();
    }

    public function test_guest_receives_json_unauthorized_response(): void
    {
        $this->withHeader('Accept', 'application/json')
            ->getJson('/api/inventarios')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }
}
