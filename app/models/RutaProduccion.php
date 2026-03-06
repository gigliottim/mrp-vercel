<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Modelo para Rutas de Producción
 *
 * Representa la secuencia de operaciones necesarias para fabricar un producto.
 * Cada ruta está asociada a un BOM y contiene una o más operaciones.
 */
final class RutaProduccion extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'rutas_produccion';
    }

    /**
     * Obtener todas las operaciones de una ruta ordenadas por secuencia
     */
    public function getOperacionesByBomId(int $bomId): array
    {
        $stmt = $this->connection->prepare('
            SELECT
                rp.id,
                rp.bom_id,
                rp.secuencia,
                rp.centro_trabajo_id,
                rp.descripcion,
                rp.tiempo_setup_mins,
                rp.tiempo_proceso_unitario_mins,
                rp.tiempo_cola_mins,
                rp.tiempo_movimiento_mins,
                rp.capacidad_requerida,
                rp.costo_operacion_fijo,
                rp.costo_operacion_variable,
                rp.instrucciones,
                rp.created_at,
                ct.codigo as centro_codigo,
                ct.nombre as centro_nombre,
                ct.activo as centro_activo
            FROM rutas_produccion rp
            INNER JOIN centros_trabajo ct ON ct.id = rp.centro_trabajo_id
            WHERE rp.bom_id = :bom_id
            ORDER BY rp.secuencia
        ');
        $stmt->execute(['bom_id' => $bomId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener una operación específica con sus relaciones
     */
    public function getOperacionWithRelations(int $id): ?array
    {
        $stmt = $this->connection->prepare('
            SELECT
                rp.*,
                ct.codigo as centro_codigo,
                ct.nombre as centro_nombre,
                ct.costo_hora as centro_costo_hora,
                ct.activo as centro_activo,
                b.id as bom_id_ref,
                b.version as bom_version,
                v.codigo_variante
            FROM rutas_produccion rp
            INNER JOIN centros_trabajo ct ON ct.id = rp.centro_trabajo_id
            INNER JOIN bom_cabecera b ON b.id = rp.bom_id
            INNER JOIN variantes v ON v.id = CAST(b.variante_padre_id AS INTEGER)
            WHERE rp.id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Calcular tiempo total de fabricación para una cantidad
     */
    public function calcularTiempoTotal(int $bomId, float $cantidad): array
    {
        $operaciones = $this->getOperacionesByBomId($bomId);

        $tiempoSetupTotal = 0;
        $tiempoProcesoTotal = 0;
        $tiempoColaTotal = 0;
        $tiempoMovimientoTotal = 0;

        foreach ($operaciones as $op) {
            $tiempoSetupTotal += (int)$op['tiempo_setup_mins'];
            $tiempoProcesoTotal += (float)$op['tiempo_proceso_unitario_mins'] * $cantidad;
            $tiempoColaTotal += (int)$op['tiempo_cola_mins'];
            $tiempoMovimientoTotal += (int)$op['tiempo_movimiento_mins'];
        }

        $tiempoTotal = $tiempoSetupTotal + $tiempoProcesoTotal +
            $tiempoColaTotal + $tiempoMovimientoTotal;

        return [
            'tiempo_setup_total_mins' => $tiempoSetupTotal,
            'tiempo_proceso_total_mins' => round($tiempoProcesoTotal, 2),
            'tiempo_cola_total_mins' => $tiempoColaTotal,
            'tiempo_movimiento_total_mins' => $tiempoMovimientoTotal,
            'tiempo_total_mins' => round($tiempoTotal, 2),
            'tiempo_total_horas' => round($tiempoTotal / 60, 2),
            'cantidad_operaciones' => count($operaciones)
        ];
    }

    /**
     * Calcular costo total de operaciones para una cantidad
     */
    public function calcularCostoTotal(int $bomId, float $cantidad): array
    {
        $operaciones = $this->getOperacionesByBomId($bomId);

        $costoFijoTotal = 0;
        $costoVariableTotal = 0;

        foreach ($operaciones as $op) {
            $costoFijoTotal += (float)$op['costo_operacion_fijo'];
            $costoVariableTotal += (float)$op['costo_operacion_variable'] * $cantidad;
        }

        $costoTotal = $costoFijoTotal + $costoVariableTotal;

        return [
            'costo_fijo_total' => round($costoFijoTotal, 2),
            'costo_variable_total' => round($costoVariableTotal, 2),
            'costo_total' => round($costoTotal, 2),
            'costo_unitario' => $cantidad > 0 ? round($costoTotal / $cantidad, 2) : 0
        ];
    }

    /**
     * Verificar si existe una ruta para un BOM
     */
    public function existeRutaParaBom(int $bomId): bool
    {
        $stmt = $this->connection->prepare('
            SELECT COUNT(*) as total
            FROM rutas_produccion
            WHERE bom_id = :bom_id
        ');
        $stmt->execute(['bom_id' => $bomId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($result['total']) && (int)$result['total'] > 0;
    }

    /**
     * Validar que las secuencias sean únicas y múltiplos de 10
     */
    public function validarSecuencias(int $bomId, array $secuencias, ?int $excludeId = null): array
    {
        $errores = [];

        // Validar múltiplos de 10
        foreach ($secuencias as $sec) {
            if ($sec % 10 !== 0) {
                $errores[] = "La secuencia $sec debe ser múltiplo de 10";
            }
        }

        // Validar unicidad en BD
        $placeholders = implode(',', array_fill(0, count($secuencias), '?'));
        $sql = "SELECT secuencia FROM rutas_produccion
                WHERE bom_id = ? AND secuencia IN ($placeholders)";

        if ($excludeId) {
            $sql .= " AND id != ?";
        }

        $stmt = $this->connection->prepare($sql);
        $params = array_merge([$bomId], $secuencias);
        if ($excludeId) {
            $params[] = $excludeId;
        }

        $stmt->execute($params);
        $existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($existentes)) {
            $errores[] = 'Las secuencias ' . implode(', ', $existentes) . ' ya existen para este BOM';
        }

        return $errores;
    }

    /**
     * Obtener la siguiente secuencia disponible para un BOM
     */
    public function getNextSecuencia(int $bomId): int
    {
        $stmt = $this->connection->prepare('
            SELECT COALESCE(MAX(secuencia), 0) + 10 as next_secuencia
            FROM rutas_produccion
            WHERE bom_id = :bom_id
        ');
        $stmt->execute(['bom_id' => $bomId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['next_secuencia'] ?? 10);
    }

    /**
     * Buscar rutas por diversos criterios
     */
    public function search(string $term = '', ?int $bomId = null, ?int $centroId = null): array
    {
        $sql = '
            SELECT DISTINCT
                rp.id,
                rp.bom_id,
                rp.secuencia,
                rp.descripcion,
                ct.codigo as centro_codigo,
                ct.nombre as centro_nombre,
                b.id as bom_id_ref,
                b.version as bom_version,
                v.codigo_variante,
                v.detalle as variante_nombre
            FROM rutas_produccion rp
            INNER JOIN centros_trabajo ct ON ct.id = rp.centro_trabajo_id
            INNER JOIN bom_cabecera b ON b.id = rp.bom_id
            INNER JOIN variantes v ON v.id = CAST(b.variante_padre_id AS INTEGER)
            WHERE 1=1
        ';

        $params = [];

        if ($term !== '') {
            $sql .= ' AND (rp.descripcion ILIKE :term
                      OR ct.nombre ILIKE :term
                      OR v.codigo_variante ILIKE :term)';
            $params['term'] = '%' . $term . '%';
        }

        if ($bomId !== null) {
            $sql .= ' AND rp.bom_id = :bom_id';
            $params['bom_id'] = $bomId;
        }

        if ($centroId !== null) {
            $sql .= ' AND rp.centro_trabajo_id = :centro_id';
            $params['centro_id'] = $centroId;
        }

        $sql .= ' ORDER BY v.codigo_variante, rp.secuencia LIMIT 100';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
