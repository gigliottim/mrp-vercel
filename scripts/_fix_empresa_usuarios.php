<?php
require '/app/bootstrap/autoload.php';
use App\Core\Config\Config;
use App\Core\Database\DatabaseManager;
use App\Core\Support\Env;
Env::load('/app/.env');
Config::load('/app/config');
$pdo = DatabaseManager::connection('mrp_auth');

echo "=== Estado ANTES ===" . PHP_EOL;
foreach ($pdo->query("SELECT code, section_key FROM menu_items WHERE code LIKE 'admin.%' ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo $r['code'] . ' => ' . $r['section_key'] . PHP_EOL;
}

$updates = [
    [10, 'admin.empresa'],
    [20, 'admin.usuarios'],
    [30, 'admin.roles'],
    [40, 'admin.permisos'],
];
$stmt = $pdo->prepare("UPDATE menu_items SET section_key='empresa_usuarios', section_label='Empresa y Usuarios', sort_order=:so WHERE code=:code");
$total = 0;
foreach ($updates as [$so, $code]) {
    $stmt->execute(['so' => $so, 'code' => $code]);
    $total += $stmt->rowCount();
}
echo PHP_EOL . "Filas actualizadas: $total" . PHP_EOL;

// Registrar si falta
$pdo->prepare("INSERT INTO schema_migrations (filename) VALUES ('2026_03_16_002_menu_empresa_usuarios_section.sql') ON CONFLICT (filename) DO NOTHING")->execute();

echo PHP_EOL . "=== Estado DESPUES ===" . PHP_EOL;
foreach ($pdo->query("SELECT code, section_key FROM menu_items WHERE code LIKE 'admin.%' ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo $r['code'] . ' => ' . $r['section_key'] . PHP_EOL;
}
