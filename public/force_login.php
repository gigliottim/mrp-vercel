<?php

/**
 * Forzar un login completo para demo-manufacturing
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Services\AuthService;
use App\Core\Auth\AuthManager;

echo "<h2>🔐 Re-Login Forzado</h2><hr>";

try {
    $authService = new AuthService();

    echo "<h3>1️⃣ Validando usuario admin@demo-mrp.test...</h3>";

    // Validar usuario
    $result = $authService->validateUser('admin@demo-mrp.test', 'demo123');

    echo "<pre>";
    echo "Usuario encontrado: {$result['user']['name']}\n";
    echo "Companies disponibles: " . count($result['companies']) . "\n";
    print_r($result['companies']);
    echo "</pre>";

    echo "<h3>2️⃣ Login con tenant 'demo-manufacturing'...</h3>";

    // Login con primer tenant
    $tenantSlug = $result['companies'][0]['slug'];
    $finalResult = $authService->loginWithTenant($result['user'], $tenantSlug);

    echo "<pre>";
    echo "Tenant seleccionado:\n";
    print_r($finalResult['tenant']);
    echo "</pre>";

    echo "<h3>3️⃣ Guardando en sesión con AuthManager::login()...</h3>";

    AuthManager::login($finalResult);

    echo "<p style='color:green;font-weight:bold;'>✅ Login ejecutado</p>";

    echo "<h3>4️⃣ Verificando sesión...</h3>";

    $sessionTenant = AuthManager::tenant();

    if ($sessionTenant !== null && isset($sessionTenant['database']['name'])) {
        echo "<div style='background:#d4edda;padding:20px;border-radius:10px;'>";
        echo "<h3 style='color:#155724;'>✅✅✅ ¡ÉXITO!</h3>";
        echo "<p><strong>Tenant en sesión:</strong></p>";
        echo "<pre>";
        print_r($sessionTenant);
        echo "</pre>";
        echo "<p><strong>Database name:</strong> {$sessionTenant['database']['name']}</p>";
        echo "<p style='margin-top:20px;'><a href='http://localhost/mrp/productos/maestro' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>🚀 IR A PRODUCTOS/MAESTRO</a></p>";
        echo "</div>";

        echo "<script>setTimeout(() => { window.location.href = 'http://localhost/mrp/productos/maestro'; }, 3000);</script>";
    } else {
        echo "<p style='color:red;font-weight:bold;'>❌ Tenant NO se guardó en sesión</p>";
        echo "<pre>";
        print_r($_SESSION);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<div style='background:#f8d7da;padding:20px;border-radius:10px;'>";
    echo "<h3 style='color:#721c24;'>❌ Error</h3>";
    echo "<p>{$e->getMessage()}</p>";
    echo "<pre>{$e->getTraceAsString()}</pre>";
    echo "</div>";
}
