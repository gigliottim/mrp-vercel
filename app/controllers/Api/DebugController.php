<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Database\DatabaseManager;
use App\Core\Auth\AuthManager;
use App\Core\Auth\TenantContext;

/**
 * DebugController - Endpoints para diagnóstico
 */
final class DebugController extends Controller
{
    /**
     * GET /api/v1/debug/tenant
     * Muestra info del tenant actual
     */
    public function tenant(Request $request): Response
    {
        @session_start();

        $sessionTenant = AuthManager::tenant();
        $contextTenant = TenantContext::get();

        return $this->json([
            'session_id' => session_id(),
            'session_tenant' => $sessionTenant,
            'context_tenant' => $contextTenant,
            'session_data' => $_SESSION ?? [],
        ]);
    }

    /**
     * GET /api/v1/debug/databases
     * Lista bases de datos disponibles
     */
    public function databases(Request $request): Response
    {
        try {
            $config = config('database.connections.tenant');

            // Conectar a postgres
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=postgres',
                $config['host'],
                $config['port']
            );

            $pdo = new \PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );

            $stmt = $pdo->query("
                SELECT datname
                FROM pg_database
                WHERE datistemplate = false
                ORDER BY datname
            ");

            $databases = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            return $this->json([
                'success' => true,
                'config' => $config,
                'databases' => $databases,
                'tenant_databases' => array_filter($databases, fn($db) => str_starts_with($db, 'mrp_tenant')),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/debug/companies
     * Muestra configuración de companies
     */
    public function companies(Request $request): Response
    {
        try {
            $conn = DatabaseManager::connection('mrp_auth');

            $stmt = $conn->query("
                SELECT c.id, c.name, c.slug, c.status,
                       cd.database_name, cd.host, cd.port, cd.username
                FROM companies c
                LEFT JOIN company_databases cd ON cd.company_id = c.id
                ORDER BY c.id
            ");

            $companies = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->json([
                'success' => true,
                'companies' => $companies,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
