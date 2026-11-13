<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\TestCase;
use ZipArchive;

class ReportesFiltrosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::table('sedes')->insert(['id' => 2, 'nombre' => 'Otra sede simulada']);
        DB::table('ambientes')->insert(['id' => 3, 'nombre' => 'Otro laboratorio', 'sede_id' => 2, 'especialidad_id' => 1, 'tipo_ambiente_id' => 1]);
        DB::table('estados_bien')->insert(['id' => 2, 'nombre' => 'Otro estado']);
        DB::table('tipos_equipo')->insert([['id' => 2, 'nombre' => 'MONITOR'], ['id' => 3, 'nombre' => 'PROYECTOR']]);
        DB::table('bienes')->where('id', 1)->update(['descripcion' => 'Computadora simulada']);
        DB::table('bienes')->where('id', 2)->update(['descripcion' => 'Monitor simulado', 'condicion_id' => 2]);
        foreach ([[3, '020003', 'Mueble simulado', 3, 1], [4, '020004', 'Proyector simulado', 3, 2], [5, '009900', 'Sin ubicacion', null, 1]] as [$id, $cbi, $descripcion, $ambiente, $estado]) {
            DB::table('bienes')->insert(['id' => $id, 'cbi' => $cbi, 'descripcion' => $descripcion, 'ambiente_id' => $ambiente, 'estado_id' => $estado, 'condicion_id' => 1, 'activo' => true]);
        }
        foreach ([[1, 1], [2, 2], [4, 3]] as [$id, $tipo]) DB::table('equipos')->insert(['bien_id' => $id, 'tipo_equipo_id' => $tipo, 'marca_id' => 1, 'modelo' => 'Modelo simulado', 'serie' => "SERIE-$id"]);
        DB::table('equipos_computo')->insert(['bien_id' => 1, 'procesador' => 'Procesador simulado', 'ram' => '16 GB', 'almacenamiento' => '512 GB SSD']);
        DB::table('monitores')->insert(['bien_id' => 2, 'tamanio_pantalla' => '24']);
        DB::table('muebles')->insert(['bien_id' => 3, 'tipo_mueble_id' => 1]);
        foreach ([[1, 2025], [3, 2026], [4, 2026], [5, 2027]] as [$id, $anio]) DB::table('inventarios')->insert(['bien_id' => $id, 'anio' => $anio, 'inventario' => "INV-$id-$anio"]);
        DB::table('tipos_mantenimiento')->insert(['id' => 2, 'nombre' => 'Otro tipo simulado']);
        foreach ([[1, 1, '2026-01-05'], [2, 2, '2026-02-10'], [1, 2, '2026-02-20'], [3, 1, '2026-02-28']] as [$id, $tipo, $fecha]) {
            DB::table('mantenimientos')->insert(['bien_id' => $id, 'tipo_mantenimiento_id' => $tipo, 'fecha_mantenimiento' => $fecha, 'descripcion' => 'Trabajo simulado']);
        }
    }

    private function asRole(string $role, bool $active = true, ?int $specialty = 1): static
    {
        $user = new Usuario(['activo' => $active, 'especialidad_id' => $specialty, 'password_hash' => 'test-session-only']);
        $user->setRelation('rol', new Rol(['nombre' => $role]));
        return $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_individual_filters_and_combined_filters_count_the_real_records(): void
    {
        $this->asRole('superadmin');
        $cases = [
            'inventario' => [['', 6], ['q=0000001', 2], ['q=Computadora', 2], ['q=02', 3], ['sede_id=2', 2], ['ambiente_id=3', 2],
                ['especialidad_id=2', 1], ['anio=2025', 1], ['estado_id=2', 1], ['condicion_id=2', 1], ['categoria=computadoras', 2],
                ['categoria=monitores', 1], ['categoria=mobiliario', 1], ['categoria=otros-equipos', 1], ['tipo_equipo_id=3', 1],
                ['q=0000001&anio=2026&sede_id=1&ambiente_id=1&especialidad_id=1&categoria=computadoras&tipo_equipo_id=1&estado_id=1&condicion_id=1', 1]],
            'equipos' => [['', 3], ['q=0000002', 1], ['q=Proyector', 1], ['sede_id=2', 1], ['ambiente_id=1', 1], ['especialidad_id=1', 2],
                ['condicion_id=2', 1], ['estado_id=2', 1], ['categoria=computadoras', 1], ['categoria=monitores', 1], ['categoria=otros-equipos', 1], ['tipo_equipo_id=3', 1],
                ['q=Computadora&sede_id=1&ambiente_id=1&especialidad_id=1&categoria=computadoras&tipo_equipo_id=1&estado_id=1&condicion_id=1', 1]],
            'mantenimientos' => [['', 4], ['q=Computadora', 2], ['q=0000002', 1], ['sede_id=2', 1], ['ambiente_id=1', 2], ['especialidad_id=2', 1],
                ['condicion_id=2', 1], ['estado_id=2', 0], ['categoria=computadoras', 2], ['tipo_equipo_id=2', 1], ['tipo_mantenimiento_id=2', 2],
                ['desde=2026-02-01&hasta=2026-02-28', 3], ['hasta=2026-01-05', 1],
                ['q=Computadora&sede_id=1&ambiente_id=1&especialidad_id=1&condicion_id=1&estado_id=1&categoria=computadoras&tipo_equipo_id=1&desde=2026-02-20&hasta=2026-02-20&tipo_mantenimiento_id=2', 1]],
        ];
        foreach ($cases as $type => $filters) foreach ($filters as [$query, $expected]) {
            $this->getJson("/api/reportes/$type/resumen?$query")->assertOk()->assertJsonPath('data.total', $expected);
        }
    }

    public function test_pdf_and_excel_use_identical_filtered_rows_and_include_current_technical_data(): void
    {
        $this->asRole('director');
        $viewData = null;
        View::composer('pdf.reporte-tabular', function ($view) use (&$viewData) { $viewData = $view->getData(); });
        foreach (['inventario' => 'anio=2026&q=0000001', 'equipos' => 'categoria=computadoras', 'mantenimientos' => 'tipo_mantenimiento_id=2&ambiente_id=1'] as $type => $filters) {
            $pdf = $this->getJson("/api/reportes/$type/pdf?$filters")->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $pdf->getContent());
            $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf->getContent()));
            $excel = $this->getJson("/api/reportes/$type/excel?$filters")->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $rows = $this->xlsxRows($excel->getContent());
            $this->assertSame($viewData['encabezados'], array_shift($rows));
            $this->assertSame(array_map(fn ($row) => array_map(fn ($value) => (string) ($value ?? ''), $row), $viewData['filas']), $rows);
            $this->assertContains('0000001', $rows[0]);
            if ($type === 'equipos') foreach (['Procesador simulado', '16 GB', '512 GB SSD', 'Modelo simulado', 'SERIE-1', 'Marca simulada'] as $value) $this->assertContains($value, $rows[0]);
        }
        $rows = $this->xlsxRows($this->getJson('/api/reportes/equipos/excel?categoria=monitores')->assertOk()->getContent());
        foreach (['Procesador', 'Memoria RAM', 'Almacenamiento'] as $header) $this->assertSame('', $rows[1][array_search($header, $rows[0], true)]);
    }

    public function test_exports_include_all_filtered_rows_independently_of_a_page_parameter(): void
    {
        $this->asRole('administrador');
        for ($year = 2000; $year < 2020; $year++) DB::table('inventarios')->insert(['bien_id' => 4, 'anio' => $year, 'inventario' => "INV-$year"]);
        $this->getJson('/api/reportes/inventario/resumen?q=020004')->assertOk()->assertJsonPath('data.total', 21);
        $excel = $this->getJson('/api/reportes/inventario/excel?q=020004&page=2')->assertOk();
        $this->assertCount(22, $this->xlsxRows($excel->getContent()));
        $pdfRows = null;
        View::composer('pdf.reporte-tabular', function ($view) use (&$pdfRows) { $pdfRows = $view->getData()['filas']; });
        $this->getJson('/api/reportes/inventario/pdf?q=020004&page=2')->assertOk();
        $this->assertCount(21, $pdfRows);
    }

    public function test_forged_filters_never_expand_specialty_or_laboratory_scope(): void
    {
        $this->asRole('coordinador');
        foreach (['inventario', 'equipos', 'mantenimientos'] as $type) {
            $this->getJson("/api/reportes/$type/resumen?q=0000002&especialidad_id=2")->assertOk()->assertJsonPath('data.total', 0);
            foreach (['pdf', 'excel'] as $format) $this->getJson("/api/reportes/$type/$format?q=0000002&especialidad_id=2")->assertUnprocessable();
            $this->getJson("/api/reportes/$type/opciones")->assertOk()->assertJsonMissing(['nombre' => 'Carrera B']);
        }
        $this->asRole('coordinador', true, null)->getJson('/api/reportes/equipos/resumen?completo=1')->assertOk()->assertJsonPath('data.total', 0);
        $this->asRole('asistente')->getJson('/api/reportes/inventario/resumen?ambiente_id=2')->assertOk()->assertJsonPath('data.total', 0);
        foreach (['equipos', 'mantenimientos'] as $type) foreach (['opciones', 'resumen', 'pdf', 'excel'] as $action) $this->getJson("/api/reportes/$type/$action")->assertForbidden();
        $options = $this->getJson('/api/reportes/inventario/opciones')->assertOk();
        $this->assertNotContains(2, array_column($options->json('data.ambientes'), 'id'));
        foreach (['superadmin', 'administrador', 'director'] as $role) $this->asRole($role)->getJson('/api/reportes/inventario/resumen?completo=1')->assertOk()->assertJsonPath('data.total', 6);
        $this->asRole('director', false)->getJson('/api/reportes/equipos/opciones')->assertForbidden();
    }

    public function test_empty_reports_invalid_filters_and_literal_search_do_not_create_files(): void
    {
        $this->asRole('superadmin');
        foreach (['inventario', 'equipos', 'mantenimientos'] as $type) {
            foreach (['pdf', 'excel'] as $format) $this->getJson("/api/reportes/$type/$format?q=sin-coincidencias")->assertUnprocessable()->assertJsonValidationErrors('reporte');
            foreach (['%25', '_', '%27%20OR%201%3D1'] as $q) $this->getJson("/api/reportes/$type/resumen?q=$q")->assertOk()->assertJsonPath('data.total', 0);
            $this->getJson("/api/reportes/$type/resumen?completo=1&q=00")->assertUnprocessable();
            $this->getJson("/api/reportes/$type/resumen?sede_id=1&ambiente_id=3")->assertUnprocessable();
            $this->getJson("/api/reportes/$type/resumen?estado_id=99999")->assertUnprocessable();
            $this->getJson("/api/reportes/$type/resumen?q[]=00")->assertUnprocessable();
        }
        $this->getJson('/api/reportes/equipos/resumen?anio=2026')->assertUnprocessable();
        $this->getJson('/api/reportes/inventario/resumen?desde=2026-01-01')->assertUnprocessable();
        $this->getJson('/api/reportes/mantenimientos/resumen?desde=2026-02-01&hasta=2026-01-01')->assertUnprocessable();
        $this->getJson('/api/reportes/mantenimientos/resumen?tipo_mantenimiento_id=99999')->assertUnprocessable();
    }

    private function xlsxRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'report-filter-test-');
        try {
            file_put_contents($path, $content);
            $zip = new ZipArchive(); $this->assertTrue($zip->open($path) === true);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close();
            $doc = new \DOMDocument(); $this->assertTrue($doc->loadXML($xml));
            $xpath = new \DOMXPath($doc); $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows = [];
            foreach ($xpath->query('//s:sheetData/s:row') as $row) {
                $values = [];
                foreach ($xpath->query('s:c', $row) as $cell) { $this->assertSame('inlineStr', $cell->getAttribute('t')); $values[] = $xpath->evaluate('string(s:is/s:t)', $cell); }
                $rows[] = $values;
            }
            return $rows;
        } finally { if (is_file($path)) unlink($path); }
    }
}
