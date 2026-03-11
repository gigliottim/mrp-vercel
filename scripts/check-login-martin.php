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
    $result = $auth->validateUser('martin@unik.ar', 'apolo785');

    echo 'OK user=' . $result['user']['email'] . ' companies=' . count($result['companies']) . PHP_EOL;
    foreach ($result['companies'] as $company) {
        echo 'tenant=' . ($company['slug'] ?? '')
            . ' db=' . ($company['database_name'] ?? '')
            . ' host=' . ($company['host'] ?? '')
            . ' port=' . ($company['port'] ?? '')
            . PHP_EOL;
    }
} catch (Throwable $exception) {
    echo 'ERR ' . $exception->getMessage() . PHP_EOL;
    exit(1);
}
