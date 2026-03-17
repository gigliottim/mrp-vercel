<?php

declare(strict_types=1);

namespace App\Services;

final class DiagnosticsService
{
    public function run(): array
    {
        return [
            'environment' => [
                'php_version' => PHP_VERSION,
                'app_env' => (string) env('APP_ENV', 'production'),
                'app_debug' => (bool) env('APP_DEBUG', false),
            ],
            'paths' => [
                'base' => base_path(),
                'storage' => base_path('storage'),
                'public' => base_path('public'),
            ],
            'security' => [
                'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'htaccess_root' => file_exists(base_path('.htaccess')),
                'htaccess_public' => file_exists(base_path('public/.htaccess')),
            ],
        ];
    }
}
