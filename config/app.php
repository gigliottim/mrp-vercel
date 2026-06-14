<?php

return [
    'name' => env('APP_NAME', 'MRP'),
    'version' => '48.0.2',
    'build' => 4216,
    'url' => env('APP_URL', 'http://localhost'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'timezone' => 'America/Argentina/Buenos_Aires',
    'locale' => 'es_AR',
    'session_name' => env('SESSION_NAME', 'MRPSESSID'),
    'asset_prefix' => parse_url(env('APP_URL', 'http://localhost'), PHP_URL_PATH) ?? '',
];







































