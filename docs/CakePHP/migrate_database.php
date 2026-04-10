<?php

declare(strict_types=1);

/**
 * Migración de Base de Datos - MRP
 *
 * Este archivo ha sido adaptado para funcionar con CakePHP.
 *
 * CAMBIOS PRINCIPALES:
 * 1. Se reemplazó el uso de `App\Core\Database\DatabaseManager` por `Cake\Database\Connection`
 * 2. Se mantiene la lógica de multi-tenancy basada en `company_databases` de mrp_auth
 * 3. Se actualizó el path de autoloading para CakePHP
 *
 * USO:
 * - `php migrate_database.php` (conexión por defecto)
 * - `php migrate_database.php --connection=mrp_auth` (base de autenticación)
 * - `php migrate_database.php --all-tenants` (aplica a todas las bases de tenants)
 * - `php migrate_database.php --status` (muestra estado sin aplicar)
 * - `php migrate_database.php --dry-run` (muestra lista sin conexión)
 *
 * NOTA: Este archivo es una adaptación para migración. En producción con CakePHP,
 * se recomienda usar los Migrations del framework (bin/cake migrations).
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Dotenv\Dotenv;
use RuntimeException;
use Exception;

// Cargar configuración de entorno
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Cargar configuración de CakePHP
Configure::load('app', 'default');
Configure::write('Database.default', [
    'className' => Connection::class,
    'driver' => Postgres::class,
    'persistent' => false,
    'host' => env('DB_PGSQL_HOST', 'postgresql'),
    'port' => env('DB_PGSQL_PORT', '5432'),
    'database' => env('DB_AUTH_DATABASE', 'mrp_auth'),
    'username' => env('DB_PGSQL_USERNAME', 'mrp'),
    'password' => env('DB_PGSQL_PASSWORD', 'CHANGE_ME_DB_PASSWORD'),
    'schema' => 'public',
    'sslmode' => 'prefer',
    'encoding' => 'utf8',
]);

$options = getopt('', ['connection::', 'path::', 'dry-run', 'status', 'db-host::', 'db-port::', 'skip-existing', 'all-tenants']);

$connectionName = (string) ($options['connection'] ?? 'default');
$migrationsPath = (string) ($options['path'] ?? base_path('database/migrations'));
$isDryRun = array_key_exists('dry-run', $options);
$showStatusOnly = array_key_exists('status', $options);
$skipExisting = array_key_exists('skip-existing', $options);
$allTenants = array_key_exists('all-tenants', $options);
$dbHostOverride = isset($options['db-host']) ? trim((string) $options['db-host']) : null;
$dbPortOverride = isset($options['db-port']) ? trim((string) $options['db-port']) : null;

// Sobrescribir configuración de host y puerto si se pasan como parámetros
if ($dbHostOverride !== null && $dbHostOverride !== '') {
    $config = Configure::read('Database');

    if (isset($config['default']['host'])) {
        Configure::write('Database.default.host', $dbHostOverride);
    }
    if ($dbPortOverride !== null && $dbPortOverride !== '') {
        Configure::write('Database.default.port', $dbPortOverride);
    }

    // Recargar la conexión con la nueva configuración
    ConnectionManager::set('default', [
        'className' => Connection::class,
        'driver' => Postgres::class,
        'host' => $dbHostOverride,
        'port' => $dbPortOverride ?? 5432,
        'database' => env('DB_AUTH_DATABASE', 'mrp_auth'),
        'username' => env('DB_PGSQL_USERNAME', 'mrp'),
        'password' => env('DB_PGSQL_PASSWORD', 'CHANGE_ME_DB_PASSWORD'),
        'schema' => 'public',
        'sslmode' => 'prefer',
        'encoding' => 'utf8',
    ]);
}

function out(string $message): void
{
    echo $message . PHP_EOL;
}

function ensureMigrationsTable(Connection $connection): void
{
    $schemaManager = $connection->getSchemaManager();
    $tables = $schemaManager->listTables();

    if (!in_array('schema_migrations', $tables)) {
        $connection->execute("
            CREATE TABLE schema_migrations (
                id SERIAL PRIMARY KEY,
                filename VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ")->close();
    }
}

function getExecutedMigrations(Connection $connection): array
{
    $stmt = $connection->query('SELECT filename FROM schema_migrations ORDER BY filename ASC');
    $rows = $stmt->fetchAll('assoc');

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

/**
 * @param string[] $files
 * @return array{applied: int, skipped: int, errors: string[]}
 */
function runMigrationsForConnection(Connection $connection, array $files, bool $skipExisting, bool $showStatusOnly): array
{
    ensureMigrationsTable($connection);
    $executed = getExecutedMigrations($connection);

    $pending = [];
    foreach ($files as $file) {
        if (!isset($executed[basename($file)])) {
            $pending[] = $file;
        }
    }

    out('  Pendientes: ' . count($pending));

    if ($showStatusOnly) {
        foreach ($pending as $file) {
            out('  - ' . basename($file));
        }
        return ['applied' => 0, 'skipped' => 0, 'errors' => []];
    }

    if ($pending === []) {
        out('  Al dia.');
        return ['applied' => 0, 'skipped' => 0, 'errors' => []];
    }

    $applied = 0;
    $skipped = 0;
    $errors = [];

    foreach ($pending as $file) {
        $filename = basename($file);
        $sql = file_get_contents($file);

        if ($sql === false || trim($sql) === '') {
            $errors[] = "Migración vacía o ilegible: {$filename}";
            continue;
        }

        $connection->transactional(function ($conn) use ($sql, $filename, &$applied, $skipExisting, &$errors, &$skipped) {
            try {
                $conn->execute($sql)->close();
                $conn->execute(
                    'INSERT INTO schema_migrations (filename) VALUES (:filename)',
                    ['filename' => $filename]
                )->close();
                $applied++;
                out("  OK: {$filename}");
            } catch (Throwable $e) {
                if ($skipExisting && isIdempotentSqlError($e)) {
                    $conn->execute(
                        'INSERT INTO schema_migrations (filename) VALUES (:filename) ON CONFLICT (filename) DO NOTHING',
                        ['filename' => $filename]
                    )->close();
                    $skipped++;
                    out("  SKIP(existing): {$filename}");
                } else {
                    $errors[] = "Error en {$filename}: " . $e->getMessage();
                    throw $e;
                }
            }
        });
    }

    return ['applied' => $applied, 'skipped' => $skipped, 'errors' => $errors];
}

