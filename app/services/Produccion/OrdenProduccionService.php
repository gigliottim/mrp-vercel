<?php

declare(strict_types=1);

namespace App\Services\Produccion;

use App\Models\OrdenProduccion;
use App\Repositories\Produccion\OrdenProduccionRepository;
use App\Repositories\Produccion\RutaProduccionRepository;

/**
 * Service para Órdenes de Producción
 *
 * Lógica de negocio completa para órdenes de producción.
 */
final class OrdenProduccionService
{
    private OrdenProduccionRepository $repository;
    private RutaProduccionRepository $rutaRepository;

    public function __construct(
        OrdenProduccionRepository $repository,
        RutaProduccionRepository $rutaRepository
    ) {
        $this->repository = $repository;
        $this->rutaRepository = $rutaRepository;
    }

    /**
     * Obtener orden por ID
     */
    public function getById(int $id): ?array
    {
        return $this->repository->findById($id);
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
        return $this->repository->search($term, $estado, $prioridad, $fechaDesde, $fechaHasta);
    }

    /**
     * Crear orden con validaciones
     */
    public function create(array $data): array
    {
        $errores = $this->validar($data);

        if (!empty($errores)) {
            return ['success' => false, 'errors' => $errores];
        }

        // Generar número si no se proporciona
        if (empty($data['numero_orden'])) {
            $data['numero_orden'] = $this->generarNumeroOrden();
        }

        $id = $this->repository->create($data);

        if ($id) {
            return ['success' => true, 'id' => $id, 'numero_orden' => $data['numero_orden']];
        }

        return ['success' => false, 'errors' => ['Error al crear la orden']];
    }

    /**
     * Actualizar orden
     */
    public function update(int $id, array $data): array
    {
        $orden = $this->repository->findById($id);

        if (!$orden) {
            return ['success' => false, 'errors' => ['Orden no encontrada']];
        }

        // No permitir editar órdenes cerradas o canceladas
        if (in_array($orden['estado'], [OrdenProduccion::ESTADO_CERRADA, OrdenProduccion::ESTADO_CANCELADA])) {
            return ['success' => false, 'errors' => ['No se puede editar una orden cerrada o cancelada']];
        }

        $errores = $this->validar($data, $id);

        if (!empty($errores)) {
            return ['success' => false, 'errors' => $errores];
        }

        $success = $this->repository->update($id, $data);

        if ($success) {
            return ['success' => true];
        }

        return ['success' => false, 'errors' => ['Error al actualizar la orden']];
    }

    /**
     * Cambiar estado de orden
     */
    public function cambiarEstado(int $id, string $nuevoEstado): array
    {
        $orden = $this->repository->findById($id);

        if (!$orden) {
            return ['success' => false, 'errors' => ['Orden no encontrada']];
        }

        // Validaciones específicas por estado
        if ($nuevoEstado === OrdenProduccion::ESTADO_LIBERADA) {
            $validacion = $this->validarParaLiberar($id);
            if (!$validacion['valida']) {
                return ['success' => false, 'errors' => $validacion['errores']];
            }
        }

        $success = $this->repository->cambiarEstado($id, $nuevoEstado);

        if ($success) {
            return ['success' => true, 'message' => "Estado cambiado a: $nuevoEstado"];
        }

        return ['success' => false, 'errors' => ['No se pudo cambiar el estado. Transición no permitida.']];
    }

    /**
     * Validar datos de orden
     */
    private function validar(array $data, ?int $excludeId = null): array
    {
        $errores = [];

        // Variante requerida
        if (empty($data['variante_id'])) {
            $errores[] = 'La variante es requerida';
        }

        // BOM requerida
        if (empty($data['bom_id_utilizada'])) {
            $errores[] = 'La lista de materiales es requerida';
        } else {
            // Verificar que existe ruta para el BOM
            $operaciones = $this->rutaRepository->getByBomId((int)$data['bom_id_utilizada']);
            if (empty($operaciones)) {
                $errores[] = 'El BOM seleccionado no tiene una ruta de producción definida';
            }
        }

        // Cantidad requerida y positiva
        if (!isset($data['cantidad_planificada']) || (float)$data['cantidad_planificada'] <= 0) {
            $errores[] = 'La cantidad debe ser mayor a cero';
        }

        // Fechas requeridas y válidas
        if (empty($data['fecha_inicio_programada'])) {
            $errores[] = 'La fecha de inicio es requerida';
        }
        if (empty($data['fecha_fin_programada'])) {
            $errores[] = 'La fecha de fin es requerida';
        }

        if (!empty($data['fecha_inicio_programada']) && !empty($data['fecha_fin_programada'])) {
            $inicio = new \DateTime($data['fecha_inicio_programada']);
            $fin = new \DateTime($data['fecha_fin_programada']);
            if ($fin <= $inicio) {
                $errores[] = 'La fecha de fin debe ser posterior a la fecha de inicio';
            }
        }

        // Número de orden único
        if (!empty($data['numero_orden'])) {
            $existe = $this->repository->findById(0); // Se validará en el modelo
            // Esta validación se hace en el modelo
        }

        return $errores;
    }

