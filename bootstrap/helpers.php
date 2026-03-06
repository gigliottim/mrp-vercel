<?php

use App\Core\Support\Env;
use App\Core\Config\Config;
use App\Core\View\View;

if (function_exists('base_path') === false) {
    function base_path(string $path = ''): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (function_exists('env') === false) {
    function env(string $key, $default = null)
    {
        return Env::get($key, $default);
    }
}

if (function_exists('config') === false) {
    function config(string $key, $default = null)
    {
        return Config::get($key, $default);
    }
}

if (function_exists('view') === false) {
    function view(string $template, array $data = [], ?string $layout = null): void
    {
        View::render($template, $data, $layout);
    }
}

if (function_exists('logger') === false) {
    function logger(): App\Core\Logging\Logger
    {
        return App\Core\Logging\Logger::getInstance();
    }
}

if (function_exists('url') === false) {
    function url(string $path = ''): string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $path = ltrim($path, '/');
        return $path === '' ? $baseUrl : $baseUrl . '/' . $path;
    }
}

if (function_exists('esc') === false) {
    function esc($value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
