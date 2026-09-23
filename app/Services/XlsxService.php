<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class XlsxService
{
    public function generar(array $encabezados, iterable $filas, string $nombreHoja = 'Reporte'): string
    {
        $filas = is_array($filas) ? $filas : iterator_to_array($filas);
        $rutaTemporal = tempnam(storage_path('framework/cache'), 'reporte_xlsx_');

        if ($rutaTemporal === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal del reporte Excel.');
        }

        try {
            $zip = new ZipArchive();

            if ($zip->open($rutaTemporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear el reporte Excel.');
            }

            $zip->addFromString('[Content_Types].xml', $this->contentTypes());
            $zip->addFromString('_rels/.rels', $this->rootRelationships());
            $zip->addFromString('xl/workbook.xml', $this->workbook($nombreHoja));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
            $zip->addFromString('xl/styles.xml', $this->styles());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheet($encabezados, $filas));
            $zip->close();

            $contenido = file_get_contents($rutaTemporal);

            if ($contenido === false) {
                throw new RuntimeException('No se pudo leer el reporte Excel generado.');
            }

            return $contenido;
        } finally {
            if (is_file($rutaTemporal)) {
                unlink($rutaTemporal);
            }
        }
    }

    private function worksheet(array $encabezados, array $filas): string
    {
        $todasLasFilas = array_merge([$encabezados], array_values($filas));
        $ultimaColumna = $this->columna(count($encabezados));
        $ultimaFila = max(1, count($todasLasFilas));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<dimension ref="A1:'.$ultimaColumna.$ultimaFila.'"/>';
        $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        $xml .= '<cols>';

        for ($indice = 1; $indice <= count($encabezados); $indice++) {
            $xml .= '<col min="'.$indice.'" max="'.$indice.'" width="20" customWidth="1"/>';
        }

        $xml .= '</cols><sheetData>';

        foreach ($todasLasFilas as $indiceFila => $fila) {
            $numeroFila = $indiceFila + 1;
            $xml .= '<row r="'.$numeroFila.'">';

            foreach (array_values($fila) as $indiceColumna => $valor) {
                $referencia = $this->columna($indiceColumna + 1).$numeroFila;
                $estilo = $numeroFila === 1 ? ' s="1"' : '';
                $texto = $this->escape($valor === null ? '' : (string) $valor);
                $xml .= '<c r="'.$referencia.'" t="inlineStr"'.$estilo.'><is><t xml:space="preserve">'.$texto.'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function columna(int $numero): string
    {
        $columna = '';

        while ($numero > 0) {
            $numero--;
            $columna = chr(65 + ($numero % 26)).$columna;
            $numero = intdiv($numero, 26);
        }

        return $columna;
    }

    private function escape(string $valor): string
    {
        $valor = preg_replace('/[^\P{C}\t\n\r]/u', '', $valor) ?? '';

        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(string $nombreHoja): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->escape(substr($nombreHoja, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFD9EAF7"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'</styleSheet>';
    }
}
