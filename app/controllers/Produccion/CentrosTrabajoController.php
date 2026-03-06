<?php

declare(strict_types=1);

namespace App\Controllers\Produccion;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\CentroTrabajo;
use App\Repositories\Produccion\CentroTrabajoRepository;
use App\Services\Produccion\CentroTrabajoService;

/**
 * Controller para Centros de Trabajo
 *
 * Gestiona la interfaz web para centros de trabajo.
 */
final class CentrosTrabajoController extends Controller
{
    private CentroTrabajoService $service;
    private CentroTrabajoRepository $repository;

    public function __construct()
    {
        $modelo = new CentroTrabajo();
        $this->repository = new CentroTrabajoRepository($modelo);
        $this->service = new CentroTrabajoService($this->repository);
    }

    /**
     * Listar centros de trabajo
     */
    public function index(Request $request): Response
    {
        $term = $request->query['search'] ?? '';

        if ($term) {
            $centros = $this->service->search($term);
        } else {
            $centros = $this->service->getAllActivos();
        }

        return $this->render('pages/produccion/centros_trabajo/index', [
            'title' => 'Centros de Trabajo',
            'centros' => $centros,
            'search' => $term
        ]);
    }

    /**
     * Mostrar formulario de creación
     */
    public function create(): Response
    {
        return $this->render('pages/produccion/centros_trabajo/create', [
            'title' => 'Nuevo Centro de Trabajo'
        ]);
    }

    /**
     * Guardar nuevo centro
     */
    public function store(Request $request): Response
    {
        $data = [
            'codigo' => $request->input('codigo'),
            'nombre' => $request->input('nombre'),
            'descripcion' => $request->input('descripcion'),
            'tipo' => $request->input('tipo', 'manual'),
            'capacidad_horas_dia' => (float)$request->input('capacidad_horas_dia', 8.00),
            'eficiencia_porcentaje' => (float)$request->input('eficiencia_porcentaje', 100.00),
            'costo_hora' => (float)$request->input('costo_hora', 0.00),
            'capacidad_finita' => $request->input('capacidad_finita') === '1',
            'ubicacion' => $request->input('ubicacion'),
            'responsable' => $request->input('responsable'),
            'observaciones' => $request->input('observaciones'),
            'activo' => $request->input('activo', '1') === '1'
        ];

        $resultado = $this->service->create($data);

        if ($resultado['success']) {
            return Response::redirect('/produccion/centros-trabajo');
        }

        return Response::redirect('/produccion/centros-trabajo/create');
    }

    /**
     * Mostrar detalle de centro
     */
    public function show(Request $request, string $id): Response
    {
        $id = (int)$id;
        $centro = $this->service->getById($id);

        if (!$centro) {
            return Response::redirect('/produccion/centros-trabajo');
        }

        // Obtener estadísticas del último mes
        $estadisticas = $this->service->getEstadisticas($id);

        return $this->render('pages/produccion/centros_trabajo/show', [
            'title' => 'Centro: ' . $centro['nombre'],
            'centro' => $centro,
            'estadisticas' => $estadisticas
        ]);
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit(Request $request, string $id): Response
    {
        $id = (int)$id;
        $centro = $this->service->getById($id);

        if (!$centro) {
            return Response::redirect('/produccion/centros-trabajo');
        }

        return $this->render('pages/produccion/centros_trabajo/edit', [
            'title' => 'Editar Centro: ' . $centro['nombre'],
            'centro' => $centro
        ]);
    }

    /**
     * Actualizar centro
     */
    public function update(Request $request, string $id): Response
    {
        $id = (int)$id;

        $data = [
            'codigo' => $request->input('codigo'),
            'nombre' => $request->input('nombre'),
            'descripcion' => $request->input('descripcion'),
            'tipo' => $request->input('tipo', 'manual'),
            'capacidad_horas_dia' => (float)$request->input('capacidad_horas_dia', 8.00),
            'eficiencia_porcentaje' => (float)$request->input('eficiencia_porcentaje', 100.00),
            'costo_hora' => (float)$request->input('costo_hora', 0.00),
            'capacidad_finita' => $request->input('capacidad_finita') === '1',
            'ubicacion' => $request->input('ubicacion'),
            'responsable' => $request->input('responsable'),
            'observaciones' => $request->input('observaciones'),
            'activo' => $request->input('activo', '1') === '1'
        ];

        $resultado = $this->service->update($id, $data);

        if ($resultado['success']) {
            return Response::redirect('/produccion/centros-trabajo');
        }

        return Response::redirect("/produccion/centros-trabajo/{$id}/edit");
    }

    /**
     * Eliminar centro
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = (int)$id;
        $resultado = $this->service->delete($id);

        if ($resultado['success']) {
            return $this->json([
                'success' => true,
                'message' => $resultado['message'] ?? 'Centro eliminado'
            ]);
        }

        return $this->json([
            'success' => false,
            'errors' => $resultado['errors']
        ], 400);
    }

    /**
     * API: Obtener todos los centros activos (JSON)
     */
    public function getAllJson(): Response
    {
        $centros = $this->service->getAllActivos();
        return $this->json($centros);
    }

    /**
     * API: Buscar centros (JSON)
     */
    public function searchJson(Request $request): Response
    {
        $term = $request->query['q'] ?? '';
        $centros = $this->service->search($term);
        return $this->json($centros);
    }

    /**
     * API: Obtener disponibilidad de centro
     */
    public function getDisponibilidad(Request $request, array $params): Response
    {
        $id = (int)$params['id'];
        $fechaInicio = $request->query['fecha_inicio'] ?? null;
        $fechaFin = $request->query['fecha_fin'] ?? null;

        if (!$fechaInicio || !$fechaFin) {
            return $this->json([
                'error' => 'Se requieren fecha_inicio y fecha_fin'
            ], 400);
        }

        $disponibilidad = $this->service->verificarDisponibilidad($id, $fechaInicio, $fechaFin);
        return $this->json($disponibilidad);
    }
}
