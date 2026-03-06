<?php

declare(strict_types=1);

namespace App\Repositories\Produccion;

use App\Models\OrdenProduccion;
use PDO;

/**
 * Repository para Órdenes de Producción
 *
 * Maneja todas las operaciones de acceso a datos para órdenes de producción.
 */
final class OrdenProduccionRepository
{
    private OrdenProduccion $model;
    private PDO $connection;

    public function __construct(OrdenProduccion $model)
    {
        $this->model = $model;
        $this->connection = $model->getConnection();
    }

    /**
     * Obtener orden por ID con relaciones
     */
    public function findById(int $id): ?array
    {
        return $this->model->getWithRelations($id);
    }

    /**
     * Buscar órdenes
     */
    public function search(
        string $term = '',
        ?string $estado = null,
        ?string $prioridad = null,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null
    ): array {
        return $this->model->search($term, $estado, $prioridad, $fechaDesde, $fechaHasta);
    }

    /**
     * Obtener órdenes por estado
     */
    public function getByEstado(string $estado, int $limit = 50): array
    {
        return $this->model->getByEstado($estado, $limit);
    }

    /**
     * Crear nueva orden de producción
     */
    public function create(array $data): ?int
    {
        // Generar número de orden si no se proporciona
        if (empty($data['numero_orden'])) {
            $data['numero_orden'] = $this->model->generarNumeroOrden();
        }

        $sql = '
            INSERT INTO ordenes_produccion (
                numero_orden, variante_id, bom_id_utilizada,
                cantidad_planificada, fecha_inicio_programada,
                fecha_fin_programada, estado, prioridad,
                configuracion_orden, observaciones, usuario_creador
            ) VALUES (
                :numero_orden, :variante_id, :bom_id_utilizada,
                :cantidad_planificada, :fecha_inicio_programada,
                :fecha_fin_programada, :estado, :prioridad,
                :configuracion_orden, :observaciones, :usuario_creador
            ) RETURNING id
        ';

        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute([
            'numero_orden' => $data['numero_orden'],
            'variante_id' => $data['variante_id'],
            'bom_id_utilizada' => $data['bom_id_utilizada'] ?? null,
            'cantidad_planificada' => $data['cantidad_planificada'],
            'fecha_inicio_programada' => $data['fecha_inicio_programada'],
            'fecha_fin_programada' => $data['fecha_fin_programada'],
            'estado' => $data['estado'] ?? OrdenProduccion::ESTADO_BORRADOR,
            'prioridad' => $data['prioridad'] ?? OrdenProduccion::PRIORIDAD_NORMAL,
            'configuracion_orden' => json_encode($data['configuracion_orden'] ?? []),
            'observaciones' => $data['observaciones'] ?? null,
            'usuario_creador' => $data['usuario_creador'] ?? null
        ]);

        if ($result) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id'] : null;
        }

