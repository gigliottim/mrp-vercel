<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Database\DatabaseManager;
use App\Core\Auth\TenantContext;
use App\Services\SearchService;
use App\Repositories\SearchRepository;
use PDO;
use RuntimeException;

/**
 * SearchController
 *
 * Controlador API liviano para búsquedas.
 * Solo orquesta: recibe request → llama service → devuelve JSON.
 * Usa resolución dinámica de conexión multi-tenant.
 */
final class SearchController extends Controller
{
    private SearchService $searchService;
    private PDO $tenantConnection;

    public function __construct()
    {
        // Cargar tenant desde sesión si existe (para APIs sin autenticación obligatoria)
        $this->loadTenantFromSession();

        // Inyección de dependencias con conexión multi-tenant
        $this->tenantConnection = $this->resolveConnection();
        $repository = new SearchRepository($this->tenantConnection);
        $this->searchService = new SearchService($repository);
    }

    /**
     * Carga el tenant en TenantContext si hay sesión activa
     * Esto permite que las APIs funcionen tanto con usuarios logueados como sin sesión
     */
    private function loadTenantFromSession(): void
    {
        // Iniciar sesión usando SessionManager para nombre correcto
        if (session_status() === PHP_SESSION_NONE) {
            \App\Core\Support\SessionManager::start();
        }

        // Solo si no está ya cargado y hay sesión
        if (TenantContext::get() === null && session_status() === PHP_SESSION_ACTIVE) {
            $tenant = \App\Core\Auth\AuthManager::tenant();
            if ($tenant !== null) {
                TenantContext::set($tenant);
            }
        }
    }

    /**
     * Resuelve la conexión a la BD del tenant actual
     * Si no hay tenant (llamada desde API sin sesión), usa conexión default
     */
    private function resolveConnection(): PDO
    {
        $tenant = TenantContext::get();

        // Si no hay tenant activo, usar conexión configurada en .env
        if ($tenant === null || empty($tenant['database']['name'])) {
            return DatabaseManager::connection('tenant');
        }

        $config = config('database.connections.tenant');
        if ($config === null) {
            throw new RuntimeException('Conexión tenant no configurada.');
        }

        // Si hay tenant, usar su BD específica
        $overrides = $tenant['database'];
        $connectionName = 'tenant_' . ($tenant['database']['name'] ?? '');
        $this->configureTenantConnection($connectionName, $config, $overrides);

        return DatabaseManager::connection($connectionName);
    }

    /**
     * Configura dinámicamente la conexión para el tenant
     */
    private function configureTenantConnection(string $name, array $baseConfig, array $overrides): void
    {
        $connections = config('database.connections', []);
        if (isset($connections[$name])) {
            return;
        }

        $connections[$name] = [
            'driver' => $baseConfig['driver'] ?? 'pgsql',
            'host' => $overrides['host'] ?? $baseConfig['host'] ?? '127.0.0.1',
            'port' => $overrides['port'] ?? $baseConfig['port'] ?? '5432',
            'database' => $overrides['name'] ?? $baseConfig['database'],
            'username' => $overrides['username'] ?? $baseConfig['username'],
            'password' => $overrides['password'] ?? $baseConfig['password'],
            'charset' => $baseConfig['charset'] ?? 'utf8',
            'options' => $baseConfig['options'] ?? [],
        ];

        \App\Core\Config\Config::set('database.connections', $connections);
    }

