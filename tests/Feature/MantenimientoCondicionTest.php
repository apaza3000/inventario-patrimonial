<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Bien;
use App\Models\CondicionBien;
use App\Models\EstadoBien;
use App\Models\Mantenimiento;
use App\Models\Rol;
use App\Models\TipoMantenimiento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class MantenimientoCondicionTest extends TestCase
{
    use DatabaseTransactions;

    private function bien(): Bien
    {
        return Bien::create([
            'descripcion' => 'Bien temporal para condición de mantenimiento',
            'estado_id' => EstadoBien::firstOrFail()->id,
            'condicion_id' => CondicionBien::orderBy('id')->firstOrFail()->id,
            'ambiente_id' => Ambiente::firstOrFail()->id,
            'activo' => false,
        ])->fresh();
    }

    private function payload(Bien $bien): array
    {
        return [
            'bien_id' => $bien->id,
            'tipo_mantenimiento_id' => TipoMantenimiento::firstOrFail()->id,
            'fecha_mantenimiento' => '2026-10-02',
            'descripcion' => 'Registro temporal de mantenimiento',
            'tecnico_id' => null,
        ];
    }

    private function authenticate(string $rol = 'superadmin', bool $activo = true): void
    {
        $usuario = new Usuario(['activo' => $activo, 'password_hash' => 'test-only-session-hash']);
        $usuario->setRelation('rol', new Rol(['nombre' => $rol]));
        $this->actingAs($usuario, 'web')->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_registration_without_condition_preserves_the_good(): void
    {
        $bien = $this->bien();
        $original = $bien->getAttributes();
        $this->authenticate();

        $this->postJson('/api/mantenimientos', $this->payload($bien))
            ->assertCreated()
            ->assertJsonPath('data.bien.condicion.id', $bien->condicion_id);

        $this->assertSame($original, $bien->fresh()->getAttributes());
    }

    /** @dataProvider fullRoles */
    public function test_registration_changes_only_condition_and_returns_it(string $rol): void
    {
        $bien = $this->bien();
        $original = $bien->getAttributes();
        $condicion = CondicionBien::where('id', '!=', $bien->condicion_id)->firstOrFail();
        $this->authenticate($rol);

        $created = $this->postJson('/api/mantenimientos', [
            ...$this->payload($bien), 'condicion_id' => $condicion->id,
        ])->assertCreated()->assertJsonPath('data.bien.condicion.id', $condicion->id);

        $this->assertSame([...$original, 'condicion_id' => $condicion->id], $bien->fresh()->getAttributes());
        $id = $created->json('data.id');
        $this->getJson("/api/mantenimientos/$id")->assertOk()
            ->assertJsonPath('data.bien.condicion.id', $condicion->id);
        $this->getJson('/api/mantenimientos')->assertOk()
            ->assertJsonPath('data.0.bien.condicion.id', $condicion->id);
        $this->deleteJson("/api/mantenimientos/$id")->assertOk();
        $this->assertSame($condicion->id, $bien->fresh()->condicion_id);
    }

    public static function fullRoles(): array
    {
        return [['superadmin'], ['administrador'], ['director']];
    }

    /** @dataProvider invalidConditions */
    public function test_invalid_condition_creates_nothing_and_preserves_the_good(mixed $condicion): void
    {
        $bien = $this->bien();
        $original = $bien->getAttributes();
        $this->authenticate();

        $this->postJson('/api/mantenimientos', [
            ...$this->payload($bien), 'condicion_id' => $condicion,
        ])->assertUnprocessable()->assertJsonValidationErrors('condicion_id');

        $this->assertDatabaseMissing('mantenimientos', ['bien_id' => $bien->id]);
        $this->assertSame($original, $bien->fresh()->getAttributes());
    }

    public static function invalidConditions(): array
    {
        return [[2147483647], [null]];
    }

    public function test_failure_after_both_writes_rolls_back_the_entire_operation(): void
    {
        $bien = $this->bien();
        $original = $bien->getAttributes();
        $condicion = CondicionBien::where('id', '!=', $bien->condicion_id)->firstOrFail();
        $this->authenticate();
        $this->withoutExceptionHandling();
        $dispatcher = Bien::getEventDispatcher();
        Bien::setEventDispatcher(clone $dispatcher);

        try {
            Bien::updated(function (Bien $updated) use ($bien): void {
                if ($updated->id === $bien->id) {
                    $this->assertDatabaseHas('mantenimientos', ['bien_id' => $bien->id]);
                    throw new RuntimeException('Fallo simulado después de actualizar la condición');
                }
            });

            try {
                $this->postJson('/api/mantenimientos', [
                    ...$this->payload($bien), 'condicion_id' => $condicion->id,
                ]);
                $this->fail('Se esperaba la excepción simulada.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Fallo simulado después de actualizar la condición', $exception->getMessage());
            }
        } finally {
            Bien::setEventDispatcher($dispatcher);
        }

        $this->assertDatabaseMissing('mantenimientos', ['bien_id' => $bien->id]);
        $this->assertSame($original, $bien->fresh()->getAttributes());
    }

    /** @dataProvider forbiddenEditConditions */
    public function test_edit_rejects_condition_even_when_null(string $method, mixed $condicion): void
    {
        $bien = $this->bien();
        $mantenimiento = Mantenimiento::create($this->payload($bien));
        $this->authenticate();

        $this->json($method, "/api/mantenimientos/{$mantenimiento->id}", [
            'condicion_id' => $condicion, 'descripcion' => 'Cambio bloqueado',
        ])->assertUnprocessable()->assertJsonValidationErrors('condicion_id');

        $this->assertSame($bien->condicion_id, $bien->fresh()->condicion_id);
        $this->assertSame($mantenimiento->descripcion, $mantenimiento->fresh()->descripcion);
    }

    public static function forbiddenEditConditions(): array
    {
        return [['PUT', 1], ['PATCH', 1], ['PUT', null], ['PATCH', null]];
    }

    /** @dataProvider deniedRoles */
    public function test_users_without_write_permission_cannot_change_condition(string $rol, bool $activo): void
    {
        $bien = $this->bien();
        $this->authenticate($rol, $activo);

        $this->postJson('/api/mantenimientos', [
            ...$this->payload($bien), 'condicion_id' => $bien->condicion_id,
        ])->assertForbidden();
        $this->assertDatabaseMissing('mantenimientos', ['bien_id' => $bien->id]);
    }

    public static function deniedRoles(): array
    {
        return [['coordinador', true], ['asistente', true], ['superadmin', false]];
    }

    public function test_guest_cannot_register_maintenance(): void
    {
        $this->postJson('/api/mantenimientos', [])->assertUnauthorized();
    }

    /** @dataProvider technicianRoles */
    public function test_technician_can_have_any_role_including_an_inactive_account(string $rol, bool $activo): void
    {
        $bien = $this->bien();
        $tecnico = Usuario::create([
            'nombres' => 'Técnico temporal', 'apellidos' => 'Prueba',
            'correo' => uniqid('mantenimiento-condicion-', true).'@example.test',
            'password_hash' => 'test-only-session-hash',
            'rol_id' => Rol::where('nombre', $rol)->firstOrFail()->id,
            'especialidad_id' => $rol === 'coordinador' ? 1 : null,
            'activo' => $activo,
        ]);
        $this->authenticate();

        $this->postJson('/api/mantenimientos', [
            ...$this->payload($bien), 'tecnico_id' => $tecnico->id,
        ])->assertCreated()->assertJsonPath('data.tecnico.id', $tecnico->id);
    }

    public static function technicianRoles(): array
    {
        return [['superadmin', true], ['administrador', true], ['director', true],
            ['coordinador', true], ['asistente', true], ['asistente', false]];
    }
}
