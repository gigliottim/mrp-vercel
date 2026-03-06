<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Compra extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'compras';
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        $sql = '
            SELECT
                c.*,
                ent.razon_social as proveedor_nombre,
                m.fecha,
                m.cantidad,
                m.id_variante,
                v.codigo_variante,
                v.detalle AS variante_detalle,
                p.codigo AS parte_codigo,
                p.detalle AS parte_detalle
            FROM compras c
            JOIN movimientos_stock m ON c.id_movimiento_stock = m.id
            LEFT JOIN entidades ent ON c.id_entidad = ent.id
            JOIN variantes v ON m.id_variante = v.id
            JOIN partes p ON v.id_parte = p.id
            ORDER BY m.fecha DESC, c.id DESC
            LIMIT :limit OFFSET :offset
        ';
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        // En el nuevo esquema, la transacción lógica la maneja el controlador:
        // 1. StockService crea movimiento
        // 2. Compra se crea linkeando al movimiento
        // Aquí solo insertamos en tabla compras.

        $id = parent::create($data);

        // Actualizar el costo actual de la variante si es necesario
        // Pero necesitamos id_variante que ahora esta en el movimiento.
        // Se asume que el controlador pasa id_variante para el update de costo o lo hacemos via join.
        // Por simplicidad, el update de costo lo haremos en el controlador o aquí recuperando el movimiento.

        return $id;
    }

    // Helper to get cost at a specific date
    public function getCostoAtDate(int $varianteId, string $date): float
    {
        $sql = "
            SELECT c.precio_unitario
            FROM compras c
            JOIN movimientos_stock m ON c.id_movimiento_stock = m.id
            WHERE m.id_variante = :id_variante AND m.fecha <= :fecha
            ORDER BY m.fecha DESC, c.id DESC
            LIMIT 1
        ";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id_variante' => $varianteId, 'fecha' => $date]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (float)$result : 0.0;
    }
}
