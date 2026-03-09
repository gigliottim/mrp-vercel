<?php

declare(strict_types=1);

namespace App\Services\Reportes;

final class ListadoIngenieriaExportService
{
    /**
     * @param array<int, mixed>|array<string, array<int, mixed>> $datosReporte
     * @return array{headers: array<int, string>, rows: array<int, array<int, string|float|int|null>>}
     */
    public function buildTabularData(array $datosReporte, string $tipoSalida, bool $conPrecios, bool $agruparTipo): array
    {
        $headers = [];
        if ($tipoSalida === 'arbol') {
            $headers[] = 'Nivel';
        }
        $headers[] = 'Codigo';
        $headers[] = 'Detalle';
        $headers[] = 'Tipo';
        $headers[] = 'Cantidad';
        $headers[] = 'Unidad';
        if ($conPrecios) {
            $headers[] = 'Precio Unit.';
            $headers[] = 'Subtotal';
        }

        $rows = [];
        if ($agruparTipo) {
            foreach ($datosReporte as $grupoNombre => $items) {
                if (!is_array($items)) {
                    continue;
                }
                $rows[] = ['[' . (string) $grupoNombre . ']'];
                $rows = array_merge($rows, $this->buildRowsForItems($items, $tipoSalida, $conPrecios));
                $rows[] = [''];
            }
        } else {
            $rows = $this->buildRowsForItems($datosReporte, $tipoSalida, $conPrecios);
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, array<int, string|float|int|null>>
     */
    private function buildRowsForItems(array $items, string $tipoSalida, bool $conPrecios): array
    {
        $rows = [];
        $hierarchyCodes = $this->buildHierarchyCodes($items, $tipoSalida);

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $codigoCompuesto = $this->composeCodigo($item);
            $detalleCompuesto = $this->composeDetalle($item);
            $cantidadAjustada = (float) ($item['cantidad_ajustada'] ?? 0);
            $precioUnitario = 0.0;
            $subtotal = $precioUnitario * $cantidadAjustada;

            $row = [];
            if ($tipoSalida === 'arbol') {
                $row[] = '_' . ($hierarchyCodes[$index] ?? '');
            }

            $row[] = $codigoCompuesto !== '' ? $codigoCompuesto : 'N/A';
            $row[] = $detalleCompuesto;
            $row[] = (string) ($item['tipo_codigo'] ?? '');
            $row[] = $cantidadAjustada;
            $row[] = (string) ($item['unidad'] ?? $item['unidad_simbolo'] ?? 'UN');

            if ($conPrecios) {
                $row[] = $precioUnitario;
                $row[] = $subtotal;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, string>
     */
    private function buildHierarchyCodes(array $items, string $tipoSalida): array
    {
        if ($tipoSalida !== 'arbol') {
            return [];
        }

        $codes = [];
        $contadoresPorNivel = [];
        $pilaCodigos = ['0'];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $nivelActual = (int) ($item['nivel'] ?? 0);
            while (count($pilaCodigos) > $nivelActual + 1) {
                array_pop($pilaCodigos);
            }

            if (!isset($contadoresPorNivel[$nivelActual])) {
                $contadoresPorNivel[$nivelActual] = 0;
            }
            $contadoresPorNivel[$nivelActual]++;

            foreach ($contadoresPorNivel as $nivel => $contador) {
                if ($nivel > $nivelActual) {
                    $contadoresPorNivel[$nivel] = 0;
                }
            }

            if ($nivelActual === 0) {
                $codigoJerarquico = sprintf('%03d', $contadoresPorNivel[$nivelActual]);
                $pilaCodigos = [$codigoJerarquico];
            } else {
                $codigoJerarquico = $pilaCodigos[$nivelActual - 1] . '.' . sprintf('%03d', $contadoresPorNivel[$nivelActual]);
                if (count($pilaCodigos) === $nivelActual + 1) {
                    $pilaCodigos[$nivelActual] = $codigoJerarquico;
                } else {
                    $pilaCodigos[] = $codigoJerarquico;
                }
            }

            $codes[$index] = $codigoJerarquico;
        }

        return $codes;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function composeCodigo(array $item): string
    {
        $parteCodigo = trim((string) ($item['parte_codigo'] ?? ''));
        $varianteCodigo = trim((string) ($item['codigo_variante'] ?? $item['componente_codigo'] ?? ''));

        if ($parteCodigo !== '' && $varianteCodigo !== '') {
            return $parteCodigo . '-' . $varianteCodigo;
        }

        return $parteCodigo !== '' ? $parteCodigo : $varianteCodigo;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function composeDetalle(array $item): string
    {
        $parteDetalle = trim((string) ($item['parte_detalle'] ?? ''));
        $varianteDetalle = trim((string) ($item['variante_detalle'] ?? $item['componente_detalle'] ?? ''));
        return trim($parteDetalle . ' + ' . $varianteDetalle, ' +');
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|float|int|null>> $rows
     */
    public function generateXlsx(array $headers, array $rows): string
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('ZipArchive no disponible para generar XLSX.');
        }

        $sheetXml = $this->buildSheetXml($headers, $rows);

        $tempFile = tempnam(sys_get_temp_dir(), 'mrp_xlsx_');
        if ($tempFile === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal para XLSX.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tempFile, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tempFile);
            throw new \RuntimeException('No se pudo crear el archivo XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $content = (string) file_get_contents($tempFile);
        @unlink($tempFile);

        return $content;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|float|int|null>> $rows
     */
    private function buildSheetXml(array $headers, array $rows): string
    {
        $sheetData = [];
        $sheetData[] = $this->buildSheetRow(1, $headers, true);

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheetData[] = $this->buildSheetRow($rowNumber, $row, false);
            $rowNumber++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $sheetData) . '</sheetData>'
            . '</worksheet>';
    }

    /**
     * @param array<int, string|float|int|null> $values
     */
    private function buildSheetRow(int $rowNumber, array $values, bool $forceText): string
    {
        $cells = [];
        foreach ($values as $index => $value) {
            $cellRef = $this->columnName($index + 1) . $rowNumber;

            if (!$forceText && (is_int($value) || is_float($value))) {
                $cells[] = '<c r="' . $cellRef . '"><v>' . $value . '</v></c>';
                continue;
            }

            $text = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $cells[] = '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $text . '</t></is></c>';
        }

        return '<row r="' . $rowNumber . '">' . implode('', $cells) . '</row>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }
        return $name;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Listado" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|float|int|null>> $rows
     */
    public function generatePdf(array $headers, array $rows, string $titulo): string
    {
        $allLines = [];
        $allLines[] = $titulo;
        $allLines[] = str_repeat('=', min(strlen($titulo), 100));
        $allLines[] = '';

        $headerLine = implode(' | ', $headers);
        $allLines[] = $headerLine;
        $allLines[] = str_repeat('-', min(strlen($headerLine), 170));

        foreach ($rows as $row) {
            $lineValues = [];
            foreach ($row as $value) {
                if (is_float($value)) {
                    $lineValues[] = number_format($value, 2, '.', '');
                } else {
                    $lineValues[] = (string) ($value ?? '');
                }
            }
            $allLines[] = implode(' | ', $lineValues);
        }

        return $this->buildPlainTextPdf($allLines);
    }

    /**
     * @param array<int, string> $lines
     */
    private function buildPlainTextPdf(array $lines): string
    {
        $linesPerPage = 48;
        $pages = array_chunk($lines, $linesPerPage);

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

        $kids = [];
        $objectNumber = 4;

        foreach ($pages as $pageLines) {
            $content = $this->buildPdfTextStream($pageLines);
            $contentObj = $objectNumber++;
            $pageObj = $objectNumber++;

            $objects[$contentObj] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObj . ' 0 R >>';
            $kids[] = $pageObj . ' 0 R';
        }

        $objects[2] = '<< /Type /Pages /Count ' . count($kids) . ' /Kids [ ' . implode(' ', $kids) . ' ] >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $num => $obj) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $obj . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObj = max(array_keys($objects));

        $pdf .= 'xref' . "\n";
        $pdf .= '0 ' . ($maxObj + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObj; $i++) {
            $off = $offsets[$i] ?? 0;
            $pdf .= str_pad((string) $off, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= 'trailer << /Size ' . ($maxObj + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    /**
     * @param array<int, string> $lines
     */
    private function buildPdfTextStream(array $lines): string
    {
        $escapedLines = [];
        foreach ($lines as $line) {
            $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $line);
            if ($encoded === false) {
                $encoded = preg_replace('/[^\x20-\x7E]/', '?', $line) ?? '';
            }

            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
            $escapedLines[] = '(' . $escaped . ') Tj';
        }

        $stream = "BT\n/F1 9 Tf\n40 800 Td\n11 TL\n";
        foreach ($escapedLines as $i => $line) {
            if ($i > 0) {
                $stream .= "T*\n";
            }
            $stream .= $line . "\n";
        }
        $stream .= 'ET';

        return $stream;
    }
}
