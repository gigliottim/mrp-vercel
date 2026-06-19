<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Modelo para Órdenes de Producción
 *
 * Representa una orden de fabricación que ejecuta la producción de una variante
 * siguiendo una ruta específica de operaciones.
 */
final class OrdenProduccion extends BaseTenantModel
{
    // Estados válidos de la orden
    public const ESTADO_BORRADOR = 'borrador';
    public const ESTADO_PLANIFICADA = 'planificada';
    public const ESTADO_LIBERADA = 'liberada';
    public const ESTADO_EN_PROCESO = 'en_proceso';
    public const ESTADO_PAUSADA = 'pausada';
    public const ESTADO_COMPLETADA = 'completada';
    public const ESTADO_CANCELADA = 'cancelada';
    public const ESTADO_CERRADA = 'cerrada';

    // Prioridades válidas
    public const PRIORIDAD_BAJA = 'baja';
    public const PRIORIDAD_NORMAL = 'normal';
    public const PRIORIDAD_ALTA = 'alta';
    public const PRIORIDAD_URGENTE = 'urgente';

    protected function getTable(): string
    {
        return 'ordenes_produccion';
    }

    /**
     * Obtener orden con todas sus relaciones
     */
    public function getWithRelations(int $id): ?array
    {
        $stmt = $this->connection->prepare('
            SELECT
                op.*,
                v.codigo_variante,
                v.detalle as variante_nombre,
                v.id_parte,
                p.codigo as parte_codigo,
                p.detalle as parte_detalle,
                b.id as bom_id_ref,
                b.version as bom_version,
                u.username as usuario_nombre
            FROM ordenes_produccion op
            LEFT JOIN variantes v ON v.id = op.variante_id
            LEFT JOIN partes p ON p.id = v.id_parte
            LEFT JOIN bom_cabecera b ON b.id = op.bom_id_utilizada
            LEFT JOIN usuarios u ON u.id = op.usuario_creador
            WHERE op.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Buscar órdenes por diversos criterios
     */
    public function search(
        string $term = '',
        ?string $estado = null,
        ?string $prioridad = null,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null
    ): array {
        $sql = '
            SELECT
                op.id,
                op.numero_orden,
                op.variante_id,
                op.cantidad_planificada,
                op.cantidad_producida,
                op.fecha_inicio_programada,
                op.fecha_fin_programada,
                op.estado,
                op.prioridad,
                v.codigo_variante,
                v.detalle as variante_nombre,
                p.codigo as parte_codigo,
                p.detalle as producto_nombre,
                COALESCE(v.codigo_variante, \'\') as variante_codigo
            FROM ordenes_produccion op
            LEFT JOIN variantes v ON v.id = op.variante_id
            LEFT JOIN partes p ON p.id = v.id_parte
            WHERE 1=1
        ';

        $params = [];

        if ($term !== '') {
            $sql .= ' AND (op.numero_orden ILIKE :term
                      OR v.codigo_variante ILIKE :term
                      OR v.detalle ILIKE :term
                      OR p.codigo ILIKE :term)';
            $params['term'] = '%' . $term . '%';
        }

        if ($estado !== null) {
            $sql .= ' AND op.estado = :estado';
            $params['estado'] = $estado;
        }

        if ($prioridad !== null) {
            $sql .= ' AND op.prioridad = :prioridad';
            $params['prioridad'] = $prioridad;
        }

        if ($fechaDesde !== null) {
            $sql .= ' AND op.fecha_inicio_programada >= :fecha_desde';
            $params['fecha_desde'] = $fechaDesde;
        }

        if ($fechaHasta !== null) {
            $sql .= ' AND op.fecha_fin_programada <= :fecha_hasta';
            $params['fecha_hasta'] = $fechaHasta;
        }

        $sql .= ' ORDER BY
            CASE op.prioridad
                WHEN \'urgente\' THEN 1
                WHEN \'alta\' THEN 2
                WHEN \'normal\' THEN 3
                WHEN \'baja\' THEN 4
            END,
            op.fecha_inicio_programada DESC
            LIMIT 100';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener órdenes por estado
     */
    public function getByEstado(string $estado, int $limit = 50): array
    {
        $stmt = $this->connection->prepare('
            SELECT
                op.*,
                v.codigo_variante,
                v.detalle as variante_nombre,
                p.codigo as parte_codigo,
                p.detalle as producto_nombre,
                COALESCE(v.codigo_variante, \'\') as variante_codigo
            FROM ordenes_produccion op
            LEFT JOIN variantes v ON v.id = op.variante_id
            LEFT JOIN partes p ON p.id = v.id_parte
            WHERE op.estado = :estado
            ORDER BY op.fecha_inicio_programada DESC
            LIMIT :limit
        ');
        $stmt->execute([
            'estado' => $estado,
            'limit' => $limit
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcular porcentaje de avance
     */
    public function calcularAvance(int $id): array
    {
        $orden = $this->find($id);
        if (!$orden) {
            return ['error' => 'Orden no encontrada'];
        }

        $cantidadPlanificada = (float)$orden['cantidad_planificada'];
        $cantidadProducida = (float)$orden['cantidad_producida'];
        $cantidadDesechada = (float)($orden['cantidad_desechada'] ?? 0);

        $porcentajeProducido = $cantidadPlanificada > 0
            ? round(($cantidadProducida / $cantidadPlanificada) * 100, 2)
            : 0;

        $porcentajeDesecho = $cantidadProducida > 0
            ? round(($cantidadDesechada / $cantidadProducida) * 100, 2)
            : 0;

        return [
            'cantidad_planificada' => $cantidadPlanificada,
            'cantidad_producida' => $cantidadProducida,
            'cantidad_desechada' => $cantidadDesechada,
            'cantidad_pendiente' => $cantidadPlanificada - $cantidadProducida,
            'porcentaje_producido' => $porcentajeProducido,
            'porcentaje_desecho' => $porcentajeDesecho,
            'esta_completa' => $cantidadProducida >= $cantidadPlanificada
        ];
    }

    /**
     * Cambiar estado de la orden (con validación)
     */
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        // Validar transiciones permitidas
        $orden = $this->find($id);
        if (!$orden) {
            return false;
        }

        $estadoActual = $orden['estado'];

        // Definir transiciones válidas
        $transicionesValidas = [
            self::ESTADO_BORRADOR => [self::ESTADO_PLANIFICADA, self::ESTADO_CANCELADA],
            self::ESTADO_PLANIFICADA => [self::ESTADO_LIBERADA, self::ESTADO_CANCELADA],
            self::ESTADO_LIBERADA => [self::ESTADO_EN_PROCESO, self::ESTADO_CANCELADA],
            self::ESTADO_EN_PROCESO => [self::ESTADO_PAUSADA, self::ESTADO_COMPLETADA, self::ESTADO_CANCELADA],
            self::ESTADO_PAUSADA => [self::ESTADO_EN_PROCESO, self::ESTADO_CANCELADA],
            self::ESTADO_COMPLETADA => [self::ESTADO_CERRADA],
            self::ESTADO_CANCELADA => [],
            self::ESTADO_CERRADA => []
        ];

        if (
            !isset($transicionesValidas[$estadoActual]) ||
            !in_array($nuevoEstado, $transicionesValidas[$estadoActual])
        ) {
            return false;
        }

        // Actualizar estado y fechas según corresponda
        $updates = ['estado = :estado'];
        $params = [
            'id' => $id,
            'estado' => $nuevoEstado
        ];

        if ($nuevoEstado === self::ESTADO_EN_PROCESO && $estadoActual === self::ESTADO_LIBERADA) {
            $updates[] = 'fecha_inicio_real = CURRENT_TIMESTAMP';
        }

        if ($nuevoEstado === self::ESTADO_COMPLETADA) {
            $updates[] = 'fecha_fin_real = CURRENT_TIMESTAMP';
        }

        $sql = "UPDATE ordenes_produccion SET " . implode(', ', $updates) .
            " WHERE id = :id";

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Generar número de orden automático
     */
    public function generarNumeroOrden(): string
    {
        $year = date('Y');

        $stmt = $this->connection->prepare("
            SELECT numero_orden
            FROM ordenes_produccion
            WHERE numero_orden LIKE :pattern
            ORDER BY numero_orden DESC
            LIMIT 1
        ");
        $stmt->execute(['pattern' => "OP-$year-%"]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($last) {
            // Extraer número y incrementar
            $parts = explode('-', $last['numero_orden']);
            $numero = isset($parts[2]) ? (int)$parts[2] + 1 : 1;
        } else {
            $numero = 1;
        }

        return sprintf('OP-%s-%06d', $year, $numero);
    }

    /**
     * Validar que el número de orden sea único
     */
    public function existeNumeroOrden(string $numeroOrden, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) as total FROM ordenes_produccion WHERE numero_orden = :numero';
        $params = ['numero' => $numeroOrden];

        if ($excludeId) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($result['total']) && (int)$result['total'] > 0;
    }
}
