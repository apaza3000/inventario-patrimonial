<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class FormRequiredLabelsTest extends TestCase
{
    public function test_required_markers_share_a_label_container_in_pages_and_modals(): void
    {
        // Unsaved session user; no database fixtures or writes are needed.
        $user = new Usuario(['nombres' => 'Prueba', 'activo' => true]);
        $user->setRelation('rol', new Rol(['nombre' => 'superadmin']));
        $this->actingAs($user, 'web');

        foreach (['/inventario/bienes', '/inventario/estaciones', '/inventario/toma-inventario', '/movimientos', '/mantenimientos', '/mantenimientos/registrar'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $document = new DOMDocument();
            @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            $xpath = new DOMXPath($document);
            $markers = $xpath->query('//span[@aria-hidden="true" and normalize-space(.)="*"]');
            $this->assertGreaterThan(0, $markers->length, $url);
            foreach ($markers as $marker) {
                $this->assertSame('span', $marker->parentNode->nodeName, $url);
                $this->assertStringContainsString('field-label', $marker->parentNode->getAttribute('class'), $url);
                $this->assertNotSame('*', trim($marker->parentNode->textContent), $url);
            }
        }
    }
}
