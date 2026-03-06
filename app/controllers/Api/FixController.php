<?php

/**
 * Script para corregir database_name en company_databases
 * Ejecutar: http://localhost/mrp/api/v1/debug/fix-database-name
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Database\DatabaseManager;

final class FixController extends Controller
{
    /**
     * GET /api/v1/debug/fix-database-name
     * Corrige el database_name de 'mrp' a 'mrp_tenant_demo'
     */
    public function fixDatabaseName(Request $request): Response
    {
        try {
            // 1. Conectar a mrp_auth
            $connAuth = DatabaseManager::connection('mrp_auth');

            // 2. Ver estado actual
            $stmt = $connAuth->query("
                SELECT c.slug, cd.database_name, cd.host
                FROM company_databases cd
                JOIN companies c ON cd.company_id = c.id
            ");
            $before = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 3. Verificar qué BDs existen en PostgreSQL
            $configTenant = config('database.connections.tenant');
            $dsnPostgres = sprintf(
                'pgsql:host=%s;port=%s;dbname=postgres',
                $configTenant['host'],
                $configTenant['port']
            );

            $pdoPostgres = new \PDO(
                $dsnPostgres,
                $configTenant['username'],
                $configTenant['password'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdoPostgres->query("
                SELECT datname
                FROM pg_database
                WHERE datistemplate = false
                AND datname LIKE 'mrp_tenant%'
                ORDER BY datname
            ");
            $availableDatabases = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // 4. Determinar BD correcta (preferir mrp_tenant_demo)
            $targetDb = null;
            if (in_array('mrp_tenant_demo', $availableDatabases)) {
                $targetDb = 'mrp_tenant_demo';
            } elseif (!empty($availableDatabases)) {
                $targetDb = $availableDatabases[0];
            }

            if ($targetDb === null) {
                return $this->json([
                    'success' => false,
                    'message' => 'No se encontró ninguna BD mrp_tenant_*',
                    'available_databases' => $availableDatabases,
                ], 400);
            }

            // 5. Actualizar company_databases
            $stmt = $connAuth->prepare("
                UPDATE company_databases
                SET database_name = :target_db
                WHERE database_name = 'mrp'
            ");
            $stmt->execute(['target_db' => $targetDb]);
            $updated = $stmt->rowCount();

            // 6. Ver estado actualizado
            $stmt = $connAuth->query("
                SELECT c.slug, cd.database_name, cd.host
                FROM company_databases cd
                JOIN companies c ON cd.company_id = c.id
            ");
            $after = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 7. Limpiar caché de conexiones
            DatabaseManager::clear();

            return $this->json([
                'success' => true,
                'message' => "✅ Actualizado: 'mrp' → '$targetDb'",
                'updated_rows' => $updated,
                'target_database' => $targetDb,
                'available_databases' => $availableDatabases,
                'before' => $before,
                'after' => $after,
                'action' => 'Por favor, recarga la página y prueba el buscador',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
}