    /**
     * Validar que la orden puede ser liberada
     */
    private function validarParaLiberar(int $ordenId): array
    {
        $errores = [];
        $orden = $this->repository->findById($ordenId);

        if ($orden['estado'] !== OrdenProduccion::ESTADO_PLANIFICADA) {
            $errores[] = 'La orden debe estar en estado "planificada"';
        }

        // Verificar que tiene planificación completa
        // (esto se implementará cuando tengamos PlanificacionService)

        return [
            'valida' => empty($errores),
            'errores' => $errores
        ];
    }

    /**
     * Generar número de orden
     */
    private function generarNumeroOrden(): string
    {
        $year = date('Y');
        $month = date('m');
        return "OP-$year$month-" . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener dashboard
     */
    public function getDashboard(): array
    {
        return $this->repository->getDashboard();
    }

    /**
     * Obtener órdenes pendientes de planificar
     */
    public function getPendientesPlanificar(): array
    {
        return $this->repository->getPendientesPlanificar();
    }

    /**
     * Obtener órdenes en proceso
     */
    public function getEnProceso(): array
    {
        return $this->repository->getEnProceso();
    }

    /**
     * Obtener órdenes atrasadas
     */
    public function getAtrasadas(): array
    {
        return $this->repository->getAtrasadas();
    }

    /**
     * Calcular avance de la orden
     */
    public function calcularAvance(int $id): array
    {
        $orden = $this->repository->findById($id);

        if (!$orden) {
            return ['error' => 'Orden no encontrada'];
        }

        $cantidadPlanificada = (float)$orden['cantidad_planificada'];
        $cantidadProducida = (float)($orden['cantidad_producida'] ?? 0);
        $cantidadDesechada = (float)($orden['cantidad_desechada'] ?? 0);

        $porcentaje = $cantidadPlanificada > 0
            ? round(($cantidadProducida / $cantidadPlanificada) * 100, 2)
            : 0;

        return [
            'orden' => $orden,
            'cantidad_planificada' => $cantidadPlanificada,
            'cantidad_producida' => $cantidadProducida,
            'cantidad_desechada' => $cantidadDesechada,
            'cantidad_pendiente' => $cantidadPlanificada - $cantidadProducida,
            'porcentaje_avance' => $porcentaje,
            'esta_completa' => $cantidadProducida >= $cantidadPlanificada
        ];
    }

    /**
     * Reportar producción
     */
    public function reportarProduccion(
        int $ordenId,
        float $cantidadProducida,
        float $cantidadDesechada = 0
    ): array {
        $orden = $this->repository->findById($ordenId);

        if (!$orden) {
            return ['success' => false, 'errors' => ['Orden no encontrada']];
        }

        if ($orden['estado'] !== OrdenProduccion::ESTADO_EN_PROCESO) {
            return ['success' => false, 'errors' => ['La orden debe estar en proceso']];
        }

        $success = $this->repository->actualizarCantidadProducida(
            $ordenId,
            $cantidadProducida,
            $cantidadDesechada
        );

        if ($success) {
            // Si se completó la cantidad, cambiar estado automáticamente
            if ($cantidadProducida >= (float)$orden['cantidad_planificada']) {
                $this->cambiarEstado($ordenId, OrdenProduccion::ESTADO_COMPLETADA);
            }

            return ['success' => true, 'message' => 'Producción reportada exitosamente'];
        }

        return ['success' => false, 'errors' => ['Error al reportar producción']];
    }
}
