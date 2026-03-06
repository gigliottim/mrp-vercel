<?php

/**
 * Test directo del SearchController
 * http://localhost/mrp/api/test-search-direct.php
 */

require_once __DIR__ . '/../../bootstrap/app.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Usar SessionManager
    \App\Core\Support\SessionManager::start();

    echo "1. Sesión iniciada\n";
    echo "   - session_name: " . session_name() . "\n";
    echo "   - session_id: " . session_id() . "\n";
    echo "   - auth_exists: " . (isset($_SESSION['auth']) ? 'true' : 'false') . "\n\n";

    // Crear SearchController
    echo "2. Creando SearchController...\n";
    $controller = new \App\Controllers\Api\SearchController();
    echo "   ✅ SearchController creado\n\n";

    // Verificar TenantContext
    $tenant = \App\Core\Auth\TenantContext::get();
    echo "3. TenantContext:\n";
    echo "   " . json_encode($tenant, JSON_PRETTY_PRINT) . "\n\n";

    // Simular request
    $requestData = [
        'query' => 'motor',
        'filters' => [],
        'limit' => 5,
        'format' => 'detailed'
    ];

    $request = new \App\Core\Http\Request(
        'POST',
        '/api/v1/search/variantes',
        [],           // query
        $requestData, // body
        [],           // headers
        []            // server
    if ($logs) {
        echo "   Logs capturados:\n";
        echo "   " . $logs . "\n\n";
    }

    echo "6. Respuesta:\n";
    echo "   - Status: " . $response->statusCode . "\n";
    echo "   - Body: " . $response->body . "\n";
} catch (\Throwable $e) {
    echo "\n❌ ERROR:\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Trace:\n";
    foreach (explode("\n", $e->getTraceAsString()) as $line) {
        echo "      $line\n";
    }
}
