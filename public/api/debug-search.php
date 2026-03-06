<?php

/**
 * Debug endpoint para diagnóstico de SearchController
 * Acceder desde: http://localhost/mrp/api/debug-search.php
 */

require_once __DIR__ . '/../../bootstrap/app.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    echo json_encode([
        'step' => 'Iniciando debug',
        'session_id' => session_id(),
        'session_status' => session_status(),
        'session_started' => session_status() === PHP_SESSION_ACTIVE
    ], JSON_PRETTY_PRINT);
    echo "\n\n---\n\n";

    // Iniciar sesión si no está activa
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    echo json_encode([
        'step' => 'Sesión iniciada',
        'session_id' => session_id(),
        'auth_exists' => isset($_SESSION['auth']),
        'tenant_exists' => isset($_SESSION['auth']['tenant'])
    ], JSON_PRETTY_PRINT);
    echo "\n\n---\n\n";

    if (isset($_SESSION['auth']['tenant'])) {
        echo json_encode([
            'step' => 'Tenant en sesión',
            'tenant' => $_SESSION['auth']['tenant']
        ], JSON_PRETTY_PRINT);
        echo "\n\n---\n\n";
    }

    // Cargar tenant desde sesión
    $tenant = \App\Core\Auth\AuthManager::tenant();
    echo json_encode([
        'step' => 'Tenant desde AuthManager',
        'tenant' => $tenant
    ], JSON_PRETTY_PRINT);
    echo "\n\n---\n\n";

    // Set tenant context
    if ($tenant) {
        \App\Core\Database\TenantContext::set($tenant);
    }

    echo json_encode([
        'step' => 'TenantContext::get()',
        'context' => \App\Core\Database\TenantContext::get()
    ], JSON_PRETTY_PRINT);
    echo "\n\n---\n\n";

    // Crear instancia de SearchController
    $controller = new \App\Controllers\Api\SearchController();

    echo json_encode([
        'step' => 'SearchController creado',
        'class' => get_class($controller)
    ], JSON_PRETTY_PRINT);
    echo "\n\n---\n\n";

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
        [],
        $requestData,
        [],
        '',
        []
    );

    echo json_encode([
        'step' => 'Request creado',
        'body' => $request->body
    ], JSON_PRETTY_PRINT);
    echo "\n\n---\n\n";

    // Ejecutar búsqueda
    echo json_encode([
        'step' => 'Ejecutando searchVariantes...'
    ], JSON_PRETTY_PRINT);
    echo "\n\n---\n\n";

    $response = $controller->searchVariantes($request);

    echo json_encode([
        'step' => 'Respuesta obtenida',
        'status' => $response->statusCode,
        'data' => json_decode($response->body, true)
    ], JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString())
    ], JSON_PRETTY_PRINT);
}
