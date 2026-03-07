<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Variante extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'variantes';
    }

    public function byParteId(int $parteId): array
    {
        $stmt = $this->connection->prepare('SELECT * FROM variantes WHERE id_parte = :parte ORDER BY codigo_variante');
        $stmt->execute(['parte' => $parteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByParteId(int $parteId): int
    {
        $stmt = $this->connection->prepare('SELECT COUNT(*) FROM variantes WHERE id_parte = :parte');
        $stmt->execute(['parte' => $parteId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<int,int> $ids
     */
    public function byParteIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->connection->prepare("SELECT * FROM variantes WHERE id_parte IN ($placeholders) ORDER BY id_parte, codigo_variante");
        $stmt->execute($ids);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($records as $record) {
            $grouped[$record['id_parte']][] = $record;
        }

        return $grouped;
    }

    /**
     * @param array<int,int> $ids
     */
    public function byParteIdsWithSearch(array $ids, string $search = ''): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT v.*, p.codigo AS parte_codigo, p.detalle AS parte_detalle
                FROM variantes v
                LEFT JOIN partes p ON p.id = v.id_parte
                WHERE v.id_parte IN ($placeholders)";

        $params = $ids;
        if ($search !== '') {
            $sql .= " AND (p.codigo ILIKE ? OR p.detalle ILIKE ? OR v.codigo_variante ILIKE ? OR v.detalle ILIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $sql .= " ORDER BY v.id_parte, v.codigo_variante";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($records as $record) {
            $grouped[$record['id_parte']][] = $record;
        }

        return $grouped;
    }

    /**
     * Obtiene todas las variantes con información de sus partes, tipos y unidades
     */
    public function allWithPartes(): array
    {
        $sql = '
            SELECT
                v.*,
                v.id_parte AS parte_id,
                p.codigo AS parte_codigo,
                p.detalle AS parte_detalle,
                COALESCE(p.factor_conversion, 1) as factor_conversion,
                tp.codigo AS tipo_codigo,
                um_compra.simbolo AS um_compra,
                um_uso.simbolo AS um_uso
            FROM variantes v
            INNER JOIN partes p ON v.id_parte = p.id
            LEFT JOIN tipos_partes tp ON p.id_tipo = tp.id
            LEFT JOIN unidades_medida um_compra ON p.id_um_compra = um_compra.id
            LEFT JOIN unidades_medida um_uso ON p.id_um_uso = um_uso.id
            ORDER BY p.codigo, v.codigo_variante
        ';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los detalles completos de una variante con relaciones
     */
    public function getFullDetails(int $id): ?array
    {
        $sql = "
            SELECT
                v.id,
                v.id_parte AS parte_id,
                v.codigo_variante,
                v.detalle,
                v.stock_actual,
                v.costo,
                v.lote_minimo,
                p.codigo AS parte_codigo,
                p.id_um_uso,
                p.id_um_compra,
                COALESCE(p.factor_conversion, 1) as factor_conversion,
                tp.codigo AS tipo_codigo,
                um.simbolo AS unidad,
                um_compra.simbolo AS um_compra_simbolo,
                um_uso.simbolo AS um_uso_simbolo
            FROM variantes v
            INNER JOIN partes p ON v.id_parte = p.id
            LEFT JOIN tipos_partes tp ON p.id_tipo = tp.id
            LEFT JOIN unidades_medida um ON p.id_um_uso = um.id
            LEFT JOIN unidades_medida um_compra ON p.id_um_compra = um_compra.id
            LEFT JOIN unidades_medida um_uso ON p.id_um_uso = um_uso.id
            WHERE v.id = :id
        ";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtiene análisis de stock crítico con filtro opcional
     */
    public function getStockCritico(?string $filtroEstado = null): array
    {
        $sql = "
            SELECT
                v.id,
                v.codigo_variante,
                v.detalle,
                v.stock_actual,
                v.punto_pedido,
                v.stock_seguridad,
                v.lote_minimo,
                v.estado AS estado_variante,
                p.codigo AS codigo_parte,
                p.detalle AS detalle_parte,
                tp.nombre AS tipo_nombre,
                gp.nombre AS grupo_nombre,
                gp.color AS grupo_color,
                um.simbolo AS unidad_medida,
                CASE
                    WHEN v.stock_actual < v.punto_pedido THEN 'critico'
                    WHEN v.stock_actual < (v.punto_pedido * 1.5) THEN 'advertencia'
                    ELSE 'normal'
                END AS estado_stock,
                (v.punto_pedido - v.stock_actual) AS faltante
            FROM variantes v
            INNER JOIN partes p ON p.id = v.id_parte
            LEFT JOIN tipos_partes tp ON tp.id = p.id_tipo
            LEFT JOIN grupos_partes gp ON gp.id = p.id_grupo
            LEFT JOIN unidades_medida um ON um.id = p.id_um_uso
            WHERE v.estado = 'activa'
        ";

        // Aplicar filtro de estado si no es null ni 'todos'
        if ($filtroEstado === 'critico') {
            $sql .= " AND v.stock_actual < v.punto_pedido";
        } elseif ($filtroEstado === 'advertencia') {
            $sql .= " AND v.stock_actual >= v.punto_pedido AND v.stock_actual < (v.punto_pedido * 1.5)";
        } elseif ($filtroEstado === 'normal') {
            $sql .= " AND v.stock_actual >= (v.punto_pedido * 1.5)";
        }

        $sql .= " ORDER BY
            CASE
                WHEN v.stock_actual < v.punto_pedido THEN 1
                WHEN v.stock_actual < (v.punto_pedido * 1.5) THEN 2
                ELSE 3
            END,
            v.stock_actual ASC,
            p.codigo ASC";

        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene estadísticas de stock crítico
     */
    public function getStockCriticoStats(): array
    {
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN stock_actual < punto_pedido THEN 1 ELSE 0 END) as critico,
                SUM(CASE WHEN stock_actual >= punto_pedido AND stock_actual < (punto_pedido * 1.5) THEN 1 ELSE 0 END) as advertencia,
                SUM(CASE WHEN stock_actual >= (punto_pedido * 1.5) THEN 1 ELSE 0 END) as normal
            FROM variantes
            WHERE estado = 'activa'
        ";

        $stmt = $this->connection->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int) ($result['total'] ?? 0),
            'critico' => (int) ($result['critico'] ?? 0),
            'advertencia' => (int) ($result['advertencia'] ?? 0),
            'normal' => (int) ($result['normal'] ?? 0),
        ];
    }
}
