<?php

declare(strict_types=1);

namespace App\Controllers\Produccion;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\OrdenProduccion;
use App\Models\CentroTrabajo;
use App\Models\RutaProduccion;
use App\Repositories\Produccion\OrdenProduccionRepository;
use App\Repositories\Produccion\CentroTrabajoRepository;
use App\Repositories\Produccion\RutaProduccionRepository;

/**
 * Controller Principal de Operaciones
 *
 * Dashboard y vista general del módulo de producción.
 */
final class OperacionesController extends Controller
{
    private OrdenProduccionRepository $ordenRepo;
    private CentroTrabajoRepository $centroRepo;
    private RutaProduccionRepository $rutaRepo;

    public function __construct()
    {
        $this->ordenRepo = new OrdenProduccionRepository(new OrdenProduccion());
        $this->centroRepo = new CentroTrabajoRepository(new CentroTrabajo());
        $this->rutaRepo = new RutaProduccionRepository(new RutaProduccion());
    }

    /**
     * Dashboard principal de operaciones
     */
    public function index(Request $request): Response
    {
        // Métricas principales (valores simplificados por ahora)
        $metricas = [
            'centros_activos' => 0,
            'rutas_configuradas' => 0,
            'ordenes_activas' => 0,
            'capacidad_utilizada' => 0.0
        ];

        try {
            // Intentar obtener métricas reales
            // Los métodos count pueden no estar implementados aún
        } catch (\Exception $e) {
            // Usar valores por defecto
        }

        // Alertas y pendientes
        $alertas = [];

        // Órdenes recientes
        $ordenesRecientes = [];

        return $this->render('pages/produccion/index', [
            'title' => 'Operaciones de Producción',
            'metricas' => $metricas,
            'alertas' => $alertas,
            'ordenes_recientes' => $ordenesRecientes
        ]);
    }
}
