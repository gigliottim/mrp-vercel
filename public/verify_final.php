<?php

/**
 * Verificar configuración final
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database\DatabaseManager;
use App\Core\Auth\AuthManager;
use App\Core\Auth\TenantContext;

echo "<h2>✅ Verificación Final</h2><hr>";

echo "<h3>1️⃣ Configuración .env</h3>";
echo "<pre>";
echo "DB_TENANT_DATABASE = " . env('DB_TENANT_DATABASE') . "\n";
echo "</pre>";

echo "<h3>2️⃣ company_databases</h3>";
try {
    $connAuth = DatabaseManager::connection('mrp_auth');
    $stmt = $connAuth->query("
        SELECT c.slug, cd.database_name, cd.host
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

echo "<h3>3️⃣ Probar conexión a tenant</h3>";
try {
    @session_start();
    $tenant = AuthManager::tenant();

    if ($tenant) {
        echo "<p style='color:green;'>✅ Tenant cargado desde sesión</p>";
        echo "<pre>";
        print_r($tenant);
        echo "</pre>";

        // Intentar conectar usando datos del tenant
        TenantContext::set($tenant);
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

        // Probar query
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM variantes");
        $count = $stmt->fetch();

        echo "<p style='color:green;font-weight:bold;font-size:18px;'>✅✅✅ CONEXIÓN EXITOSA - {$count['total']} variantes</p>";

        echo "<div style='background:#d4edda;padding:20px;border-radius:10px;margin-top:20px;'>";
        echo "<h3 style='color:#155724;'>🎉 TODO LISTO</h3>";
        echo "<p>Ahora el buscador debería funcionar correctamente.</p>";
        echo "<p><strong>Prueba en:</strong></p>";
        echo "<ul>";
        echo "<li><a href='http://localhost/mrp/productos/maestro'>productos/maestro</a></li>";
        echo "<li><a href='http://localhost/mrp/productos/partes'>productos/partes</a></li>";
        echo "</ul>";
        echo "</div>";

        echo "<script>setTimeout(() => { window.location.href = 'http://localhost/mrp/productos/maestro'; }, 3000);</script>";
    } else {
        echo "<p style='color:orange;'>⚠️ No hay tenant en sesión. Inicia sesión primero.</p>";
        echo "<p><a href='http://localhost/mrp/login'>Ir a Login</a></p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ Error: {$e->getMessage()}</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
