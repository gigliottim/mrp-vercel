<?php

declare(strict_types=1);

namespace App\Repositories\Produccion;

use App\Models\CentroTrabajo;
use PDO;

/**
 * Repository para Centros de Trabajo
 *
 * Maneja todas las operaciones de acceso a datos para centros de trabajo.
 */
final class CentroTrabajoRepository
{
    private CentroTrabajo $model;
    private PDO $connection;

    public function __construct(CentroTrabajo $model)
    {
        $this->model = $model;
        $this->connection = $model->getConnection();
    }

    /**
     * Obtener todos los centros activos
     */
    public function getAllActivos(): array
    {
        $stmt = $this->connection->prepare('
            SELECT * FROM centros_trabajo
            WHERE activo = true
            ORDER BY codigo
        ');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener centro por ID
     */
    public function findById(int $id): ?array
    {
        return $this->model->find($id);
    }

    /**
     * Buscar centros
     */
    public function search(string $term = ''): array
    {
        return $this->model->search($term);
    }

    /**
     * Crear nuevo centro
     */
    public function create(array $data): ?int
    {
        $sql = '
            INSERT INTO centros_trabajo (
                codigo, nombre, descripcion, tipo, capacidad_horas_dia,
                eficiencia_porcentaje, costo_hora, capacidad_finita,
                calendario_id, activo, ubicacion, responsable, observaciones
            ) VALUES (
                :codigo, :nombre, :descripcion, :tipo, :capacidad_horas_dia,
                :eficiencia_porcentaje, :costo_hora, :capacidad_finita,
                :calendario_id, :activo, :ubicacion, :responsable, :observaciones
            ) RETURNING id
        ';

        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute([
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'tipo' => $data['tipo'] ?? 'manual',
            'capacidad_horas_dia' => $data['capacidad_horas_dia'] ?? 8.00,
            'eficiencia_porcentaje' => $data['eficiencia_porcentaje'] ?? 100.00,
            'costo_hora' => $data['costo_hora'] ?? 0.00,
            'capacidad_finita' => $data['capacidad_finita'] ?? false,
            'calendario_id' => $data['calendario_id'] ?? null,
            'activo' => $data['activo'] ?? true,
            'ubicacion' => $data['ubicacion'] ?? null,
            'responsable' => $data['responsable'] ?? null,
            'observaciones' => $data['observaciones'] ?? null
        ]);

        if ($result) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id'] : null;
        }

        return null;
    }

    /**
     * Actualizar centro
     */
    public function update(int $id, array $data): bool
    {
        $sql = '
            UPDATE centros_trabajo SET
                codigo = :codigo,
                nombre = :nombre,
                descripcion = :descripcion,
                tipo = :tipo,
                capacidad_horas_dia = :capacidad_horas_dia,
                eficiencia_porcentaje = :eficiencia_porcentaje,
                costo_hora = :costo_hora,
                capacidad_finita = :capacidad_finita,
                calendario_id = :calendario_id,
                activo = :activo,
                ubicacion = :ubicacion,
                responsable = :responsable,
                observaciones = :observaciones,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ';

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'tipo' => $data['tipo'] ?? 'manual',
            'capacidad_horas_dia' => $data['capacidad_horas_dia'] ?? 8.00,
            'eficiencia_porcentaje' => $data['eficiencia_porcentaje'] ?? 100.00,
            'costo_hora' => $data['costo_hora'] ?? 0.00,
            'capacidad_finita' => $data['capacidad_finita'] ?? false,
            'calendario_id' => $data['calendario_id'] ?? null,
            'activo' => $data['activo'] ?? true,
            'ubicacion' => $data['ubicacion'] ?? null,
            'responsable' => $data['responsable'] ?? null,
            'observaciones' => $data['observaciones'] ?? null
        ]);
    }

    /**
     * Desactivar centro (soft delete)
     */
    public function desactivar(int $id): bool
    {
        $stmt = $this->connection->prepare('
            UPDATE centros_trabajo
            SET activo = false, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Eliminar centro (solo si no tiene dependencias)
     */
    public function delete(int $id): bool
    {
        // Verificar dependencias
        if ($this->tieneDependencias($id)) {
            return false;
        }

        $stmt = $this->connection->prepare('DELETE FROM centros_trabajo WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verificar si el centro tiene dependencias
     */
    public function tieneDependencias(int $id): bool
    {
        // Verificar en rutas_produccion
        $stmt = $this->connection->prepare('
            SELECT COUNT(*) as total FROM rutas_produccion WHERE centro_trabajo_id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && (int)$result['total'] > 0) {
            return true;
        }

        // Verificar en planificacion_recursos
        $stmt = $this->connection->prepare('
            SELECT COUNT(*) as total FROM planificacion_recursos WHERE centro_trabajo_id = :id
        ');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && (int)$result['total'] > 0;
    }

    /**
     * Verificar si el código ya existe
     */
    public function existeCodigo(string $codigo, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) as total FROM centros_trabajo WHERE codigo = :codigo';
        $params = ['codigo' => $codigo];

        if ($excludeId) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && (int)$result['total'] > 0;
    }

    /**
     * Obtener estadísticas del centro
     */
    public function getEstadisticas(int $id, ?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $centro = $this->findById($id);
        if (!$centro) {
            return ['error' => 'Centro no encontrado'];
        }

        // Si no se proporcionan fechas, usar últimos 30 días
        if (!$fechaInicio || !$fechaFin) {
            $fechaFin = date('Y-m-d');
            $fechaInicio = date('Y-m-d', strtotime('-30 days'));
        }

        // Obtener operaciones asignadas
        $stmt = $this->connection->prepare('
            SELECT
                COUNT(*) as total_operaciones,
                COUNT(DISTINCT pr.orden_produccion_id) as ordenes_distintas,
                SUM(EXTRACT(EPOCH FROM (upper(pr.periodo) - lower(pr.periodo))) / 3600) as horas_asignadas
            FROM planificacion_recursos pr
            WHERE pr.centro_trabajo_id = :centro_id
              AND pr.periodo && tsrange(:fecha_inicio, :fecha_fin)
        ');

        $stmt->execute([
            'centro_id' => $id,
            'fecha_inicio' => $fechaInicio . ' 00:00:00',
            'fecha_fin' => $fechaFin . ' 23:59:59'
        ]);

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calcular capacidad
        $inicio = new \DateTime($fechaInicio);
        $fin = new \DateTime($fechaFin);
        $dias = $inicio->diff($fin)->days;

        $capacidadTotal = $dias * (float)$centro['capacidad_horas_dia'] *
            ((float)$centro['eficiencia_porcentaje'] / 100);

        $horasAsignadas = (float)($stats['horas_asignadas'] ?? 0);
        $porcentajeOcupacion = $capacidadTotal > 0
            ? round(($horasAsignadas / $capacidadTotal) * 100, 2)
            : 0;

        return [
            'centro' => $centro,
            'periodo' => [
                'inicio' => $fechaInicio,
                'fin' => $fechaFin,
                'dias' => $dias
            ],
            'capacidad_total_horas' => round($capacidadTotal, 2),
            'horas_asignadas' => round($horasAsignadas, 2),
            'horas_disponibles' => round($capacidadTotal - $horasAsignadas, 2),
            'porcentaje_ocupacion' => $porcentajeOcupacion,
            'total_operaciones' => (int)($stats['total_operaciones'] ?? 0),
            'ordenes_distintas' => (int)($stats['ordenes_distintas'] ?? 0)
        ];
    }
}
