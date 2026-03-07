<?php

declare(strict_types=1);

namespace App\Services\Partes;

final class PartesVariantesImportRowMapper
{
    private const ALLOWED_VARIANT_STATES = ['activa', 'desarrollo', 'obsoleta', 'descontinuada'];

    public function mapRowToEntities(array $row, array $tipoMap, array $grupoMap, array $unitMap): array
    {
        $errors = [];

        $codigoParte = strtoupper(trim((string) ($row['parte_codigo'] ?? '')));
        $detalleParte = trim((string) ($row['parte_detalle'] ?? ''));
        $tipoCodigo = $this->normalizeLookupKey((string) ($row['tipo_codigo'] ?? ''));
        $grupoCodigo = $this->normalizeLookupKey((string) ($row['grupo_codigo'] ?? ''));

        if ($codigoParte === '') {
            $errors[] = 'parte_codigo es obligatorio.';
        }
        if ($detalleParte === '') {
            $errors[] = 'parte_detalle es obligatorio.';
        }
        if ($tipoCodigo === '' || !isset($tipoMap[$tipoCodigo])) {
            $errors[] = 'tipo_codigo no existe.';
        }
        if ($grupoCodigo === '' || !isset($grupoMap[$grupoCodigo])) {
            $errors[] = 'grupo_codigo no existe.';
        }

        $idUmCompra = $this->resolveUnitId($row['um_compra_codigo'] ?? '', $unitMap, true, $errors, 'um_compra_codigo');
        $idUmUso = $this->resolveUnitId($row['um_uso_codigo'] ?? '', $unitMap, true, $errors, 'um_uso_codigo');

        $factorConversion = $this->parseDecimal($row['factor_conversion'] ?? null);
        if ($idUmCompra !== null && $idUmUso !== null) {
            if ($idUmCompra === $idUmUso) {
                $factorConversion = 1.0;
            } elseif ($factorConversion === null || $factorConversion <= 0) {
                $errors[] = 'factor_conversion es obligatorio cuando um_compra y um_uso son distintas.';
            }
        }

        $partData = [
            'codigo' => $codigoParte,
            'id_tipo' => (int) ($tipoMap[$tipoCodigo] ?? 0),
            'id_grupo' => (int) ($grupoMap[$grupoCodigo] ?? 0),
            'detalle' => $detalleParte,
            'activo' => $this->parseBoolean($row['activo'] ?? '1') ? 1 : 0,
            'id_um_compra' => $idUmCompra,
            'id_um_uso' => $idUmUso,
            'factor_conversion' => $factorConversion,
            'largo_alto' => $this->parseDecimal($row['largo_alto'] ?? null),
            'id_um_largo_alto' => $this->resolveUnitId($row['um_largo_alto_codigo'] ?? '', $unitMap, true, $errors, 'um_largo_alto_codigo'),
            'ancho' => $this->parseDecimal($row['ancho'] ?? null),
            'id_um_ancho' => $this->resolveUnitId($row['um_ancho_codigo'] ?? '', $unitMap, true, $errors, 'um_ancho_codigo'),
            'espesor_profundidad' => $this->parseDecimal($row['espesor_profundidad'] ?? null),
            'id_um_espesor' => $this->resolveUnitId($row['um_espesor_codigo'] ?? '', $unitMap, true, $errors, 'um_espesor_codigo'),
            'superficie' => $this->parseDecimal($row['superficie'] ?? null),
            'id_um_superficie' => $this->resolveUnitId($row['um_superficie_codigo'] ?? '', $unitMap, true, $errors, 'um_superficie_codigo'),
            'volumen' => $this->parseDecimal($row['volumen'] ?? null),
            'id_um_volumen' => $this->resolveUnitId($row['um_volumen_codigo'] ?? '', $unitMap, true, $errors, 'um_volumen_codigo'),
        ];

        $codigoVariante = strtoupper(trim((string) ($row['variante_codigo'] ?? '')));
        $detalleVariante = trim((string) ($row['variante_detalle'] ?? ''));
        $estadoVariante = strtolower(trim((string) ($row['variante_estado'] ?? 'activa')));

        if ($codigoVariante === '') {
            $errors[] = 'variante_codigo es obligatorio.';
        }
        if ($detalleVariante === '') {
            $errors[] = 'variante_detalle es obligatorio.';
        }
        if (!in_array($estadoVariante, self::ALLOWED_VARIANT_STATES, true)) {
            $errors[] = 'variante_estado invalido.';
        }

        $peso = $this->parseDecimal($row['peso'] ?? null);
        $idUmPeso = $this->resolveUnitId($row['um_peso_codigo'] ?? '', $unitMap, true, $errors, 'um_peso_codigo');
        if ($peso !== null && $idUmPeso === null) {
            $errors[] = 'um_peso_codigo es obligatorio cuando se informa peso.';
        }

        $variantData = [
            'id_parte' => 0,
            'codigo_variante' => $codigoVariante,
            'detalle' => $detalleVariante,
            'estado' => $estadoVariante,
            'lote_minimo' => $this->parseDecimal($row['lote_minimo'] ?? null) ?? 1,
            'punto_pedido' => $this->parseDecimal($row['punto_pedido'] ?? null) ?? 0,
            'peso' => $peso,
            'id_um_peso' => $idUmPeso,
            'ubicacion_cuerpo' => trim((string) ($row['ubicacion_cuerpo'] ?? '')),
            'ubicacion_pasillo' => trim((string) ($row['ubicacion_pasillo'] ?? '')),
            'ubicacion_estante' => trim((string) ($row['ubicacion_estante'] ?? '')),
        ];

        return [$partData, $variantData, $errors];
    }

