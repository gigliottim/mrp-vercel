<?php

declare(strict_types=1);

namespace App\Controllers\Produccion;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\OrdenProduccion;
use App\Models\RutaProduccion;
use App\Repositories\Produccion\OrdenProduccionRepository;
use App\Repositories\Produccion\RutaProduccionRepository;
use App\Services\Produccion\OrdenProduccionService;

/**
 * Controller para Órdenes de Producción
 *
 * Gestiona el ciclo completo de órdenes de fabricación.
 */
final class OrdenesProduccionController extends Controller
{
    private OrdenProduccionService $service;
    private OrdenProduccionRepository $repository;

    public function __construct()
    {
        $ordenModel = new OrdenProduccion();
        $rutaModel = new RutaProduccion();
        $this->repository = new OrdenProduccionRepository($ordenModel);
        $rutaRepository = new RutaProduccionRepository($rutaModel);
        $this->service = new OrdenProduccionService($this->repository, $rutaRepository);
    }

    /**
     * Listar órdenes de producción
     */
    public function index(Request $request): Response
    {
        $term = $request->query['search'] ?? '';
        $estado = $request->query['estado'] ?? null;
        $prioridad = $request->query['prioridad'] ?? null;
        $fechaDesde = $request->query['fecha_desde'] ?? null;
        $fechaHasta = $request->query['fecha_hasta'] ?? null;

        $ordenes = $this->service->search($term, $estado, $prioridad, $fechaDesde, $fechaHasta);
        $dashboard = $this->service->getDashboard();

        return $this->render('pages/produccion/ordenes/index', [
            'title' => 'Órdenes de Producción',
            'ordenes' => $ordenes,
            'dashboard' => $dashboard,
            'filters' => [
                'search' => $term,
                'estado' => $estado,
                'prioridad' => $prioridad,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta
            ]
        ]);
    }

    /**
     * Mostrar formulario de nueva orden
     */
    public function create(): Response
    {
        return $this->render('pages/produccion/ordenes/create', [
            'title' => 'Nueva Orden de Producción',
            'prioridades' => $this->getPrioridades(),
            'estados' => $this->getEstados()
        ]);
    }

    /**
     * Guardar nueva orden
     */
    public function store(Request $request): Response
    {
        $data = [
            'numero_orden' => $request->input('numero_orden'),
            'variante_id' => (int)$request->input('variante_id'),
            'bom_id_utilizada' => (int)$request->input('bom_id_utilizada'),
            'cantidad_planificada' => (float)$request->input('cantidad_planificada'),
            'fecha_inicio_programada' => $request->input('fecha_inicio_programada'),
            'fecha_fin_programada' => $request->input('fecha_fin_programada'),
            'estado' => $request->input('estado', OrdenProduccion::ESTADO_BORRADOR),
            'prioridad' => $request->input('prioridad', OrdenProduccion::PRIORIDAD_NORMAL),
            'observaciones' => $request->input('observaciones'),
            'usuario_creador' => $this->getUserId()
        ];

        $resultado = $this->service->create($data);

        if ($resultado['success']) {
            $ordenId = $resultado['id'];
            return Response::redirect("/produccion/ordenes/{$ordenId}");
        }

        return Response::redirect('/produccion/ordenes/create');
    }

