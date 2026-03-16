<?php

/**
 * Patch 2: mover ítems de Administración y crear sección Empresa/Usuarios
 * - produccion.centros_trabajo, produccion.rutas, catalogos.entidades → catalogo_productos
 * - admin.empresa, admin.usuarios, admin.roles, admin.permisos → empresa_usuarios
 * Ejecutar una sola vez en el servidor. Borrar luego.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/autoload.php';

use App\Core\Config\Config;
use App\Core\Database\DatabaseManager;
use App\Core\Support\Env;

Env::load(base_path('.env'));
Config::load(base_path('config'));

$pdo = DatabaseManager::connection('mrp_auth');

$updates = [
    // Mover a catálogo_productos (datos maestros de estructura productiva)
    ['catalogo_productos', 'Catálogo de Productos', 65, 'catalogos.entidades'],
    ['catalogo_productos', 'Catálogo de Productos', 70, 'produccion.centros_trabajo'],
    ['catalogo_productos', 'Catálogo de Productos', 75, 'produccion.rutas'],

    // Crear sección Empresa · Usuarios · Roles
    ['empresa_usuarios', 'Empresa · Usuarios · Roles', 10, 'admin.empresa'],
    ['empresa_usuarios', 'Empresa · Usuarios · Roles', 20, 'admin.usuarios'],
    ['empresa_usuarios', 'Empresa · Usuarios · Roles', 30, 'admin.roles'],
    ['empresa_usuarios', 'Empresa · Usuarios · Roles', 40, 'admin.permisos'],

    // Reordenar administracion (compactar sort_order sin los ítems removidos)
    ['administracion', 'Administración', 10, 'catalogos.configuracion'],
    ['administracion', 'Administración', 20, 'catalogos.unidades'],
    ['administracion', 'Administración', 30, 'catalogos.tipos_partes'],
    ['administracion', 'Administración', 40, 'catalogos.tipos_depositos'],
    ['administracion', 'Administración', 50, 'catalogos.validaciones_depositos'],
    ['administracion', 'Administración', 60, 'catalogos.grupos_partes'],
];

$stmt = $pdo->prepare(
    'UPDATE menu_items SET section_key = :sk, section_label = :sl, sort_order = :so WHERE code = :code'
);

$pdo->beginTransaction();
try {
    $total = 0;
    foreach ($updates as [$sk, $sl, $so, $code]) {
        $stmt->execute(['sk' => $sk, 'sl' => $sl, 'so' => $so, 'code' => $code]);
        $rows = $stmt->rowCount();
        echo ($rows > 0 ? 'OK' : 'SKIP(not found)') . " [{$sk}] {$code}\n";
        $total += $rows;
    }
    $pdo->commit();
    echo "\n=== {$total} filas actualizadas. ===\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}

// Verificación
echo "\nEstado final:\n";
foreach (
    $pdo->query(
        'SELECT section_key, section_label, COUNT(*) n FROM menu_items GROUP BY section_key, section_label ORDER BY section_key'
    )->fetchAll(PDO::FETCH_ASSOC) as $r
) {
    echo "  {$r['section_key']} ({$r['section_label']}): {$r['n']} ítems\n";
}
