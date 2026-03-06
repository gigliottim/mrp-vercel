<?php

// Simular entorno web con las variables de Apache
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
$_SERVER['DOCUMENT_ROOT'] = 'C:/wamp64/www/mrp';
$_SERVER['REQUEST_URI'] = '/';

require_once __DIR__ . '/../bootstrap/autoload.php';

use App\Core\Support\Env;
use App\Core\Config\Config;
use App\Core\Support\AssetHelper;

Env::load(base_path('.env'));
Config::load(base_path('config'));

echo "=== Simulación de entorno Apache ===\n\n";

echo "Variables del servidor:\n";
echo "  SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "  DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "  REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n\n";

echo "Configuración:\n";
echo "  app.asset_prefix: '" . config('app.asset_prefix') . "'\n\n";

echo "Rutas generadas por AssetHelper:\n";
echo "  Bootstrap CSS: " . AssetHelper::getBootstrap('css') . "\n";
echo "  Font Awesome: " . AssetHelper::getFontAwesome() . "\n";
echo "  main.css: " . AssetHelper::css('global/main.css') . "\n\n";

echo "URLs completas que verá el navegador:\n";
echo "  http://mrp.local" . AssetHelper::getBootstrap('css') . "\n";
echo "  http://mrp.local" . AssetHelper::getFontAwesome() . "\n";
echo "  http://mrp.local" . AssetHelper::css('global/main.css') . "\n";
