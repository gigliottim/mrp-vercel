<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth\AuthManager;
use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\OrdenProduccion;
use App\Models\RutaProduccion;
use App\Repositories\Produccion\OrdenProduccionRepository;
use App\Repositories\Produccion\RutaProduccionRepository;
use App\Services\DashboardSummaryService;
use App\Services\DiagnosticsService;
use App\Services\Produccion\OrdenProduccionService;

final class HomeController extends Controller
{
    private DiagnosticsService $diagnostics;
    private ?OrdenProduccionService $ordenesService;
    private DashboardSummaryService $summaryService;

    public function __construct(?DiagnosticsService $diagnostics = null, ?OrdenProduccionService $ordenesService = null, ?DashboardSummaryService $summaryService = null)
    {
        $this->diagnostics = $diagnostics ?? new DiagnosticsService();
        $this->ordenesService = $ordenesService;
        $this->summaryService = $summaryService ?? new DashboardSummaryService();
    }

    public function index(Request $request): Response
    {
        $data = [
            'title' => 'MRP · Control de Produccion e Inventario',
        ];

        return $this->render('pages/home', $data, 'layouts/public');
    }

    public function dashboard(Request $request): Response
    {
        if (AuthManager::check() === false) {
            return Response::redirect(url('login'));
        }

        $dashboard = [
            'por_estado' => [],
            'atrasadas' => 0,
            'urgentes' => 0,
            'total_activas' => 0,
            'operativas_hoy' => 0,
            'cumplimiento' => 0,
        ];

        try {
            if ($this->ordenesService === null) {
                $this->ordenesService = new OrdenProduccionService(
                    new OrdenProduccionRepository(new OrdenProduccion()),
                    new RutaProduccionRepository(new RutaProduccion())
                );
            }

            $raw = $this->ordenesService->getDashboard();
            $dashboard['por_estado'] = $raw['por_estado'] ?? [];
            $dashboard['atrasadas'] = (int)($raw['atrasadas'] ?? 0);
            $dashboard['urgentes'] = (int)($raw['urgentes'] ?? 0);

            foreach ($dashboard['por_estado'] as $row) {
                $estado = (string)($row['estado'] ?? '');
                $cantidad = (int)($row['cantidad'] ?? 0);
                $dashboard['total_activas'] += $cantidad;

                if (in_array($estado, ['liberada', 'en_proceso'], true)) {
                    $dashboard['operativas_hoy'] += $cantidad;
                }
            }

            if ($dashboard['total_activas'] > 0) {
                $sinAtraso = max(0, $dashboard['total_activas'] - $dashboard['atrasadas']);
                $dashboard['cumplimiento'] = (int)round(($sinAtraso / $dashboard['total_activas']) * 100);
            }

            $dashboard = array_merge($dashboard, $this->summaryService->getSummary());
        } catch (\Throwable $e) {
            // Si falla el acceso a datos, se mantiene dashboard base sin romper la pantalla.
        }

        return $this->render('pages/dashboard', [
            'title' => 'MRP · Panel Inicial',
            'dashboard' => $dashboard,
            'diagnostics' => $this->diagnostics->run(),
        ]);
    }

    public function menu(Request $request): Response
    {
        if (AuthManager::check() === false) {
            return Response::redirect(url('login'));
        }

        return $this->render('pages/menu', [
            'title' => 'MRP · Mapa del sistema',
            'sections' => AuthManager::sidebarTree(),
        ]);
    }
}
