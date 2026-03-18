<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class GrupoParte extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'grupos_partes';
    }

    public function activos(): array
    {
        $stmt = $this->connection->query('SELECT * FROM grupos_partes ORDER BY nombre');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todos los grupos con estadísticas de partes asignadas
     */
    public function getResumenConItems(): array
    {
        $sql = "
            SELECT
                g.id,
                g.codigo,
                g.nombre,
                g.descripcion,
                g.color,
                g.activo,
                COUNT(DISTINCT p.id) as total_partes,
                COUNT(DISTINCT v.id) as total_variantes,
                SUM(CASE WHEN v.estado = 'activa' THEN 1 ELSE 0 END) as variantes_activas
            FROM grupos_partes g
            LEFT JOIN partes p ON p.id_grupo = g.id
            LEFT JOIN variantes v ON v.id_parte = p.id
            WHERE g.activo = true
            GROUP BY g.id, g.codigo, g.nombre, g.descripcion, g.color, g.activo
            ORDER BY g.nombre
        ";

        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las partes y variantes asignadas a un grupo específico
     */
    public function getPartesDeGrupo(int $grupoId): array
    {
        $sql = "
            SELECT
                p.id as parte_id,
                p.codigo as parte_codigo,
                p.detalle as parte_detalle,
                tp.nombre as tipo_nombre,
                tp.codigo as tipo_codigo,
                v.id as variante_id,
                v.codigo_variante,
                v.detalle as variante_detalle,
                v.estado as variante_estado,
                v.stock_actual,
                v.punto_pedido,
                v.lote_minimo,
                um.simbolo as unidad_medida,
                CASE
                    WHEN v.stock_actual = 0 THEN 'critico'
                    WHEN v.stock_actual < v.punto_pedido THEN 'critico'
                    WHEN v.stock_actual < (v.punto_pedido * 1.5) THEN 'advertencia'
                    ELSE 'normal'
                END AS estado_stock
            FROM partes p
            LEFT JOIN tipos_partes tp ON tp.id = p.id_tipo
            LEFT JOIN variantes v ON v.id_parte = p.id
            LEFT JOIN unidades_medida um ON um.id = p.id_um_uso
            WHERE p.id_grupo = :grupo_id
            ORDER BY p.codigo, v.codigo_variante
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['grupo_id' => $grupoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todas las partes sin grupo asignado
     */
    public function getPartesSinGrupo(): array
    {
        $sql = "
            SELECT
                p.id as parte_id,
                p.codigo as parte_codigo,
                p.detalle as parte_detalle,
                tp.nombre as tipo_nombre,
                tp.codigo as tipo_codigo,
                v.id as variante_id,
                v.codigo_variante,
                v.detalle as variante_detalle,
                v.estado as variante_estado,
                v.stock_actual,
                v.punto_pedido,
                um.simbolo as unidad_medida
            FROM partes p
            LEFT JOIN tipos_partes tp ON tp.id = p.id_tipo
            LEFT JOIN variantes v ON v.id_parte = p.id
            LEFT JOIN unidades_medida um ON um.id = p.id_um_uso
            WHERE p.id_grupo IS NULL
            ORDER BY p.codigo, v.codigo_variante
        ";

        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
