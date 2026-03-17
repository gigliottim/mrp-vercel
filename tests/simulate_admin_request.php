<?php

declare(strict_types=1);

use App\Core\Auth\AuthManager;
use App\Core\Http\Request;
use App\Core\Support\Env;
use App\Core\Config\Config;

require_once __DIR__ . '/../bootstrap/autoload.php';

Env::load(base_path('.env'));
Config::load(base_path('config'));

$kernel = require base_path('bootstrap/app.php');

$payload = [
    'user' => [
        'id' => 2,
        'name' => 'Usuario Demo',
        'email' => 'demo@mrp.local',
    ],
    'tenant' => [
        'id' => 2,
        'name' => 'Empresa Demo',
        'slug' => 'demo',
        'role_id' => 1,
        'database' => [
            'name' => 'mrp_tenant_demo',
            'host' => '127.0.0.1',
            'port' => '3306',
            'username' => 'root',
            'password' => '',
        ],
    ],
    'permissions' => [
        'systems.manage',
        'systems.users',
        'systems.roles',
        'tenants.provision',
        'tenants.switch',
        'inventory.catalog.read',
        'inventory.catalog.write',
        'production.orders',
        'reports.execution',
        'security.audit',
    ],
];

AuthManager::login($payload);

$request = new Request('GET', '/produccion/centros');
$response = $kernel->handle($request);

file_put_contents('php://stdout', "Response length: " . strlen($response->getContent()) . "\n");
