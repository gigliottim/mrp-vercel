<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Database\DatabaseManager;
use App\Services\TenantProvisioningService;

function out(string $message = ''): void
{
    echo $message . PHP_EOL;
}

function line(string $label, string $value, string $state = 'INFO'): void
{
    $tag = match ($state) {
        'OK' => '[OK]',
        'WARN' => '[WARN]',
        'FAIL' => '[FAIL]',
        default => '[INFO]',
    };

    out(sprintf('%-6s %-32s %s', $tag, $label . ':', $value));
}

function fail(string $message, int $code = 1): never
{
    line('Resultado', $message, 'FAIL');
    exit($code);
}

function boolFlag(array $status, string $key): bool
{
    return !empty($status[$key]);
}

function cleanupSmokeData(string $databaseName, string $email): void
{
    $auth = DatabaseManager::connection('mrp_auth');

    // Resolve IDs first to keep cleanup deterministic.
    $companyIdStmt = $auth->prepare('SELECT id FROM companies WHERE slug = :slug LIMIT 1');
    $companyIdStmt->execute(['slug' => $databaseName]);
    $companyId = $companyIdStmt->fetchColumn();

    $userIdStmt = $auth->prepare('SELECT id FROM users WHERE lower(email) = :email LIMIT 1');
    $userIdStmt->execute(['email' => mb_strtolower($email)]);
    $userId = $userIdStmt->fetchColumn();

    if ($companyId !== false) {
        $auth->prepare('DELETE FROM company_databases WHERE company_id = :company_id')->execute(['company_id' => $companyId]);
        $auth->prepare("DELETE FROM audit_logs WHERE company_id = :company_id AND action = 'tenant.registered'")->execute(['company_id' => $companyId]);
    }

    if ($userId !== false) {
        $auth->prepare('UPDATE users SET default_company_id = NULL WHERE id = :user_id')->execute(['user_id' => $userId]);
        $auth->prepare('DELETE FROM user_has_roles WHERE user_id = :user_id')->execute(['user_id' => $userId]);
        $auth->prepare('DELETE FROM user_company WHERE user_id = :user_id')->execute(['user_id' => $userId]);
        $auth->prepare("DELETE FROM audit_logs WHERE user_id = :user_id AND action = 'tenant.registered'")->execute(['user_id' => $userId]);
    }

    if ($companyId !== false) {
        $auth->prepare('DELETE FROM companies WHERE id = :company_id')->execute(['company_id' => $companyId]);
    }

    if ($userId !== false) {
        $auth->prepare('DELETE FROM users WHERE id = :user_id')->execute(['user_id' => $userId]);
    }

    $identifier = '"' . str_replace('"', '""', $databaseName) . '"';
    $auth->exec(
        "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = " . $auth->quote($databaseName) . ' AND pid <> pg_backend_pid()'
    );
    $auth->exec('DROP DATABASE IF EXISTS ' . $identifier);
}

out('============================================================');
out(' Smoke Test Registro Empresa (wizard)');
out('============================================================');
out();

try {
    $auth = DatabaseManager::connection('mrp_auth');
    line('Conexion mrp_auth', 'Exitosa', 'OK');

    $roleStmt = $auth->query("SELECT rolcreatedb FROM pg_roles WHERE rolname = current_user LIMIT 1");
    $canCreateDb = $roleStmt !== false ? (bool) $roleStmt->fetchColumn() : false;
    line('Permiso CREATEDB', $canCreateDb ? 'Si' : 'No', $canCreateDb ? 'OK' : 'FAIL');

    if (!$canCreateDb) {
        fail('Sin CREATEDB no se puede ejecutar smoke test de alta.', 2);
    }

    $suffix = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    $companyName = 'Smoke Test ' . $suffix;
    $email = 'smoke_' . $suffix . '@example.test';

    $input = [
        'admin_name' => 'Smoke',
        'admin_lastname' => 'Tester',
        'email' => $email,
        'password' => 'SmokeTest123!',
        'password_confirmation' => 'SmokeTest123!',
        'company_name' => $companyName,
        'tax_id' => 'SMK-' . substr($suffix, -6),
        'country' => 'AR',
        'terms' => '1',
    ];

    line('Empresa de prueba', $companyName, 'INFO');
    line('Email de prueba', $email, 'INFO');

    $service = new TenantProvisioningService();
    $result = $service->provision($input);

    if (($result['success'] ?? false) !== true) {
        $errors = $result['errors'] ?? [];
        $errorSummary = is_array($errors) ? json_encode($errors, JSON_UNESCAPED_UNICODE) : 'Sin detalle';
        fail('Provision devolvio success=false. ' . (string) $errorSummary);
    }

    $databaseName = (string) ($result['database_name'] ?? '');
    if ($databaseName === '') {
        fail('Provision no devolvio database_name.');
    }

    $status = $result['provisioning_status'] ?? [];
    $dbOk = boolFlag($status, 'database_created');
    $userOk = boolFlag($status, 'admin_user_created');
    $bindingOk = boolFlag($status, 'company_binding_created');

    line('BD creada', $dbOk ? 'Si' : 'No', $dbOk ? 'OK' : 'FAIL');
    line('Usuario creado', $userOk ? 'Si' : 'No', $userOk ? 'OK' : 'FAIL');
    line('Vinculacion creada', $bindingOk ? 'Si' : 'No', $bindingOk ? 'OK' : 'FAIL');

    if (!$dbOk || !$userOk || !$bindingOk) {
        fail('Alta incompleta segun provisioning_status.');
    }

    line('Database test', $databaseName, 'OK');

    try {
        cleanupSmokeData($databaseName, $email);
        line('Limpieza', 'OK (auth + tenant removidos)', 'OK');
    } catch (Throwable $cleanupError) {
        line('Limpieza', $cleanupError->getMessage(), 'WARN');
        fail('Alta OK pero limpieza incompleta. Revisar manualmente ' . $databaseName . ' y ' . $email, 3);
    }

    out();
    line('Resultado', 'Smoke test OK', 'OK');
    exit(0);
} catch (Throwable $exception) {
    fail($exception->getMessage());
}