    /**
     * Mostrar detalle de orden
     */
    public function show(Request $request, string $id): Response
    {
        $id = (int)$id;
        $orden = $this->service->getById($id);

        if (!$orden) {
            return Response::redirect('/produccion/ordenes');
        }

        $avance = $this->service->calcularAvance($id);

        return $this->render('pages/produccion/ordenes/show', [
            'title' => 'Orden: ' . $orden['numero_orden'],
            'orden' => $orden,
            'avance' => $avance
        ]);
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit(Request $request, string $id): Response
    {
        $id = (int)$id;
        $orden = $this->service->getById($id);

        if (!$orden) {
            return Response::redirect('/produccion/ordenes');
        }

        // No permitir editar órdenes cerradas o canceladas
        if (in_array($orden['estado'], [OrdenProduccion::ESTADO_CERRADA, OrdenProduccion::ESTADO_CANCELADA])) {
            return Response::redirect("/produccion/ordenes/{$id}");
        }

        return $this->render('pages/produccion/ordenes/edit', [
            'title' => 'Editar Orden: ' . $orden['numero_orden'],
            'orden' => $orden,
            'prioridades' => $this->getPrioridades()
        ]);
    }

    /**
     * Actualizar orden
     */
    public function update(Request $request, string $id): Response
    {
        $id = (int)$id;

        $data = [
            'numero_orden' => $request->input('numero_orden'),
            'variante_id' => (int)$request->input('variante_id'),
            'bom_id_utilizada' => (int)$request->input('bom_id_utilizada'),
            'cantidad_planificada' => (float)$request->input('cantidad_planificada'),
            'cantidad_producida' => (float)($request->input('cantidad_producida') ?? 0),
            'cantidad_desechada' => (float)($request->input('cantidad_desechada') ?? 0),
            'fecha_inicio_programada' => $request->input('fecha_inicio_programada'),
            'fecha_fin_programada' => $request->input('fecha_fin_programada'),
            'prioridad' => $request->input('prioridad'),
            'observaciones' => $request->input('observaciones')
        ];

        $resultado = $this->service->update($id, $data);

        if ($resultado['success']) {
            return Response::redirect("/produccion/ordenes/{$id}");
        }

        return Response::redirect("/produccion/ordenes/{$id}/edit");
    }

    /**
     * Eliminar orden (solo borradores)
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = (int)$id;
        $resultado = $this->repository->delete($id);

        if ($resultado) {
            return $this->json([
                'success' => true,
                'message' => 'Orden eliminada'
            ]);
        }

        return $this->json([
            'success' => false,
            'errors' => ['Solo se pueden eliminar órdenes en estado borrador']
        ], 400);
    }

    /**
     * Liberar orden para producción
     */
    public function liberar(Request $request, string $id): Response
    {
        $id = (int)$id;
        $resultado = $this->service->cambiarEstado($id, OrdenProduccion::ESTADO_LIBERADA);

        if ($resultado['success']) {
            return $this->json([
                'success' => true,
                'message' => 'Orden liberada para producción'
            ]);
        }

        return $this->json([
            'success' => false,
            'errors' => $resultado['errors']
        ], 400);
    }

    /**
     * Iniciar producción
     */
    public function iniciar(Request $request, string $id): Response
    {
        $id = (int)$id;
        $resultado = $this->service->cambiarEstado($id, OrdenProduccion::ESTADO_EN_PROCESO);

        if ($resultado['success']) {
            return $this->json([
                'success' => true,
                'message' => 'Producción iniciada'
            ]);
        }

        return $this->json([
            'success' => false,
            'errors' => $resultado['errors']
        ], 400);
    }

    /**
     * Completar orden
     */
    public function completar(Request $request, string $id): Response
    {
        $id = (int)$id;
        $resultado = $this->service->cambiarEstado($id, OrdenProduccion::ESTADO_COMPLETADA);

        if ($resultado['success']) {
            return $this->json([
                'success' => true,
                'message' => 'Orden completada'
            ]);
        }

        return $this->json([
            'success' => false,
            'errors' => $resultado['errors']
        ], 400);
    }

    /**
     * API: Obtener órdenes pendientes de planificar
     */
    public function getPendientes(): Response
    {
        $ordenes = $this->service->getPendientesPlanificar();
        return $this->json($ordenes);
    }

    /**
     * API: Obtener órdenes en proceso
     */
    public function getEnProceso(): Response
    {
        $ordenes = $this->service->getEnProceso();
        return $this->json($ordenes);
    }

    /**
     * API: Buscar órdenes (JSON)
     */
    public function searchJson(Request $request): Response
    {
        $term = $request->query['q'] ?? '';
        $estado = $request->query['estado'] ?? null;

        $ordenes = $this->service->search($term, $estado);
        return $this->json($ordenes);
    }

    /**
     * Obtener prioridades disponibles
     */
    private function getPrioridades(): array
    {
        return [
            OrdenProduccion::PRIORIDAD_BAJA => 'Baja',
            OrdenProduccion::PRIORIDAD_NORMAL => 'Normal',
            OrdenProduccion::PRIORIDAD_ALTA => 'Alta',
            OrdenProduccion::PRIORIDAD_URGENTE => 'Urgente'
        ];
    }

    /**
     * Obtener estados disponibles
     */
    private function getEstados(): array
    {
        return [
            OrdenProduccion::ESTADO_BORRADOR => 'Borrador',
            OrdenProduccion::ESTADO_PLANIFICADA => 'Planificada',
            OrdenProduccion::ESTADO_LIBERADA => 'Liberada',
            OrdenProduccion::ESTADO_EN_PROCESO => 'En Proceso',
            OrdenProduccion::ESTADO_PAUSADA => 'Pausada',
            OrdenProduccion::ESTADO_COMPLETADA => 'Completada',
            OrdenProduccion::ESTADO_CANCELADA => 'Cancelada',
            OrdenProduccion::ESTADO_CERRADA => 'Cerrada'
        ];
    }

    /**
     * Obtener ID del usuario actual
     */
    private function getUserId(): ?int
    {
        // Implementar según tu sistema de autenticación
        return $_SESSION['user_id'] ?? null;
    }
}
