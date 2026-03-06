<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Modelo para Planificación de Recursos
 *
 * Representa la asignación de recursos (centros de trabajo) a operaciones
 * de órdenes de producción en períodos de tiempo específicos.
 */
final class PlanificacionRecurso extends BaseTenantModel
{
    public const ESTADO_PROGRAMADO = 'programado';
    public const ESTADO_EN_EJECUCION = 'en_ejecucion';
    public const ESTADO_COMPLETADO = 'completado';

    protected function getTable(): string
    {
        return 'planificacion_recursos';
    }

    /**
     * Obtener planificación con todas sus relaciones
     */
    public function getWithRelations(int $id): ?array
    {
        $stmt = $this->connection->prepare('
            SELECT
                pr.*,
                op.numero_orden,
                op.variante_id,
                op.cantidad_planificada,
                op.estado as orden_estado,
                v.codigo_variante,
                ct.codigo as centro_codigo,
                ct.nombre as centro_nombre,
                rp.descripcion as operacion_descripcion,
                rp.secuencia as operacion_secuencia
            FROM planificacion_recursos pr
            INNER JOIN ordenes_produccion op ON op.id = pr.orden_produccion_id
            INNER JOIN variantes v ON v.id = op.variante_id
            INNER JOIN centros_trabajo ct ON ct.id = pr.centro_trabajo_id
            LEFT JOIN rutas_produccion rp ON rp.id = pr.operacion_id
            WHERE pr.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtener planificación por centro de trabajo
     */
    public function getByCentroTrabajo(
        int $centroId,
        ?string $fechaInicio = null,
        ?string $fechaFin = null
    ): array {
        $sql = '
            SELECT
                pr.id,
                pr.orden_produccion_id,
                pr.periodo,
                pr.estado,
                op.numero_orden,
                v.codigo_variante,
                rp.descripcion as operacion_descripcion,
                rp.secuencia
            FROM planificacion_recursos pr
            INNER JOIN ordenes_produccion op ON op.id = pr.orden_produccion_id
            INNER JOIN variantes v ON v.id = op.variante_id
            LEFT JOIN rutas_produccion rp ON rp.id = pr.operacion_id
            WHERE pr.centro_trabajo_id = :centro_id
        ';

        $params = ['centro_id' => $centroId];

        if ($fechaInicio && $fechaFin) {
            $sql .= ' AND pr.periodo && tsrange(:fecha_inicio, :fecha_fin)';
            $params['fecha_inicio'] = $fechaInicio;
            $params['fecha_fin'] = $fechaFin;
        }

        $sql .= ' ORDER BY lower(pr.periodo)';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener planificación por orden de producción
     */
    public function getByOrdenProduccion(int $ordenId): array
    {
        $stmt = $this->connection->prepare('
            SELECT
                pr.*,
                ct.codigo as centro_codigo,
                ct.nombre as centro_nombre,
                rp.descripcion as operacion_descripcion,
                rp.secuencia
            FROM planificacion_recursos pr
            INNER JOIN centros_trabajo ct ON ct.id = pr.centro_trabajo_id
            LEFT JOIN rutas_produccion rp ON rp.id = pr.operacion_id
            WHERE pr.orden_produccion_id = :orden_id
            ORDER BY rp.secuencia NULLS LAST, lower(pr.periodo)
        ');
        $stmt->execute(['orden_id' => $ordenId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar disponibilidad de centro en un período
     */
    public function verificarDisponibilidad(
        int $centroId,
        string $fechaInicio,
        string $fechaFin,
        ?int $excludeId = null
    ): bool {
        $sql = '
            SELECT COUNT(*) as conflictos
            FROM planificacion_recursos pr
            INNER JOIN centros_trabajo ct ON ct.id = pr.centro_trabajo_id
            WHERE pr.centro_trabajo_id = :centro_id
              AND ct.capacidad_finita = true
              AND pr.periodo && tsrange(:fecha_inicio, :fecha_fin)
              AND pr.estado != :estado_completado
        ';

        $params = [
            'centro_id' => $centroId,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'estado_completado' => self::ESTADO_COMPLETADO
        ];

        if ($excludeId) {
            $sql .= ' AND pr.id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return isset($result['conflictos']) && (int)$result['conflictos'] === 0;
    }

    /**
     * Obtener conflictos de capacidad
     */
    public function getConflictos(?int $centroId = null): array
    {
        $sql = '
            SELECT
                ct.id as centro_id,
                ct.codigo as centro_codigo,
                ct.nombre as centro_nombre,
                pr1.id as planificacion1_id,
                pr1.orden_produccion_id as orden1_id,
                op1.numero_orden as orden1_numero,
                pr1.periodo as periodo1,
                pr2.id as planificacion2_id,
                pr2.orden_produccion_id as orden2_id,
                op2.numero_orden as orden2_numero,
                pr2.periodo as periodo2
            FROM planificacion_recursos pr1
            INNER JOIN planificacion_recursos pr2
                ON pr1.centro_trabajo_id = pr2.centro_trabajo_id
                AND pr1.id < pr2.id
                AND pr1.periodo && pr2.periodo
            INNER JOIN centros_trabajo ct ON ct.id = pr1.centro_trabajo_id
            INNER JOIN ordenes_produccion op1 ON op1.id = pr1.orden_produccion_id
            INNER JOIN ordenes_produccion op2 ON op2.id = pr2.orden_produccion_id
            WHERE ct.capacidad_finita = true
              AND pr1.estado != :estado_completado
              AND pr2.estado != :estado_completado
        ';

        $params = ['estado_completado' => self::ESTADO_COMPLETADO];

        if ($centroId) {
            $sql .= ' AND ct.id = :centro_id';
            $params['centro_id'] = $centroId;
        }

        $sql .= ' ORDER BY ct.codigo, lower(pr1.periodo)';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcular carga de trabajo por centro
     */
    public function calcularCargaTrabajo(
        int $centroId,
        string $fechaInicio,
        string $fechaFin
    ): array {
        // Obtener capacidad del centro
        $stmtCentro = $this->connection->prepare('
            SELECT capacidad_horas_dia, eficiencia_porcentaje
            FROM centros_trabajo
            WHERE id = :centro_id
        ');
        $stmtCentro->execute(['centro_id' => $centroId]);
        $centro = $stmtCentro->fetch(PDO::FETCH_ASSOC);

        if (!$centro) {
            return ['error' => 'Centro no encontrado'];
        }

        // Calcular horas asignadas
        $stmt = $this->connection->prepare('
            SELECT
                COUNT(*) as total_asignaciones,
                SUM(
                    EXTRACT(EPOCH FROM (upper(periodo) - lower(periodo))) / 3600
                ) as horas_asignadas
            FROM planificacion_recursos
            WHERE centro_trabajo_id = :centro_id
              AND periodo && tsrange(:fecha_inicio, :fecha_fin)
              AND estado != :estado_completado
        ');

        $stmt->execute([
            'centro_id' => $centroId,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'estado_completado' => self::ESTADO_COMPLETADO
        ]);

        $carga = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calcular capacidad total del período
        $inicio = new \DateTime($fechaInicio);
        $fin = new \DateTime($fechaFin);
        $dias = $inicio->diff($fin)->days;

        $capacidadTotal = $dias * (float)$centro['capacidad_horas_dia'] *
            ((float)$centro['eficiencia_porcentaje'] / 100);

        $horasAsignadas = (float)($carga['horas_asignadas'] ?? 0);
        $horasDisponibles = max(0, $capacidadTotal - $horasAsignadas);
        $porcentajeOcupacion = $capacidadTotal > 0
            ? round(($horasAsignadas / $capacidadTotal) * 100, 2)
            : 0;

        return [
            'centro_id' => $centroId,
            'periodo' => [
                'inicio' => $fechaInicio,
                'fin' => $fechaFin,
                'dias' => $dias
            ],
            'capacidad_total_horas' => round($capacidadTotal, 2),
            'horas_asignadas' => round($horasAsignadas, 2),
            'horas_disponibles' => round($horasDisponibles, 2),
            'porcentaje_ocupacion' => $porcentajeOcupacion,
            'total_asignaciones' => (int)($carga['total_asignaciones'] ?? 0)
        ];
    }

    /**
     * Cambiar estado de planificación
     */
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        $stmt = $this->connection->prepare('
            UPDATE planificacion_recursos
            SET estado = :estado
            WHERE id = :id
        ');
        return $stmt->execute([
            'id' => $id,
            'estado' => $nuevoEstado
        ]);
    }
}
