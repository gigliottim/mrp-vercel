<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Database\DatabaseManager;

function out(string $message = ''): void
{
    echo $message . PHP_EOL;
}

function statusLine(string $label, string $value, string $status = 'INFO'): void
{
    $prefix = match ($status) {
        'OK' => '[OK]',
        'WARN' => '[WARN]',
        'FAIL' => '[FAIL]',
        default => '[INFO]',
    };

    out(sprintf('%-6s %-34s %s', $prefix, $label . ':', $value));
}

out('============================================================');
out(' Verificacion de permisos para alta de empresa (wizard)');
out('============================================================');
out();

$authConfig = config('database.connections.mrp_auth', []);
statusLine('Host configurado', (string) ($authConfig['host'] ?? 'n/d'), 'INFO');
statusLine('Puerto configurado', (string) ($authConfig['port'] ?? 'n/d'), 'INFO');
statusLine('DB configurada', (string) ($authConfig['database'] ?? 'n/d'), 'INFO');
statusLine('Usuario configurado', (string) ($authConfig['username'] ?? 'n/d'), 'INFO');
out();

try {
    $conn = DatabaseManager::connection('mrp_auth');
    statusLine('Conexion mrp_auth', 'Exitosa', 'OK');
} catch (Throwable $exception) {
    statusLine('Conexion mrp_auth', $exception->getMessage(), 'FAIL');
    out();
    out('No se puede continuar sin conexion a mrp_auth.');
    exit(1);
}

try {
    $identityStmt = $conn->query(
        "SELECT current_user AS current_user, current_database() AS current_database"
    );
    $identity = $identityStmt !== false ? $identityStmt->fetch(PDO::FETCH_ASSOC) : false;

    $roleStmt = $conn->query(
        "SELECT rolname, rolcreatedb, rolsuper FROM pg_roles WHERE rolname = current_user LIMIT 1"
    );
    $role = $roleStmt !== false ? $roleStmt->fetch(PDO::FETCH_ASSOC) : false;

    if (!is_array($identity) || !is_array($role)) {
        throw new RuntimeException('No se pudo leer la identidad/rol actual en PostgreSQL.');
    }

    $currentUser = (string) ($identity['current_user'] ?? 'desconocido');
    $currentDatabase = (string) ($identity['current_database'] ?? 'desconocida');
    $canCreateDb = (bool) ($role['rolcreatedb'] ?? false);
    $isSuperUser = (bool) ($role['rolsuper'] ?? false);

    statusLine('Usuario conectado', $currentUser, 'INFO');
    statusLine('Base actual', $currentDatabase, 'INFO');
    statusLine('Rol superusuario', $isSuperUser ? 'Si' : 'No', $isSuperUser ? 'OK' : 'WARN');
    statusLine('Permiso CREATEDB', $canCreateDb ? 'Si' : 'No', $canCreateDb ? 'OK' : 'FAIL');

    $sqlCreateTest = 'CREATE DATABASE "mrp_perm_test_tmp"';
    statusLine('Prueba SQL objetivo', $sqlCreateTest, 'INFO');

    out();
    if ($canCreateDb) {
        out('Resultado: la configuracion actual permite crear bases para nuevas empresas.');
        out('Sugerencia: si el wizard igual falla, revisar host/credenciales de DB_TENANT y logs.');
        exit(0);
    }

    out('Resultado: la configuracion actual NO permite crear bases para nuevas empresas.');
    out();
    out('Accion requerida (ejecutar como superusuario de PostgreSQL):');
    out(sprintf('  ALTER ROLE "%s" CREATEDB;', $currentUser));
    out();
    out('Luego volver a ejecutar este chequeo y repetir el wizard.');
    exit(2);
} catch (Throwable $exception) {
    statusLine('Verificacion de permisos', $exception->getMessage(), 'FAIL');
    exit(1);
}