    public function buildReferenceMap(array $records, string $primaryCode, string $idKey, ?string $fallbackCode = null): array
    {
        $map = [];
        foreach ($records as $record) {
            $id = (int) ($record[$idKey] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $code = $this->normalizeLookupKey((string) ($record[$primaryCode] ?? ''));
            if ($code !== '') {
                $map[$code] = $id;
            }

            if ($fallbackCode !== null) {
                $fallback = $this->normalizeLookupKey((string) ($record[$fallbackCode] ?? ''));
                if ($fallback !== '') {
                    $map[$fallback] = $id;
                }
            }
        }

        return $map;
    }

    public function buildUnitMap(array $units): array
    {
        $map = ['code' => [], 'id' => []];

        foreach ($units as $unit) {
            $id = (int) ($unit['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $map['id'][$id] = true;

            $symbol = (string) ($unit['simbolo'] ?? '');
            $key = $this->normalizeUnitLookupKey($symbol);
            if ($key !== '') {
                $map['code'][$key] = $id;
            }

            $unitName = (string) ($unit['unidad'] ?? '');
            $nameKey = $this->normalizeLookupKey($unitName);
            if ($nameKey !== '') {
                $map['code'][$nameKey] = $id;
            }
        }

        return $map;
    }

    private function resolveUnitId(mixed $value, array $unitMap, bool $nullable, array &$errors, string $field): ?int
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $nullable ? null : 0;
        }

        if (ctype_digit($raw)) {
            $id = (int) $raw;
            if (isset($unitMap['id'][$id])) {
                return $id;
            }
        }

        $normalized = $this->normalizeUnitLookupKey($raw);
        if ($normalized === '' || !isset($unitMap['code'][$normalized])) {
            $errors[] = $field . ' no existe (' . $raw . ').';
            return null;
        }

        return (int) $unitMap['code'][$normalized];
    }

    private function normalizeLookupKey(string $value): string
    {
        return strtoupper(trim($value));
    }

    private function normalizeUnitLookupKey(string $value): string
    {
        $normalized = strtoupper(trim($value));
        $normalized = str_replace(["\u{00B2}", "\u{00B3}", ' '], ['2', '3', ''], $normalized);
        return $normalized;
    }

    private function parseDecimal(mixed $value): ?float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $raw) === 1) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',') && !str_contains($raw, '.')) {
            $raw = str_replace(',', '.', $raw);
        }

        if (!is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    private function parseBoolean(mixed $value): bool
    {
        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return true;
        }

        return in_array($raw, ['1', 'true', 'si', 'yes', 'y'], true);
    }
}
