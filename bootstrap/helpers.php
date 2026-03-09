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

if (function_exists('app_general_settings') === false) {
    function app_general_settings(bool $reload = false): array
    {
        static $cached = null;

        if ($reload || !is_array($cached)) {
            try {
                $model = new \App\Models\ConfiguracionGeneral();
                $cached = $model->getSettings();
            } catch (\Throwable $exception) {
                $cached = \App\Models\ConfiguracionGeneral::defaults();
            }
        }

        return $cached;
    }
}

if (function_exists('app_round_decimal') === false) {
    function app_round_decimal(float $value, int $decimals, string $mode = 'half_up'): float
    {
        $factor = 10 ** max(0, $decimals);

        if ($mode === 'truncate') {
            if ($value >= 0) {
                return floor($value * $factor) / $factor;
            }

            return ceil($value * $factor) / $factor;
        }

        if ($mode === 'half_down') {
            return round($value, $decimals, PHP_ROUND_HALF_DOWN);
        }

        if ($mode === 'half_even') {
            return round($value, $decimals, PHP_ROUND_HALF_EVEN);
        }

        return round($value, $decimals, PHP_ROUND_HALF_UP);
    }
}

if (function_exists('app_format_number') === false) {
    function app_format_number($value, ?int $decimals = null): string
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        $settings = app_general_settings();
        $usedDecimals = $decimals ?? (int) ($settings['decimal_places'] ?? 4);
        $usedDecimals = max(0, min(10, $usedDecimals));
        $roundingMode = (string) ($settings['rounding_mode'] ?? 'half_up');

        $rounded = app_round_decimal((float) $value, $usedDecimals, $roundingMode);

        $decimalSeparator = (string) ($settings['decimal_separator'] ?? ',');
        $thousandSeparator = (string) ($settings['thousand_separator'] ?? '.');

        $formatted = number_format($rounded, $usedDecimals, $decimalSeparator, $thousandSeparator);
        $trimmed = rtrim(rtrim($formatted, '0'), $decimalSeparator);

        return $trimmed === '-0' ? '0' : $trimmed;
    }
}

if (function_exists('app_decimal_step') === false) {
    function app_decimal_step(): string
    {
        $settings = app_general_settings();
        $decimals = (int) ($settings['decimal_places'] ?? 4);
        $decimals = max(1, min(10, $decimals));

        return number_format(1 / (10 ** $decimals), $decimals, '.', '');
    }
}

if (function_exists('app_format_datetime') === false) {
    function app_format_datetime($value, bool $includeTime = true): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            $date = $value;
        } else {
            try {
                $date = new DateTimeImmutable((string) $value);
            } catch (\Throwable $exception) {
                return (string) $value;
            }
        }

        $settings = app_general_settings();
        $dateFormat = (string) ($settings['date_format'] ?? 'd/m/Y');
        $timeFormat = (string) ($settings['time_format'] ?? 'H:i');

        $pattern = $includeTime ? trim($dateFormat . ' ' . $timeFormat) : $dateFormat;
        return $date->format($pattern);
    }
}
