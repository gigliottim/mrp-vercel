<?php

declare(strict_types=1);

namespace App\Core\Logging;

final class Logger
{
    private static ?self $instance = null;
    private string $logPath;

    private function __construct()
    {
        $this->logPath = base_path('storage/logs/app.log');
        if (is_dir(dirname($this->logPath)) === false) {
            mkdir(dirname($this->logPath), 0775, true);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $entry = sprintf(
            '[%s] %s.%s %s %s%s',
            date('Y-m-d H:i:s'),
            strtoupper((string) config('app.env', 'production')),
            $level,
            $message,
            json_encode($context, JSON_UNESCAPED_SLASHES),
            PHP_EOL
        );
        file_put_contents($this->logPath, $entry, FILE_APPEND);
    }
}
