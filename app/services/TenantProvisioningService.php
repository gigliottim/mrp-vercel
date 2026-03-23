<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database\DatabaseManager;
use App\Core\Logging\Logger;
use PDO;
use RuntimeException;
use Throwable;

final class TenantProvisioningService
{
    private const DATABASE_PREFIX = 'mrp_';
    private const MAX_IDENTIFIER_LENGTH = 63;

    private PDO $authConnection;

    public function __construct(?PDO $authConnection = null)
    {
        $this->authConnection = $authConnection ?? DatabaseManager::connection('mrp_auth');
    }

    /**
     * @param array<string, string> $input
     * @return array{success: bool, errors: array<string, string>, database_name?: string, admin_email?: string, provisioning_status?: array{database_created: bool, admin_user_created: bool, company_binding_created: bool}}
     */
    public function provision(array $input): array
    {
        $errors = $this->validate($input);
        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $databaseName = $this->resolveAvailableDatabaseName((string) $input['company_name']);

        try {
            $this->createDatabase($databaseName);
            $this->applyWizardScripts($databaseName);
            $this->createAuthRecords($databaseName, $input);
        } catch (RuntimeException $exception) {
            $this->dropDatabaseIfExists($databaseName);
            Logger::getInstance()->error('Wizard provisioning failed', [
                'database' => $databaseName,
                'message' => $exception->getMessage(),
            ]);

            $resolvedMessage = $this->resolveProvisioningFailureMessage($exception);
            if ($resolvedMessage !== null) {
                throw new RuntimeException($resolvedMessage);
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->dropDatabaseIfExists($databaseName);
            Logger::getInstance()->error('Wizard provisioning failed', [
                'database' => $databaseName,
                'message' => $exception->getMessage(),
            ]);

            $resolvedMessage = $this->resolveProvisioningFailureMessage($exception);
            throw new RuntimeException($resolvedMessage ?? 'No se pudo completar el alta de la empresa. Intenta nuevamente en unos minutos.');
        }

        $adminEmail = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $verification = $this->verifyProvisioningResult($adminEmail, $databaseName);

        return [
            'success' => true,
            'errors' => [],
            'database_name' => $databaseName,
            'admin_email' => $adminEmail,
            'provisioning_status' => $verification,
        ];
    }

    /**
     * @return array{database_created: bool, admin_user_created: bool, company_binding_created: bool}
     */
    public function verifyProvisioningResult(string $email, string $databaseName): array
    {
        $normalizedEmail = mb_strtolower(trim($email));

        $adminUserCreated = false;
        if ($normalizedEmail !== '') {
            $userStmt = $this->authConnection->prepare('SELECT 1 FROM users WHERE lower(email) = :email LIMIT 1');
            $userStmt->execute(['email' => $normalizedEmail]);
            $adminUserCreated = $userStmt->fetchColumn() !== false;
        }

        $bindingStmt = $this->authConnection->prepare('SELECT 1 FROM company_databases WHERE database_name = :database_name LIMIT 1');
        $bindingStmt->execute(['database_name' => $databaseName]);
        $companyBindingCreated = $bindingStmt->fetchColumn() !== false;

        return [
            'database_created' => $this->databaseExists($databaseName),
            'admin_user_created' => $adminUserCreated,
            'company_binding_created' => $companyBindingCreated,
        ];
    }

    public function previewDatabaseName(string $companyName): string
    {
        $normalized = $this->normalizeCompanyToken($companyName);
        return self::DATABASE_PREFIX . $normalized;
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];

        $adminName = trim((string) ($input['admin_name'] ?? ''));
        $adminLastName = trim((string) ($input['admin_lastname'] ?? ''));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirmation = (string) ($input['password_confirmation'] ?? '');
        $companyName = trim((string) ($input['company_name'] ?? ''));
        $taxId = trim((string) ($input['tax_id'] ?? ''));
        $terms = (string) ($input['terms'] ?? '');

        if ($adminName === '') {
            $errors['admin_name'] = 'El nombre es obligatorio.';
        }

        if ($adminLastName === '') {
            $errors['admin_lastname'] = 'El apellido es obligatorio.';
        }

        if ($email === '') {
            $errors['email'] = 'El email es obligatorio.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'El email no es valido.';
        } elseif ($this->emailExists($email)) {
            $errors['email'] = 'Ya existe un usuario con ese email.';
        }

        if ($password === '') {
            $errors['password'] = 'La contrasena es obligatoria.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'La contrasena debe tener al menos 8 caracteres.';
        }

        if ($passwordConfirmation === '') {
            $errors['password_confirmation'] = 'Debes confirmar la contrasena.';
        } elseif ($passwordConfirmation !== $password) {
            $errors['password_confirmation'] = 'La confirmacion no coincide.';
        }

        if ($companyName === '') {
            $errors['company_name'] = 'El nombre de la empresa es obligatorio.';
        }

        if ($taxId !== '' && strlen($taxId) > 50) {
            $errors['tax_id'] = 'El identificador fiscal no puede superar 50 caracteres.';
        }

        if ($terms !== '1') {
            $errors['terms'] = 'Debes aceptar los terminos para continuar.';
        }

        return $errors;
    }

