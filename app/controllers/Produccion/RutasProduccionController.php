<?php

declare(strict_types=1);

namespace App\Controllers\Produccion;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\RutaProduccion;
use App\Models\CentroTrabajo;
use App\Repositories\Produccion\RutaProduccionRepository;
use App\Repositories\Produccion\CentroTrabajoRepository;
use App\Services\Produccion\RutaProduccionService;

/**
 * Controller para Rutas de Producción
 *
 * Gestiona rutas y operaciones de fabricación.
 */
final class RutasProduccionController extends Controller
{
    private RutaProduccionService $service;
    private RutaProduccionRepository $repository;
    private CentroTrabajoRepository $centroRepository;

    public function __construct()
    {
        $rutaModel = new RutaProduccion();
        $centroModel = new CentroTrabajo();
        $this->repository = new RutaProduccionRepository($rutaModel);
        $this->centroRepository = new CentroTrabajoRepository($centroModel);
        $this->service = new RutaProduccionService($this->repository, $this->centroRepository);
    }

    /**
     * Listar rutas/operaciones
     */
    public function index(Request $request): Response
    {
        $term = $request->query['search'] ?? '';
        $bomId = $request->query['bom_id'] ?? null;
        $centroId = $request->query['centro_id'] ?? null;

        $operaciones = $this->service->search(
            $term,
            $bomId ? (int)$bomId : null,
            $centroId ? (int)$centroId : null
        );

        $centros = $this->centroRepository->getAllActivos();

        return $this->render('pages/produccion/rutas/index', [
            'title' => 'Rutas de Producción',
            'operaciones' => $operaciones,
            'centros' => $centros,
            'search' => $term,
            'bom_id' => $bomId,
            'centro_id' => $centroId
        ]);
    }

    /**
     * Editor visual de ruta para un BOM
     */
    public function editor(Request $request, string $id): Response
    {
        $bomId = (int)$id;

        // Obtener operaciones existentes
        $operaciones = $this->service->getByBomId($bomId);

        // Obtener centros disponibles
        $centros = $this->centroRepository->getAllActivos();

        // Calcular resumen si hay operaciones
        $resumen = !empty($operaciones)
            ? $this->service->getResumenRuta($bomId, 1)
            : ['operaciones' => [], 'tiempos' => [], 'costos' => []];

        return $this->render('pages/produccion/rutas/editor', [
            'title' => 'Editor de Ruta',
            'bom_id' => $bomId,
            'operaciones' => $operaciones,
            'centros' => $centros,
            'resumen' => $resumen
        ]);
    }

    /**
     * Agregar operación a una ruta
     */
    public function addOperacion(Request $request, string $id): Response
    {
        $bomId = (int)$id;

        $data = [
            'bom_id' => $bomId,
            'secuencia' => (int)$request->input('secuencia'),
            'centro_trabajo_id' => (int)$request->input('centro_trabajo_id'),
            'descripcion' => $request->input('descripcion'),
            'tiempo_setup_mins' => (int)($request->input('tiempo_setup_mins') ?? 0),
            'tiempo_proceso_unitario_mins' => (float)($request->input('tiempo_proceso_unitario_mins') ?? 0),
            'tiempo_cola_mins' => (int)($request->input('tiempo_cola_mins') ?? 0),
            'tiempo_movimiento_mins' => (int)($request->input('tiempo_movimiento_mins') ?? 0),
            'capacidad_requerida' => (float)($request->input('capacidad_requerida') ?? 1.0),
            'costo_operacion_fijo' => (float)($request->input('costo_operacion_fijo') ?? 0),
            'costo_operacion_variable' => (float)($request->input('costo_operacion_variable') ?? 0),
            'instrucciones' => $request->input('instrucciones')
        ];

        $resultado = $this->service->create($data);

        if ($resultado['success']) {
            return $this->json([
                'success' => true,
                'id' => $resultado['id'],
                'message' => 'Operación agregada exitosamente'
            ]);
        }

        return $this->json([
            'success' => false,
            'errors' => $resultado['errors']
        ], 400);
    }

