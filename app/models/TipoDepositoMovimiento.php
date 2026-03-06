<?php

declare(strict_types=1);

namespace App\Models;

final class TipoDepositoMovimiento extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'tipos_depositos_movimientos';
    }

    /**
     * Obtiene todos los movimientos configurados con información de tipos de depósito
     */
    public function getAll(): array
    {
        $sql = 'SELECT
                    tdm.*,
                    td_origen.codigo as origen_codigo,
                    td_origen.nombre as origen_nombre,
                    td_destino.codigo as destino_codigo,
                    td_destino.nombre as destino_nombre
                FROM tipos_depositos_movimientos tdm
                INNER JOIN tipos_depositos td_origen ON td_origen.id = tdm.tipo_deposito_origen_id
                INNER JOIN tipos_depositos td_destino ON td_destino.id = tdm.tipo_deposito_destino_id
                ORDER BY td_origen.orden, td_origen.nombre, td_destino.orden, td_destino.nombre';

        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los tipos de depósito destino permitidos para un origen específico
     */
    public function getDestinosPermitidos(int $tipoDepositoOrigenId): array
    {
        $sql = 'SELECT
                    td.*
                FROM tipos_depositos_movimientos tdm
                INNER JOIN tipos_depositos td ON td.id = tdm.tipo_deposito_destino_id
                WHERE tdm.tipo_deposito_origen_id = ?
                  AND tdm.activo = TRUE
                  AND td.activo = TRUE
                ORDER BY td.orden, td.nombre';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$tipoDepositoOrigenId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Verifica si un movimiento está permitido entre dos tipos de depósito
     */
    public function isMovimientoPermitido(int $tipoOrigenId, int $tipoDestinoId): bool
    {
        $sql = 'SELECT COUNT(*) as count
                FROM tipos_depositos_movimientos
                WHERE tipo_deposito_origen_id = ?
                  AND tipo_deposito_destino_id = ?
                  AND activo = TRUE';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$tipoOrigenId, $tipoDestinoId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result && (int) $result['count'] > 0;
    }

    /**
     * Obtiene los movimientos configurados para un tipo de depósito origen
     */
    public function getByOrigen(int $tipoDepositoOrigenId): array
    {
        $sql = 'SELECT
                    tdm.*,
                    td.codigo as destino_codigo,
                    td.nombre as destino_nombre
                FROM tipos_depositos_movimientos tdm
                INNER JOIN tipos_depositos td ON td.id = tdm.tipo_deposito_destino_id
                WHERE tdm.tipo_deposito_origen_id = ?
                ORDER BY td.orden, td.nombre';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$tipoDepositoOrigenId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Crea o actualiza un movimiento permitido
     */
    public function createOrUpdate(int $origenId, int $destinoId, bool $activo = true, ?string $observaciones = null): bool
    {
        $sql = 'INSERT INTO tipos_depositos_movimientos
                    (tipo_deposito_origen_id, tipo_deposito_destino_id, activo, observaciones, updated_at)
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (tipo_deposito_origen_id, tipo_deposito_destino_id)
                DO UPDATE SET
                    activo = EXCLUDED.activo,
                    observaciones = EXCLUDED.observaciones,
                    updated_at = CURRENT_TIMESTAMP';

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([$origenId, $destinoId, $activo, $observaciones]);
    }

    /**
     * Elimina un movimiento configurado
     */
    public function deleteMovimiento(int $origenId, int $destinoId): bool
    {
        $sql = 'DELETE FROM tipos_depositos_movimientos
                WHERE tipo_deposito_origen_id = ?
                  AND tipo_deposito_destino_id = ?';

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([$origenId, $destinoId]);
    }

    /**
     * Elimina todos los movimientos de un tipo de depósito origen
     */
    public function deleteByOrigen(int $tipoDepositoOrigenId): bool
    {
        $sql = 'DELETE FROM tipos_depositos_movimientos
                WHERE tipo_deposito_origen_id = ?';

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([$tipoDepositoOrigenId]);
    }

    /**
     * Establece los destinos permitidos para un origen (reemplaza configuración existente)
     */
    public function setDestinosPermitidos(int $tipoOrigenId, array $destinosIds, ?string $observaciones = null): bool
    {
        try {
            $this->connection->beginTransaction();

            // Eliminar configuración existente
            $this->deleteByOrigen($tipoOrigenId);

            // Insertar nuevos destinos
            if (!empty($destinosIds)) {
                $sql = 'INSERT INTO tipos_depositos_movimientos
                        (tipo_deposito_origen_id, tipo_deposito_destino_id, activo, observaciones)
                        VALUES (?, ?, TRUE, ?)';

                $stmt = $this->connection->prepare($sql);

                foreach ($destinosIds as $destinoId) {
                    $stmt->execute([$tipoOrigenId, (int) $destinoId, $observaciones]);
                }
            }

            $this->connection->commit();
            return true;
        } catch (\Exception $e) {
            $this->connection->rollBack();
            return false;
        }
    }
}
