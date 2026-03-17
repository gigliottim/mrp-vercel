<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use PDOException;
use RuntimeException;

final class DatabaseManager
{
    /** @var array<string, PDO> */
    private static array $connections = [];

    public static function connection(string $name = null): PDO
    {
        $name = $name ?? (string) config('database.default', 'default');

        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        }

        $config = config('database.connections.' . $name);
        if ($config === null) {
            throw new RuntimeException(sprintf('Database connection [%s] not configured.', $name));
        }

        $driver = $config['driver'] ?? 'mysql';

        if ($driver === 'pgsql') {
            $charset = strtoupper(str_replace(['-', '_'], '', $config['charset'] ?? 'utf8'));
            $dsn = sprintf(
                "pgsql:host=%s;port=%s;dbname=%s;options='--client_encoding=%s'",
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? '5432',
                $config['database'] ?? '',
                $charset
            );
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? '3306',
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );
        }

        $options = $config['options'] ?? [];
        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO(
                $dsn,
                $config['username'] ?? '',
                $config['password'] ?? '',
                $options + $defaults
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection error: ' . $exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        self::$connections[$name] = $pdo;
        return $pdo;
    }

    public static function clear(?string $name = null): void
    {
        if ($name === null) {
            self::$connections = [];
            return;
        }

        unset(self::$connections[$name]);
    }
}
