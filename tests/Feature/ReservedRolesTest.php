<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReservedRolesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $superadmin = Rol::query()->where('nombre', 'superadmin')->firstOrFail();
        $usuario = Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Superadmin',
            'correo' => uniqid('roles-', true).'@example.test',
            'password_hash' => Hash::make('ClaveSegura123!'),
            'rol_id' => $superadmin->id,
            'activo' => true,
            'fecha_registro' => now(),
        ]);

        $this->actingAs($usuario, 'web')->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ]);
    }

    public function test_superadmin_cannot_be_renamed(): void
    {
        $rol = Rol::query()->where('nombre', 'superadmin')->firstOrFail();

        $this->patchJson("/api/roles/{$rol->id}", ['nombre' => 'superusuario'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nombre.0', 'No se puede cambiar el nombre de un rol reservado del sistema.');

        $this->assertDatabaseHas('roles', ['id' => $rol->id, 'nombre' => 'superadmin']);
    }

    public function test_asistente_cannot_be_renamed(): void
    {
        $rol = Rol::query()->where('nombre', 'asistente')->firstOrFail();

        $this->patchJson("/api/roles/{$rol->id}", ['nombre' => 'auxiliar'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('roles', ['id' => $rol->id, 'nombre' => 'asistente']);
    }

    public function test_reserved_role_description_can_be_updated(): void
    {
        $rol = Rol::query()->where('nombre', 'director')->firstOrFail();

        $this->patchJson("/api/roles/{$rol->id}", ['descripcion' => 'Descripción temporal'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'director')
            ->assertJsonPath('data.descripcion', 'Descripción temporal');
    }

    public function test_reserved_role_cannot_be_deleted(): void
    {
        $rol = Rol::query()->where('nombre', 'coordinador')->firstOrFail();

        $this->deleteJson("/api/roles/{$rol->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'No se puede eliminar un rol reservado del sistema.');

        $this->assertDatabaseHas('roles', ['id' => $rol->id, 'nombre' => 'coordinador']);
    }

    public function test_additional_role_can_be_created_renamed_and_deleted(): void
    {
        $created = $this->postJson('/api/roles', [
            'nombre' => 'temporal',
            'descripcion' => null,
        ])->assertCreated();
        $id = $created->json('data.id');

        $this->patchJson("/api/roles/{$id}", [
            'nombre' => 'temporal-editado',
            'descripcion' => 'Descripción temporal',
        ])->assertOk()
            ->assertJsonPath('data.nombre', 'temporal-editado');

        $this->deleteJson("/api/roles/{$id}")->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $id]);
    }
}
