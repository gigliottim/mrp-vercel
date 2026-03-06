<?php

// Test para verificar qué layout usa cada página

require_once __DIR__ . '/../bootstrap/autoload.php';

use App\Core\Support\Env;
use App\Core\Config\Config;

Env::load(base_path('.env'));
Config::load(base_path('config'));

echo "=== Verificación de Layouts ===\n\n";

// Simular entorno web
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
$_SERVER['DOCUMENT_ROOT'] = 'C:/wamp64/www/mrp';
$_SERVER['REQUEST_URI'] = '/';

echo "Login usa: layouts/auth\n";
echo "Home usa: layouts/app (por defecto)\n\n";

$authLayout = base_path('views/layouts/auth.php');
$appLayout = base_path('views/layouts/app.php');

echo "Archivos de layout:\n";
echo "  auth.php: " . (file_exists($authLayout) ? 'Existe' : 'NO EXISTE') . "\n";
echo "  app.php: " . (file_exists($appLayout) ? 'Existe' : 'NO EXISTE') . "\n\n";

// Leer primeras líneas de cada layout para comparar
if (file_exists($authLayout)) {
    $authContent = file_get_contents($authLayout);
    preg_match('/AssetHelper::getBootstrap/', $authContent, $authMatches);
    echo "auth.php contiene AssetHelper::getBootstrap: " . (count($authMatches) > 0 ? 'SÍ' : 'NO') . "\n";
}

if (file_exists($appLayout)) {
    $appContent = file_get_contents($appLayout);
    preg_match('/AssetHelper::getBootstrap/', $appContent, $appMatches);
    echo "app.php contiene AssetHelper::getBootstrap: " . (count($appMatches) > 0 ? 'SÍ' : 'NO') . "\n";
}
