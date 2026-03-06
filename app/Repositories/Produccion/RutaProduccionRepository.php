<?php

declare(strict_types=1);

namespace App\Repositories\Produccion;

use App\Models\RutaProduccion;
use PDO;

/**
 * Repository para Rutas de Producción
 *
 * Maneja operaciones CRUD y consultas complejas para rutas y operaciones.
 */
final class RutaProduccionRepository
{
    private RutaProduccion $model;
    private PDO $connection;

    public function __construct(RutaProduccion $model)
    {
        $this->model = $model;
        $this->connection = $model->getConnection();
    }

    /**
     * Obtener operación por ID
     */
    public function findById(int $id): ?array
    {
        return $this->model->getOperacionWithRelations($id);
    }

    /**
     * Obtener todas las operaciones de un BOM
     */
    public function getByBomId(int $bomId): array
    {
        return $this->model->getOperacionesByBomId($bomId);
    }

    /**
     * Buscar rutas/operaciones
     */
    public function search(string $term = '', ?int $bomId = null, ?int $centroId = null): array
    {
        return $this->model->search($term, $bomId, $centroId);
    }

    /**
     * Crear nueva operación en ruta
     */
    public function create(array $data): ?int
    {
        $sql = '
            INSERT INTO rutas_produccion (
                bom_id, secuencia, centro_trabajo_id, descripcion,
                tiempo_setup_mins, tiempo_proceso_unitario_mins,
                tiempo_cola_mins, tiempo_movimiento_mins,
                capacidad_requerida, costo_operacion_fijo,
                costo_operacion_variable, instrucciones
            ) VALUES (
                :bom_id, :secuencia, :centro_trabajo_id, :descripcion,
                :tiempo_setup_mins, :tiempo_proceso_unitario_mins,
                :tiempo_cola_mins, :tiempo_movimiento_mins,
                :capacidad_requerida, :costo_operacion_fijo,
                :costo_operacion_variable, :instrucciones
            ) RETURNING id
        ';

        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute([
            'bom_id' => $data['bom_id'],
            'secuencia' => $data['secuencia'],
            'centro_trabajo_id' => $data['centro_trabajo_id'],
            'descripcion' => $data['descripcion'],
            'tiempo_setup_mins' => $data['tiempo_setup_mins'] ?? 0,
            'tiempo_proceso_unitario_mins' => $data['tiempo_proceso_unitario_mins'] ?? 0,
            'tiempo_cola_mins' => $data['tiempo_cola_mins'] ?? 0,
            'tiempo_movimiento_mins' => $data['tiempo_movimiento_mins'] ?? 0,
            'capacidad_requerida' => $data['capacidad_requerida'] ?? 1.0,
            'costo_operacion_fijo' => $data['costo_operacion_fijo'] ?? 0.00,
            'costo_operacion_variable' => $data['costo_operacion_variable'] ?? 0.00,
            'instrucciones' => $data['instrucciones'] ?? null
        ]);

        if ($result) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id'] : null;
        }

