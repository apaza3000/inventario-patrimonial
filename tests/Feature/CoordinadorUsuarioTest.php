<?php

namespace Tests\Feature;

use App\Models\Especialidad;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoordinadorUsuarioTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $usuario = Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Superadmin',
            'correo' => uniqid('usuarios-coordinador-', true).'@example.test',
            'password_hash' => Hash::make('ClaveSegura123!'),
            'rol_id' => Rol::query()->where('nombre', 'superadmin')->firstOrFail()->id,
            'activo' => true,
            'fecha_registro' => now(),
        ]);

        $this->actingAs($usuario, 'web')->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ]);
    }

    public function test_coordinador_requires_an_existing_especialidad(): void
    {
        $this->postJson('/api/usuarios', $this->datosUsuario())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('especialidad_id');

        $this->postJson('/api/usuarios', [
            ...$this->datosUsuario(),
            'especialidad_id' => 2147483647,
        ])->assertUnprocessable()->assertJsonValidationErrors('especialidad_id');
    }

    public function test_coordinador_accepts_all_three_academic_especialidades(): void
    {
        foreach ([1, 2, 3] as $especialidadId) {
            $this->assertDatabaseHas('especialidades', ['id' => $especialidadId]);

            $this->postJson('/api/usuarios', [
                ...$this->datosUsuario(),
                'especialidad_id' => $especialidadId,
            ])->assertCreated()
                ->assertJsonPath('data.especialidad_id', $especialidadId);
        }
    }

    public function test_coordinador_rejects_administrativo_and_otros(): void
    {
        foreach ([4, 5] as $especialidadId) {
            $this->postJson('/api/usuarios', [
                ...$this->datosUsuario(),
                'especialidad_id' => $especialidadId,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('especialidad_id')
                ->assertJsonPath(
                    'errors.especialidad_id.0',
                    'La especialidad seleccionada no corresponde a una carrera académica.'
                );
        }
    }

    public function test_coordinador_can_change_especialidad_and_loses_it_when_role_changes(): void
    {
        $especialidades = Especialidad::query()->orderBy('id')->limit(2)->get();
        $this->assertCount(2, $especialidades);

        $created = $this->postJson('/api/usuarios', [
            ...$this->datosUsuario(),
            'especialidad_id' => $especialidades[0]->id,
        ])->assertCreated()
            ->assertJsonPath('data.especialidad_id', $especialidades[0]->id)
            ->assertJsonPath('data.especialidad.id', $especialidades[0]->id);

        $id = $created->json('data.id');

        $this->patchJson("/api/usuarios/$id", [
            'especialidad_id' => $especialidades[1]->id,
        ])->assertOk()->assertJsonPath('data.especialidad_id', $especialidades[1]->id);

        $director = Rol::query()->where('nombre', 'director')->firstOrFail();
        $this->patchJson("/api/usuarios/$id", ['rol_id' => $director->id])
            ->assertOk()
            ->assertJsonPath('data.especialidad_id', null);

        $this->assertDatabaseHas('usuarios', [
            'id' => $id,
            'rol_id' => $director->id,
            'especialidad_id' => null,
        ]);
    }

    public function test_non_coordinator_never_keeps_an_especialidad(): void
    {
        $especialidad = Especialidad::query()->firstOrFail();
        $director = Rol::query()->where('nombre', 'director')->firstOrFail();

        $response = $this->postJson('/api/usuarios', [
            ...$this->datosUsuario(),
            'rol_id' => $director->id,
            'especialidad_id' => $especialidad->id,
        ])->assertCreated();

        $this->assertNull($response->json('data.especialidad_id'));
    }

    public function test_especialidad_assigned_to_coordinator_cannot_be_deleted(): void
    {
        $especialidad = Especialidad::query()->whereDoesntHave('ambientes')->firstOrFail();

        $this->postJson('/api/usuarios', [
            ...$this->datosUsuario(),
            'especialidad_id' => $especialidad->id,
        ])->assertCreated();

        $this->deleteJson("/api/especialidades/{$especialidad->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'No se puede eliminar la especialidad porque está asignada a uno o más usuarios.');
    }

    private function datosUsuario(): array
    {
        return [
            'nombres' => 'Coordinador',
            'apellidos' => 'Temporal',
            'correo' => uniqid('coordinador-', true).'@example.test',
            'password' => 'ClaveSegura123!',
            'rol_id' => Rol::query()->where('nombre', 'coordinador')->firstOrFail()->id,
            'activo' => true,
        ];
    }
}
