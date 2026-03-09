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
        $sheetXml = $this->buildSheetXml($headers, $rows);
        $entries = [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->relsXml(),
            'docProps/app.xml' => $this->docPropsAppXml(),
            'docProps/core.xml' => $this->docPropsCoreXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelsXml(),
            'xl/styles.xml' => $this->stylesXml(),
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ];

        if (!class_exists('ZipArchive')) {
            return $this->buildZipArchive($entries);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'mrp_xlsx_');
        if ($tempFile === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal para XLSX.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tempFile);
            throw new \RuntimeException('No se pudo crear el archivo XLSX.');
        }

        foreach ($entries as $path => $content) {
            $zip->addFromString($path, $content);
        }
        $zip->close();

        $content = file_get_contents($tempFile);
        @unlink($tempFile);

        if ($content === false || $content === '') {
            throw new \RuntimeException('No se pudo leer el XLSX generado.');
        }

        return (string) $content;
    }

    /**
     * @param array<string, string> $entries
     */
    private function buildZipArchive(array $entries): string
    {
        $localRecords = '';
        $centralDirectory = '';
        $offset = 0;
        $entryCount = 0;

        foreach ($entries as $name => $content) {
            $entryCount++;
            $name = str_replace('\\\\', '/', $name);
            $nameLen = strlen($name);
            $dataLen = strlen($content);
            $crc = crc32($content);
            if ($crc < 0) {
                $crc += 4294967296;
            }

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $dataLen,
                $dataLen,
                $nameLen,
                0
            ) . $name;

            $localRecord = $localHeader . $content;
            $localRecords .= $localRecord;

            $centralHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $dataLen,
                $dataLen,
                $nameLen,
                0,
                0,
                0,
                0,
                32,
                $offset
            ) . $name;

            $centralDirectory .= $centralHeader;
            $offset += strlen($localRecord);
        }

        $endOfCentral = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $entryCount,
            $entryCount,
            strlen($centralDirectory),
            strlen($localRecords),
            0
        );

        return $localRecords . $centralDirectory . $endOfCentral;
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

        $lastColumn = $this->columnName(max(1, count($headers)));
        $lastRow = max(1, count($rows) + 1);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
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
            $normalized = $this->normalizeCellValue($value);

            if (!$forceText && (is_int($normalized) || is_float($normalized))) {
                $cells[] = '<c r="' . $cellRef . '"><v>' . $normalized . '</v></c>';
                continue;
            }

            $text = $this->xmlSafeText((string) ($normalized ?? ''));
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
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
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
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '</styleSheet>';
    }

    private function docPropsAppXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>MRP</Application>'
            . '</Properties>';
    }

    private function docPropsCoreXml(): string
    {
        $now = gmdate('Y-m-d\\TH:i:s\\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Listado de Ingenieria</dc:title>'
            . '<dc:creator>MRP</dc:creator>'
            . '<cp:lastModifiedBy>MRP</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    /**
     * @param string|float|int|null $value
     */
    private function normalizeCellValue($value): string|float|int|null
    {
        if (is_float($value)) {
            return is_finite($value) ? $value : 0.0;
        }

        if (is_int($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }

    private function xmlSafeText(string $value): string
    {
        $clean = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);
        $clean = $clean ?? '';
        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|float|int|null>> $rows
     */
    public function generatePdf(array $headers, array $rows, string $titulo): string
    {
        $pageWidth = 595.0;
        $pageHeight = 842.0;
        $margin = 28.0;
        $tableWidth = $pageWidth - ($margin * 2);

        $titleHeight = 26.0;
        $headerRowHeight = 20.0;
        $rowHeight = 18.0;

        $columnWidths = $this->scaledColumnWidths($headers, $tableWidth);
        $cellChars = $this->columnCharCapacity($columnWidths);

        $pages = [];
        $currentPage = [];
        $y = $pageHeight - $margin;

        $drawPageHeader = function () use (&$currentPage, &$y, $titulo, $margin, $pageHeight, $titleHeight, $headerRowHeight, $headers, $columnWidths, $tableWidth, $cellChars): void {
            $y = $pageHeight - $margin;

            // Title
            $this->drawFilledRect($currentPage, $margin, $y - $titleHeight, $tableWidth, $titleHeight, [0.90, 0.94, 1.0]);
            $this->drawRect($currentPage, $margin, $y - $titleHeight, $tableWidth, $titleHeight, [0.62, 0.71, 0.84], 0.8);
            $this->drawText($currentPage, $margin + 8, $y - 17, $titulo, 12, true, [0.12, 0.22, 0.35]);
            $y -= $titleHeight + 8;

            // Header row
            $this->drawFilledRect($currentPage, $margin, $y - $headerRowHeight, $tableWidth, $headerRowHeight, [0.95, 0.95, 0.95]);
            $this->drawRect($currentPage, $margin, $y - $headerRowHeight, $tableWidth, $headerRowHeight, [0.70, 0.70, 0.70], 0.7);

            $x = $margin;
            foreach ($headers as $index => $header) {
                $w = $columnWidths[$index] ?? 60.0;
                $headerText = $this->fitText((string) $header, $cellChars[$index] ?? 10);
                $this->drawText($currentPage, $x + 3, $y - 14, $headerText, 9, true, [0.20, 0.20, 0.20]);
                if ($index > 0) {
                    $this->drawLine($currentPage, $x, $y - $headerRowHeight, $x, $y, [0.75, 0.75, 0.75], 0.5);
                }
                $x += $w;
            }
            $y -= $headerRowHeight;
        };

        $drawPageHeader();

        foreach ($rows as $row) {
            if ($y - $rowHeight < $margin) {
                $pages[] = $currentPage;
                $currentPage = [];
                $drawPageHeader();
            }

            $isGroupRow = count($row) === 1;
            if ($isGroupRow) {
                $this->drawFilledRect($currentPage, $margin, $y - $rowHeight, $tableWidth, $rowHeight, [0.97, 0.98, 1.00]);
                $this->drawRect($currentPage, $margin, $y - $rowHeight, $tableWidth, $rowHeight, [0.82, 0.86, 0.93], 0.6);
                $text = $this->fitText((string) ($row[0] ?? ''), $this->columnCharCapacity([$tableWidth])[0]);
                $this->drawText($currentPage, $margin + 4, $y - 12, $text, 9, true, [0.16, 0.26, 0.41]);
                $y -= $rowHeight;
                continue;
            }

            $this->drawRect($currentPage, $margin, $y - $rowHeight, $tableWidth, $rowHeight, [0.82, 0.82, 0.82], 0.45);
            $x = $margin;
            foreach ($headers as $index => $header) {
                $w = $columnWidths[$index] ?? 60.0;
                if ($index > 0) {
                    $this->drawLine($currentPage, $x, $y - $rowHeight, $x, $y, [0.88, 0.88, 0.88], 0.35);
                }

                $raw = $row[$index] ?? '';
                $cellValue = is_float($raw) ? number_format($raw, 2, ',', '.') : (string) $raw;
                $cellText = $this->fitText($cellValue, $cellChars[$index] ?? 10);

                $alignRight = in_array((string) $header, ['Cantidad', 'Precio Unit.', 'Subtotal'], true);
                if ($alignRight) {
                    $textWidth = $this->estimatedTextWidth($cellText, 8.2);
                    $this->drawText($currentPage, max($x + 2, $x + $w - $textWidth - 3), $y - 12, $cellText, 8.2, false, [0.10, 0.10, 0.10]);
                } else {
                    $this->drawText($currentPage, $x + 3, $y - 12, $cellText, 8.2, false, [0.10, 0.10, 0.10]);
                }

                $x += $w;
            }

            $y -= $rowHeight;
        }

        if (!empty($currentPage)) {
            $pages[] = $currentPage;
        }

        return $this->buildVectorPdf($pages);
    }

    /**
     * @param array<int, string> $headers
     * @return array<int, float>
     */
    private function scaledColumnWidths(array $headers, float $targetWidth): array
    {
        $baseByHeader = [
            'Nivel' => 58.0,
            'Codigo' => 86.0,
            'Detalle' => 220.0,
            'Tipo' => 72.0,
            'Cantidad' => 72.0,
            'Unidad' => 55.0,
            'Precio Unit.' => 88.0,
            'Subtotal' => 90.0,
        ];

        $widths = [];
        foreach ($headers as $header) {
            $widths[] = $baseByHeader[$header] ?? 80.0;
        }

        $sum = array_sum($widths);
        if ($sum <= 0.0) {
            return $widths;
        }

        $scale = $targetWidth / $sum;
        foreach ($widths as $index => $w) {
            $widths[$index] = max(40.0, round($w * $scale, 2));
        }

        // Ajuste fino para que calce exacto
        $delta = $targetWidth - array_sum($widths);
        if (!empty($widths)) {
            $widths[count($widths) - 1] += $delta;
        }

        return $widths;
    }

    /**
     * @param array<int, float> $widths
     * @return array<int, int>
     */
    private function columnCharCapacity(array $widths): array
    {
        $caps = [];
        foreach ($widths as $i => $w) {
            $caps[$i] = max(4, (int) floor(($w - 6) / 4.7));
        }
        return $caps;
    }

    private function fitText(string $text, int $maxChars): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($text, 'UTF-8') <= $maxChars) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, max(1, $maxChars - 1), 'UTF-8')) . '…';
    }

    private function estimatedTextWidth(string $text, float $fontSize): float
    {
        return mb_strlen($text, 'UTF-8') * ($fontSize * 0.52);
    }

    /** @param array<int, string> $ops */
    private function drawRect(array &$ops, float $x, float $y, float $w, float $h, array $rgb, float $lineWidth): void
    {
        $ops[] = sprintf('%.2F w %.3F %.3F %.3F RG %.2F %.2F %.2F %.2F re S', $lineWidth, $rgb[0], $rgb[1], $rgb[2], $x, $y, $w, $h);
    }

    /** @param array<int, string> $ops */
    private function drawFilledRect(array &$ops, float $x, float $y, float $w, float $h, array $rgb): void
    {
        $ops[] = sprintf('%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f', $rgb[0], $rgb[1], $rgb[2], $x, $y, $w, $h);
    }

    /** @param array<int, string> $ops */
    private function drawLine(array &$ops, float $x1, float $y1, float $x2, float $y2, array $rgb, float $lineWidth): void
    {
        $ops[] = sprintf('%.2F w %.3F %.3F %.3F RG %.2F %.2F m %.2F %.2F l S', $lineWidth, $rgb[0], $rgb[1], $rgb[2], $x1, $y1, $x2, $y2);
    }

    /** @param array<int, string> $ops */
    private function drawText(array &$ops, float $x, float $y, string $text, float $size, bool $bold, array $rgb): void
    {
        $font = $bold ? '/F2' : '/F1';
        $encoded = $this->pdfEncode($text);
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
        $ops[] = sprintf('BT %.3F %.3F %.3F rg %s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET', $rgb[0], $rgb[1], $rgb[2], $font, $size, $x, $y, $escaped);
    }

    private function pdfEncode(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($encoded === false) {
            return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        }
        return $encoded;
    }

    /**
     * @param array<int, array<int, string>> $pagesOps
     */
    private function buildVectorPdf(array $pagesOps): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $kids = [];
        $objectNumber = 5;

        foreach ($pagesOps as $ops) {
            $stream = implode("\n", $ops);
            $contentObj = $objectNumber++;
            $pageObj = $objectNumber++;

            $objects[$contentObj] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
            $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> '
                . '/Contents ' . $contentObj . ' 0 R >>';
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

        $pdf .= "xref\n";
        $pdf .= '0 ' . ($maxObj + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObj; $i++) {
            $off = $offsets[$i] ?? 0;
            $pdf .= str_pad((string) $off, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= 'trailer << /Size ' . ($maxObj + 1) . ' /Root 1 0 R >>' . "\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }
}
