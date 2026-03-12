<?php

declare(strict_types=1);

namespace App\Services\Planeamiento;

use App\Models\Bom;
use PDO;

/**
 * Servicio de Sugerencias MRP.
 *
 * Para cada variante que tiene una BOM activa determina si se puede fabricar
 * como mínimo 1 unidad completa (100 %) comparando el stock_actual de cada
 * componente con la cantidad_necesaria declarada en la BOM.
 *
 * Resultados posibles por variante:
 *  - fabricable : min(stock / qty_requerida) >= 1  → puede fabricarse al 100 %
 *  - parcial    : 0 < ratio < 1                   → stock insuficiente en algún componente
 *  - sin_stock  : ratio == 0 en 1 o más componentes
 */
final class SugerenciasService
{
    private PDO $db;

    public function __construct()
    {
        $bom = new Bom();
        $this->db = $bom->getConnection();
    }

    /**
     * Devuelve el análisis completo de fabricabilidad.
     *
     * @param string|null $filtro  'fabricable'|'parcial'|'sin_stock'|null (todos)
     * @return array{
     *   resumen: array{fabricables: int, parciales: int, sin_stock: int, total: int},
     *   variantes: list<array{
     *     bom_id: int,
     *     variante_id: int,
     *     parte_codigo: string,
     *     parte_detalle: string,
     *     variante_codigo: string,
     *     variante_detalle: string,
     *     status: string,
     *     cobertura_pct: float,
     *     max_unidades: float,
     *     componentes: list<array{...}>
     *   }>
     * }
     */
    public function analizar(?string $filtro = null): array
    {
        $filas = $this->fetchRows();
        $agrupado = $this->agrupar($filas);
        $resumen = ['fabricables' => 0, 'parciales' => 0, 'sin_stock' => 0, 'total' => 0];

        foreach ($agrupado as &$variante) {
            $resumen[$this->keyResumen($variante['status'])]++;
            $resumen['total']++;
        }
        unset($variante);

        if ($filtro !== null) {
            $agrupado = array_values(array_filter(
                $agrupado,
                fn(array $v) => $v['status'] === $filtro
            ));
        }

        return ['resumen' => $resumen, 'variantes' => $agrupado];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function fetchRows(): array
    {
        $sql = "
            SELECT
                bc.id                                      AS bom_id,
                CAST(bc.variante_padre_id AS INTEGER)      AS variante_id,
                pp.codigo                                  AS parte_codigo,
                pp.detalle                                 AS parte_detalle,
                vp.codigo_variante                        AS variante_codigo,
                vp.detalle                                AS variante_detalle,
                CAST(d.variante_componente_id AS INTEGER)  AS comp_variante_id,
                pc.codigo                                  AS comp_parte_codigo,
                vc.codigo_variante                        AS comp_codigo_variante,
                vc.detalle                                AS comp_detalle,
                d.cantidad_necesaria,
                d.secuencia,
                um.simbolo                                AS unidad_simbolo,
                COALESCE(CAST(vc.stock_actual AS NUMERIC), 0) AS stock_disponible
            FROM bom_cabecera bc
            JOIN variantes  vp ON CAST(bc.variante_padre_id AS INTEGER) = vp.id
            JOIN partes      pp ON vp.id_parte = pp.id
            JOIN bom_detalle  d  ON d.bom_id = bc.id
            JOIN variantes  vc ON CAST(d.variante_componente_id AS INTEGER) = vc.id
            JOIN partes      pc ON vc.id_parte = pc.id
            JOIN unidades_medida um ON d.unidad_medida_id = um.id
            WHERE bc.activa = TRUE
            ORDER BY pp.codigo, vp.codigo_variante, d.secuencia
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function agrupar(array $filas): array
    {
        $byBom = [];

        foreach ($filas as $fila) {
            $bomId = (int)$fila['bom_id'];

            if (!isset($byBom[$bomId])) {
                $byBom[$bomId] = [
                    'bom_id'           => $bomId,
                    'variante_id'      => (int)$fila['variante_id'],
                    'parte_codigo'     => $fila['parte_codigo'],
                    'parte_detalle'    => $fila['parte_detalle'],
                    'variante_codigo'  => $fila['variante_codigo'],
                    'variante_detalle' => $fila['variante_detalle'],
                    'componentes'      => [],
                    'status'           => 'fabricable',
                    'cobertura_pct'    => 100.0,
                    'max_unidades'     => PHP_FLOAT_MAX,
                ];
            }

            $qty       = (float)$fila['cantidad_necesaria'];
            $stock     = (float)$fila['stock_disponible'];
            $ratio     = $qty > 0 ? $stock / $qty : 0.0;
            $maxUnid   = $qty > 0 ? floor($stock / $qty) : 0.0;

            $byBom[$bomId]['componentes'][] = [
                'comp_variante_id'   => (int)$fila['comp_variante_id'],
                'comp_parte_codigo'  => $fila['comp_parte_codigo'],
                'comp_codigo'        => $fila['comp_codigo_variante'],
                'comp_detalle'       => $fila['comp_detalle'],
                'cantidad_necesaria' => $qty,
                'stock_disponible'   => $stock,
                'unidad_simbolo'     => $fila['unidad_simbolo'],
                'ratio'              => $ratio,
                'cobertura_pct'      => min(round($ratio * 100, 1), 100.0),
                'secuencia'          => (int)$fila['secuencia'],
            ];

            // El stock máximo fabricable lo limita el componente con menor ratio
            $byBom[$bomId]['max_unidades'] = min($byBom[$bomId]['max_unidades'], $maxUnid);
        }

        // Calcular status y cobertura global de la BOM
        foreach ($byBom as &$bom) {
            if ($bom['max_unidades'] === PHP_FLOAT_MAX) {
                $bom['max_unidades'] = 0.0;
            }

            $minRatio = PHP_FLOAT_MAX;
            foreach ($bom['componentes'] as $comp) {
                $minRatio = min($minRatio, $comp['ratio']);
            }
            if ($minRatio === PHP_FLOAT_MAX) {
                $minRatio = 0.0;
            }

            $bom['cobertura_pct'] = min(round($minRatio * 100, 1), 100.0);

            if ($minRatio >= 1.0) {
                $bom['status'] = 'fabricable';
            } elseif ($minRatio > 0.0) {
                $bom['status'] = 'parcial';
            } else {
                $bom['status'] = 'sin_stock';
            }
        }
        unset($bom);

        return array_values($byBom);
    }

    private function keyResumen(string $status): string
    {
        return match ($status) {
            'fabricable' => 'fabricables',
            'parcial'    => 'parciales',
            default      => 'sin_stock',
        };
    }
}
