<?php

declare(strict_types=1);

namespace App\Core\Config;

use RuntimeException;

final class Config
{
    private static array $items = [];
    private static bool $loaded = false;

    public static function load(string $configPath): void
    {
        if (self::$loaded) {
            return;
        }

        if (is_dir($configPath) === false) {
            throw new RuntimeException(sprintf('Config directory not found: %s', $configPath));
        }

        $files = glob($configPath . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $key = basename($file, '.php');
            self::$items[$key] = require $file;
        }

        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (is_array($value) === false || array_key_exists($segment, $value) === false) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, $value): void
    {
        $segments = explode('.', $key);
        $reference = &self::$items;

        foreach ($segments as $segment) {
            if (isset($reference[$segment]) === false || is_array($reference[$segment]) === false) {
                $reference[$segment] = [];
            }
            $reference = &$reference[$segment];
        }

        $reference = $value;
    }

    public static function has(string $key): bool
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (is_array($value) === false || array_key_exists($segment, $value) === false) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }
}
