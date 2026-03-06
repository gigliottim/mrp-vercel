<?php

declare(strict_types=1);

namespace App\Controllers\Produccion;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\PlanificacionRecurso;
use App\Models\OrdenProduccion;
use App\Models\RutaProduccion;
use App\Repositories\Produccion\PlanificacionRepository;
use App\Repositories\Produccion\OrdenProduccionRepository;
use App\Repositories\Produccion\RutaProduccionRepository;
use App\Services\Produccion\PlanificacionService;

/**
 * Controller para Planificación de Recursos
 *
 * Gestiona la asignación de recursos y visualización de planificación.
 */
final class PlanificacionController extends Controller
{
    private PlanificacionService $service;
    private PlanificacionRepository $repository;

    public function __construct()
    {
        $planificacionModel = new PlanificacionRecurso();
        $ordenModel = new OrdenProduccion();
        $rutaModel = new RutaProduccion();

        $this->repository = new PlanificacionRepository($planificacionModel);
        $ordenRepository = new OrdenProduccionRepository($ordenModel);
        $rutaRepository = new RutaProduccionRepository($rutaModel);

        $this->service = new PlanificacionService(
            $this->repository,
            $ordenRepository,
            $rutaRepository
        );
    }

    /**
     * Vista principal de planificación
     */
    public function index(Request $request): Response
    {
        $centroId = $request->query['centro_id'] ?? null;
        $fechaInicio = $request->query['fecha_inicio'] ?? date('Y-m-d');
        $fechaFin = $request->query['fecha_fin'] ?? date('Y-m-d', strtotime('+30 days'));

        $planificaciones = $centroId
            ? $this->service->getByCentroTrabajo((int)$centroId, $fechaInicio, $fechaFin)
            : [];

        $conflictos = $this->service->getConflictos($centroId ? (int)$centroId : null);

        return $this->render('pages/produccion/planificacion/index', [
            'title' => 'Planificación de Recursos',
            'planificaciones' => $planificaciones,
            'conflictos' => $conflictos,
            'filters' => [
                'centro_id' => $centroId,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin
            ]
        ]);
    }

    /**
     * Vista Gantt de planificación
     */
    public function gantt(Request $request): Response
    {
        $centroId = $request->query['centro_id'] ?? null;
        $fechaInicio = $request->query['fecha_inicio'] ?? date('Y-m-d');
        $fechaFin = $request->query['fecha_fin'] ?? date('Y-m-d', strtotime('+30 days'));

        $ganttData = $this->service->getGanttData(
            $centroId ? (int)$centroId : null,
            $fechaInicio,
            $fechaFin
        );

        return $this->render('pages/produccion/planificacion/gantt', [
            'title' => 'Gantt de Planificación',
            'gantt_data' => $ganttData,
            'filters' => [
                'centro_id' => $centroId,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin
            ]
        ]);
    }

    /**
     * Calcular planificación automática para una orden
     */
    public function calcular(Request $request): Response
    {
        $ordenId = (int)$request->input('orden_id');
        $fechaInicio = $request->input('fecha_inicio');

        $resultado = $this->service->planificarOrdenAutomatica($ordenId, $fechaInicio);

        if ($resultado['success']) {
            return $this->json([
                'success' => true,
                'planificaciones' => $resultado['planificaciones'],
                'message' => 'Planificación calculada exitosamente'
            ]);
        }

        return $this->json([
            'success' => false,
            'errors' => $resultado['errors'] ?? [$resultado['error']]
        ], 400);
    }

