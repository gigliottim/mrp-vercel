<?php

declare(strict_types=1);

$options = getopt('', ['path:', 'skip-existing', 'tenant-only', 'auth-only']);
$migrationsPath = $options['path'] ?? __DIR__ . '/database/migrations';
$skipExisting = isset($options['skip-existing']);
$tenantOnly = isset($options['tenant-only']);
$authOnly = isset($options['auth-only']);

if (!is_dir($migrationsPath)) {
    echo "Migration path does not exist: {$migrationsPath}\n";
    exit(1);
}

$sqlFiles = glob($migrationsPath . '/*.sql');
if (empty($sqlFiles)) {
    echo "No .sql files found in {$migrationsPath}\n";
    exit(0);
}

sort($sqlFiles);

require __DIR__ . '/bootstrap/autoload.php';
\App\Core\Env::load(__DIR__ . '/.env');
\App\Core\Config\Config::load(__DIR__ . '/config');

$authHost = env('DB_AUTH_HOST', env('DB_PGSQL_HOST', 'postgresql'));
$authPort = env('DB_AUTH_PORT', env('DB_PGSQL_PORT', '5432'));
$authDb = env('DB_AUTH_DATABASE', 'mrp_auth');
$authUser = env('DB_AUTH_USERNAME', env('DB_PGSQL_USERNAME', 'mrp'));
$authPass = env('DB_AUTH_PASSWORD', env('DB_PGSQL_PASSWORD', ''));

$authDsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $authHost, $authPort, $authDb);

try {
    $authPdo = new PDO($authDsn, $authUser, $authPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    echo "Auth DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

function runMigrations(PDO $pdo, array $sqlFiles, bool $skipExisting): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) PRIMARY KEY,
        executed_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
    )");

    foreach ($sqlFiles as $file) {
        $filename = basename($file);

        if ($skipExisting) {
            $stmt = $pdo->prepare("SELECT 1 FROM schema_migrations WHERE filename = ?");
            $stmt->execute([$filename]);
            if ($stmt->fetchColumn()) {
                echo "  [SKIP] {$filename} (already executed)\n";
                continue;
            }
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            echo "  [ERROR] Cannot read {$filename}\n";
            continue;
        }

        echo "  [RUN] {$filename}...\n";
        try {
            $pdo->exec($sql);
            $stmt = $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?) ON CONFLICT (filename) DO NOTHING");
            $stmt->execute([$filename]);
            echo "  [OK] {$filename}\n";
        } catch (PDOException $e) {
            echo "  [ERROR] {$filename}: " . $e->getMessage() . "\n";
        }
    }
}

$databases = [];

if (!$tenantOnly) {
    $databases[] = [
        'name' => $authDb,
        'pdo' => $authPdo,
    ];
}

if (!$authOnly) {
    $stmt = $authPdo->query("SELECT database_name, host, port, username, password_encrypted FROM company_databases WHERE is_active = true");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tenants as $tenant) {
        $dbName = $tenant['database_name'];
        if ($dbName === $authDb) {
            continue;
        }

        $host = $tenant['host'] ?? $authHost;
        $port = $tenant['port'] ?? $authPort;
        $user = $tenant['username'] ?? $authUser;
        $pass = $tenant['password_encrypted'] ?? $authPass;

        $tenantDsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbName);

        try {
            $tenantPdo = new PDO($tenantDsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $databases[] = [
                'name' => $dbName,
                'pdo' => $tenantPdo,
            ];
        } catch (PDOException $e) {
            echo "[WARN] Cannot connect to tenant DB {$dbName}: " . $e->getMessage() . "\n";
        }
    }
}

foreach ($databases as $db) {
    echo "\n==> Running migrations on database: {$db['name']}\n";
    runMigrations($db['pdo'], $sqlFiles, $skipExisting);
}

echo "\nAll migrations complete.\n";