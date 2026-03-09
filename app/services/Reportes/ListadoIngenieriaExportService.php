<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

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
        return trim($parteDetalle . ' - ' . $varianteDetalle, ' -');
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|float|int|null>> $rows
     */
    public function generateXlsx(array $headers, array $rows, string $titulo = 'Listado de Ingenieria', string $subtitulo = ''): string
    {
        $spreadsheet = $this->buildSpreadsheet($headers, $rows, $titulo, $subtitulo);
        return $this->writeSpreadsheetToString($spreadsheet, 'Xlsx');
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|float|int|null>> $rows
     */
    public function generatePdf(array $headers, array $rows, string $titulo, string $subtitulo = ''): string
    {
        $pdfOrientation = $this->resolvePdfOrientation($headers);
        $spreadsheet = $this->buildSpreadsheet($headers, $rows, $titulo, $subtitulo, true, $pdfOrientation);

        // Renderizar PDF con Dompdf (PhpSpreadsheet Writer\Pdf\Dompdf)
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getPageSetup()
            ->setOrientation($pdfOrientation)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()
            ->setTop(0.15)
            ->setBottom(0.15)
            ->setLeft(0.1)
            ->setRight(0.1);
        $sheet->getPageSetup()->setHorizontalCentered(true);

        return $this->writeSpreadsheetToString($spreadsheet, 'Dompdf');
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|float|int|null>> $rows
     */
    private function buildSpreadsheet(
        array $headers,
        array $rows,
        string $title,
        string $subtitle = '',
        bool $forPdf = false,
        string $pdfOrientation = PageSetup::ORIENTATION_LANDSCAPE
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(9);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Listado');

        $columnCount = count($headers);
        $lastCol = Coordinate::stringFromColumnIndex(max(1, $columnCount));

        // Titulo
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['argb' => 'FF1F3A5B']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE4ECF7'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF98B5DD'],
                ],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(36);

        if ($subtitle !== '') {
            $sheet->mergeCells('A2:' . $lastCol . '2');
            $sheet->setCellValue('A2', $subtitle);
            $sheet->getStyle('A2')->applyFromArray([
                'font' => ['bold' => false, 'size' => 9, 'color' => ['argb' => 'FF3A4A5A']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ]);
            $sheet->getRowDimension(2)->setRowHeight(20);
        }

        // Header
        $headerRow = 4;
        $headerDisplayMap = [
            'Precio Unit.' => "Precio\nUnit.",
            'Subtotal' => "Sub\ntotal",
        ];
        foreach ($headers as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . $headerRow, $headerDisplayMap[$header] ?? $header);
        }

        $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF333333']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE9E9E9'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFBDBDBD'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        $startDataRow = $headerRow + 1;
        $rowNum = $startDataRow;
        $detalleColIndex = array_search('Detalle', $headers, true);
        $columnWidths = $this->calculateColumnWidths($headers, $rows, $forPdf, $pdfOrientation);
        $detalleWidth = $columnWidths['Detalle'] ?? 47.0;

        foreach ($rows as $row) {
            $isGroup = count($row) === 1;
            if ($isGroup) {
                $sheet->mergeCells('A' . $rowNum . ':' . $lastCol . $rowNum);
                $sheet->setCellValue('A' . $rowNum, (string) ($row[0] ?? ''));
                $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF294A75']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFF4F7FB'],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFD0D8E5'],
                        ],
                    ],
                ]);
                $rowNum++;
                continue;
            }

            $detalleLines = 1;
            foreach ($headers as $i => $header) {
                $colIdx = $i + 1;
                $col = Coordinate::stringFromColumnIndex($colIdx);
                $value = $row[$i] ?? '';

                if ($detalleColIndex !== false && $i === $detalleColIndex) {
                    $detalleTexto = trim((string) $value);
                    $detalleLines = $this->estimateDetailLines($detalleTexto, $detalleWidth);
                    $sheet->setCellValueExplicit($col . $rowNum, $detalleTexto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    continue;
                }

                if (in_array($header, ['Cantidad', 'Precio Unit.', 'Subtotal'], true) && is_numeric($value)) {
                    $sheet->setCellValueExplicit($col . $rowNum, (float) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                } else {
                    // Mantener Nivel como texto para no perder ceros/puntos
                    $sheet->setCellValueExplicit($col . $rowNum, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }

            $sheet->getStyle('A' . $rowNum . ':' . $lastCol . $rowNum)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFD0D0D0'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => false,
                ],
            ]);

            if ($detalleColIndex !== false) {
                $detalleCol = Coordinate::stringFromColumnIndex($detalleColIndex + 1);
                $sheet->getStyle($detalleCol . $rowNum)->getAlignment()->setWrapText(true);
            }

            $sheet->getRowDimension($rowNum)->setRowHeight(max(18, 18 + (($detalleLines - 1) * 12)));

            $rowNum++;
        }

        $endDataRow = max($startDataRow, $rowNum - 1);

        // Alineaciones por columna
        foreach ($headers as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $range = $col . $startDataRow . ':' . $col . $endDataRow;

            if (in_array($header, ['Cantidad', 'Precio Unit.', 'Subtotal'], true)) {
                $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
            } else {
                $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_JUSTIFY);
                if ($header !== 'Detalle') {
                    $sheet->getStyle($range)->getAlignment()->setShrinkToFit(true);
                }
            }
        }

        foreach ($headers as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($col)->setWidth((float) ($columnWidths[$header] ?? 14));
        }

        // Fila header y pane fijo
        $sheet->freezePane('A5');

        // Area de impresion
        $sheet->getPageSetup()->setPrintArea('A1:' . $lastCol . $endDataRow);
        $sheet->getPageMargins()
            ->setTop(0.15)
            ->setBottom(0.15)
            ->setLeft(0.1)
            ->setRight(0.1);

        return $spreadsheet;
    }

    private function estimateDetailLines(string $value, float $detailWidth): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($text === '') {
            return 1;
        }

        $charsPerLine = max(24, (int) floor($detailWidth * 1.40));
        return max(1, (int) ceil(mb_strlen($text, 'UTF-8') / $charsPerLine));
    }

    /**
     * @param array<int, string> $headers
     */
    private function resolvePdfOrientation(array $headers): string
    {
        // Hasta 6 columnas en vertical; 7+ en horizontal
        return count($headers) > 6
            ? PageSetup::ORIENTATION_LANDSCAPE
            : PageSetup::ORIENTATION_PORTRAIT;
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, float>
     */
    private function calculateColumnWidths(array $headers, array $rows, bool $forPdf, string $pdfOrientation): array
    {
        $min = [
            'Nivel' => 10.0,
            'Codigo' => 12.0,
            'Detalle' => 24.0,
            'Tipo' => 7.0,
            'Cantidad' => 8.0,
            'Unidad' => 6.0,
            'Precio Unit.' => 10.0,
            'Subtotal' => 10.0,
        ];
        $max = [
            'Nivel' => 20.0,
            'Codigo' => 22.0,
            'Detalle' => 80.0,
            'Tipo' => 12.0,
            'Cantidad' => 14.0,
            'Unidad' => 10.0,
            'Precio Unit.' => 18.0,
            'Subtotal' => 18.0,
        ];

        $headerDisplayMap = [
            'Precio Unit.' => "Precio\nUnit.",
            'Subtotal' => "Sub\ntotal",
        ];

        $widths = [];
        foreach ($headers as $colIndex => $header) {
            $displayHeader = $headerDisplayMap[$header] ?? $header;
            $headerLen = 0;
            foreach (preg_split('/\n/', (string) $displayHeader) ?: [$displayHeader] as $part) {
                $headerLen = max($headerLen, mb_strlen(trim((string) $part), 'UTF-8'));
            }

            $maxLen = $headerLen;
            foreach ($rows as $row) {
                if (!is_array($row) || count($row) === 1) {
                    continue;
                }
                $cell = isset($row[$colIndex]) ? (string) $row[$colIndex] : '';
                $len = mb_strlen(trim(preg_replace('/\s+/u', ' ', $cell) ?? ''), 'UTF-8');
                $maxLen = max($maxLen, $len);
            }

            // Conversión aproximada caracteres -> ancho de columna Excel
            $autoWidth = round(($maxLen * 1.05) + 2, 1);
            $widths[$header] = max($min[$header] ?? 8.0, min($max[$header] ?? 20.0, $autoWidth));
        }

        if (!$forPdf) {
            // En XLSX también aplicamos lógica: detalle ocupa el remanente de hoja visible.
            $targetTotal = 125.0;
            $fixedSum = 0.0;
            foreach ($headers as $header) {
                if ($header === 'Detalle') {
                    continue;
                }
                $fixedSum += $widths[$header] ?? 10.0;
            }
            $widths['Detalle'] = max($min['Detalle'], min($max['Detalle'], $targetTotal - $fixedSum));
            return $widths;
        }

        // En PDF apuntamos a un ancho total mayor para que FitToWidth aproveche toda la hoja.
        $targetTotal = $pdfOrientation === PageSetup::ORIENTATION_PORTRAIT ? 108.0 : 150.0;

        $fixedSum = 0.0;
        foreach ($headers as $header) {
            if ($header === 'Detalle') {
                continue;
            }
            $fixedSum += $widths[$header] ?? 10.0;
        }

        $detailWidth = max($min['Detalle'], $targetTotal - $fixedSum);
        $widths['Detalle'] = $detailWidth;

        return $widths;
    }

    private function writeSpreadsheetToString(Spreadsheet $spreadsheet, string $writerType): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mrp_export_');
        if ($tmp === false) {
            $spreadsheet->disconnectWorksheets();
            throw new \RuntimeException('No se pudo crear archivo temporal de exportacion.');
        }

        try {
            $writer = IOFactory::createWriter($spreadsheet, $writerType);
            $writer->save($tmp);
            $content = file_get_contents($tmp);
        } finally {
            @unlink($tmp);
            $spreadsheet->disconnectWorksheets();
        }

        if ($content === false || $content === '') {
            throw new \RuntimeException('No se pudo leer el archivo exportado.');
        }

        return (string) $content;
    }
}
