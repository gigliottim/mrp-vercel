<?php

/**
 * Test rápido para verificar login del usuario demo
 */

require_once __DIR__ . '/../bootstrap/autoload.php';

use App\Core\Support\Env;
use App\Core\Config\Config;
use App\Services\AuthService;

// Cargar configuración
Env::load(base_path('.env'));
Config::load(base_path('config'));

echo "=== Test de Autenticación Usuario Demo ===\n\n";

try {
    $authService = new AuthService();

    echo "Intentando autenticar:\n";
    echo "  Tenant: demo\n";
    echo "  Email: demo@mrp.local\n";
    echo "  Password: demo123\n\n";

    $result = $authService->attempt('demo', 'demo@mrp.local', 'demo123');

    echo "✓ Autenticación exitosa!\n\n";
    echo "Datos del usuario:\n";
    echo "  ID: {$result['user']['id']}\n";
    echo "  Nombre: {$result['user']['name']}\n";
    echo "  Email: {$result['user']['email']}\n\n";

    echo "Datos del tenant:\n";
    echo "  ID: {$result['tenant']['id']}\n";
    echo "  Nombre: {$result['tenant']['name']}\n";
    echo "  Slug: {$result['tenant']['slug']}\n\n";

    echo "Base de datos del tenant:\n";
    echo "  Host: {$result['tenant']['database']['host']}\n";
    echo "  Database: {$result['tenant']['database']['name']}\n\n";

    echo "Permisos (" . count($result['permissions']) . " total):\n";
    foreach (array_slice($result['permissions'], 0, 5) as $permission) {
        echo "  - {$permission}\n";
    }
    if (count($result['permissions']) > 5) {
        echo "  ... y " . (count($result['permissions']) - 5) . " más\n";
    }

    echo "\n=== Test completado exitosamente ===\n";
} catch (Exception $e) {
    echo "✗ Error: {$e->getMessage()}\n";
    exit(1);
}
