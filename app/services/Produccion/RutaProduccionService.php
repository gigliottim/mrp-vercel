<?php

declare(strict_types=1);

namespace App\Services\Produccion;

use App\Repositories\Produccion\RutaProduccionRepository;
use App\Repositories\Produccion\CentroTrabajoRepository;

/**
 * Service para Rutas de Producción
 *
 * Lógica de negocio para gestión de rutas y operaciones de producción.
 */
final class RutaProduccionService
{
    private RutaProduccionRepository $repository;
    private CentroTrabajoRepository $centroRepository;

    public function __construct(
        RutaProduccionRepository $repository,
        CentroTrabajoRepository $centroRepository
    ) {
        $this->repository = $repository;
        $this->centroRepository = $centroRepository;
    }

    /**
     * Obtener operación por ID
     */
    public function getById(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Obtener ruta completa de un BOM
     */
    public function getByBomId(int $bomId): array
    {
        return $this->repository->getByBomId($bomId);
    }

    /**
     * Buscar rutas/operaciones
     */
    public function search(string $term = '', ?int $bomId = null, ?int $centroId = null): array
    {
        return $this->repository->search($term, $bomId, $centroId);
    }

    /**
     * Crear operación con validaciones
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

        return ['success' => false, 'errors' => ['Error al crear la operación']];
    }

    /**
     * Actualizar operación
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

        return ['success' => false, 'errors' => ['Error al actualizar la operación']];
    }

    /**
     * Eliminar operación
     */
    public function delete(int $id): array
    {
        $success = $this->repository->delete($id);

        if ($success) {
            return ['success' => true, 'message' => 'Operación eliminada'];
        }

        return ['success' => false, 'errors' => ['No se puede eliminar. Tiene planificaciones asociadas.']];
    }

    /**
     * Validar datos de operación
     */
    private function validar(array $data, ?int $excludeId = null): array
    {
        $errores = [];

        // BOM ID requerido
        if (empty($data['bom_id'])) {
            $errores[] = 'El BOM es requerido';
        }

        // Secuencia requerida
        if (!isset($data['secuencia']) || $data['secuencia'] <= 0) {
            $errores[] = 'La secuencia es requerida y debe ser mayor a 0';
        } elseif ($data['secuencia'] % 10 !== 0) {
            $errores[] = 'La secuencia debe ser múltiplo de 10';
        }

        // Centro de trabajo requerido y activo
        if (empty($data['centro_trabajo_id'])) {
            $errores[] = 'El centro de trabajo es requerido';
        } else {
            $centro = $this->centroRepository->findById((int)$data['centro_trabajo_id']);
            if (!$centro) {
                $errores[] = 'El centro de trabajo no existe';
            } elseif (!$centro['activo']) {
                $errores[] = 'El centro de trabajo no está activo';
            }
        }

        // Descripción requerida
        if (empty($data['descripcion'])) {
            $errores[] = 'La descripción es requerida';
        }

        // Tiempos no negativos
        $camposTiempo = [
            'tiempo_setup_mins',
            'tiempo_proceso_unitario_mins',
            'tiempo_cola_mins',
            'tiempo_movimiento_mins'
        ];
        foreach ($camposTiempo as $campo) {
            if (isset($data[$campo]) && (float)$data[$campo] < 0) {
                $errores[] = "El campo $campo no puede ser negativo";
            }
        }

        // Costos no negativos
        if (isset($data['costo_operacion_fijo']) && (float)$data['costo_operacion_fijo'] < 0) {
            $errores[] = 'El costo fijo no puede ser negativo';
        }
        if (isset($data['costo_operacion_variable']) && (float)$data['costo_operacion_variable'] < 0) {
            $errores[] = 'El costo variable no puede ser negativo';
        }

        return $errores;
    }

    /**
     * Reordenar operaciones de una ruta
     */
    public function reordenar(int $bomId, array $nuevasSecuencias): array
    {
        $success = $this->repository->reordenar($bomId, $nuevasSecuencias);

        if ($success) {
            return ['success' => true, 'message' => 'Operaciones reordenadas exitosamente'];
        }

        return ['success' => false, 'errors' => ['Error al reordenar operaciones']];
    }

    /**
     * Clonar ruta de un BOM a otro
     */
    public function clonarRuta(int $bomOrigenId, int $bomDestinoId): array
    {
        $success = $this->repository->clonarRuta($bomOrigenId, $bomDestinoId);

        if ($success) {
            return ['success' => true, 'message' => 'Ruta clonada exitosamente'];
        }

        return ['success' => false, 'errors' => ['Error al clonar la ruta']];
    }

    /**
     * Obtener resumen completo de ruta
     */
    public function getResumenRuta(int $bomId, float $cantidad = 1): array
    {
        $resumen = $this->repository->getResumenRuta($bomId, $cantidad);
        $validaciones = $this->repository->validarRuta($bomId);

        return [
            'resumen' => $resumen,
            'validaciones' => $validaciones,
            'es_valida' => empty($validaciones)
        ];
    }

    /**
     * Calcular tiempos de fabricación
     */
    public function calcularTiempos(int $bomId, float $cantidad): array
    {
        $operaciones = $this->repository->getByBomId($bomId);

        if (empty($operaciones)) {
            return ['error' => 'No hay operaciones definidas para este BOM'];
        }

        $fechaActual = new \DateTime();
        $resultados = [];

        foreach ($operaciones as $op) {
            $tiempoSetup = (int)$op['tiempo_setup_mins'];
            $tiempoProceso = (float)$op['tiempo_proceso_unitario_mins'] * $cantidad;
            $tiempoCola = (int)$op['tiempo_cola_mins'];
            $tiempoMovimiento = (int)$op['tiempo_movimiento_mins'];

            $duracionTotal = $tiempoSetup + $tiempoProceso + $tiempoCola + $tiempoMovimiento;

            $fechaFin = clone $fechaActual;
            $fechaFin->modify("+{$duracionTotal} minutes");

            $resultados[] = [
                'secuencia' => $op['secuencia'],
                'descripcion' => $op['descripcion'],
                'centro' => $op['centro_nombre'],
                'fecha_inicio' => $fechaActual->format('Y-m-d H:i:s'),
                'fecha_fin' => $fechaFin->format('Y-m-d H:i:s'),
                'duracion_mins' => round($duracionTotal, 2),
                'duracion_horas' => round($duracionTotal / 60, 2)
            ];

            $fechaActual = clone $fechaFin;
        }

        return [
            'operaciones' => $resultados,
            'fecha_inicio_total' => $resultados[0]['fecha_inicio'] ?? null,
            'fecha_fin_total' => end($resultados)['fecha_fin'] ?? null,
            'duracion_total_mins' => array_sum(array_column($resultados, 'duracion_mins')),
            'duracion_total_horas' => round(array_sum(array_column($resultados, 'duracion_mins')) / 60, 2)
        ];
    }

    /**
     * Calcular costos de fabricación
     */
    public function calcularCostos(int $bomId, float $cantidad): array
    {
        $operaciones = $this->repository->getByBomId($bomId);

        if (empty($operaciones)) {
            return ['error' => 'No hay operaciones definidas para este BOM'];
        }

        $resultados = [];
        $costoTotal = 0;

        foreach ($operaciones as $op) {
            $costoFijo = (float)$op['costo_operacion_fijo'];
            $costoVariable = (float)$op['costo_operacion_variable'] * $cantidad;
            $costoOperacion = $costoFijo + $costoVariable;
            $costoTotal += $costoOperacion;

            $resultados[] = [
                'secuencia' => $op['secuencia'],
                'descripcion' => $op['descripcion'],
                'centro' => $op['centro_nombre'],
                'costo_fijo' => round($costoFijo, 2),
                'costo_variable' => round($costoVariable, 2),
                'costo_total_operacion' => round($costoOperacion, 2)
            ];
        }

        return [
            'operaciones' => $resultados,
            'costo_total' => round($costoTotal, 2),
            'costo_unitario' => $cantidad > 0 ? round($costoTotal / $cantidad, 2) : 0
        ];
    }
}
