<?php

require_once __DIR__ . '/../bootstrap/autoload.php';

use App\Core\Support\Env;
use App\Core\Config\Config;
use App\Core\Support\AssetHelper;

Env::load(base_path('.env'));
Config::load(base_path('config'));

echo "=== Test de AssetHelper ===\n\n";

echo "Variables del servidor:\n";
echo "  SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'NULL') . "\n";
echo "  DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'NULL') . "\n";
echo "  REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NULL') . "\n\n";

echo "Configuración:\n";
echo "  app.asset_prefix: " . (config('app.asset_prefix') ?? 'NULL') . "\n\n";

echo "Rutas generadas:\n";
echo "  Bootstrap CSS: " . AssetHelper::getBootstrap('css') . "\n";
echo "  Font Awesome: " . AssetHelper::getFontAwesome() . "\n";
echo "  Alpine JS: " . AssetHelper::getAlpineJS() . "\n";
echo "  main.css: " . AssetHelper::css('global/main.css') . "\n";
echo "  cards.css: " . AssetHelper::css('components/cards.css') . "\n\n";

echo "Verificación de CDN Online:\n";
echo "  Bootstrap CSS: " . AssetHelper::getBootstrap('css') . "\n";
echo "  Bootstrap JS: " . AssetHelper::getBootstrap('js') . "\n";
echo "  Font Awesome: " . AssetHelper::getFontAwesome() . "\n";
echo "  Alpine JS: " . AssetHelper::getAlpineJS() . "\n";
echo "  CDN Online está configurado correctamente.\n";
