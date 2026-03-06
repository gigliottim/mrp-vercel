<?php

/**
 * Session Check - Verificar si las APIs comparten sesión con el navegador
 * Acceder desde: http://localhost/mrp/api/session-check.php
 */

require_once __DIR__ . '/../../bootstrap/app.php';

header('Content-Type: application/json; charset=utf-8');

// Usar SessionManager para configurar nombre correcto
use App\Core\Support\SessionManager;

if (session_status() !== PHP_SESSION_ACTIVE) {
    SessionManager::start();
}

$response = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_status_text' => [
        0 => 'PHP_SESSION_DISABLED',
        1 => 'PHP_SESSION_NONE',
        2 => 'PHP_SESSION_ACTIVE'
    ][session_status()],
    'cookies_received' => $_COOKIE,
    'session_name' => session_name(),
    'session_data' => $_SESSION ?? [],
    'auth_exists' => isset($_SESSION['auth']),
    'tenant_info' => $_SESSION['auth']['tenant'] ?? null,
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
