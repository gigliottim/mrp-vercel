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
        return trim($parteDetalle . ' + ' . $varianteDetalle, ' +');
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|float|int|null>> $rows
     */
    public function generateXlsx(array $headers, array $rows): string
    {
        $spreadsheet = $this->buildSpreadsheet($headers, $rows, 'Listado de Ingenieria');
        return $this->writeSpreadsheetToString($spreadsheet, 'Xlsx');
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|float|int|null>> $rows
     */
    public function generatePdf(array $headers, array $rows, string $titulo): string
    {
        $spreadsheet = $this->buildSpreadsheet($headers, $rows, $titulo);

        // Renderizar PDF con Dompdf (PhpSpreadsheet Writer\Pdf\Dompdf)
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        return $this->writeSpreadsheetToString($spreadsheet, 'Dompdf');
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|float|int|null>> $rows
     */
    private function buildSpreadsheet(array $headers, array $rows, string $title): Spreadsheet
    {
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

        // Header (fila 3)
        $headerRow = 3;
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

            $detalleTieneDosLineas = false;
            foreach ($headers as $i => $header) {
                $colIdx = $i + 1;
                $col = Coordinate::stringFromColumnIndex($colIdx);
                $value = $row[$i] ?? '';

                if ($detalleColIndex !== false && $i === $detalleColIndex) {
                    $detalleFormateado = $this->formatDetalleForCell((string) $value);
                    $detalleTieneDosLineas = str_contains($detalleFormateado, "\n");
                    $sheet->setCellValueExplicit($col . $rowNum, $detalleFormateado, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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

            $sheet->getRowDimension($rowNum)->setRowHeight($detalleTieneDosLineas ? 30 : 18);

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

        // Anchos de columna
        $widthMap = [
            'Nivel' => 14,
            'Codigo' => 16,
            'Detalle' => 44,
            'Tipo' => 9,
            'Cantidad' => 11,
            'Unidad' => 8,
            'Precio Unit.' => 12,
            'Subtotal' => 12,
        ];

        foreach ($headers as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($col)->setWidth($widthMap[$header] ?? 14);
        }

        // Fila header y pane fijo
        $sheet->freezePane('A4');

        // Area de impresion
        $sheet->getPageSetup()->setPrintArea('A1:' . $lastCol . $endDataRow);
        $sheet->getPageMargins()
            ->setTop(0.3)
            ->setBottom(0.3)
            ->setLeft(0.2)
            ->setRight(0.2);

        return $spreadsheet;
    }

    private function formatDetalleForCell(string $value): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text, 'UTF-8') <= 58) {
            return $text;
        }

        $slice = mb_substr($text, 0, 116, 'UTF-8');
        $breakPos = mb_strrpos($slice, ' ', 0, 'UTF-8');
        if ($breakPos === false || $breakPos < 30) {
            $breakPos = 58;
        }

        $line1 = trim(mb_substr($slice, 0, $breakPos, 'UTF-8'));
        $line2 = trim(mb_substr($slice, $breakPos, null, 'UTF-8'));

        if (mb_strlen($text, 'UTF-8') > mb_strlen($slice, 'UTF-8')) {
            $line2 = rtrim(mb_substr($line2, 0, 55, 'UTF-8')) . '...';
        }

        return $line1 . "\n" . $line2;
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
