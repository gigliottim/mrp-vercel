<?php

declare(strict_types=1);

namespace App\Repositories\Produccion;

use App\Models\PlanificacionRecurso;
use PDO;

/**
 * Repository para Planificación de Recursos
 *
 * Maneja planificación y asignación de recursos a órdenes de producción.
 */
final class PlanificacionRepository
{
    private PlanificacionRecurso $model;
    private PDO $connection;

    public function __construct(PlanificacionRecurso $model)
    {
        $this->model = $model;
        $this->connection = $model->getConnection();
    }

    /**
     * Obtener planificación por ID
     */
    public function findById(int $id): ?array
    {
        return $this->model->getWithRelations($id);
    }

    /**
     * Obtener planificaciones por centro de trabajo
     */
    public function getByCentroTrabajo(
        int $centroId,
        ?string $fechaInicio = null,
        ?string $fechaFin = null
    ): array {
        return $this->model->getByCentroTrabajo($centroId, $fechaInicio, $fechaFin);
    }

    /**
     * Obtener planificaciones por orden
     */
    public function getByOrdenProduccion(int $ordenId): array
    {
        return $this->model->getByOrdenProduccion($ordenId);
    }

    /**
     * Crear nueva planificación
     */
    public function create(array $data): ?int
    {
        $sql = '
            INSERT INTO planificacion_recursos (
                orden_produccion_id, operacion_id, centro_trabajo_id,
                periodo, estado
            ) VALUES (
                :orden_produccion_id, :operacion_id, :centro_trabajo_id,
                tsrange(:fecha_inicio, :fecha_fin), :estado
            ) RETURNING id
        ';

        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute([
            'orden_produccion_id' => $data['orden_produccion_id'],
            'operacion_id' => $data['operacion_id'] ?? null,
            'centro_trabajo_id' => $data['centro_trabajo_id'],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $data['fecha_fin'],
            'estado' => $data['estado'] ?? PlanificacionRecurso::ESTADO_PROGRAMADO
        ]);

        if ($result) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id'] : null;
        }

