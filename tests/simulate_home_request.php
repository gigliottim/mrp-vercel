<?php

use App\Controllers\HomeController;
use App\Core\Http\Request;
use App\Core\Support\Env;
use App\Core\Config\Config;

require_once __DIR__ . '/../bootstrap/autoload.php';

Env::load(base_path('.env'));
Config::load(base_path('config'));

// Simular petición al home sin pasar por el router para evitar middleware de auth
$server = [
    'SCRIPT_NAME' => '/public/index.php',
    'DOCUMENT_ROOT' => 'C:/wamp64/www/mrp',
    'REQUEST_URI' => '/',
    'REQUEST_METHOD' => 'GET',
];

$request = new Request('GET', '/', [], [], [], $server);
$controller = new HomeController();

try {
    $response = $controller->index($request);

    // Extraer solo el <head> de la respuesta
    $html = $response->getContent();

    preg_match('/<head>(.*?)<\/head>/s', $html, $matches);
    if (isset($matches[1])) {
        echo "=== <HEAD> del Home ===\n\n";

        // Extraer todas las etiquetas link
        preg_match_all('/<link[^>]+>/i', $matches[1], $links);
        foreach ($links[0] as $link) {
            // Extraer el href
            if (preg_match('/href=["\']([^"\']+)["\']/', $link, $href)) {
                echo $href[1] . "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
