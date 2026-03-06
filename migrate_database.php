<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap/autoload.php';

use App\Core\Config\Config;
use App\Core\Database\DatabaseManager;
use App\Core\Support\Env;

Env::load(base_path('.env'));
Config::load(base_path('config'));

$options = getopt('', ['connection::', 'path::', 'dry-run', 'status', 'db-host::', 'db-port::', 'skip-existing']);

$connectionName = (string) ($options['connection'] ?? 'default');
$migrationsPath = (string) ($options['path'] ?? base_path('database/migrations'));
$isDryRun = array_key_exists('dry-run', $options);
$showStatusOnly = array_key_exists('status', $options);
$skipExisting = array_key_exists('skip-existing', $options);
$dbHostOverride = isset($options['db-host']) ? trim((string) $options['db-host']) : null;
$dbPortOverride = isset($options['db-port']) ? trim((string) $options['db-port']) : null;

if ($dbHostOverride !== null && $dbHostOverride !== '') {
    $connections = config('database.connections', []);
    foreach (['default', 'pgsql', 'mrp_auth', 'tenant'] as $connectionKey) {
        if (isset($connections[$connectionKey]) && is_array($connections[$connectionKey])) {
            $connections[$connectionKey]['host'] = $dbHostOverride;
            if ($dbPortOverride !== null && $dbPortOverride !== '') {
                $connections[$connectionKey]['port'] = $dbPortOverride;
            }
        }
    }
    Config::set('database.connections', $connections);
}

function out(string $message): void
{
    echo $message . PHP_EOL;
}

function ensureMigrationsTable(PDO $connection): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id SERIAL PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ";

    $connection->exec($sql);
}

function getExecutedMigrations(PDO $connection): array
{
    $stmt = $connection->query('SELECT filename FROM schema_migrations ORDER BY filename ASC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $executed = [];
    foreach ($rows as $row) {
        $filename = $row['filename'] ?? null;
        if (is_string($filename) && $filename !== '') {
            $executed[$filename] = true;
        }
    }

    return $executed;
}

function isIdempotentSqlError(Throwable $error): bool
{
    $message = strtolower($error->getMessage());

    $knownMarkers = [
        'sqlstate[42701]',
        'duplicate column',
        'already exists',
        'sqlstate[42p07]',
        'duplicate table',
        'sqlstate[42710]',
        'duplicate object',
        'sqlstate[42703]',
        'undefined column',
        'sqlstate[42p01]',
        'undefined table',
    ];

    foreach ($knownMarkers as $marker) {
        if (str_contains($message, $marker)) {
            return true;
        }
    }

    return false;
}

try {
    if (!is_dir($migrationsPath)) {
        throw new RuntimeException("Directorio de migraciones no encontrado: {$migrationsPath}");
    }

    $files = glob(rtrim($migrationsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql');
    if ($files === false) {
        throw new RuntimeException('No se pudieron listar archivos de migración.');
    }

    sort($files, SORT_STRING);

    out('=== Migraciones DB ===');
    out('Conexión: ' . $connectionName);
    out('Directorio: ' . $migrationsPath);
    out('Total archivos: ' . count($files));

    if ($isDryRun) {
        out('Modo dry-run (sin conexión DB):');
        foreach ($files as $file) {
            out(' - ' . basename($file));
        }
        exit(0);
    }

    $connection = DatabaseManager::connection($connectionName);
    ensureMigrationsTable($connection);

    $executed = getExecutedMigrations($connection);

    $pending = [];
    foreach ($files as $file) {
        $filename = basename($file);
        if (!isset($executed[$filename])) {
            $pending[] = $file;
        }
    }

    out('Pendientes: ' . count($pending));

    if ($showStatusOnly) {
        if ($pending === []) {
            out('Estado: al día.');
            exit(0);
        }

        out('Pendientes:');
        foreach ($pending as $file) {
            out(' - ' . basename($file));
        }
        exit(0);
    }

    if ($pending === []) {
        out('No hay migraciones pendientes.');
        exit(0);
    }

    $appliedCount = 0;

    foreach ($pending as $file) {
        $filename = basename($file);
        $sql = file_get_contents($file);

        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException("Migración vacía o ilegible: {$filename}");
        }

        out("Aplicando: {$filename}");

        $connection->beginTransaction();
        try {
            $connection->exec($sql);
            $insert = $connection->prepare('INSERT INTO schema_migrations (filename) VALUES (:filename)');
            $insert->execute(['filename' => $filename]);
            $connection->commit();
            $appliedCount++;
            out("OK: {$filename}");
        } catch (Throwable $e) {
            $connection->rollBack();

            if ($skipExisting && isIdempotentSqlError($e)) {
                $insert = $connection->prepare('INSERT INTO schema_migrations (filename) VALUES (:filename) ON CONFLICT (filename) DO NOTHING');
                $insert->execute(['filename' => $filename]);
                out("SKIP(existing): {$filename}");
                continue;
            }

            throw new RuntimeException("Error en {$filename}: " . $e->getMessage(), 0, $e);
        }
    }

    out("Migraciones aplicadas: {$appliedCount}");
    out('Proceso finalizado.');
    exit(0);
} catch (Throwable $e) {
    out('ERROR: ' . $e->getMessage());
    exit(1);
}
