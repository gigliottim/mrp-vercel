<?php

/**
 * Debug completo de la búsqueda
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Auth\AuthManager;
use App\Core\Auth\TenantContext;
use App\Core\Database\DatabaseManager;

@session_start();

echo "<h2>🔍 Debug: Estado actual completo</h2><hr>";

// 1. Sesión
echo "<h3>1️⃣ Sesión</h3>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Tenant en sesión:\n";
$sessionTenant = AuthManager::tenant();
print_r($sessionTenant);
echo "</pre>";

// 2. TenantContext
echo "<h3>2️⃣ TenantContext (después de set)</h3>";
if ($sessionTenant) {
    TenantContext::set($sessionTenant);
}
echo "<pre>";
print_r(TenantContext::get());
echo "</pre>";

// 3. Company databases en BD
echo "<h3>3️⃣ company_databases (en BD)</h3>";
try {
    $connAuth = DatabaseManager::connection('mrp_auth');
    $stmt = $connAuth->query("
        SELECT c.slug, cd.database_name, cd.host, cd.port, cd.username
        FROM company_databases cd
        JOIN companies c ON cd.company_id = c.id
    ");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: {$e->getMessage()}</p>";
}

// 4. Intentar resolver conexión como SearchController
echo "<h3>4️⃣ Simulación SearchController</h3>";
try {
    $tenant = TenantContext::get();

    if ($tenant === null || empty($tenant['database']['name'])) {
        echo "<p style='color:orange;'>⚠️ No hay tenant o database_name está vacío</p>";
        echo "<p>Usando fallback: DatabaseManager::connection('tenant')</p>";

        $config = config('database.connections.tenant');
        echo "<pre>";
        print_r($config);
        echo "</pre>";

        $conn = DatabaseManager::connection('tenant');
        echo "<p style='color:green;'>✅ Conexión fallback exitosa</p>";
    } else {
        echo "<p style='color:green;'>✅ Tenant encontrado</p>";
        echo "<pre>";
        echo "Database name: {$tenant['database']['name']}\n";
        echo "Host: {$tenant['database']['host']}\n";
        echo "Port: {$tenant['database']['port']}\n";
        echo "Username: {$tenant['database']['username']}\n";
        echo "</pre>";

        $config = config('database.connections.tenant');
        $overrides = $tenant['database'];

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $overrides['host'] ?? $config['host'],
            $overrides['port'] ?? $config['port'],
            $overrides['name'] ?? $config['database']
        );

        echo "<h4>DSN construido:</h4>";
        echo "<pre>$dsn</pre>";

        $pdo = new PDO(
            $dsn,
            $overrides['username'] ?? $config['username'],
            $overrides['password'] ?? $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        echo "<p style='color:green;'>✅ Conexión exitosa</p>";

        // Probar query
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM variantes");
        $count = $stmt->fetch();

        echo "<p style='color:green;font-size:18px;font-weight:bold;'>✅✅✅ Query exitosa: {$count['total']} variantes</p>";
    }
} catch (Exception $e) {
    echo "<div style='background:#f8d7da;padding:20px;border-radius:10px;'>";
    echo "<h4 style='color:#721c24;'>❌ ERROR</h4>";
    echo "<p><strong>Mensaje:</strong> {$e->getMessage()}</p>";
    echo "<p><strong>Código:</strong> {$e->getCode()}</p>";
    echo "<pre style='font-size:11px;'>{$e->getTraceAsString()}</pre>";
    echo "</div>";
}

// 5. Verificar .env
echo "<h3>5️⃣ Configuración .env</h3>";
echo "<pre>";
echo "DB_TENANT_DATABASE = " . env('DB_TENANT_DATABASE') . "\n";
echo "DB_TENANT_HOST = " . env('DB_TENANT_HOST') . "\n";
echo "DB_TENANT_PORT = " . env('DB_TENANT_PORT') . "\n";
echo "DB_TENANT_USERNAME = " . env('DB_TENANT_USERNAME') . "\n";
echo "</pre>";
