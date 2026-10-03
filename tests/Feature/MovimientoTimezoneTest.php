<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Bien;
use App\Models\Movimiento;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MovimientoTimezoneTest extends TestCase
{
    public function test_local_movement_and_signature_times_serialize_as_the_correct_instants(): void
    {
        $this->assertSame('America/Lima', config('app.timezone'));
        $movimiento = new Movimiento([
            'fecha_movimiento' => '2026-10-02 10:00:00',
            'fecha_firma' => '2026-10-02 11:30:00',
        ]);

        $this->assertSame('2026-10-02 10:00:00', $movimiento->getAttributes()['fecha_movimiento']);
        $this->assertSame('2026-10-02T15:00:00.000000Z', $movimiento->toArray()['fecha_movimiento']);
        $this->assertSame('2026-10-02T16:30:00.000000Z', $movimiento->toArray()['fecha_firma']);
        $this->assertSame('America/Lima', $movimiento->fecha_movimiento->timezoneName);
    }

    public function test_signature_now_uses_lima_and_null_signature_remains_null(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-02T15:00:00Z'));
        try {
            $movimiento = new Movimiento(['fecha_firma' => now()]);
            $this->assertSame('2026-10-02 10:00:00', $movimiento->getAttributes()['fecha_firma']);
            $this->assertSame('2026-10-02T15:00:00.000000Z', $movimiento->toArray()['fecha_firma']);
            $this->assertNull((new Movimiento(['fecha_firma' => null]))->toArray()['fecha_firma']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_and_pdf_template_show_the_same_local_movement_time(): void
    {
        // Unsaved models: this test neither queries nor changes real records.
        $usuario = new Usuario(['nombres' => 'Prueba', 'apellidos' => 'Local', 'activo' => true]);
        $usuario->setRelation('rol', new Rol(['nombre' => 'superadmin']));
        $this->actingAs($usuario, 'web');
        $movimiento = new Movimiento([
            'fecha_movimiento' => '2026-10-02 10:00:00',
            'estado' => Movimiento::ESTADO_PENDIENTE_FIRMA, 'motivo' => 'Prueba en memoria',
        ]);
        $movimiento->id = 1;
        $movimiento->setRelation('bien', new Bien(['cbi' => 'PRUEBA', 'descripcion' => 'Bien en memoria']));
        $movimiento->setRelation('ambienteOrigen', null);
        $movimiento->setRelation('ambienteDestino', new Ambiente(['nombre' => 'Destino en memoria']));
        $movimiento->setRelation('ordenadoPor', $usuario);
        $movimiento->setRelation('ejecutadoPor', $usuario);

        $dashboard = view('dashboard.index', [
            'resumen' => [], 'distribucion' => collect(), 'hayMasAmbientes' => false,
            'movimientos' => collect([$movimiento]), 'puedeConsultarBienes' => false,
            'puedeConsultarMovimientos' => true, 'puedeRegistrarMovimiento' => false,
            'puedeConsultarInventario' => false, 'mantenimientoPorEspecialidad' => false,
        ])->render();
        $pdf = view('pdf.movimiento', ['movimiento' => $movimiento])->render();

        $this->assertStringContainsString('02/10/2026 10:00', $dashboard);
        $this->assertStringContainsString('02/10/2026 10:00:00', $pdf);
        $this->assertStringNotContainsString('02/10/2026 05:00', $dashboard.$pdf);
    }
}
