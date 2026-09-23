<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\CondicionBien;
use App\Models\Especialidad;
use App\Models\EstadoBien;
use App\Models\Sede;
use App\Models\TipoAmbiente;
use App\Models\Usuario;
use Tests\TestCase;

class CatalogRouteIdConstraintTest extends TestCase
{
    private const RESOURCES = [
        'sedes' => Sede::class,
        'tipos-ambiente' => TipoAmbiente::class,
        'especialidades' => Especialidad::class,
        'estados-bien' => EstadoBien::class,
        'condiciones-bien' => CondicionBien::class,
        'ambientes' => Ambiente::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $superadmin = Usuario::query()
            ->where('activo', true)
            ->whereHas('rol', fn ($query) => $query->where('nombre', 'superadmin'))
            ->firstOrFail();

        $this->actingAs($superadmin, 'web')->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Accept' => 'application/json',
        ]);
    }

    public function test_normal_and_missing_ids_keep_the_expected_behavior(): void
    {
        foreach (self::RESOURCES as $resource => $model) {
            $id = $model::query()->orderBy('id')->value('id');

            $this->assertNotNull($id, "No existen registros en $resource para ejecutar la prueba.");
            $this->getJson("/api/$resource/$id")->assertOk();
            $this->getJson("/api/$resource/2147483647")->assertNotFound();
        }
    }

    public function test_overlong_ids_return_not_found_for_read_update_and_delete_routes(): void
    {
        $overlongId = str_repeat('9', 30);

        foreach (array_keys(self::RESOURCES) as $resource) {
            $uri = "/api/$resource/$overlongId";

            $this->getJson($uri)->assertNotFound();
            $this->putJson($uri)->assertNotFound();
            $this->patchJson($uri)->assertNotFound();
            $this->deleteJson($uri)->assertNotFound();
        }
    }
}
