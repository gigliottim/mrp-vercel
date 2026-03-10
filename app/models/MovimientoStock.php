<?php

declare(strict_types=1);

namespace App\Models;

final class MovimientoStock extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'movimientos_stock';
    }

    public function getRecentWithDetails(int $limit = 20): array
    {
        $sql = "
            SELECT
                m.*,
                v.codigo_variante,
                v.detalle as variante_detalle,
                td_origen.codigo as origen_codigo,
                td_origen.nombre as origen_nombre,
                td_destino.codigo as destino_codigo,
                td_destino.nombre as destino_nombre,
                p.codigo as parte_codigo,
                p.detalle as parte_detalle,
                p.id_um_compra,
                p.id_um_uso,
                COALESCE(p.factor_conversion, 1) as factor_conversion,
                um_uso.simbolo as um_uso_simbolo,
                c.id as compra_id,
                c.id_entidad,
                c.nro_comprobante,
                c.precio_unitario as compra_precio_unitario,
                c.observaciones as compra_observaciones
            FROM movimientos_stock m
            JOIN variantes v ON m.id_variante = v.id
            JOIN partes p ON v.id_parte = p.id
            JOIN tipos_depositos td_origen ON m.id_tipo_deposito_origen = td_origen.id
            JOIN tipos_depositos td_destino ON m.id_tipo_deposito_destino = td_destino.id
            LEFT JOIN unidades_medida um_uso ON um_uso.id = p.id_um_uso
            LEFT JOIN compras c ON c.id_movimiento_stock = m.id
            ORDER BY m.fecha DESC, m.id DESC
            LIMIT :limit
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
