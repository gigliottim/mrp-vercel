<?php

declare(strict_types=1);

use App\Core\Kernel;
use App\Core\Routing\Router;
use App\Core\Support\Env;
use App\Core\Config\Config;

require_once __DIR__ . '/autoload.php';

Env::load(base_path('.env'));
Config::load(base_path('config'));

$appTimezone = (string) Config::get('app.timezone', 'America/Argentina/Buenos_Aires');
if (@date_default_timezone_set($appTimezone) === false) {
    date_default_timezone_set('America/Argentina/Buenos_Aires');
}

$router = new Router();

$routesDir = base_path('routes');
if (is_dir($routesDir)) {
    foreach (['web.php', 'api.php'] as $routesFile) {
        $fullPath = $routesDir . DIRECTORY_SEPARATOR . $routesFile;
        if (file_exists($fullPath)) {
            require $fullPath;
        }
    }
}

return new Kernel($router);
