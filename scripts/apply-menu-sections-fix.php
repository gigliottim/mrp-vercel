<?php
/**
 * Script one-shot: aplica los UPDATEs de secciones del menú.
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
    // TALLER
    ['taller', 'Taller',  5,   'panel.inicio'],
    ['taller', 'Taller', 10,   'produccion.dashboard'],
    ['taller', 'Taller', 20,   'produccion.ordenes'],
    ['taller', 'Taller', 30,   'transacciones.movimientos'],
    ['taller', 'Taller', 40,   'inventario.critico'],
    ['taller', 'Taller', 50,   'produccion.gantt'],
    // CATÁLOGO DE PRODUCTOS
    ['catalogo_productos', 'Catálogo de Productos', 10, 'productos.partes'],
    ['catalogo_productos', 'Catálogo de Productos', 20, 'productos.manager'],
    ['catalogo_productos', 'Catálogo de Productos', 30, 'productos.bom'],
    ['catalogo_productos', 'Catálogo de Productos', 40, 'productos.maestro'],
    ['catalogo_productos', 'Catálogo de Productos', 50, 'productos.copiar_componentes'],
    ['catalogo_productos', 'Catálogo de Productos', 60, 'productos.reemplazar_partes'],
    // PLANIFICACIÓN Y COMPRAS
    ['planificacion_compras', 'Planificación y Compras', 10, 'planeamiento.sugerencias'],
    ['planificacion_compras', 'Planificación y Compras', 20, 'planeamiento.ordenes'],
    ['planificacion_compras', 'Planificación y Compras', 30, 'produccion.planificacion'],
    ['planificacion_compras', 'Planificación y Compras', 40, 'transacciones.compras'],
    // ADMINISTRACIÓN
    ['administracion', 'Administración', 10,  'catalogos.configuracion'],
    ['administracion', 'Administración', 20,  'catalogos.entidades'],
    ['administracion', 'Administración', 30,  'catalogos.unidades'],
    ['administracion', 'Administración', 40,  'catalogos.tipos_partes'],
    ['administracion', 'Administración', 50,  'catalogos.tipos_depositos'],
    ['administracion', 'Administración', 60,  'catalogos.validaciones_depositos'],
    ['administracion', 'Administración', 70,  'catalogos.grupos_partes'],
    ['administracion', 'Administración', 80,  'produccion.centros_trabajo'],
    ['administracion', 'Administración', 90,  'produccion.rutas'],
    ['administracion', 'Administración', 100, 'admin.empresa'],
    ['administracion', 'Administración', 110, 'admin.usuarios'],
    ['administracion', 'Administración', 120, 'admin.roles'],
    ['administracion', 'Administración', 130, 'admin.permisos'],
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
        echo ($rows > 0 ? "OK" : "SKIP(not found)") . " [{$sk}] {$code}\n";
        $total += $rows;
    }
    $pdo->commit();
    echo "\n=== {$total} filas actualizadas. ===\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Verificación
echo "\nEstado final:\n";
foreach ($pdo->query('SELECT section_key, COUNT(*) n FROM menu_items GROUP BY section_key ORDER BY section_key')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  {$r['section_key']}: {$r['n']}\n";
}
