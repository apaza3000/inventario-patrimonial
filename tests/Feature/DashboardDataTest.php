<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Bien;
use App\Models\CondicionBien;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\Mantenimiento;
use App\Models\Movimiento;
use App\Models\Rol;
use App\Models\Sede;
use App\Models\TipoEquipo;
use App\Models\TipoMantenimiento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_summary_counts_real_conditions_and_maintenance_records_for_full_access_roles(): void
    {
        $total = Bien::count();
        $operativos = Bien::whereHas('condicion', fn ($query) => $query->where('nombre', 'OPERATIVO'))->count();
        $ausentes = Bien::whereHas('condicion', fn ($query) => $query->where('nombre', 'AUSENTE'))->count();
        $mantenimientos = Mantenimiento::count();

        foreach (['OPERATIVO', 'AUSENTE', 'BUENO'] as $nombre) {
            $condicion = CondicionBien::firstOrCreate(['nombre' => $nombre]);
            $this->bien(null, $condicion->id);
        }
        $bien = $this->bien();
        $this->mantenimiento($bien);
        $this->mantenimiento($bien);

        foreach (['superadmin', 'administrador', 'director'] as $rol) {
            $response = $this->actingAs($this->usuario($rol), 'web')->get('/home');
            $response->assertOk()->assertViewHas('resumen', [
                'total' => $total + 4,
                'operativos' => $operativos + 1,
                'ausentes' => $ausentes + 1,
                'mantenimientos' => $mantenimientos + 2,
            ])->assertSee('Ausentes')->assertSee('Registros de mantenimiento')
                ->assertSee('Registrar nuevo bien')->assertSee('Registrar movimiento')->assertSee('Toma de inventario')
                ->assertDontSee('Nueva incidencia')->assertDontSee('Faltantes');
        }
    }

    public function test_distribution_shows_top_five_and_unassigned_goods_only_when_they_rank(): void
    {
        $sinAmbiente = Bien::whereNull('ambiente_id')->count();
        $maximo = (int) (Bien::selectRaw('COUNT(*) as cantidad')
            ->groupBy('ambiente_id')->orderByDesc('cantidad')->first()?->cantidad ?? 0);
        $ambientes = [];
        for ($indice = 1; $indice <= 6; $indice++) {
            $ambiente = $this->ambiente();
            $ambientes[] = $ambiente;
            DB::table('bienes')->insert(array_fill(0, $maximo + $indice, [
                'descripcion' => 'Bien temporal para probar la distribución',
                'ambiente_id' => $ambiente->id,
                'activo' => true,
            ]));
        }
        $vacio = $this->ambiente();

        $response = $this->actingAs($this->usuario('superadmin'), 'web')->get('/home')->assertOk();
        $distribucion = $response->viewData('distribucion');
        $this->assertSame(array_map(fn ($indice) => $ambientes[$indice]->id, [5, 4, 3, 2, 1]), $distribucion->pluck('id')->all());
        $this->assertSame([$maximo + 6, $maximo + 5, $maximo + 4, $maximo + 3, $maximo + 2], $distribucion->pluck('total')->all());
        $this->assertNull($distribucion->firstWhere('id', $vacio->id));
        $response->assertViewHas('hayMasAmbientes', true)
            ->assertSee('Ver todos los ambientes')->assertSee('href="'.route('inventario.bienes').'"', false)
            ->assertDontSee('Sin ambiente asignado')->assertDontSee($ambientes[0]->nombre);

        DB::table('bienes')->insert(array_fill(0, $maximo + 8 - $sinAmbiente, [
            'descripcion' => 'Bien temporal sin ambiente',
            'ambiente_id' => null,
            'activo' => true,
        ]));
        $response = $this->get('/home')->assertOk();
        $distribucion = $response->viewData('distribucion');
        $this->assertSame([null, $ambientes[5]->id, $ambientes[4]->id, $ambientes[3]->id, $ambientes[2]->id], $distribucion->pluck('id')->all());
        $sinAsignar = $distribucion->first(fn ($fila) => $fila['id'] === null);
        $this->assertSame('Sin ambiente asignado', $sinAsignar['nombre']);
        $this->assertSame($maximo + 8, $sinAsignar['total']);
        $response->assertSee('Sin ambiente asignado')->assertDontSee($ambientes[1]->nombre);
    }

    public function test_recent_movements_are_limited_and_sorted_by_date_then_id(): void
    {
        $usuario = $this->usuario('superadmin');
        $bien = $this->bien();
        $destino = $this->ambiente();
        $creados = [];
        foreach ([6, 1, 6, 2, 4, 3] as $indice => $dia) {
            $creados[] = Movimiento::create([
                'bien_id' => $bien->id,
                'ambiente_origen_id' => null,
                'ambiente_destino_id' => $destino->id,
                'ordenado_por' => $usuario->id,
                'ejecutado_por' => $usuario->id,
                'fecha_movimiento' => sprintf('9999-01-%02d 10:00:00', $dia),
                'motivo' => 'Movimiento temporal del dashboard',
                'estado' => $indice === 0 ? Movimiento::ESTADO_FINALIZADO : Movimiento::ESTADO_PENDIENTE_FIRMA,
            ]);
        }

        $response = $this->actingAs($usuario, 'web')->get('/home')->assertOk();
        $esperados = array_map(fn ($indice) => $creados[$indice]->id, [2, 0, 4, 5, 3]);
        $this->assertSame($esperados, $response->viewData('movimientos')->pluck('id')->all());
        $response->assertSee($bien->descripcion)->assertSee($destino->nombre)
            ->assertSee('Pendiente de firma')->assertSee('Finalizado')
            ->assertDontSee('No hay movimientos registrados.');
    }

    public function test_assistant_only_has_permitted_shortcuts_without_global_data(): void
    {
        $response = $this->actingAs($this->usuario('asistente'), 'web')->get('/home')->assertOk();
        $response->assertViewHas('resumen', [])->assertViewHas('distribucion', fn ($items) => $items->isEmpty())
            ->assertViewHas('movimientos', fn ($items) => $items->isEmpty())
            ->assertDontSee('Total de bienes')->assertDontSee('Operativos')->assertDontSee('Ausentes')
            ->assertDontSee('Registros de mantenimiento')->assertDontSee('Distribución por ambiente')
            ->assertDontSee('Últimos movimientos')->assertDontSee('Registrar nuevo bien')
            ->assertSee('Registrar movimiento')->assertSee('Toma de inventario');
        foreach (['movimientos.index', 'inventario.toma-inventario'] as $ruta) {
            $this->get(route($ruta))->assertOk();
        }
    }

    public function test_coordinator_maintenance_count_respects_specialty_and_equipment_scope(): void
    {
        $propia = Especialidad::create(['nombre' => uniqid('Especialidad dashboard ')]);
        $ajena = Especialidad::create(['nombre' => uniqid('Otra especialidad dashboard ')]);
        $ambientePropio = $this->ambiente($propia->id);
        $ambienteAjeno = $this->ambiente($ajena->id);
        $equipoPropio = $this->bien($ambientePropio->id);
        $equipoAjeno = $this->bien($ambienteAjeno->id);
        $sinEquipo = $this->bien($ambientePropio->id);
        $tipo = TipoEquipo::firstOrCreate(['nombre' => 'Equipo prueba dashboard']);
        foreach ([$equipoPropio, $equipoAjeno] as $bien) {
            Equipo::create(['bien_id' => $bien->id, 'tipo_equipo_id' => $tipo->id]);
        }
        $this->mantenimiento($equipoPropio);
        $this->mantenimiento($equipoPropio);
        $this->mantenimiento($equipoAjeno);
        $this->mantenimiento($sinEquipo);

        $this->actingAs($this->usuario('coordinador', $propia->id), 'web')->get('/home')
            ->assertOk()->assertViewHas('resumen', ['mantenimientos' => 2])
            ->assertViewHas('movimientos', fn ($items) => $items->isEmpty())
            ->assertViewHas('distribucion', fn ($items) => $items->isEmpty())
            ->assertSee('Registros de mantenimiento de tu especialidad')
            ->assertDontSee('Total de bienes')->assertDontSee('Distribución por ambiente')
            ->assertDontSee('Últimos movimientos')->assertDontSee('Registrar nuevo bien')
            ->assertDontSee('Registrar movimiento')->assertDontSee('Toma de inventario')
            ->assertDontSee('href="'.route('inventario.bienes').'"', false);

        $this->actingAs($this->usuario('coordinador'), 'web')->get('/home')
            ->assertOk()->assertViewHas('resumen', ['mantenimientos' => 0]);
    }

    public function test_inactive_user_cannot_access_dashboard_and_session_is_closed(): void
    {
        $usuario = $this->usuario('superadmin');
        $usuario->update(['activo' => false]);
        $this->actingAs($usuario, 'web')->get('/home')->assertForbidden()
            ->assertJsonPath('message', 'El usuario está inactivo.');
        $this->assertGuest('web');
        $this->get('/home')->assertRedirect('/login');
    }

    private function usuario(string $rol, ?int $especialidad = null): Usuario
    {
        return Usuario::create([
            'nombres' => 'Prueba Dashboard',
            'apellidos' => 'Temporal',
            'correo' => uniqid('dashboard-', true).'@example.test',
            'password_hash' => 'no-se-utiliza-en-acting-as',
            'rol_id' => Rol::where('nombre', $rol)->firstOrFail()->id,
            'especialidad_id' => $especialidad,
            'activo' => true,
        ]);
    }

    private function ambiente(?int $especialidad = null): Ambiente
    {
        return Ambiente::create([
            'nombre' => uniqid('Ambiente dashboard '),
            'sede_id' => Sede::firstOrFail()->id,
            'especialidad_id' => $especialidad,
            'activo' => true,
        ]);
    }

    private function bien(?int $ambiente = null, ?int $condicion = null): Bien
    {
        return Bien::create([
            'descripcion' => uniqid('Bien dashboard '),
            'ambiente_id' => $ambiente,
            'condicion_id' => $condicion,
            'activo' => true,
        ]);
    }

    private function mantenimiento(Bien $bien): Mantenimiento
    {
        $tipo = TipoMantenimiento::firstOrCreate(['nombre' => 'Mantenimiento prueba dashboard']);

        return Mantenimiento::create([
            'bien_id' => $bien->id,
            'tipo_mantenimiento_id' => $tipo->id,
            'fecha_mantenimiento' => '2026-10-01',
            'descripcion' => 'Registro temporal para probar el dashboard',
        ]);
    }
}
