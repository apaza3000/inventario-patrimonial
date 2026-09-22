<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Bien;
use App\Models\Movimiento;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovimientoFirmaTest extends TestCase
{
    use DatabaseTransactions;

    private Usuario $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->asistente = $this->createUsuario('asistente');
        $this->actingAs($this->asistente, 'web')->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ]);
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('movimientos');

        parent::tearDown();
    }

    private function createUsuario(string $rol, bool $activo = true): Usuario
    {
        return Usuario::create([
            'nombres' => 'Prueba',
            'apellidos' => ucfirst($rol),
            'correo' => uniqid('firma-'.$rol.'-', true).'@example.test',
            'password_hash' => Hash::make('ClaveSegura123!'),
            'rol_id' => Rol::query()->where('nombre', $rol)->firstOrFail()->id,
            'activo' => $activo,
            'fecha_registro' => now(),
        ]);
    }

    private function movementData(?Bien $bien = null): array
    {
        $bien ??= Bien::query()->firstOrFail();
        $destino = Ambiente::query()
            ->where('activo', true)
            ->whereKeyNot($bien->ambiente_id)
            ->firstOrFail();

        return [
            'bien_id' => $bien->id,
            'ambiente_origen_id' => $bien->ambiente_id,
            'ambiente_destino_id' => $destino->id,
            'ordenado_por' => $this->asistente->id,
            'ejecutado_por' => $this->asistente->id,
            'fecha_movimiento' => now()->format('Y-m-d H:i:s'),
            'motivo' => 'Traslado para prueba de firma',
            'observaciones' => 'Documento generado durante una prueba',
        ];
    }

    private function createPending(?Bien $bien = null): Movimiento
    {
        $response = $this->postJson('/api/movimientos', $this->movementData($bien))
            ->assertCreated();

        return Movimiento::query()->findOrFail($response->json('data.id'));
    }

    private function signedPdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'movimiento-firmado.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
        );
    }

    public function test_registration_generates_private_pdf_and_does_not_move_asset(): void
    {
        $bien = Bien::query()->firstOrFail();
        $originalEnvironment = $bien->ambiente_id;

        $response = $this->postJson('/api/movimientos', $this->movementData($bien))
            ->assertCreated()
            ->assertJsonPath('data.estado', Movimiento::ESTADO_PENDIENTE_FIRMA)
            ->assertJsonMissingPath('data.pdf_generado_ruta')
            ->assertJsonMissingPath('data.pdf_firmado_ruta');

        $movimiento = Movimiento::query()->findOrFail($response->json('data.id'));
        Storage::disk('local')->assertExists($movimiento->pdf_generado_ruta);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($movimiento->pdf_generado_ruta));
        $this->assertDatabaseHas('bienes', ['id' => $bien->id, 'ambiente_id' => $originalEnvironment]);
        $this->assertNull($movimiento->pdf_firmado_ruta);
        $this->assertNull($movimiento->fecha_firma);
    }

    public function test_responsible_users_are_required_valid_and_active(): void
    {
        $data = $this->movementData();
        unset($data['ordenado_por'], $data['ejecutado_por']);
        $this->postJson('/api/movimientos', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ordenado_por', 'ejecutado_por']);

        $inactive = $this->createUsuario('asistente', false);
        $data = $this->movementData();
        $data['ordenado_por'] = $inactive->id;
        $data['ejecutado_por'] = $inactive->id;
        $this->postJson('/api/movimientos', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ordenado_por', 'ejecutado_por']);
    }

    public function test_a_good_cannot_have_two_pending_movements(): void
    {
        $bien = Bien::query()->firstOrFail();
        $this->createPending($bien);

        $this->postJson('/api/movimientos', $this->movementData($bien))
            ->assertConflict()
            ->assertJsonPath('message', 'El bien ya tiene un movimiento pendiente de firma.');
    }

    public function test_generated_pdf_can_be_downloaded_privately(): void
    {
        $movimiento = $this->createPending();

        $this->get("/api/movimientos/{$movimiento->id}/pdf-generado")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_signed_pdf_finalizes_movement_and_cannot_be_replaced(): void
    {
        $movimiento = $this->createPending();
        $originalEnvironment = $movimiento->bien->ambiente_id;

        $this->post("/api/movimientos/{$movimiento->id}/pdf-firmado", [
            'archivo' => $this->signedPdf(),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.estado', Movimiento::ESTADO_FINALIZADO)
            ->assertJsonMissingPath('data.pdf_firmado_ruta');

        $movimiento->refresh();
        $this->assertNotSame($originalEnvironment, $movimiento->ambiente_destino_id);
        $this->assertNotNull($movimiento->fecha_firma);
        Storage::disk('local')->assertExists($movimiento->pdf_firmado_ruta);
        $this->assertDatabaseHas('bienes', [
            'id' => $movimiento->bien_id,
            'ambiente_id' => $movimiento->ambiente_destino_id,
        ]);

        $this->get("/api/movimientos/{$movimiento->id}/pdf-firmado")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $storedPath = $movimiento->pdf_firmado_ruta;
        $this->post("/api/movimientos/{$movimiento->id}/pdf-firmado", [
            'archivo' => $this->signedPdf(),
        ], ['Accept' => 'application/json'])->assertConflict();
        $this->assertSame($storedPath, $movimiento->fresh()->pdf_firmado_ruta);
    }

    public function test_signed_upload_accepts_only_pdf_up_to_ten_megabytes(): void
    {
        $movimiento = $this->createPending();

        $this->post("/api/movimientos/{$movimiento->id}/pdf-firmado", [
            'archivo' => UploadedFile::fake()->create('documento.txt', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archivo');

        $this->post("/api/movimientos/{$movimiento->id}/pdf-firmado", [
            'archivo' => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archivo');
    }

    public function test_origin_destination_and_chronology_validations_remain_active(): void
    {
        $bien = Bien::query()->firstOrFail();
        $data = $this->movementData($bien);
        $data['ambiente_origen_id'] = Ambiente::query()->whereKeyNot($bien->ambiente_id)->value('id');
        $this->postJson('/api/movimientos', $data)->assertConflict();

        $data = $this->movementData($bien);
        $data['ambiente_destino_id'] = $bien->ambiente_id;
        $this->postJson('/api/movimientos', $data)->assertUnprocessable();

        Movimiento::create([
            ...$this->movementData($bien),
            'fecha_movimiento' => '2026-09-22 12:00:00',
            'estado' => Movimiento::ESTADO_FINALIZADO,
        ]);
        $data = $this->movementData($bien);
        $data['fecha_movimiento'] = '2026-09-22 11:59:59';
        $this->postJson('/api/movimientos', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_movimiento');
    }
}
