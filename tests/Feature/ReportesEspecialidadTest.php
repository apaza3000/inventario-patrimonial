<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Bien;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\Mantenimiento;
use App\Models\Rol;
use App\Models\TipoEquipo;
use App\Models\TipoMantenimiento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use ZipArchive;

class ReportesEspecialidadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_coordinador_only_reads_equipment_and_maintenance_from_own_especialidad(): void
    {
        [$propio, $ajeno] = $this->crearEquiposEnEspecialidadesDistintas();
        [$mantenimientoPropio, $mantenimientoAjeno] = $this->crearMantenimientos($propio, $ajeno);
        $coordinador = $this->crearUsuario('coordinador', $propio->bien->ambiente->especialidad_id);

        $listado = $this->como($coordinador)->getJson('/api/equipos')->assertOk();
        foreach ($listado->json('data') as $equipo) {
            $this->assertSame($coordinador->especialidad_id, $equipo['bien']['ambiente']['especialidad_id']);
        }

        $this->getJson("/api/equipos/{$propio->bien_id}")->assertOk();
        $this->getJson("/api/equipos/{$ajeno->bien_id}")->assertNotFound();
        $this->getJson("/api/mantenimientos/{$mantenimientoPropio->id}")->assertOk();
        $this->getJson("/api/mantenimientos/{$mantenimientoAjeno->id}")->assertNotFound();
        $this->postJson('/api/equipos', [])->assertForbidden();
        $this->patchJson("/api/mantenimientos/{$mantenimientoPropio->id}", [])->assertForbidden();
        $this->deleteJson("/api/equipos/{$propio->bien_id}")->assertForbidden();
    }

    public function test_coordinator_reports_use_the_same_especialidad_scope(): void
    {
        [$propio, $ajeno] = $this->crearEquiposEnEspecialidadesDistintas();
        $this->crearMantenimientos($propio, $ajeno);
        $coordinador = $this->crearUsuario('coordinador', $propio->bien->ambiente->especialidad_id);
        $this->como($coordinador);

        $equiposExcel = $this->get('/api/reportes/equipos/excel')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $xmlEquipos = $this->hojaXlsx($equiposExcel->getContent());
        $this->assertStringContainsString($propio->bien->cbi, $xmlEquipos);
        $this->assertStringNotContainsString($ajeno->bien->cbi, $xmlEquipos);

        $mantenimientosExcel = $this->get('/api/reportes/mantenimientos/excel')->assertOk();
        $xmlMantenimientos = $this->hojaXlsx($mantenimientosExcel->getContent());
        $this->assertStringContainsString($propio->bien->cbi, $xmlMantenimientos);
        $this->assertStringNotContainsString($ajeno->bien->cbi, $xmlMantenimientos);

        $this->get('/api/reportes/equipos/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->get('/api/reportes/mantenimientos/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_asistente_only_exports_laboratory_inventory(): void
    {
        $laboratorio = Ambiente::query()->whereHas('tipoAmbiente', fn ($query) => $query->where('nombre', 'LABORATORIO'))->firstOrFail();
        $otro = Ambiente::query()->whereHas('tipoAmbiente', fn ($query) => $query->where('nombre', '!=', 'LABORATORIO'))->firstOrFail();
        $bienLaboratorio = $this->crearBien($laboratorio, 'LAB');
        $bienFuera = $this->crearBien($otro, 'EXT');
        $anio = 2099;

        DB::table('inventarios')->insert([
            ['bien_id' => $bienLaboratorio->id, 'anio' => $anio, 'inventario' => 'INV-LAB-TEST'],
            ['bien_id' => $bienFuera->id, 'anio' => $anio, 'inventario' => 'INV-EXT-TEST'],
        ]);

        $this->como($this->crearUsuario('asistente'));

        $excel = $this->get('/api/reportes/inventario/excel')->assertOk();
        $xml = $this->hojaXlsx($excel->getContent());
        $this->assertStringContainsString($bienLaboratorio->cbi, $xml);
        $this->assertStringNotContainsString($bienFuera->cbi, $xml);

        $this->get('/api/reportes/inventario/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->get('/api/reportes/equipos/pdf')->assertForbidden();
        $this->get('/api/reportes/mantenimientos/excel')->assertForbidden();
    }

    public function test_administrador_can_download_complete_reports(): void
    {
        $this->assertFullReportsFor('administrador');
    }

    public function test_director_can_download_complete_reports(): void
    {
        $this->assertFullReportsFor('director');
    }

    private function crearEquiposEnEspecialidadesDistintas(): array
    {
        $ambientes = Ambiente::query()
            ->whereNotNull('especialidad_id')
            ->orderBy('especialidad_id')
            ->get()
            ->unique('especialidad_id')
            ->take(2)
            ->values();
        $this->assertCount(2, $ambientes);
        $tipo = TipoEquipo::query()->firstOrFail();

        return $ambientes->map(function (Ambiente $ambiente, int $indice) use ($tipo) {
            $bien = $this->crearBien($ambiente, 'EQ'.$indice);

            return Equipo::create([
                'bien_id' => $bien->id,
                'tipo_equipo_id' => $tipo->id,
                'marca_id' => null,
                'modelo' => 'Modelo de prueba',
                'serie' => uniqid('SERIE-', true),
            ])->load('bien.ambiente');
        })->all();
    }

    private function crearMantenimientos(Equipo $propio, Equipo $ajeno): array
    {
        $tipo = TipoMantenimiento::query()->first()
            ?? TipoMantenimiento::create(['nombre' => 'Mantenimiento de prueba']);

        return collect([$propio, $ajeno])->map(fn (Equipo $equipo) => Mantenimiento::create([
            'bien_id' => $equipo->bien_id,
            'tipo_mantenimiento_id' => $tipo->id,
            'tecnico_id' => null,
            'fecha_mantenimiento' => '2026-09-22',
            'descripcion' => 'Mantenimiento temporal para prueba',
            'diagnostico' => null,
            'trabajo_realizado' => null,
            'observaciones' => null,
        ]))->all();
    }

    private function crearBien(Ambiente $ambiente, string $prefijo): Bien
    {
        return Bien::create([
            'cbi' => $prefijo.'-'.strtoupper(substr(uniqid(), -10)),
            'descripcion' => "Bien temporal $prefijo",
            'estado_id' => null,
            'condicion_id' => null,
            'ambiente_id' => $ambiente->id,
            'observaciones' => null,
            'activo' => true,
            'fecha_registro' => now(),
        ]);
    }

    private function crearUsuario(string $rol, ?int $especialidadId = null): Usuario
    {
        return Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => ucfirst($rol),
            'correo' => uniqid("reporte-$rol-", true).'@example.test',
            'password_hash' => Hash::make('ClaveSegura123!'),
            'rol_id' => Rol::query()->where('nombre', $rol)->firstOrFail()->id,
            'especialidad_id' => $especialidadId,
            'activo' => true,
            'fecha_registro' => now(),
        ]);
    }

    private function como(Usuario $usuario): static
    {
        Auth::forgetGuards();

        return $this->actingAs($usuario, 'web')->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ]);
    }

    private function hojaXlsx(string $contenido): string
    {
        $ruta = tempnam(storage_path('framework/cache'), 'prueba_xlsx_');
        $this->assertNotFalse($ruta);
        file_put_contents($ruta, $contenido);

        try {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($ruta) === true);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertNotFalse($xml);

            return $xml;
        } finally {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
    }

    private function assertFullReportsFor(string $rol): void
    {
        $this->como($this->crearUsuario($rol));
        $this->get('/api/reportes/inventario/excel')->assertOk();
        $this->get('/api/reportes/equipos/pdf')->assertOk();
        $this->get('/api/reportes/mantenimientos/excel')->assertOk();
    }
}
