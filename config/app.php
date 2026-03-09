<?php

return [
    'name' => env('APP_NAME', 'MRP'),
    'version' => '15.21.0',
    'build' => 3989,
    'url' => env('APP_URL', 'http://localhost'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => 'es_AR',
    'session_name' => env('SESSION_NAME', 'MRPSESSID'),
    'asset_prefix' => parse_url(env('APP_URL', 'http://localhost'), PHP_URL_PATH) ?? '',
];




























































































































