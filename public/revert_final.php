<?php

/**
 * Revertir company_databases a 'mrp'
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database\DatabaseManager;

echo "<h2>🔄 Revertir company_databases</h2><hr>";

try {
    $connAuth = DatabaseManager::connection('mrp_auth');

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

    echo "<h3>🔧 Ejecutando UPDATE...</h3>";
    $stmt = $connAuth->exec("
        UPDATE company_databases
        SET database_name = 'mrp'
        WHERE database_name = 'mrp_tenant_demo'
    ");

    echo "<p style='color:green;font-weight:bold;'>✅ Filas actualizadas: $stmt</p>";

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

    DatabaseManager::clear();

    if ($after[0]['database_name'] === 'mrp') {
        echo "<div style='background:#d4edda;padding:20px;border-radius:10px;'>";
        echo "<h3 style='color:#155724;'>✅ ¡REVERTIDO EXITOSAMENTE!</h3>";
        echo "<p><strong>Siguiente paso:</strong></p>";
        echo "<ol>";
        echo "<li>Ir a <a href='http://localhost/mrp/login'>Login</a></li>";
        echo "<li>Iniciar sesión con tu usuario</li>";
        echo "<li>Probar el buscador en <a href='http://localhost/mrp/productos/maestro'>productos/maestro</a></li>";
        echo "</ol>";
        echo "</div>";

        echo "<script>setTimeout(() => { window.location.href = 'http://localhost/mrp/login'; }, 3000);</script>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ Error: {$e->getMessage()}</p>";
}