        return null;
    }

    /**
     * Actualizar orden
     */
    public function update(int $id, array $data): bool
    {
        $sql = '
            UPDATE ordenes_produccion SET
                numero_orden = :numero_orden,
                variante_id = :variante_id,
                bom_id_utilizada = :bom_id_utilizada,
                cantidad_planificada = :cantidad_planificada,
                cantidad_producida = :cantidad_producida,
                cantidad_desechada = :cantidad_desechada,
                fecha_inicio_programada = :fecha_inicio_programada,
                fecha_fin_programada = :fecha_fin_programada,
                prioridad = :prioridad,
                configuracion_orden = :configuracion_orden,
                observaciones = :observaciones,
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id = :id
        ';

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'numero_orden' => $data['numero_orden'],
            'variante_id' => $data['variante_id'],
            'bom_id_utilizada' => $data['bom_id_utilizada'] ?? null,
            'cantidad_planificada' => $data['cantidad_planificada'],
            'cantidad_producida' => $data['cantidad_producida'] ?? 0,
            'cantidad_desechada' => $data['cantidad_desechada'] ?? 0,
            'fecha_inicio_programada' => $data['fecha_inicio_programada'],
            'fecha_fin_programada' => $data['fecha_fin_programada'],
            'prioridad' => $data['prioridad'] ?? OrdenProduccion::PRIORIDAD_NORMAL,
            'configuracion_orden' => json_encode($data['configuracion_orden'] ?? []),
            'observaciones' => $data['observaciones'] ?? null
        ]);
    }

    /**
     * Cambiar estado de la orden
     */
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        return $this->model->cambiarEstado($id, $nuevoEstado);
    }

    /**
     * Actualizar cantidad producida
     */
    public function actualizarCantidadProducida(int $id, float $cantidad, float $cantidadDesechada = 0): bool
    {
        $stmt = $this->connection->prepare('
            UPDATE ordenes_produccion
            SET cantidad_producida = :cantidad,
                cantidad_desechada = :cantidad_desechada,
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id = :id
        ');

        return $stmt->execute([
            'id' => $id,
            'cantidad' => $cantidad,
            'cantidad_desechada' => $cantidadDesechada
        ]);
    }

    /**
     * Eliminar orden (solo borradores)
     */
    public function delete(int $id): bool
    {
        $orden = $this->findById($id);

        if (!$orden || $orden['estado'] !== OrdenProduccion::ESTADO_BORRADOR) {
            return false;
        }

        // Eliminar planificaciones asociadas
        $stmt = $this->connection->prepare('DELETE FROM planificacion_recursos WHERE orden_produccion_id = :id');
        $stmt->execute(['id' => $id]);

        // Eliminar orden
        $stmt = $this->connection->prepare('DELETE FROM ordenes_produccion WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Obtener órdenes pendientes de planificar
     */
    public function getPendientesPlanificar(int $limit = 20): array
    {
        $stmt = $this->connection->prepare('
            SELECT
                op.*,
                v.codigo_variante,
                v.detalle as variante_nombre,
                p.codigo as parte_codigo,
                COUNT(pr.id) as operaciones_planificadas,
                COUNT(rp.id) as total_operaciones
            FROM ordenes_produccion op
            INNER JOIN variantes v ON v.id = op.variante_id
            INNER JOIN partes p ON p.id = v.id_parte
            LEFT JOIN rutas_produccion rp ON rp.bom_id = op.bom_id_utilizada
            LEFT JOIN planificacion_recursos pr ON pr.orden_produccion_id = op.id
            WHERE op.estado IN (:estado1, :estado2)
            GROUP BY op.id, v.codigo_variante, v.detalle, p.codigo
            HAVING COUNT(pr.id) < COUNT(rp.id) OR COUNT(rp.id) = 0
            ORDER BY
                CASE op.prioridad
                    WHEN :urgente THEN 1
                    WHEN :alta THEN 2
                    WHEN :normal THEN 3
                    WHEN :baja THEN 4
                END,
                op.fecha_inicio_programada
            LIMIT :limit
        ');

        $stmt->execute([
            'estado1' => OrdenProduccion::ESTADO_PLANIFICADA,
            'estado2' => OrdenProduccion::ESTADO_LIBERADA,
            'urgente' => OrdenProduccion::PRIORIDAD_URGENTE,
            'alta' => OrdenProduccion::PRIORIDAD_ALTA,
            'normal' => OrdenProduccion::PRIORIDAD_NORMAL,
            'baja' => OrdenProduccion::PRIORIDAD_BAJA,
            'limit' => $limit
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener órdenes en proceso
     */
    public function getEnProceso(): array
    {
        return $this->getByEstado(OrdenProduccion::ESTADO_EN_PROCESO);
    }

    /**
     * Obtener órdenes atrasadas
     */
    public function getAtrasadas(): array
    {
        $stmt = $this->connection->prepare('
            SELECT
                op.*,
                v.codigo_variante,
                v.detalle as variante_nombre,
                p.codigo as parte_codigo,
                (CURRENT_DATE - op.fecha_fin_programada) as dias_atraso
            FROM ordenes_produccion op
            INNER JOIN variantes v ON v.id = op.variante_id
            INNER JOIN partes p ON p.id = v.id_parte
            WHERE op.estado IN (:estado1, :estado2, :estado3)
              AND op.fecha_fin_programada < CURRENT_DATE
            ORDER BY dias_atraso DESC
        ');

        $stmt->execute([
            'estado1' => OrdenProduccion::ESTADO_LIBERADA,
            'estado2' => OrdenProduccion::ESTADO_EN_PROCESO,
            'estado3' => OrdenProduccion::ESTADO_PAUSADA
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener dashboard de órdenes
     */
    public function getDashboard(): array
    {
        $stmt = $this->connection->prepare('
            SELECT
                estado,
                COUNT(*) as cantidad,
                SUM(cantidad_planificada - cantidad_producida) as cantidad_pendiente
            FROM ordenes_produccion
            WHERE estado NOT IN (:cerrada, :cancelada)
            GROUP BY estado
        ');

        $stmt->execute([
            'cerrada' => OrdenProduccion::ESTADO_CERRADA,
            'cancelada' => OrdenProduccion::ESTADO_CANCELADA
        ]);

        $estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener órdenes atrasadas
        $atrasadas = count($this->getAtrasadas());

        // Obtener órdenes urgentes
        $stmt = $this->connection->prepare('
            SELECT COUNT(*) as total
            FROM ordenes_produccion
            WHERE prioridad = :urgente
              AND estado IN (:estado1, :estado2, :estado3)
        ');

        $stmt->execute([
            'urgente' => OrdenProduccion::PRIORIDAD_URGENTE,
            'estado1' => OrdenProduccion::ESTADO_LIBERADA,
            'estado2' => OrdenProduccion::ESTADO_EN_PROCESO,
            'estado3' => OrdenProduccion::ESTADO_PAUSADA
        ]);

        $urgentes = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'por_estado' => $estadisticas,
            'atrasadas' => $atrasadas,
            'urgentes' => (int)($urgentes['total'] ?? 0)
        ];
    }
}
