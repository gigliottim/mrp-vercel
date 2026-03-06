<?php

declare(strict_types=1);

namespace App\Core\Support;

final class Env
{
    private static array $variables = [];

    public static function load(string $path): void
    {
        if (file_exists($path) === false) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $line, 2), 2, null);
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $value = $value === null ? '' : trim($value);
            self::$variables[$key] = self::sanitize($value);
            putenv(sprintf('%s=%s', $key, self::$variables[$key]));
        }
    }

    public static function get(string $key, $default = null)
    {
        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }

        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return self::sanitize($value);
    }

    private static function sanitize(string $value)
    {
        $value = trim($value, "\"' ");
        $lower = strtolower($value);
        if (in_array($lower, ['true', '(true)'], true)) {
            return true;
        }
        if (in_array($lower, ['false', '(false)'], true)) {
            return false;
        }
        if (in_array($lower, ['null', '(null)'], true)) {
            return null;
        }
        return $value;
    }
}
