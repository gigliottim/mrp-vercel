<?php

declare(strict_types=1);

if (defined('BASE_PATH') === false) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/bootstrap/helpers.php';

$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $relativePath = str_replace('\\', '/', $relativeClass) . '.php';
    $file = $baseDir . $relativePath;

    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // Linux is case-sensitive; resolve case-insensitively for legacy mixed-case folders.
    $parts = explode('/', $relativePath);
    $currentPath = rtrim($baseDir, '/');

    foreach ($parts as $index => $part) {
        $isLast = $index === count($parts) - 1;
        $candidate = $currentPath . '/' . $part;

        if (($isLast && is_file($candidate)) || (!$isLast && is_dir($candidate))) {
            $currentPath = $candidate;
            continue;
        }

        if (!is_dir($currentPath)) {
            return;
        }

        $entries = scandir($currentPath);
        if ($entries === false) {
            return;
        }

        $matched = null;
        foreach ($entries as $entry) {
            if (strcasecmp($entry, $part) === 0) {
                $matched = $entry;
                break;
            }
        }

        if ($matched === null) {
            return;
        }

        $currentPath .= '/' . $matched;
    }

    if (is_file($currentPath)) {
        require_once $currentPath;
    }
});
