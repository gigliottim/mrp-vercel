<?php

declare(strict_types=1);

namespace App\Services\Partes;

use RuntimeException;

final class PartesVariantesImportCsvParser
{
    private const REQUIRED_HEADERS = [
        'parte_codigo',
        'parte_detalle',
        'tipo_codigo',
        'grupo_codigo',
        'variante_codigo',
        'variante_detalle',
    ];

    public function parseFile(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        $lineNo = 0;
        $header = [];
        $rows = [];
        $delimiter = ';';

        while (($line = fgets($handle)) !== false) {
            $lineNo++;
            if (trim($line) === '') {
                continue;
            }

            if ($header === []) {
                $delimiter = $this->detectDelimiter($line);
            }
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            $parsed = str_getcsv($line, $delimiter);
            if ($parsed === null) {
                continue;
            }

            $parsed = array_map(static fn($value) => trim((string) $value), $parsed);
            if ($parsed === [] || ($parsed[0] ?? '') === '') {
                continue;
            }

            if (str_starts_with((string) ($parsed[0] ?? ''), '#')) {
                continue;
            }

            if ($header === []) {
                $header = array_map(fn($item) => strtolower(trim((string) $item)), $parsed);
                $this->assertRequiredHeaders($header);
                continue;
            }

            $row = [];
            foreach ($header as $index => $column) {
                $row[$column] = $parsed[$index] ?? '';
            }

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rows[] = [
                'line' => $lineNo,
                'data' => $row,
            ];
        }

        fclose($handle);

        if ($header === []) {
            throw new RuntimeException('El archivo no contiene cabecera valida.');
        }

        return [
            'header' => $header,
            'rows' => $rows,
        ];
    }

    private function assertRequiredHeaders(array $header): void
    {
        $missing = [];
        foreach (self::REQUIRED_HEADERS as $required) {
            if (!in_array($required, $header, true)) {
                $missing[] = $required;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Faltan columnas obligatorias: ' . implode(', ', $missing));
        }
    }

    private function detectDelimiter(string $line): string
    {
        $commaCount = substr_count($line, ',');
        $semicolonCount = substr_count($line, ';');
        return $semicolonCount >= $commaCount ? ';' : ',';
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
