<?php
require '/app/bootstrap/autoload.php';
use App\Core\Config\Config;
use App\Core\Database\DatabaseManager;
use App\Core\Support\Env;
Env::load('/app/.env');
Config::load('/app/config');
$pdo = DatabaseManager::connection('mrp_auth');
$rows = $pdo->query("SELECT code, section_key, section_label FROM menu_items WHERE code IN ('catalogos.entidades','produccion.centros_trabajo','produccion.rutas') ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['code'] . ' | ' . $r['section_key'] . ' | ' . $r['section_label'] . PHP_EOL;
}
$migs = $pdo->query("SELECT filename, executed_at FROM schema_migrations ORDER BY executed_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "--- schema_migrations (ultimas 5) ---" . PHP_EOL;
foreach ($migs as $m) {
    echo $m['filename'] . ' => ' . $m['executed_at'] . PHP_EOL;
}
