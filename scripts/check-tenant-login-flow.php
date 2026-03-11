<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/autoload.php';

use App\Core\Config\Config;
use App\Core\Support\Env;
use App\Services\AuthService;

Env::load(base_path('.env'));
Config::load(base_path('config'));

$auth = new AuthService();

try {
    $validated = $auth->validateUser('martin@unik.ar', 'apolo785');
    $tenantSlug = (string) ($validated['companies'][0]['slug'] ?? 'mrp_tunna');

    $payload = $auth->loginWithTenant($validated['user'], $tenantSlug);
    $db = $payload['tenant']['database'] ?? [];

    $host = (string) ($db['host'] ?? '');
    $port = (string) ($db['port'] ?? '5432');
    $name = (string) ($db['name'] ?? '');
    $username = (string) ($db['username'] ?? '');
    $password = (string) ($db['password'] ?? '');

    echo 'AUTH_OK tenant=' . $tenantSlug . ' db=' . $name . ' host=' . $host . ' user=' . $username . PHP_EOL;

    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $row = $pdo->query('SELECT current_database() AS db, current_user AS usr')->fetch(PDO::FETCH_ASSOC);

    echo 'TENANT_DB_OK db=' . ($row['db'] ?? '') . ' user=' . ($row['usr'] ?? '') . PHP_EOL;
} catch (Throwable $exception) {
    echo 'FLOW_ERR ' . $exception->getMessage() . PHP_EOL;
    exit(1);
}
