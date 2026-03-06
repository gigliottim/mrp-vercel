<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Compra;
use App\Models\Variante;
use PDO;

final class DashboardSummaryService
{
    /**
     * @return array<string, int|float>
     */
    public function getSummary(): array
    {
        $summary = [
            'stock_total' => 0,
            'stock_critico' => 0,
            'stock_advertencia' => 0,
            'compras_mes' => 0,
            'gasto_mes' => 0.0,
            'mrp_sugerencias_total' => 0,
            'mrp_sugerencias_pendientes' => 0,
            'mrp_resumen_items' => 0,
        ];

        try {
            $variante = new Variante();
            $stats = $variante->getStockCriticoStats();
            $summary['stock_total'] = (int)($stats['total'] ?? 0);
            $summary['stock_critico'] = (int)($stats['critico'] ?? 0);
            $summary['stock_advertencia'] = (int)($stats['advertencia'] ?? 0);
        } catch (\Throwable $e) {
            // Mantener valores default cuando no se pueda consultar stock.
        }

        try {
            $compra = new Compra();
            $conn = $compra->getConnection();
            $sql = '
                SELECT
                    COUNT(*) FILTER (WHERE DATE_TRUNC(\'month\', m.fecha) = DATE_TRUNC(\'month\', CURRENT_DATE)) as compras_mes,
                    COALESCE(SUM(CASE
                        WHEN DATE_TRUNC(\'month\', m.fecha) = DATE_TRUNC(\'month\', CURRENT_DATE)
                        THEN COALESCE(c.precio_unitario, 0) * COALESCE(m.cantidad, 0)
                        ELSE 0
                    END), 0) as gasto_mes
                FROM compras c
                INNER JOIN movimientos_stock m ON m.id = c.id_movimiento_stock
            ';
            $row = $conn->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
            $summary['compras_mes'] = (int)($row['compras_mes'] ?? 0);
            $summary['gasto_mes'] = (float)($row['gasto_mes'] ?? 0);

            $summary = $this->loadMrpSummary($conn, $summary);
        } catch (\Throwable $e) {
            // Mantener valores default cuando no se pueda consultar compras/MRP.
        }

        return $summary;
    }

    /**
     * @param array<string, int|float> $summary
     * @return array<string, int|float>
     */
    private function loadMrpSummary(PDO $conn, array $summary): array
    {
        if ($this->tableExists($conn, 'mrp_sugerencias')) {
            $summary['mrp_sugerencias_total'] = (int)$conn->query('SELECT COUNT(*) FROM mrp_sugerencias')->fetchColumn();

            if ($this->columnExists($conn, 'mrp_sugerencias', 'estado')) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM mrp_sugerencias WHERE LOWER(COALESCE(estado, '')) IN ('pendiente', 'nueva', 'nuevo', 'abierta')");
                $stmt->execute();
                $summary['mrp_sugerencias_pendientes'] = (int)$stmt->fetchColumn();
            }
        }

        if ($this->tableExists($conn, 'vista_mrp_resumen')) {
            $summary['mrp_resumen_items'] = (int)$conn->query('SELECT COUNT(*) FROM vista_mrp_resumen')->fetchColumn();
        }

        return $summary;
    }

    private function tableExists(PDO $conn, string $table): bool
    {
        $sql = 'SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table)';
        $stmt = $conn->prepare($sql);
        $stmt->execute(['schema' => 'public', 'table' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    private function columnExists(PDO $conn, string $table, string $column): bool
    {
        $sql = 'SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name = :column)';
        $stmt = $conn->prepare($sql);
        $stmt->execute(['schema' => 'public', 'table' => $table, 'column' => $column]);
        return (bool)$stmt->fetchColumn();
    }
}