        return null;
    }

    /**
     * Actualizar planificación
     */
    public function update(int $id, array $data): bool
    {
        $sql = '
            UPDATE planificacion_recursos SET
                orden_produccion_id = :orden_produccion_id,
                operacion_id = :operacion_id,
                centro_trabajo_id = :centro_trabajo_id,
                periodo = tsrange(:fecha_inicio, :fecha_fin),
                estado = :estado
            WHERE id = :id
        ';

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'orden_produccion_id' => $data['orden_produccion_id'],
            'operacion_id' => $data['operacion_id'] ?? null,
            'centro_trabajo_id' => $data['centro_trabajo_id'],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $data['fecha_fin'],
            'estado' => $data['estado']
        ]);
    }

    /**
     * Eliminar planificación
     */
    public function delete(int $id): bool
    {
        $stmt = $this->connection->prepare('DELETE FROM planificacion_recursos WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Cambiar estado
     */
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        return $this->model->cambiarEstado($id, $nuevoEstado);
    }

    /**
     * Verificar disponibilidad de centro
     */
    public function verificarDisponibilidad(
        int $centroId,
        string $fechaInicio,
        string $fechaFin,
        ?int $excludeId = null
    ): bool {
        return $this->model->verificarDisponibilidad($centroId, $fechaInicio, $fechaFin, $excludeId);
    }

    /**
     * Obtener conflictos de capacidad
     */
    public function getConflictos(?int $centroId = null): array
    {
        return $this->model->getConflictos($centroId);
    }

    /**
     * Calcular carga de trabajo
     */
    public function calcularCargaTrabajo(
        int $centroId,
        string $fechaInicio,
        string $fechaFin
    ): array {
        return $this->model->calcularCargaTrabajo($centroId, $fechaInicio, $fechaFin);
    }

    /**
     * Planificar orden completa automáticamente
     */
    public function planificarOrdenAutomatica(
        int $ordenId,
        string $fechaInicioOrden,
        array $operaciones
    ): array {
        $resultados = [];
        $fechaInicio = new \DateTime($fechaInicioOrden);

        try {
            $this->connection->beginTransaction();

            foreach ($operaciones as $op) {
                // Calcular duración de la operación
                $tiempoSetup = (int)($op['tiempo_setup_mins'] ?? 0);
                $tiempoProceso = (float)($op['tiempo_proceso_unitario_mins'] ?? 0);
                $tiempoCola = (int)($op['tiempo_cola_mins'] ?? 0);
                $tiempoMovimiento = (int)($op['tiempo_movimiento_mins'] ?? 0);

                // Obtener cantidad de la orden
                $stmtOrden = $this->connection->prepare('
                    SELECT cantidad_planificada FROM ordenes_produccion WHERE id = :id
                ');
                $stmtOrden->execute(['id' => $ordenId]);
                $orden = $stmtOrden->fetch(PDO::FETCH_ASSOC);
                $cantidad = (float)($orden['cantidad_planificada'] ?? 1);

                $duracionMinutos = $tiempoSetup + ($tiempoProceso * $cantidad) +
                    $tiempoCola + $tiempoMovimiento;

                $fechaFin = clone $fechaInicio;
                $fechaFin->modify("+{$duracionMinutos} minutes");

                // Crear planificación
                $planificacionId = $this->create([
                    'orden_produccion_id' => $ordenId,
                    'operacion_id' => $op['id'],
                    'centro_trabajo_id' => $op['centro_trabajo_id'],
                    'fecha_inicio' => $fechaInicio->format('Y-m-d H:i:s'),
                    'fecha_fin' => $fechaFin->format('Y-m-d H:i:s'),
                    'estado' => PlanificacionRecurso::ESTADO_PROGRAMADO
                ]);

                $resultados[] = [
                    'operacion_id' => $op['id'],
                    'planificacion_id' => $planificacionId,
                    'fecha_inicio' => $fechaInicio->format('Y-m-d H:i:s'),
                    'fecha_fin' => $fechaFin->format('Y-m-d H:i:s'),
                    'duracion_mins' => $duracionMinutos
                ];

                // La siguiente operación inicia después del tiempo de movimiento
                $fechaInicio = clone $fechaFin;
            }

            $this->connection->commit();
            return ['success' => true, 'planificaciones' => $resultados];
        } catch (\Exception $e) {
            $this->connection->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtener vista Gantt para un rango de fechas
     */
    public function getGanttData(
        ?int $centroId = null,
        ?string $fechaInicio = null,
        ?string $fechaFin = null
    ): array {
        $sql = '
            SELECT
                pr.id,
                pr.periodo,
                pr.estado,
                op.numero_orden,
                op.prioridad,
                v.codigo_variante,
                ct.id as centro_id,
                ct.codigo as centro_codigo,
                ct.nombre as centro_nombre,
                rp.secuencia,
                rp.descripcion as operacion_descripcion
            FROM planificacion_recursos pr
            INNER JOIN ordenes_produccion op ON op.id = pr.orden_produccion_id
            INNER JOIN variantes v ON v.id = op.variante_id
            INNER JOIN centros_trabajo ct ON ct.id = pr.centro_trabajo_id
            LEFT JOIN rutas_produccion rp ON rp.id = pr.operacion_id
            WHERE 1=1
        ';

        $params = [];

        if ($centroId) {
            $sql .= ' AND ct.id = :centro_id';
            $params['centro_id'] = $centroId;
        }

        if ($fechaInicio && $fechaFin) {
            $sql .= ' AND pr.periodo && tsrange(:fecha_inicio, :fecha_fin)';
            $params['fecha_inicio'] = $fechaInicio;
            $params['fecha_fin'] = $fechaFin;
        }

        $sql .= ' ORDER BY ct.codigo, lower(pr.periodo)';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
