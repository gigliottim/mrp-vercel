<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap/autoload.php';

use App\Services\AuthService;

date_default_timezone_set('UTC');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$schemaStatements = [
    'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT UNIQUE, password TEXT)',
    'CREATE TABLE companies (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT UNIQUE, status TEXT)',
    'CREATE TABLE company_databases (id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER, host TEXT, port TEXT, database_name TEXT, username TEXT, password_encrypted TEXT, FOREIGN KEY(company_id) REFERENCES companies(id))',
    'CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE)',
    'CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE)',
    'CREATE TABLE role_has_permissions (role_id INTEGER, permission_id INTEGER)',
    'CREATE TABLE user_has_roles (user_id INTEGER, role_id INTEGER)',
    'CREATE TABLE user_company (user_id INTEGER, company_id INTEGER, role_id INTEGER)'
];

foreach ($schemaStatements as $statement) {
    $pdo->exec($statement);
}

$passwordHash = password_hash('secret123', PASSWORD_BCRYPT);
$pdo->prepare('INSERT INTO users (name, email, password) VALUES (:name, :email, :password)')
    ->execute([
        'name' => 'MRP Admin',
        'email' => 'admin@example.com',
        'password' => $passwordHash,
    ]);

$pdo->prepare('INSERT INTO companies (name, slug, status) VALUES (:name, :slug, :status)')
    ->execute([
        'name' => 'Demo Manufacturing',
        'slug' => 'demo-manufacturing',
        'status' => 'active',
    ]);
$companyId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO company_databases (company_id, host, port, database_name, username, password_encrypted) VALUES (:company_id, :host, :port, :database_name, :username, :password_encrypted)')
    ->execute([
        'company_id' => $companyId,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database_name' => 'mrp_demo',
        'username' => 'root',
        'password_encrypted' => 'ENC(local)',
    ]);

$pdo->prepare('INSERT INTO roles (name) VALUES (:name)')->execute(['name' => 'administrator']);
$roleId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO permissions (name) VALUES (:name)')->execute(['name' => 'inventory.catalog.read']);
$permissionId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO role_has_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)')
    ->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);

$userId = 1;
$pdo->prepare('INSERT INTO user_has_roles (user_id, role_id) VALUES (:user_id, :role_id)')
    ->execute(['user_id' => $userId, 'role_id' => $roleId]);
$pdo->prepare('INSERT INTO user_company (user_id, company_id, role_id) VALUES (:user_id, :company_id, :role_id)')
    ->execute(['user_id' => $userId, 'company_id' => $companyId, 'role_id' => $roleId]);

$service = new AuthService($pdo);
$result = $service->attempt('mrp_demo', 'admin@example.com', 'secret123');

assert($result['user']['email'] === 'admin@example.com');
assert(($result['tenant']['database']['name'] ?? null) === 'mrp_demo');
assert(in_array('inventory.catalog.read', $result['permissions'], true));

echo "Login flow test: OK\n";
