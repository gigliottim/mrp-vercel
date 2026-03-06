<?php

/**
 * Debug SearchController
 */

session_start();

echo "<h2>Debug SearchController - Multi-tenant</h2>";
echo "<hr>";

// 1. Verificar sesión
echo "<h3>1. Estado de la sesión</h3>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session status: " . session_status() . " (1=disabled, 2=active)\n";
echo "\n<strong>$_SESSION:</strong>\n";
print_r($_SESSION);
echo "</pre>";

// 2. Verificar AuthManager
echo "<h3>2. AuthManager::tenant()</h3>";
require __DIR__ . '/../bootstrap/app.php';

use App\Core\Auth\AuthManager;
use App\Core\Auth\TenantContext;

$tenant = AuthManager::tenant();
echo "<pre>";
print_r($tenant);
echo "</pre>";

// 3. Verificar TenantContext
echo "<h3>3. TenantContext después de set()</h3>";
if ($tenant !== null) {
    TenantContext::set($tenant);
    echo "<pre>";
    print_r(TenantContext::get());
    echo "</pre>";

    // 4. Verificar base de datos del tenant
    echo "<h3>4. Intentar conectar a BD del tenant</h3>";
    echo "<pre>";
    echo "Database name: " . ($tenant['database']['name'] ?? 'N/D') . "\n";
    echo "Database host: " . ($tenant['database']['host'] ?? 'N/D') . "\n";
    echo "</pre>";

    // 5. Probar conexión
    try {
        $config = config('database.connections.tenant');
        $overrides = $tenant['database'];

        echo "<h3>5. Configuración de conexión</h3>";
        echo "<pre>";
        echo "Base config:\n";
        print_r($config);
        echo "\nTenant overrides:\n";
        print_r($overrides);

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $overrides['host'] ?? $config['host'],
            $overrides['port'] ?? $config['port'],
            $overrides['name'] ?? $config['database']
        );

        echo "\nDSN: $dsn\n";
        echo "</pre>";

        $pdo = new PDO(
            $dsn,
            $overrides['username'] ?? $config['username'],
            $overrides['password'] ?? $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        echo "<h3>6. ✅ Conexión exitosa</h3>";

        // Probar query
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM variantes");
        $result = $stmt->fetch();

        echo "<pre>";
        echo "Total variantes: " . $result['total'] . "\n";
        echo "</pre>";
    } catch (Exception $e) {
        echo "<h3>❌ Error de conexión</h3>";
        echo "<pre style='color: red;'>";
        echo "Error: " . $e->getMessage() . "\n";
        echo "Code: " . $e->getCode() . "\n";
        echo "\nStack trace:\n" . $e->getTraceAsString();
        echo "</pre>";
    }
} else {
    echo "<p style='color: red;'>No hay tenant en la sesión</p>";
}