    /**
     * POST /api/v1/search/variantes
     */
    public function searchVariantes(Request $request): Response
    {
        try {
            // Debugging to a file
            file_put_contents(__DIR__ . '/../../../public/debug_search.log', date('Y-m-d H:i:s') . " - Start searchVariantes\n", FILE_APPEND);

            $query = $request->body['query'] ?? '';
            $filters = $request->body['filters'] ?? [];
            $limit = (int) ($request->body['limit'] ?? 10);
            $format = $request->body['format'] ?? 'standard';

            file_put_contents(__DIR__ . '/../../../public/debug_search.log', "Query: $query\n", FILE_APPEND);

            if (empty(trim($query))) {
                return $this->json([
                    'success' => false,
                    'message' => 'El parámetro "query" es requerido',
                    'results' => []
                ], 400);
            }

            $result = $this->searchService->searchVariantes($query, [
                'filters' => $filters,
                'limit' => $limit,
                'format' => $format
            ]);

            return $this->json($result);
        } catch (\Throwable $e) {
            $msg = "Error: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString() . "\n";
            file_put_contents(__DIR__ . '/../../../public/debug_search.log', $msg, FILE_APPEND);

            return $this->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
                'results' => []
            ], 500);
        }
    }

    /**
     * GET /api/v1/search/variantes/:id
     */
    public function getVariante(Request $request, int $id): Response
    {
        $format = $request->query['format'] ?? 'standard';

        $variante = $this->searchService->getVarianteById($id, $format);

        if ($variante === null) {
            return $this->json([
                'success' => false,
                'message' => 'Variante no encontrada'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'result' => $variante
        ]);
    }

    /**
     * POST /api/v1/search/tipos-partes
     */
    public function searchTiposParte(Request $request): Response
    {
        $query = $request->body['query'] ?? '';

        if (empty(trim($query))) {
            return $this->json([
                'success' => false,
                'message' => 'El parámetro "query" es requerido',
                'results' => []
            ], 400);
        }

        $result = $this->searchService->searchTiposParte($query);
        return $this->json($result);
    }

    /**
     * POST /api/v1/search/partes
     */
    public function searchPartes(Request $request): Response
    {
        try {
            $query = $request->body['query'] ?? '';
            $filters = $request->body['filters'] ?? [];
            $limit = (int) ($request->body['limit'] ?? 10);
            $format = $request->body['format'] ?? 'standard';

            if (empty(trim($query))) {
                return $this->json([
                    'success' => false,
                    'message' => 'El parámetro "query" es requerido',
                    'results' => []
                ], 400);
            }

            $result = $this->searchService->searchPartes($query, [
                'filters' => $filters,
                'limit' => $limit,
                'format' => $format
            ]);

            return $this->json($result);
        } catch (\Throwable $e) {
            error_log("💥 SearchController::searchPartes - Exception");
            error_log("  - Message: " . $e->getMessage());
            error_log("  - File: " . $e->getFile() . ":" . $e->getLine());

            return $this->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
                'results' => []
            ], 500);
        }
    }

    /**
     * POST /api/v1/search/centros-trabajo
     */
    public function searchCentrosTrabajo(Request $request): Response
    {
        $query = $request->body['query'] ?? '';

        if (empty(trim($query))) {
            return $this->json([
                'success' => false,
                'message' => 'El parámetro "query" es requerido',
                'results' => []
            ], 400);
        }

        $result = $this->searchService->searchCentrosTrabajo($query);
        return $this->json($result);
    }

    /**
     * GET /api/v1/bom/variantes/{id}
     * Devuelve las BOMs disponibles para una variante (para seleccionar en órdenes).
     */
    public function getBomsByVariante(Request $request, int $id): Response
    {
        try {
            $bom = new \App\Models\Bom($this->tenantConnection);
            $boms = $bom->getAllByVariante($id);

            $items = array_map(static fn(array $b) => [
                'id'                => (int) $b['id'],
                'version'           => (int) $b['version'],
                'activa'            => (bool) $b['activa'],
                'parte_codigo'      => (string) ($b['parte_codigo'] ?? ''),
                'variante_codigo'   => (string) ($b['variante_codigo'] ?? ''),
                'variante_detalle'  => (string) ($b['variante_detalle'] ?? ''),
                'parte_detalle'     => (string) ($b['parte_detalle'] ?? ''),
            ], $boms);

            return $this->json(['success' => true, 'boms' => $items]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage(), 'boms' => []], 500);
        }
    }

    /**
     * GET /api/v1/bom/variantes/:id/nivel1
     * Devuelve los componentes directos (nivel 1) de la BOM activa de una variante.
     */
    public function getBomNivel1(Request $request, int $id): Response
    {
        try {
            $bom    = new \App\Models\Bom($this->tenantConnection);
            $header = $bom->getActiveByVariante($id);

            if ($header === null) {
                return $this->json(['success' => true, 'bom_id' => null, 'items' => []]);
            }

            $detalles = $bom->getDetalles((int) $header['id']);

            $items = array_map(static fn(array $d) => [
                'id'                    => (int) $d['id'],
                'variante_componente_id' => (int) $d['variante_componente_id'],
                'parte_codigo'          => (string) ($d['parte_codigo']       ?? ''),
                'parte_detalle'         => (string) ($d['parte_detalle']      ?? ''),
                'componente_codigo'     => (string) ($d['componente_codigo']  ?? ''),
                'componente_detalle'    => (string) ($d['componente_detalle'] ?? ''),
                'tipo_codigo'           => (string) ($d['tipo_codigo']        ?? ''),
                'cantidad'              => (float)  ($d['cantidad_necesaria'] ?? 0),
                'unidad'                => (string) ($d['unidad_simbolo']     ?? ''),
            ], $detalles);

            return $this->json(['success' => true, 'bom_id' => (int) $header['id'], 'items' => $items]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage(), 'items' => []], 500);
        }
    }
}
