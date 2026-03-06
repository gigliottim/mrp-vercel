<?php

declare(strict_types=1);

namespace App\Services\Produccion;

use App\Repositories\Produccion\PlanificacionRepository;
use App\Repositories\Produccion\OrdenProduccionRepository;
use App\Repositories\Produccion\RutaProduccionRepository;

/**
 * Service para Planificación de Recursos
 *
 * Lógica de negocio para planificación y asignación de recursos.
 */
final class PlanificacionService
{
    private PlanificacionRepository $repository;
    private OrdenProduccionRepository $ordenRepository;
    private RutaProduccionRepository $rutaRepository;

    public function __construct(
        PlanificacionRepository $repository,
        OrdenProduccionRepository $ordenRepository,
        RutaProduccionRepository $rutaRepository
    ) {
        $this->repository = $repository;
        $this->ordenRepository = $ordenRepository;
        $this->rutaRepository = $rutaRepository;
    }

    /**
     * Obtener planificación por ID
     */
    public function getById(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Obtener planificación por centro
     */
    public function getByCentroTrabajo(
        int $centroId,
        ?string $fechaInicio = null,
        ?string $fechaFin = null
    ): array {
        return $this->repository->getByCentroTrabajo($centroId, $fechaInicio, $fechaFin);
    }

    /**
     * Obtener planificación por orden
     */
    public function getByOrdenProduccion(int $ordenId): array
    {
        return $this->repository->getByOrdenProduccion($ordenId);
    }

    /**
     * Crear asignación de planificación
     */
    public function create(array $data): array
    {
        $errores = $this->validar($data);

        if (!empty($errores)) {
            return ['success' => false, 'errors' => $errores];
        }

        $id = $this->repository->create($data);

        if ($id) {
            return ['success' => true, 'id' => $id];
        }

        return ['success' => false, 'errors' => ['Error al crear la planificación']];
    }

    /**
     * Actualizar planificación
     */
    public function update(int $id, array $data): array
    {
        $errores = $this->validar($data, $id);

        if (!empty($errores)) {
            return ['success' => false, 'errors' => $errores];
        }

        $success = $this->repository->update($id, $data);

        if ($success) {
            return ['success' => true];
        }

        return ['success' => false, 'errors' => ['Error al actualizar la planificación']];
    }

    /**
     * Eliminar planificación
     */
    public function delete(int $id): array
    {
        $success = $this->repository->delete($id);

        if ($success) {
            return ['success' => true, 'message' => 'Planificación eliminada'];
        }

        return ['success' => false, 'errors' => ['Error al eliminar la planificación']];
    }

    /**
     * Validar datos de planificación
     */
    private function validar(array $data, ?int $excludeId = null): array
    {
        $errores = [];

        // Orden requerida
        if (empty($data['orden_produccion_id'])) {
            $errores[] = 'La orden de producción es requerida';
        }

        // Centro requerido
        if (empty($data['centro_trabajo_id'])) {
            $errores[] = 'El centro de trabajo es requerido';
        }

        // Fechas requeridas
        if (empty($data['fecha_inicio']) || empty($data['fecha_fin'])) {
            $errores[] = 'Las fechas de inicio y fin son requeridas';
        }

        // Validar que fecha fin sea posterior a inicio
        if (!empty($data['fecha_inicio']) && !empty($data['fecha_fin'])) {
            $inicio = new \DateTime($data['fecha_inicio']);
            $fin = new \DateTime($data['fecha_fin']);
            if ($fin <= $inicio) {
                $errores[] = 'La fecha de fin debe ser posterior a la de inicio';
            }
        }

        // Verificar disponibilidad del centro
        if (
            !empty($data['centro_trabajo_id']) &&
            !empty($data['fecha_inicio']) &&
            !empty($data['fecha_fin'])
        ) {

            $disponible = $this->repository->verificarDisponibilidad(
                (int)$data['centro_trabajo_id'],
                $data['fecha_inicio'],
                $data['fecha_fin'],
                $excludeId
            );

            if (!$disponible) {
                $errores[] = 'El centro no está disponible en el período seleccionado';
            }
        }

        return $errores;
    }

    /**
     * Planificar orden automáticamente
     */
    public function planificarOrdenAutomatica(int $ordenId, ?string $fechaInicio = null): array
    {
        $orden = $this->ordenRepository->findById($ordenId);

        if (!$orden) {
            return ['success' => false, 'errors' => ['Orden no encontrada']];
        }

        if (empty($orden['bom_id_utilizada'])) {
            return ['success' => false, 'errors' => ['La orden no tiene un BOM asignado']];
        }

        $operaciones = $this->rutaRepository->getByBomId((int)$orden['bom_id_utilizada']);

        if (empty($operaciones)) {
            return ['success' => false, 'errors' => ['No hay operaciones definidas en la ruta']];
        }

        // Usar fecha programada de inicio si no se especifica
        $fechaInicio = $fechaInicio ?? $orden['fecha_inicio_programada'];

        $resultado = $this->repository->planificarOrdenAutomatica(
            $ordenId,
            $fechaInicio,
            $operaciones
        );

        return $resultado;
    }

    /**
     * Obtener conflictos de capacidad
     */
    public function getConflictos(?int $centroId = null): array
    {
        return $this->repository->getConflictos($centroId);
    }

    /**
     * Calcular carga de trabajo
     */
    public function calcularCargaTrabajo(
        int $centroId,
        string $fechaInicio,
        string $fechaFin
    ): array {
        return $this->repository->calcularCargaTrabajo($centroId, $fechaInicio, $fechaFin);
    }

    /**
     * Obtener datos para Gantt
     */
    public function getGanttData(
        ?int $centroId = null,
        ?string $fechaInicio = null,
        ?string $fechaFin = null
    ): array {
        // Si no se especifican fechas, usar próximos 30 días
        if (!$fechaInicio || !$fechaFin) {
            $fechaInicio = date('Y-m-d');
            $fechaFin = date('Y-m-d', strtotime('+30 days'));
        }

        $datos = $this->repository->getGanttData($centroId, $fechaInicio, $fechaFin);

        // Formatear para librería Gantt (si se usa)
        return [
            'periodo' => [
                'inicio' => $fechaInicio,
                'fin' => $fechaFin
            ],
            'datos' => $datos,
            'total_asignaciones' => count($datos)
        ];
    }

    /**
     * Validar capacidad disponible
     */
    public function validarCapacidad(
        int $centroId,
        string $fechaInicio,
        string $fechaFin,
        float $horasRequeridas
    ): array {
        $carga = $this->calcularCargaTrabajo($centroId, $fechaInicio, $fechaFin);

        $disponible = $carga['horas_disponibles'] >= $horasRequeridas;

        return [
            'valida' => $disponible,
            'horas_requeridas' => $horasRequeridas,
            'horas_disponibles' => $carga['horas_disponibles'],
            'horas_faltantes' => $disponible ? 0 : $horasRequeridas - $carga['horas_disponibles'],
            'carga_actual' => $carga
        ];
    }

    /**
     * Reprogramar asignación
     */
    public function reprogramar(
        int $planificacionId,
        string $nuevaFechaInicio,
        string $nuevaFechaFin
    ): array {
        $planificacion = $this->repository->findById($planificacionId);

        if (!$planificacion) {
            return ['success' => false, 'errors' => ['Planificación no encontrada']];
        }

        $data = [
            'orden_produccion_id' => $planificacion['orden_produccion_id'],
            'operacion_id' => $planificacion['operacion_id'],
            'centro_trabajo_id' => $planificacion['centro_trabajo_id'],
            'fecha_inicio' => $nuevaFechaInicio,
            'fecha_fin' => $nuevaFechaFin,
            'estado' => $planificacion['estado']
        ];

        return $this->update($planificacionId, $data);
    }
}