    /**
     * Actualizar operación
     */
    public function updateOperacion(Request $request, string $opId): Response
    {
        $opId = (int)$opId;

        $data = [
            'bom_id' => (int)$request->input('bom_id'),
            'secuencia' => (int)$request->input('secuencia'),
            'centro_trabajo_id' => (int)$request->input('centro_trabajo_id'),
            'descripcion' => $request->input('descripcion'),
            'tiempo_setup_mins' => (int)($request->input('tiempo_setup_mins') ?? 0),
            'tiempo_proceso_unitario_mins' => (float)($request->input('tiempo_proceso_unitario_mins') ?? 0),
            'tiempo_cola_mins' => (int)($request->input('tiempo_cola_mins') ?? 0),
            'tiempo_movimiento_mins' => (int)($request->input('tiempo_movimiento_mins') ?? 0),
            'capacidad_requerida' => (float)($request->input('capacidad_requerida') ?? 1.0),
            'costo_operacion_fijo' => (float)($request->input('costo_operacion_fijo') ?? 0),
            'costo_operacion_variable' => (float)($request->input('costo_operacion_variable') ?? 0),
            'instrucciones' => $request->input('instrucciones')
        ];

        $resultado = $this->service->update($opId, $data);

        if ($resultado['success']) {
            return $this->json([
                'success' => true,
                'message' => 'Operación actualizada'
            ]);
        }

        return $this->json([
            'success' => false,
            'errors' => $resultado['errors']
        ], 400);
    }

    /**
     * Eliminar operación
     */
    public function deleteOperacion(Request $request, string $opId): Response
    {
        $opId = (int)$opId;
        $resultado = $this->service->delete($opId);

        if ($resultado['success']) {
            return $this->json([
                'success' => true,
                'message' => $resultado['message']
            ]);
        }

        return $this->json([
            'success' => false,
            'errors' => $resultado['errors']
        ], 400);
    }

    /**
     * API: Obtener operaciones de un BOM
     */
    public function getOperacionesByBom(Request $request, string $bomId): Response
    {
        $bomId = (int)$bomId;
        $operaciones = $this->service->getByBomId($bomId);
        return $this->json($operaciones);
    }

    /**
     * API: Calcular tiempos de fabricación
     */
    public function calcularTiempos(Request $request, string $id): Response
    {
        $bomId = (int)$id;
        $cantidad = (float)($request->input('cantidad') ?? 1);

        $tiempos = $this->service->calcularTiempos($bomId, $cantidad);
        return $this->json($tiempos);
    }

    /**
     * API: Calcular costos de fabricación
     */
    public function calcularCostos(Request $request, string $id): Response
    {
        $bomId = (int)$id;
        $cantidad = (float)($request->input('cantidad') ?? 1);

        $costos = $this->service->calcularCostos($bomId, $cantidad);
        return $this->json($costos);
    }

    /**
     * Mostrar operación individual
     */
    public function show(Request $request, string $id): Response
    {
        $id = (int)$id;
        $operacion = $this->service->getById($id);

        if (!$operacion) {
            return Response::redirect('/produccion/rutas');
        }

        return $this->render('pages/produccion/rutas/show', [
            'title' => 'Operación: ' . $operacion['descripcion'],
            'operacion' => $operacion
        ]);
    }

    /**
     * Formulario de creación (standalone)
     */
    public function create(): Response
    {
        $centros = $this->centroRepository->getAllActivos();

        return $this->render('pages/produccion/rutas/create', [
            'title' => 'Nueva Operación',
            'centros' => $centros
        ]);
    }

    /**
     * Guardar operación (standalone)
     */
    public function store(Request $request): Response
    {
        $data = [
            'bom_id' => (int)$request->input('bom_id'),
            'secuencia' => (int)$request->input('secuencia'),
            'centro_trabajo_id' => (int)$request->input('centro_trabajo_id'),
            'descripcion' => $request->input('descripcion'),
            'tiempo_setup_mins' => (int)($request->input('tiempo_setup_mins') ?? 0),
            'tiempo_proceso_unitario_mins' => (float)($request->input('tiempo_proceso_unitario_mins') ?? 0),
            'tiempo_cola_mins' => (int)($request->input('tiempo_cola_mins') ?? 0),
            'tiempo_movimiento_mins' => (int)($request->input('tiempo_movimiento_mins') ?? 0),
            'capacidad_requerida' => (float)($request->input('capacidad_requerida') ?? 1.0),
            'costo_operacion_fijo' => (float)($request->input('costo_operacion_fijo') ?? 0),
            'costo_operacion_variable' => (float)($request->input('costo_operacion_variable') ?? 0),
            'instrucciones' => $request->input('instrucciones')
        ];

        $resultado = $this->service->create($data);

        if ($resultado['success']) {
            return Response::redirect('/produccion/rutas');
        }

        return Response::redirect('/produccion/rutas/create');
    }
}
