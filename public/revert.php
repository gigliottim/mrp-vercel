<?php

/**
 * Script de reversión automática
 * Ejecutar: http://localhost/mrp/revert.php
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database\DatabaseManager;

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Revertir BD</title>";
echo "<style>body{font-family:system-ui;max-width:800px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;border:1px solid #c3e6cb;}";
echo ".info{background:#d1ecf1;color:#0c5460;padding:15px;border-radius:5px;border:1px solid #bee5eb;margin-top:20px;}";
echo "</style></head><body>";

echo "<h1>🔄 Revertir Database Name</h1>";
echo "<hr>";

try {
    $connAuth = DatabaseManager::connection('mrp_auth');

    // Ver estado actual
    echo "<h3>📊 Estado ANTES:</h3>";
    $stmt = $connAuth->query("
        SELECT c.slug, cd.database_name, cd.host
        FROM company_databases cd
        JOIN companies c ON cd.company_id = c.id
    ");
    $before = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($before);
    echo "</pre>";

    // Revertir
    echo "<h3>🔧 Ejecutando UPDATE...</h3>";
    $stmt = $connAuth->prepare("
        UPDATE company_databases
        SET database_name = 'mrp'
        WHERE database_name = 'mrp_tenant_demo'
    ");
    $stmt->execute();
    $updated = $stmt->rowCount();

    echo "<div class='success'>";
    echo "<strong>✅ Revertido exitosamente</strong><br>";
    echo "Filas actualizadas: $updated";
    echo "</div>";

    // Ver estado final
    echo "<h3>📊 Estado DESPUÉS:</h3>";
    $stmt = $connAuth->query("
        SELECT c.slug, cd.database_name, cd.host
        FROM company_databases cd
        JOIN companies c ON cd.company_id = c.id
    ");
    $after = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($after);
    echo "</pre>";

    // Limpiar caché
    DatabaseManager::clear();

    echo "<div class='info'>";
    echo "<strong>🔄 Siguiente paso:</strong><br>";
    echo "Recarga la página: <a href='http://localhost/mrp/productos/maestro'>productos/maestro</a><br>";
    echo "O: <a href='http://localhost/mrp/productos/partes'>productos/partes</a>";
    echo "</div>";

    echo "<script>setTimeout(() => { if(confirm('¿Recargar página principal?')) window.location.href = 'http://localhost/mrp/productos/maestro'; }, 2000);</script>";
} catch (Exception $e) {
    echo "<div style='background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;'>";
    echo "<strong>❌ Error:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";
