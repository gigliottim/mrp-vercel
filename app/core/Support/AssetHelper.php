<?php

declare(strict_types=1);

namespace App\Core\Support;

final class AssetHelper
{
    private static ?string $prefix = null;

    public static function css(string $path): string
    {
        return self::buildPath('assets/css/' . ltrim($path, '/'));
    }

    public static function js(string $path): string
    {
        return self::buildPath('assets/js/' . ltrim($path, '/'));
    }

    public static function image(string $path): string
    {
        return self::buildPath('assets/img/' . ltrim($path, '/'));
    }

    public static function getBootstrap(string $type = 'css'): string
    {
        $version = config('cdn.cdn.versions.bootstrap', '5.3.7');
        $base = 'CDN/bootstrap/' . $version;
        $path = $type === 'js' ? $base . '/js/bootstrap.bundle.min.js' : $base . '/css/bootstrap.min.css';
        return self::buildPath($path);
    }

    public static function getFontAwesome(): string
    {
        $version = config('cdn.cdn.versions.fontawesome', '6.7.2');
        return self::buildPath('CDN/font-awesome/' . $version . '/css/all.min.css');
    }

    public static function getAlpineJS(): string
    {
        $version = config('cdn.cdn.versions.alpinejs', '3.14.9');
        return self::buildPath('CDN/alpinejs/' . $version . '/alpine.min.js');
    }

    private static function buildPath(string $path): string
    {
        $prefix = self::detectPrefix();
        $normalized = '/' . ltrim($path, '/');
        return $prefix === '' ? $normalized : rtrim($prefix, '/') . $normalized;
    }

    private static function detectPrefix(): string
    {
        if (self::$prefix !== null) {
            return self::$prefix;
        }

        // Si hay configuración explícita en el archivo de configuración, usarla
        $configured = config('app.asset_prefix', null);
        if ($configured !== null && $configured !== '') {
            return self::$prefix = '/' . trim($configured, '/');
        }

        // Si la configuración es explícitamente '', no usar prefijo
        if ($configured === '') {
            return self::$prefix = '';
        }

        // Si llegamos aquí, usar detección automática
        // En Apache con rewrite, SCRIPT_NAME siempre será /public/index.php
        // pero los assets deben servirse SIN el prefijo /public
        // porque Apache ya maneja la redirección
        return self::$prefix = '';
    }
}
