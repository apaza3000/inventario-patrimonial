<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\TipoMantenimiento;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TiposMantenimientoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TipoMantenimientoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seeder_is_registered_idempotent_and_preserves_existing_values(): void
    {
        $this->seed(DatabaseSeeder::class);
        $preventivo = TipoMantenimiento::where('nombre', 'Preventivo')->firstOrFail();
        $preventivo->update(['descripcion' => 'Descripción existente']);
        $original = TipoMantenimiento::orderBy('id')->get()->toArray();

        $this->seed(TiposMantenimientoSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($original, TipoMantenimiento::orderBy('id')->get()->toArray());
        foreach (['Preventivo', 'Correctivo'] as $nombre) {
            $this->assertSame(1, TipoMantenimiento::where('nombre', $nombre)->count());
        }
    }

    /** @dataProvider maintenanceRoles */
    public function test_roles_with_maintenance_access_can_read_the_catalog(string $rol): void
    {
        $this->seed(TiposMantenimientoSeeder::class);
        $expected = TipoMantenimiento::orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion'])->toArray();

        $this->asRole($rol)->getJson('/api/tipos-mantenimiento')
            ->assertOk()
            ->assertExactJson(['data' => $expected]);
    }

    public static function maintenanceRoles(): array
    {
        return array_map(fn ($rol) => [$rol], ['superadmin', 'administrador', 'director', 'coordinador']);
    }

    public function test_guest_and_unauthorized_users_cannot_read_the_catalog(): void
    {
        $this->getJson('/api/tipos-mantenimiento')->assertUnauthorized();
        $this->asRole('asistente')->getJson('/api/tipos-mantenimiento')->assertForbidden();
    }

    public function test_inactive_user_cannot_read_the_catalog(): void
    {
        $this->asRole('superadmin', false)->getJson('/api/tipos-mantenimiento')->assertForbidden();
    }

    public function test_catalog_has_no_write_or_detail_routes(): void
    {
        $this->asRole('superadmin');
        $this->postJson('/api/tipos-mantenimiento')->assertStatus(405);
        $this->putJson('/api/tipos-mantenimiento/1')->assertNotFound();
        $this->patchJson('/api/tipos-mantenimiento/1')->assertNotFound();
        $this->deleteJson('/api/tipos-mantenimiento/1')->assertNotFound();
        $this->getJson('/api/tipos-mantenimiento/1')->assertNotFound();
    }

    private function asRole(string $rol, bool $activo = true): static
    {
        $usuario = Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => 'Catálogo',
            'correo' => uniqid('tipo-mantenimiento-', true).'@example.test',
            'password_hash' => Hash::make('ClaveSegura123!'),
            'rol_id' => Rol::where('nombre', $rol)->firstOrFail()->id,
            'especialidad_id' => $rol === 'coordinador' ? 1 : null,
            'activo' => $activo,
            'fecha_registro' => now(),
        ]);

        Auth::forgetGuards();

        return $this->actingAs($usuario, 'web')->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ]);
    }
}