    /**
     * Asignar recursos manualmente
     */
    public function asignarRecursos(Request $request): Response
    {
        $data = [
            'orden_produccion_id' => (int)$request->input('orden_produccion_id'),
            'operacion_id' => $request->input('operacion_id') ? (int)$request->input('operacion_id') : null,
            'centro_trabajo_id' => (int)$request->input('centro_trabajo_id'),
            'fecha_inicio' => $request->input('fecha_inicio'),
            'fecha_fin' => $request->input('fecha_fin'),
            'estado' => $request->input('estado', PlanificacionRecurso::ESTADO_PROGRAMADO)
        ];

        $resultado = $this->service->create($data);

        if ($resultado['success']) {
            return $this->json([
                'success' => true,
                'id' => $resultado['id'],
                'message' => 'Recurso asignado exitosamente'
            ]);
        }

        return $this->json([
            'success' => false,
            'errors' => $resultado['errors']
        ], 400);
    }

    /**
     * Actualizar asignación de recurso
     */
    public function updateAsignacion(Request $request, string $id): Response
    {
        $id = (int)$id;

        $data = [
            'orden_produccion_id' => (int)$request->input('orden_produccion_id'),
            'operacion_id' => $request->input('operacion_id') ? (int)$request->input('operacion_id') : null,
            'centro_trabajo_id' => (int)$request->input('centro_trabajo_id'),
            'fecha_inicio' => $request->input('fecha_inicio'),
            'fecha_fin' => $request->input('fecha_fin'),
            'estado' => $request->input('estado')
        ];

        $resultado = $this->service->update($id, $data);

        if ($resultado['success']) {
            return $this->json([
                'success' => true,
                'message' => 'Asignación actualizada'
            ]);
        }

        return $this->json([
            'success' => false,
            'errors' => $resultado['errors']
        ], 400);
    }

    /**
     * Eliminar asignación
     */
    public function deleteAsignacion(Request $request, string $id): Response
    {
        $id = (int)$id;
        $resultado = $this->service->delete($id);

        if ($resultado['success']) {
            return $this->json([
                'success' => true,
                'message' => 'Asignación eliminada'
            ]);
        }

        return $this->json([
            'success' => false,
            'errors' => $resultado['errors']
        ], 400);
    }

    /**
     * API: Obtener planificación por centro
     */
    public function getPlanificacionByCentro(Request $request, string $centroId): Response
    {
        $centroId = (int)$centroId;
        $fechaInicio = $request->query['fecha_inicio'] ?? null;
        $fechaFin = $request->query['fecha_fin'] ?? null;

        $planificaciones = $this->service->getByCentroTrabajo($centroId, $fechaInicio, $fechaFin);
        return $this->json($planificaciones);
    }

    /**
     * API: Obtener planificación por orden
     */
    public function getPlanificacionByOrden(Request $request, string $ordenId): Response
    {
        $ordenId = (int)$ordenId;
        $planificaciones = $this->service->getByOrdenProduccion($ordenId);
        return $this->json($planificaciones);
    }

    /**
     * API: Obtener conflictos de capacidad
     */
    public function getConflictos(Request $request): Response
    {
        $centroId = $request->query['centro_id'] ?? null;
        $conflictos = $this->service->getConflictos($centroId ? (int)$centroId : null);
        return $this->json($conflictos);
    }

    /**
     * API: Validar capacidad disponible
     */
    public function validarCapacidad(Request $request): Response
    {
        $centroId = (int)$request->input('centro_id');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $horasRequeridas = (float)$request->input('horas_requeridas');

        $validacion = $this->service->validarCapacidad(
            $centroId,
            $fechaInicio,
            $fechaFin,
            $horasRequeridas
        );

        return $this->json($validacion);
    }

    /**
     * API: Calcular carga de trabajo de centro
     */
    public function getCargaTrabajo(Request $request, string $centroId): Response
    {
        $centroId = (int)$centroId;
        $fechaInicio = $request->query['fecha_inicio'] ?? date('Y-m-d');
        $fechaFin = $request->query['fecha_fin'] ?? date('Y-m-d', strtotime('+30 days'));

        $carga = $this->service->calcularCargaTrabajo($centroId, $fechaInicio, $fechaFin);
        return $this->json($carga);
    }
}