/**
 * Crea una conexión PDO para un tenant específico (mantiene compatibilidad con lógica actual)
 */
function makeTenantConnection(string $host, string $port, string $database, string $username, string $password): Connection
{
    $config = [
        'className' => Connection::class,
        'driver' => Postgres::class,
        'host' => $host,
        'port' => (int) $port,
        'database' => $database,
        'username' => $username,
        'password' => $password,
        'schema' => 'public',
        'sslmode' => 'prefer',
        'encoding' => 'utf8',
    ];

    // Crear conexión temporal con ConnectionManager
    $tempName = 'tenant_' . md5($database . time());
    ConnectionManager::set($tempName, $config);

    return ConnectionManager::get($tempName);
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
    out('Directorio: ' . $migrationsPath);
    out('Total archivos: ' . count($files));

    if ($isDryRun) {
        out('Modo dry-run (sin conexión DB):');
        foreach ($files as $file) {
            out(' - ' . basename($file));
        }
        exit(0);
    }

    // ── Modo all-tenants ──────────────────────────────────────────────────────
    if ($allTenants) {
        out('Modo: all-tenants (todas las BDs empresa)');

        // Obtener conexión a mrp_auth
        $authConnection = ConnectionManager::get('default');

        // Verificar si la tabla company_databases existe
        $schemaManager = $authConnection->getSchemaManager();
        $tables = $schemaManager->listTables();

        if (!in_array('company_databases', $tables)) {
            out('Tabla company_databases no encontrada. Asegúrese de ejecutar las migraciones de mrp_auth primero.');
            exit(0);
        }

        $stmt = $authConnection->query(
            'SELECT database_name, host, port, username, password_encrypted
             FROM company_databases
             ORDER BY database_name'
        );
        $tenants = $stmt->fetchAll('assoc');

        if (empty($tenants)) {
            out('Sin tenants registrados en company_databases.');
            exit(0);
        }

        out('Tenants encontrados: ' . count($tenants));

        $globalErrors = [];

        foreach ($tenants as $tenant) {
            $dbName   = (string) ($tenant['database_name'] ?? '');
            $host     = (string) ($tenant['host'] ?? '');
            $port     = (string) ($tenant['port'] ?? '5432');
            $username = (string) ($tenant['username'] ?? '');
            $rawPass  = (string) ($tenant['password_encrypted'] ?? '');
            $password = ($rawPass === 'ENC(local-dev-only)') ? 'a77MUbg_7QxdvdP7C9MrR' : $rawPass;

            out('');
            out("--- Tenant: {$dbName} ({$host}:{$port}) ---");

            try {
                $tenantConn = makeTenantConnection($host, $port, $dbName, $username, $password);
                $result = runMigrationsForConnection($tenantConn, $files, $skipExisting, $showStatusOnly);

                out("  Aplicadas: {$result['applied']}  Saltadas: {$result['skipped']}");

                foreach ($result['errors'] as $err) {
                    out("  ERROR: {$err}");
                    $globalErrors[] = "[{$dbName}] {$err}";
                }
            } catch (Throwable $e) {
                out("  FALLO conexion/migracion: " . $e->getMessage());
                $globalErrors[] = "[{$dbName}] " . $e->getMessage();
            }
        }

        out('');
        out('=== Resumen all-tenants ===');
        out('Tenants procesados: ' . count($tenants));
        out('Errores: ' . count($globalErrors));

        foreach ($globalErrors as $err) {
            out('  ' . $err);
        }

        exit($globalErrors !== [] ? 1 : 0);
    }

    // ── Modo single-connection (comportamiento original) ──────────────────────
    out('Conexión: ' . $connectionName);

    try {
        $connection = ConnectionManager::get($connectionName);
    } catch (Exception $e) {
        out("ERROR: Conexión '{$connectionName}' no encontrada. Verifique config/app.php");
        out("Detalles: " . $e->getMessage());
        exit(1);
    }

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

        $connection->transactional(function ($conn) use ($sql, $filename, &$appliedCount) {
            try {
                $conn->execute($sql)->close();
                $conn->execute(
                    'INSERT INTO schema_migrations (filename) VALUES (:filename)',
                    ['filename' => $filename]
                )->close();
                $appliedCount++;
                out("OK: {$filename}");
            } catch (Throwable $e) {
                if ($skipExisting && isIdempotentSqlError($e)) {
                    $conn->execute(
                        'INSERT INTO schema_migrations (filename) VALUES (:filename) ON CONFLICT (filename) DO NOTHING',
                        ['filename' => $filename]
                    )->close();
                    out("SKIP(existing): {$filename}");
                } else {
                    throw new RuntimeException("Error en {$filename}: " . $e->getMessage(), 0, $e);
                }
            }
        });
    }

    out("Migraciones aplicadas: {$appliedCount}");
    out('Proceso finalizado.');
    exit(0);
} catch (Throwable $e) {
    out('ERROR: ' . $e->getMessage());
    exit(1);
}
