<?php

declare(strict_types=1);

namespace App\Services\Produccion;

use App\Repositories\Produccion\CentroTrabajoRepository;

/**
 * Service para Centros de Trabajo
 *
 * Contiene la lógica de negocio para gestionar centros de trabajo.
 */
final class CentroTrabajoService
{
    private CentroTrabajoRepository $repository;

    public function __construct(CentroTrabajoRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obtener todos los centros activos
     */
    public function getAllActivos(): array
    {
        return $this->repository->getAllActivos();
    }

    /**
     * Obtener centro por ID
     */
    public function getById(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Buscar centros
     */
    public function search(string $term = ''): array
    {
        return $this->repository->search($term);
    }

    /**
     * Crear nuevo centro con validaciones
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

        return ['success' => false, 'errors' => ['Error al crear el centro de trabajo']];
    }

    /**
     * Actualizar centro con validaciones
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

        return ['success' => false, 'errors' => ['Error al actualizar el centro de trabajo']];
    }

    /**
     * Desactivar centro
     */
    public function desactivar(int $id): array
    {
        $centro = $this->repository->findById($id);

        if (!$centro) {
            return ['success' => false, 'errors' => ['Centro no encontrado']];
        }

        $success = $this->repository->desactivar($id);

        if ($success) {
            return ['success' => true, 'message' => 'Centro desactivado exitosamente'];
        }

        return ['success' => false, 'errors' => ['Error al desactivar el centro']];
    }

    /**
     * Eliminar centro
     */
    public function delete(int $id): array
    {
        if ($this->repository->tieneDependencias($id)) {
            return [
                'success' => false,
                'errors' => ['No se puede eliminar el centro porque tiene rutas u órdenes asociadas']
            ];
        }

        $success = $this->repository->delete($id);

        if ($success) {
            return ['success' => true, 'message' => 'Centro eliminado exitosamente'];
        }

        return ['success' => false, 'errors' => ['Error al eliminar el centro']];
    }

    /**
     * Validar datos del centro
     */
    private function validar(array $data, ?int $excludeId = null): array
    {
        $errores = [];

        // Código requerido
        if (empty($data['codigo'])) {
            $errores[] = 'El código es requerido';
        } elseif ($this->repository->existeCodigo($data['codigo'], $excludeId)) {
            $errores[] = 'El código ya está en uso';
        }

        // Nombre requerido
        if (empty($data['nombre'])) {
            $errores[] = 'El nombre es requerido';
        }

        // Tipo válido
        $tiposValidos = ['manual', 'semi_automatico', 'automatico'];
        if (isset($data['tipo']) && !in_array($data['tipo'], $tiposValidos)) {
            $errores[] = 'El tipo debe ser: manual, semi_automatico o automatico';
        }

        // Capacidad horas día
        if (isset($data['capacidad_horas_dia'])) {
            $capacidad = (float)$data['capacidad_horas_dia'];
            if ($capacidad < 0 || $capacidad > 24) {
                $errores[] = 'La capacidad debe estar entre 0 y 24 horas';
            }
        }

        // Eficiencia porcentaje
        if (isset($data['eficiencia_porcentaje'])) {
            $eficiencia = (float)$data['eficiencia_porcentaje'];
            if ($eficiencia < 0 || $eficiencia > 100) {
                $errores[] = 'La eficiencia debe estar entre 0 y 100%';
            }
        }

        // Costo hora
        if (isset($data['costo_hora']) && (float)$data['costo_hora'] < 0) {
            $errores[] = 'El costo por hora no puede ser negativo';
        }

        return $errores;
    }

    /**
     * Obtener estadísticas del centro
     */
    public function getEstadisticas(int $id, ?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        return $this->repository->getEstadisticas($id, $fechaInicio, $fechaFin);
    }

    /**
     * Verificar disponibilidad del centro
     */
    public function verificarDisponibilidad(
        int $id,
        string $fechaInicio,
        string $fechaFin
    ): array {
        $centro = $this->repository->findById($id);

        if (!$centro) {
            return ['disponible' => false, 'error' => 'Centro no encontrado'];
        }

        if (!$centro['activo']) {
            return ['disponible' => false, 'error' => 'Centro inactivo'];
        }

        $stats = $this->repository->getEstadisticas($id, $fechaInicio, $fechaFin);

        return [
            'disponible' => $stats['horas_disponibles'] > 0,
            'centro' => $centro,
            'estadisticas' => $stats
        ];
    }
}
