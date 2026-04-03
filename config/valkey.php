<?php

declare(strict_types=1);

/**
 * Configuración de Valkey para el Agente AI
 */

return [
    'host' => env('VALKEY_HOST', 'localhost'),
    'port' => (int)env('VALKEY_PORT', 6379),
    'password' => env('VALKEY_PASSWORD'),
    'prefix' => 'mrp:agent:',
    'ttl' => [
        'response' => 3600,  // 1 hora para respuestas de IA
        'session' => 1800,   // 30 minutos para sesiones activas
    ],
    'rate_limit' => [
        'max_requests' => 10,  // 10 requests por ventana
        'window_seconds' => 3600,  // por hora
    ],
];
