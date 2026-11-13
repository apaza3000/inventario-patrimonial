<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioEspecialidadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->asRole('superadmin');
    }

    private function asRole(string $role): void
    {
        $actor = Usuario::findOrFail(1);
        $actor->setRelation('rol', Rol::where('nombre', $role)->firstOrFail());
        $this->actingAs($actor, 'web')->withHeader('Origin', 'http://localhost:3000');
    }

    private function payload(string $role): array
    {
        return ['nombres' => 'Prueba', 'apellidos' => 'Especialidad', 'correo' => uniqid('specialty-', true).'@example.test',
            'password' => 'Only-test-password-123!', 'rol_id' => Rol::where('nombre', $role)->firstOrFail()->id, 'activo' => true];
    }

    public function test_both_roles_require_an_existing_academic_specialty_on_registration(): void
    {
        $original = Usuario::count();
        foreach (['asistente', 'coordinador'] as $role) {
            $this->postJson('/api/usuarios', $this->payload($role))->assertUnprocessable()->assertJsonValidationErrors('especialidad_id');
            foreach ([null, '', 99999, 4, 5, [1], '1 OR 1=1', 1.5] as $invalid) {
                $this->postJson('/api/usuarios', [...$this->payload($role), 'especialidad_id' => $invalid])
                    ->assertUnprocessable()->assertJsonValidationErrors('especialidad_id');
            }
        }
        $this->assertSame($original, Usuario::count());
    }

    public function test_full_access_roles_can_assign_all_existing_academic_specialties_to_both_roles(): void
    {
        foreach (['superadmin', 'administrador', 'director'] as $actor) {
            $this->asRole($actor);
            foreach (['asistente', 'coordinador'] as $role) foreach ([1, 2, 3] as $specialty) {
                // Numeric strings from ordinary forms must also pass integer validation.
                $response = $this->postJson('/api/usuarios', [...$this->payload($role), 'especialidad_id' => (string) $specialty])
                    ->assertCreated()->assertJsonPath('data.especialidad_id', $specialty)->assertJsonPath('data.especialidad.id', $specialty)
                    ->assertJsonMissingPath('data.password_hash')->assertJsonMissingPath('data.password');
                $user = Usuario::findOrFail($response->json('data.id'));
                $this->assertTrue(Hash::check('Only-test-password-123!', $user->getAuthPassword()));
                $this->getJson('/api/usuarios/'.$user->id)->assertOk()->assertJsonPath('data.especialidad.id', $specialty);
            }
        }
    }

    public function test_edits_keep_the_saved_specialty_and_password_when_omitted_and_allow_changing_only_specialty(): void
    {
        foreach (['asistente', 'coordinador'] as $role) {
            $response = $this->postJson('/api/usuarios', [...$this->payload($role), 'especialidad_id' => 1])->assertCreated();
            $id = $response->json('data.id'); $hash = Usuario::findOrFail($id)->getAuthPassword();
            foreach (['PUT', 'PATCH'] as $method) {
                $this->json($method, "/api/usuarios/$id", ['nombres' => 'Actualizado'])
                    ->assertOk()->assertJsonPath('data.especialidad_id', 1)->assertJsonPath('data.especialidad.id', 1);
            }
            $this->patchJson("/api/usuarios/$id", ['especialidad_id' => '2'])->assertOk()->assertJsonPath('data.especialidad.id', 2);
            foreach ([null, 4, 99999] as $invalid) {
                $this->patchJson("/api/usuarios/$id", ['especialidad_id' => $invalid, 'nombres' => 'No debe guardarse'])
                    ->assertUnprocessable()->assertJsonValidationErrors('especialidad_id');
            }
            $this->assertDatabaseHas('usuarios', ['id' => $id, 'especialidad_id' => 2, 'nombres' => 'Actualizado']);
            $this->assertSame($hash, Usuario::findOrFail($id)->getAuthPassword());
        }
    }

    public function test_switching_between_academic_roles_preserves_specialty_but_other_roles_keep_their_previous_behavior(): void
    {
        $response = $this->postJson('/api/usuarios', [...$this->payload('asistente'), 'especialidad_id' => 1])->assertCreated();
        $id = $response->json('data.id');
        foreach (['coordinador', 'asistente'] as $role) {
            $this->patchJson("/api/usuarios/$id", ['rol_id' => Rol::where('nombre', $role)->firstOrFail()->id])
                ->assertOk()->assertJsonPath('data.especialidad.id', 1);
        }
        $director = Rol::where('nombre', 'director')->firstOrFail()->id;
        $assistant = Rol::where('nombre', 'asistente')->firstOrFail()->id;
        $this->patchJson("/api/usuarios/$id", ['rol_id' => $director])->assertOk()->assertJsonPath('data.especialidad_id', null);
        $this->patchJson("/api/usuarios/$id", ['rol_id' => $assistant])->assertUnprocessable()->assertJsonValidationErrors('especialidad_id');
        $this->assertDatabaseHas('usuarios', ['id' => $id, 'rol_id' => $director, 'especialidad_id' => null]);
        $this->patchJson("/api/usuarios/$id", ['rol_id' => $assistant, 'especialidad_id' => 3])->assertOk()->assertJsonPath('data.especialidad.id', 3);
        foreach (['superadmin', 'administrador', 'director'] as $role) {
            $this->postJson('/api/usuarios', [...$this->payload($role), 'especialidad_id' => 1])->assertCreated()->assertJsonPath('data.especialidad_id', null);
            $this->postJson('/api/usuarios', $this->payload($role))->assertCreated()->assertJsonPath('data.especialidad_id', null);
        }
    }

    public function test_legacy_unassigned_assistant_is_not_changed_automatically_and_can_be_assigned_without_resetting_password(): void
    {
        $data = $this->payload('asistente'); $password = $data['password']; unset($data['password']);
        $user = Usuario::create([...$data, 'password_hash' => Hash::make($password)]);
        $hash = $user->getAuthPassword();
        $this->getJson('/api/usuarios/'.$user->id)->assertOk()->assertJsonPath('data.especialidad_id', null);
        $this->patchJson('/api/usuarios/'.$user->id, ['nombres' => 'No debe cambiar'])
            ->assertUnprocessable()->assertJsonValidationErrors('especialidad_id');
        $this->assertDatabaseHas('usuarios', ['id' => $user->id, 'nombres' => 'Prueba', 'especialidad_id' => null]);
        $this->patchJson('/api/usuarios/'.$user->id, ['especialidad_id' => 1])->assertOk()->assertJsonPath('data.especialidad.id', 1);
        $this->assertSame($hash, $user->fresh()->getAuthPassword());
    }

    public function test_assigned_specialty_cannot_be_deleted_and_configuration_permissions_are_unchanged(): void
    {
        $created = $this->postJson('/api/usuarios', [...$this->payload('asistente'), 'especialidad_id' => 3])->assertCreated();
        $this->deleteJson('/api/especialidades/3')->assertConflict();
        foreach (['asistente', 'coordinador'] as $role) {
            $this->asRole($role);
            $this->get('/configuracion')->assertForbidden();
            $this->getJson('/api/usuarios')->assertForbidden();
            $this->postJson('/api/usuarios', [...$this->payload('asistente'), 'especialidad_id' => 1])->assertForbidden();
            $this->patchJson('/api/usuarios/'.$created->json('data.id'), ['especialidad_id' => 2])->assertForbidden();
        }
    }

    public function test_assigning_specialty_does_not_widen_the_existing_data_scopes(): void
    {
        foreach ([1, 2] as $id) DB::table('equipos')->insert(['bien_id' => $id, 'tipo_equipo_id' => 1]);
        DB::table('ambientes')->insert(['id' => 3, 'nombre' => 'Laboratorio de otra carrera', 'sede_id' => 1, 'especialidad_id' => 2, 'tipo_ambiente_id' => 1]);
        DB::table('bienes')->insert(['id' => 3, 'cbi' => '0000003', 'descripcion' => 'Bien de otro laboratorio', 'ambiente_id' => 3]);
        DB::table('inventarios')->insert(['bien_id' => 3, 'anio' => 2026, 'inventario' => 'INV-3']);
        $coordinator = $this->postJson('/api/usuarios', [...$this->payload('coordinador'), 'especialidad_id' => 1])->assertCreated()->json('data.id');
        $assistant = $this->postJson('/api/usuarios', [...$this->payload('asistente'), 'especialidad_id' => 1])->assertCreated()->json('data.id');
        $this->flushSession();
        $this->actingAs(Usuario::findOrFail($coordinator), 'web');
        $this->getJson('/api/equipos')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.bien_id', 1);
        $this->getJson('/api/equipos/2')->assertNotFound();
        $this->getJson('/api/reportes/inventario/resumen?especialidad_id=2')->assertOk()->assertJsonPath('data.total', 0);
        $this->flushSession();
        $this->actingAs(Usuario::findOrFail($assistant), 'web');
        // Assistant access remains laboratory-based; this stage does not add a new specialty restriction.
        $this->getJson('/api/inventarios')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/inventarios/2/2026')->assertNotFound();
        $this->getJson('/api/inventarios/3/2026')->assertOk();
        $this->getJson('/api/reportes/equipos/pdf')->assertForbidden();
        $this->getJson('/api/mantenimientos')->assertForbidden();
    }
}