        return null;
    }

    /**
     * Actualizar operación
     */
    public function update(int $id, array $data): bool
    {
        $sql = '
            UPDATE rutas_produccion SET
                bom_id = :bom_id,
                secuencia = :secuencia,
                centro_trabajo_id = :centro_trabajo_id,
                descripcion = :descripcion,
                tiempo_setup_mins = :tiempo_setup_mins,
                tiempo_proceso_unitario_mins = :tiempo_proceso_unitario_mins,
                tiempo_cola_mins = :tiempo_cola_mins,
                tiempo_movimiento_mins = :tiempo_movimiento_mins,
                capacidad_requerida = :capacidad_requerida,
                costo_operacion_fijo = :costo_operacion_fijo,
                costo_operacion_variable = :costo_operacion_variable,
                instrucciones = :instrucciones
            WHERE id = :id
        ';

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'bom_id' => $data['bom_id'],
            'secuencia' => $data['secuencia'],
            'centro_trabajo_id' => $data['centro_trabajo_id'],
            'descripcion' => $data['descripcion'],
            'tiempo_setup_mins' => $data['tiempo_setup_mins'] ?? 0,
            'tiempo_proceso_unitario_mins' => $data['tiempo_proceso_unitario_mins'] ?? 0,
            'tiempo_cola_mins' => $data['tiempo_cola_mins'] ?? 0,
            'tiempo_movimiento_mins' => $data['tiempo_movimiento_mins'] ?? 0,
            'capacidad_requerida' => $data['capacidad_requerida'] ?? 1.0,
            'costo_operacion_fijo' => $data['costo_operacion_fijo'] ?? 0.00,
            'costo_operacion_variable' => $data['costo_operacion_variable'] ?? 0.00,
            'instrucciones' => $data['instrucciones'] ?? null
        ]);
    }

    /**
     * Eliminar operación
     */
    public function delete(int $id): bool
    {
        // Verificar si tiene planificaciones asociadas
        if ($this->tienePlanificaciones($id)) {
            return false;
        }

        $stmt = $this->connection->prepare('DELETE FROM rutas_produccion WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Eliminar todas las operaciones de un BOM
     */
    public function deleteByBomId(int $bomId): bool
    {
        $stmt = $this->connection->prepare('DELETE FROM rutas_produccion WHERE bom_id = :bom_id');
        return $stmt->execute(['bom_id' => $bomId]);
    }

    /**
     * Verificar si la operación tiene planificaciones asociadas
     */
    private function tienePlanificaciones(int $operacionId): bool
    {
        $stmt = $this->connection->prepare('
            SELECT COUNT(*) as total
            FROM planificacion_recursos
            WHERE operacion_id = :operacion_id
        ');
        $stmt->execute(['operacion_id' => $operacionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && (int)$result['total'] > 0;
    }

    /**
     * Reordenar secuencias de operaciones
     */
    public function reordenar(int $bomId, array $nuevasSecuencias): bool
    {
        // $nuevasSecuencias = [id => nueva_secuencia]

        try {
            $this->connection->beginTransaction();

            $stmt = $this->connection->prepare('
                UPDATE rutas_produccion
                SET secuencia = :secuencia
                WHERE id = :id AND bom_id = :bom_id
            ');

            foreach ($nuevasSecuencias as $operacionId => $secuencia) {
                $stmt->execute([
                    'id' => $operacionId,
                    'secuencia' => $secuencia,
                    'bom_id' => $bomId
                ]);
            }

            $this->connection->commit();
            return true;
        } catch (\Exception $e) {
            $this->connection->rollBack();
            return false;
        }
    }

    /**
     * Clonar ruta de un BOM a otro
     */
    public function clonarRuta(int $bomOrigenId, int $bomDestinoId): bool
    {
        try {
            $this->connection->beginTransaction();

            // Obtener operaciones del BOM origen
            $operaciones = $this->getByBomId($bomOrigenId);

            if (empty($operaciones)) {
                $this->connection->rollBack();
                return false;
            }

            // Insertar cada operación en el BOM destino
            foreach ($operaciones as $op) {
                $this->create([
                    'bom_id' => $bomDestinoId,
                    'secuencia' => $op['secuencia'],
                    'centro_trabajo_id' => $op['centro_trabajo_id'],
                    'descripcion' => $op['descripcion'],
                    'tiempo_setup_mins' => $op['tiempo_setup_mins'],
                    'tiempo_proceso_unitario_mins' => $op['tiempo_proceso_unitario_mins'],
                    'tiempo_cola_mins' => $op['tiempo_cola_mins'],
                    'tiempo_movimiento_mins' => $op['tiempo_movimiento_mins'],
                    'capacidad_requerida' => $op['capacidad_requerida'],
                    'costo_operacion_fijo' => $op['costo_operacion_fijo'],
                    'costo_operacion_variable' => $op['costo_operacion_variable'],
                    'instrucciones' => $op['instrucciones']
                ]);
            }

            $this->connection->commit();
            return true;
        } catch (\Exception $e) {
            $this->connection->rollBack();
            return false;
        }
    }

    /**
     * Obtener resumen de ruta (tiempos y costos)
     */
    public function getResumenRuta(int $bomId, float $cantidad = 1): array
    {
        $operaciones = $this->getByBomId($bomId);

        if (empty($operaciones)) {
            return [
                'operaciones' => [],
                'tiempos' => [],
                'costos' => [],
                'total_operaciones' => 0
            ];
        }

        $tiempos = $this->model->calcularTiempoTotal($bomId, $cantidad);
        $costos = $this->model->calcularCostoTotal($bomId, $cantidad);

        return [
            'operaciones' => $operaciones,
            'tiempos' => $tiempos,
            'costos' => $costos,
            'total_operaciones' => count($operaciones)
        ];
    }

    /**
     * Validar ruta completa
     */
    public function validarRuta(int $bomId): array
    {
        $errores = [];
        $operaciones = $this->getByBomId($bomId);

        if (empty($operaciones)) {
            $errores[] = 'La ruta no tiene operaciones definidas';
            return $errores;
        }

        // Validar que todos los centros estén activos
        foreach ($operaciones as $op) {
            if (!$op['centro_activo']) {
                $errores[] = "La operación {$op['secuencia']} usa el centro {$op['centro_codigo']} que está inactivo";
            }
        }

        // Validar secuencias consecutivas
        $secuencias = array_column($operaciones, 'secuencia');
        sort($secuencias);
        for ($i = 1; $i < count($secuencias); $i++) {
            if ($secuencias[$i] - $secuencias[$i - 1] > 50) {
                $errores[] = "Hay un gapsignificativo entre las secuencias {$secuencias[$i - 1]} y {$secuencias[$i]}";
            }
        }

        return $errores;
    }
}
