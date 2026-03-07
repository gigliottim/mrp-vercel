<?php

declare(strict_types=1);

namespace App\Services\Partes;

use App\Models\GrupoParte;
use App\Models\TipoParte;
use App\Models\UnidadMedida;

final class PartesVariantesImportTemplateService
{
    public function __construct(
        private readonly TipoParte $tipos,
        private readonly GrupoParte $grupos,
        private readonly UnidadMedida $unidades
    ) {}

    public function buildTemplateCsv(): string
    {
        $delimiter = ';';
        $lines = [];

        $headers = [
            'parte_codigo',
            'parte_detalle',
            'tipo_codigo',
            'grupo_codigo',
            'activo',
            'um_compra_codigo',
            'um_uso_codigo',
            'factor_conversion',
            'largo_alto',
            'um_largo_alto_codigo',
            'ancho',
            'um_ancho_codigo',
            'espesor_profundidad',
            'um_espesor_codigo',
            'superficie',
            'um_superficie_codigo',
            'volumen',
            'um_volumen_codigo',
            'variante_codigo',
            'variante_detalle',
            'variante_estado',
            'lote_minimo',
            'punto_pedido',
            'peso',
            'um_peso_codigo',
            'ubicacion_cuerpo',
            'ubicacion_pasillo',
            'ubicacion_estante',
        ];

        $lines[] = $this->buildCsvLine($headers, $delimiter);
        $lines[] = $this->buildCsvLine([
            'PRT-0001',
            'Lamina galvanizada 0.8 mm',
            'MATERIA_PRIMA',
            'CHAPA',
            '1',
            'mL',
            'm2',
            '1.20',
            '',
            '',
            '1.20',
            'm',
            '0.80',
            'mm',
            '',
            '',
            '',
            '',
            'BASE',
            'Bobina estandar',
            'activa',
            '1',
            '0',
            '',
            '',
            'A',
            'P01',
            'E1',
        ], $delimiter);
        $lines[] = $this->buildCsvLine(['# Completa cada parte/variante en una fila.'], $delimiter);
        $lines[] = $this->buildCsvLine(['# Codigos UM: usar simbolo (ej: m, mm, mL, m2, kg, u).'], $delimiter);
        $lines[] = '';

        $lines[] = $this->buildCsvLine(['# CATALOGO_UM'], $delimiter);
        $lines[] = $this->buildCsvLine(['um_codigo', 'id_um', 'simbolo', 'unidad', 'tipo'], $delimiter);
        foreach ($this->unidades->allActive(1000, 0) as $unidad) {
            $simbolo = (string) ($unidad['simbolo'] ?? '');
            $lines[] = $this->buildCsvLine([
                $simbolo,
                (string) ($unidad['id'] ?? ''),
                $simbolo,
                (string) ($unidad['unidad'] ?? ''),
                (string) ($unidad['tipo'] ?? ''),
            ], $delimiter);
        }

        $lines[] = '';
        $lines[] = $this->buildCsvLine(['# CATALOGO_TIPOS_PARTES'], $delimiter);
        $lines[] = $this->buildCsvLine(['tipo_codigo', 'tipo_nombre'], $delimiter);
        foreach ($this->tipos->activos() as $tipo) {
            $lines[] = $this->buildCsvLine([
                (string) ($tipo['codigo'] ?? ''),
                (string) ($tipo['nombre'] ?? ''),
            ], $delimiter);
        }

        $lines[] = '';
        $lines[] = $this->buildCsvLine(['# CATALOGO_GRUPOS_PARTES'], $delimiter);
        $lines[] = $this->buildCsvLine(['grupo_codigo', 'grupo_nombre'], $delimiter);
        foreach ($this->grupos->activos() as $grupo) {
            $lines[] = $this->buildCsvLine([
                (string) ($grupo['codigo'] ?? ''),
                (string) ($grupo['nombre'] ?? ''),
            ], $delimiter);
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    private function buildCsvLine(array $values, string $delimiter): string
    {
        $escaped = array_map(static function ($value): string {
            $stringValue = (string) $value;
            $stringValue = str_replace('"', '""', $stringValue);
            return '"' . $stringValue . '"';
        }, $values);

        return implode($delimiter, $escaped);
    }
}