    private function emailExists(string $email): bool
    {
        $stmt = $this->authConnection->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetchColumn() !== false;
    }

    private function resolveAvailableDatabaseName(string $companyName): string
    {
        $token = $this->normalizeCompanyToken($companyName);
        $candidate = self::DATABASE_PREFIX . $token;

        if ($this->databaseExists($candidate) === false) {
            return $candidate;
        }

        $counter = 2;
        while (true) {
            $suffix = '_' . $counter;
            $maxTokenLength = self::MAX_IDENTIFIER_LENGTH - strlen(self::DATABASE_PREFIX) - strlen($suffix);
            $trimmedToken = substr($token, 0, max(1, $maxTokenLength));
            $withSuffix = self::DATABASE_PREFIX . $trimmedToken . $suffix;

            if ($this->databaseExists($withSuffix) === false) {
                return $withSuffix;
            }

            $counter++;
        }
    }

    private function normalizeCompanyToken(string $companyName): string
    {
        $value = trim($companyName);
        if ($value === '') {
            $value = 'empresa';
        }

        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }

        $value = mb_strtolower($value);
        $value = (string) preg_replace('/[^a-z0-9]+/', '_', $value);
        $value = (string) preg_replace('/_+/', '_', $value);
        $value = trim($value, '_');

        if ($value === '') {
            $value = 'empresa';
        }

