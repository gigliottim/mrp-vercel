<?php
require '/app/bootstrap/autoload.php';
use App\Core\Config\Config;
use App\Core\Database\DatabaseManager;
use App\Core\Support\Env;
Env::load('/app/.env');
Config::load('/app/config');
$pdo = DatabaseManager::connection('mrp_auth');

// Verificar schema_migrations
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (id SERIAL PRIMARY KEY, filename VARCHAR(255) NOT NULL UNIQUE, executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)");

$executed = [];
foreach ($pdo->query("SELECT filename FROM schema_migrations")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $executed[$row['filename']] = true;
}

echo "=== schema_migrations registradas ===" . PHP_EOL;
foreach (array_keys($executed) as $f) echo "  DONE: $f" . PHP_EOL;

$migrations = [
    '/app/database/migrations/2026_03_15_001_menu_items_herramientas_bom.sql',
    '/app/database/migrations/2026_03_15_002_reorganize_menu_sections_minipyme.sql',
    '/app/database/migrations/2026_03_16_001_menu_reorganize_produccion_maestros.sql',
];

foreach ($migrations as $file) {
    $base = basename($file);
    if (isset($executed[$base])) {
        echo "SKIP (ya ejecutada): $base" . PHP_EOL;
        continue;
    }
    $sql = file_get_contents($file);
    echo "Aplicando: $base ..." . PHP_EOL;
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (:f) ON CONFLICT (filename) DO NOTHING");
        $stmt->execute([':f' => $base]);
        echo "OK: $base" . PHP_EOL;
    } catch (Throwable $e) {
        echo "ERROR en $base: " . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "=== Estado final menu_items ===" . PHP_EOL;
$rows = $pdo->query("SELECT code, section_key, section_label FROM menu_items WHERE code IN ('catalogos.entidades','produccion.centros_trabajo','produccion.rutas') ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['code'] . ' | ' . $r['section_key'] . ' | ' . $r['section_label'] . PHP_EOL;
}

echo PHP_EOL . "=== Renombramiento catalogo_productos ===" . PHP_EOL;
$rows2 = $pdo->query("SELECT DISTINCT section_key, section_label FROM menu_items WHERE section_key = 'catalogo_productos' LIMIT 1")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows2 as $r) {
    echo $r['section_key'] . ' => label: ' . $r['section_label'] . PHP_EOL;
}
