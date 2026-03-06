<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Database\DatabaseManager;

final class RevertController extends Controller
{
    /**
     * GET /api/v1/debug/revert-database-name
     * Revierte el database_name a 'mrp'
     */
    public function revertDatabaseName(Request $request): Response
    {
        try {
            $connAuth = DatabaseManager::connection('mrp_auth');

            // Ver estado actual
            $stmt = $connAuth->query("
                SELECT c.slug, cd.database_name, cd.host
                FROM company_databases cd
                JOIN companies c ON cd.company_id = c.id
            ");
            $before = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Revertir a 'mrp'
            $stmt = $connAuth->prepare("
                UPDATE company_databases
                SET database_name = 'mrp'
                WHERE database_name = 'mrp_tenant_demo'
            ");
            $stmt->execute();
            $updated = $stmt->rowCount();

            // Ver estado actualizado
            $stmt = $connAuth->query("
                SELECT c.slug, cd.database_name, cd.host
                FROM company_databases cd
                JOIN companies c ON cd.company_id = c.id
            ");
            $after = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Limpiar caché
            DatabaseManager::clear();

            return $this->json([
                'success' => true,
                'message' => "✅ Revertido: 'mrp_tenant_demo' → 'mrp'",
                'updated_rows' => $updated,
                'before' => $before,
                'after' => $after,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