        $maxTokenLength = self::MAX_IDENTIFIER_LENGTH - strlen(self::DATABASE_PREFIX);
        return substr($value, 0, max(1, $maxTokenLength));
    }

    private function databaseExists(string $databaseName): bool
    {
        $stmt = $this->authConnection->prepare('SELECT 1 FROM pg_database WHERE datname = :database_name LIMIT 1');
        $stmt->execute(['database_name' => $databaseName]);
        return $stmt->fetchColumn() !== false;
    }

    private function createDatabase(string $databaseName): void
    {
        $identifier = $this->quoteIdentifier($databaseName);
        $sql = 'CREATE DATABASE ' . $identifier
            . " ENCODING 'UTF8'"
            . " LOCALE_PROVIDER 'icu'"
            . " ICU_LOCALE 'es-ES'"
            . ' TEMPLATE template0';
        $this->authConnection->exec($sql);
    }

    private function dropDatabaseIfExists(string $databaseName): void
    {
        if ($this->databaseExists($databaseName) === false) {
            return;
        }

        $identifier = $this->quoteIdentifier($databaseName);

        try {
            $terminate = $this->authConnection->prepare(
                'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = :database_name AND pid <> pg_backend_pid()'
            );
            $terminate->execute(['database_name' => $databaseName]);
            $this->authConnection->exec('DROP DATABASE IF EXISTS ' . $identifier);
        } catch (Throwable $exception) {
            Logger::getInstance()->warning('Wizard cleanup failed', [
                'database' => $databaseName,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function applyWizardScripts(string $databaseName): void
    {
        $wizardFiles = [
            '00_mrp_wizard.sql',
            '01_mrp_depositos.sql',
            '02_mrp_validaciones.sql',
            '03_mrp_um.sql',
        ];

        $tenantConnection = $this->newConnectionForDatabase($databaseName);

        foreach ($wizardFiles as $wizardFile) {
            // The base dump may set search_path to empty; reset it before each script.
            $tenantConnection->exec('SET search_path TO public');

            $path = base_path('database/wizard/' . $wizardFile);
            if (file_exists($path) === false) {
                throw new RuntimeException('Falta script wizard requerido: ' . $wizardFile);
            }

            $sql = $this->buildExecutableSql($path);
            if (trim($sql) === '') {
                continue;
            }

            $tenantConnection->exec($sql);
        }
    }

    private function createAuthRecords(string $databaseName, array $input): void
    {
        $tenantConfig = config('database.connections.tenant', []);
        $host = (string) ($tenantConfig['host'] ?? '127.0.0.1');
        $port = (string) ($tenantConfig['port'] ?? '5432');
        $username = (string) ($tenantConfig['username'] ?? '');
        $password = (string) ($tenantConfig['password'] ?? '');

        $companyName = trim((string) $input['company_name']);
        $companySlug = substr($databaseName, 0, 160);
        $taxId = trim((string) ($input['tax_id'] ?? ''));
        $email = mb_strtolower(trim((string) $input['email']));
        $fullName = trim((string) $input['admin_name'] . ' ' . (string) $input['admin_lastname']);

        $this->authConnection->beginTransaction();

        try {
            $companyStmt = $this->authConnection->prepare(
                'INSERT INTO companies (name, slug, tax_id, contact_email, status, created_at, updated_at)
                 VALUES (:name, :slug, :tax_id, :contact_email, :status, NOW(), NOW())
                 RETURNING id'
            );
            $companyStmt->execute([
                'name' => $companyName,
                'slug' => $companySlug,
                'tax_id' => $taxId !== '' ? $taxId : null,
                'contact_email' => $email,
                'status' => 'active',
            ]);
            $companyId = (int) $companyStmt->fetchColumn();

            $userStmt = $this->authConnection->prepare(
                'INSERT INTO users (name, email, password, two_factor_enabled, created_at, updated_at)
                 VALUES (:name, :email, :password, FALSE, NOW(), NOW())
                 RETURNING id'
            );
            $userStmt->execute([
                'name' => $fullName,
                'email' => $email,
                'password' => password_hash((string) $input['password'], PASSWORD_DEFAULT),
            ]);
            $userId = (int) $userStmt->fetchColumn();

            $dbStmt = $this->authConnection->prepare(
                'INSERT INTO company_databases (company_id, host, port, database_name, username, password_encrypted, created_at, updated_at)
                 VALUES (:company_id, :host, :port, :database_name, :username, :password_encrypted, NOW(), NOW())'
            );
            $dbStmt->execute([
                'company_id' => $companyId,
                'host' => $host,
                'port' => $port,
                'database_name' => $databaseName,
                'username' => $username,
                'password_encrypted' => $password,
            ]);

            $roleId = $this->resolveAdministratorRoleId();

            $userCompanyStmt = $this->authConnection->prepare(
                'INSERT INTO user_company (user_id, company_id, role_id) VALUES (:user_id, :company_id, :role_id)'
            );
            $userCompanyStmt->execute([
                'user_id' => $userId,
                'company_id' => $companyId,
                'role_id' => $roleId,
            ]);

            $userRoleStmt = $this->authConnection->prepare(
                'INSERT INTO user_has_roles (user_id, role_id) VALUES (:user_id, :role_id)'
            );
            $userRoleStmt->execute([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);

            $defaultCompanyStmt = $this->authConnection->prepare(
                'UPDATE users SET default_company_id = :company_id, updated_at = NOW() WHERE id = :user_id'
            );
            $defaultCompanyStmt->execute([
                'company_id' => $companyId,
                'user_id' => $userId,
            ]);

            $this->writeAuditLog($userId, $companyId, $databaseName);

            $this->authConnection->commit();
        } catch (Throwable $exception) {
            $this->authConnection->rollBack();
            throw $exception;
        }
    }

    private function resolveAdministratorRoleId(): int
    {
        $stmt = $this->authConnection->query("SELECT id FROM roles WHERE lower(name) = 'administrator' LIMIT 1");
        $roleId = $stmt !== false ? $stmt->fetchColumn() : false;

        if ($roleId !== false) {
            return (int) $roleId;
        }

        $fallback = $this->authConnection->query('SELECT id FROM roles ORDER BY id ASC LIMIT 1');
        $fallbackRoleId = $fallback !== false ? $fallback->fetchColumn() : false;

        if ($fallbackRoleId === false) {
            throw new RuntimeException('No existe un rol para asociar al administrador inicial.');
        }

        return (int) $fallbackRoleId;
    }

    private function writeAuditLog(int $userId, int $companyId, string $databaseName): void
    {
        $stmt = $this->authConnection->query("SELECT to_regclass('public.audit_logs')");
        $exists = $stmt !== false ? $stmt->fetchColumn() : false;

        if ($exists === null || $exists === false) {
            return;
        }

        $auditStmt = $this->authConnection->prepare(
            'INSERT INTO audit_logs (user_id, company_id, action, description, created_at)
             VALUES (:user_id, :company_id, :action, :description, NOW())'
        );
        $auditStmt->execute([
            'user_id' => $userId,
            'company_id' => $companyId,
            'action' => 'tenant.registered',
            'description' => 'Alta via wizard. Base creada: ' . $databaseName,
        ]);
    }

    private function newConnectionForDatabase(string $databaseName): PDO
    {
        $baseConfig = config('database.connections.tenant', []);
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;options='--client_encoding=UTF8'",
            $baseConfig['host'] ?? '127.0.0.1',
            $baseConfig['port'] ?? '5432',
            $databaseName
        );

        return new PDO(
            $dsn,
            (string) ($baseConfig['username'] ?? ''),
            (string) ($baseConfig['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    private function buildExecutableSql(string $path): string
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('No se pudo leer script wizard: ' . basename($path));
        }

        $sql = $this->stripUtf8Bom($sql);

        if (preg_match('/^\s*--\s*SOURCE_DUMP_SCHEMA\s*:\s*(.+)\s*$/mi', $sql, $matches) !== 1) {
            // Filtrar meta-comandos psql (\restrict, \connect, etc.) que no son SQL válido
            $lines = preg_split('/\r\n|\r|\n/', $sql);
            if ($lines !== false) {
                $lines = array_filter($lines, static function (string $line): bool {
                    return preg_match('/^\s*\\\\[a-zA-Z]/', $line) !== 1;
                });
                $sql = implode(PHP_EOL, $lines);
            }
            return $sql;
        }

        $relativePath = trim($matches[1]);
        $dumpPath = base_path($relativePath);
        return $this->extractSchemaFromDump($dumpPath);
    }

    private function extractSchemaFromDump(string $dumpPath): string
    {
        $dumpContent = file_get_contents($dumpPath);
        if ($dumpContent === false) {
            throw new RuntimeException('No se pudo leer el dump base del wizard.');
        }

        $dumpContent = $this->stripUtf8Bom($dumpContent);

        $lines = preg_split('/\r\n|\r|\n/', $dumpContent);
        if ($lines === false) {
            throw new RuntimeException('No se pudo procesar el dump base del wizard.');
        }

        $schemaLines = [];
        $skippingCopyData = false;

        foreach ($lines as $line) {
            $trimmed = ltrim($line);

            if ($skippingCopyData) {
                if (trim($line) === '\\.') {
                    $skippingCopyData = false;
                }
                continue;
            }

            if (preg_match('/^COPY\s+/i', $trimmed) === 1) {
                $skippingCopyData = true;
                continue;
            }

            if (preg_match('/^SELECT\s+pg_catalog\.setval/i', $trimmed) === 1) {
                continue;
            }

            if (preg_match('/^--\s*Data for Name:/i', $trimmed) === 1) {
                continue;
            }

            if (trim($line) === '\\.') {
                continue;
            }

            $schemaLines[] = $line;
        }

        return implode(PHP_EOL, $schemaLines);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[a-z0-9_]+$/', $identifier) !== 1) {
            throw new RuntimeException('Nombre de base invalido.');
        }

        return '"' . $identifier . '"';
    }

    private function resolveProvisioningFailureMessage(Throwable $exception): ?string
    {
        $message = mb_strtolower($exception->getMessage());

        if (
            str_contains($message, 'permission denied to create database')
            || str_contains($message, 'sqlstate[42501]')
        ) {
            return 'Configuracion incompleta: el usuario de DB_AUTH no tiene permiso CREATEDB para crear bases nuevas.';
        }

        if (str_contains($message, 'sqlstate[42p01]')) {
            return 'Error de esquema durante inicializacion del wizard: falta una tabla requerida o no se encontro en el esquema public.';
        }

        if (
            str_contains($message, 'sqlstate[42601]')
            && str_contains($message, 'at or near "﻿"')
        ) {
            return 'Error de formato SQL en scripts del wizard (BOM UTF-8). Reintentado con limpieza de BOM; verifica archivos en database/wizard si persiste.';
        }

        return null;
    }

    private function stripUtf8Bom(string $content): string
    {
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            return substr($content, 3);
        }

        return $content;
    }
}
