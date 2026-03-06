<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Auth\AuthManager;
use App\Core\Auth\TenantContext;
use App\Core\Database\DatabaseManager;
use App\Core\Support\SessionManager;

if (session_status() === PHP_SESSION_NONE) {
    SessionManager::start();
}

$tenant = AuthManager::tenant();
if ($tenant === null) {
    echo "[ERROR] No hay sesión de tenant activa.\n";
    echo "Iniciá sesión en la app y volvé a ejecutar este script.\n";
    exit(1);
}

TenantContext::set($tenant);
$conn = DatabaseManager::connection('tenant');

function check(bool $condition, string $ok, string $fail): void
{
    echo $condition ? "[OK] {$ok}\n" : "[FAIL] {$fail}\n";
}

echo "Verificando modelo de unidades y conversión...\n\n";

$columns = [
    'is_system',
    'locked',
    'activo',
];

foreach ($columns as $column) {
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_name = 'unidades_medida' AND column_name = :c");
    $stmt->execute(['c' => $column]);
    check((bool) $stmt->fetchColumn(), "Columna '{$column}' existe", "Falta columna '{$column}' en unidades_medida");
}

echo "\nUnidades protegidas esperadas:\n";
$expected = [
    ['tipo' => 'longitud', 'simbolo' => 'm'],
    ['tipo' => 'longitud', 'simbolo' => 'mL'],
    ['tipo' => 'superficie', 'simbolo' => 'm²'],
    ['tipo' => 'masa', 'simbolo' => 'kg'],
    ['tipo' => 'unidad', 'simbolo' => 'u'],
    ['tipo' => 'unidad', 'simbolo' => 'caja'],
    ['tipo' => 'unidad', 'simbolo' => 'rollo'],
];

foreach ($expected as $unit) {
    $stmt = $conn->prepare(
        'SELECT is_system, locked FROM unidades_medida WHERE tipo = :tipo AND simbolo = :simbolo LIMIT 1'
    );
    $stmt->execute($unit);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        check(false, '', "No existe {$unit['tipo']} / {$unit['simbolo']}");
        continue;
    }

    $ok = ((int) ($row['is_system'] ?? 0) === 1) && ((int) ($row['locked'] ?? 0) === 1);
    check(
        $ok,
        "{$unit['tipo']} / {$unit['simbolo']} está protegida",
        "{$unit['tipo']} / {$unit['simbolo']} existe pero no está protegida"
    );
}

echo "\nVerificación de datos Parte/Variante para doble UM:\n";

$sql = "
SELECT
    COUNT(*) FILTER (WHERE p.id_um_compra IS NOT NULL AND p.id_um_uso IS NOT NULL) AS partes_con_doble_um,
    COUNT(*) FILTER (WHERE p.id_um_compra IS NOT NULL AND p.id_um_uso IS NOT NULL AND COALESCE(p.factor_conversion, 0) > 0) AS partes_con_factor,
    COUNT(*) FILTER (WHERE v.lote_minimo > 0) AS variantes_lote_ok,
    COUNT(*) AS variantes_total
FROM partes p
LEFT JOIN variantes v ON v.id_parte = p.id
";

$row = $conn->query($sql)->fetch(PDO::FETCH_ASSOC);

$partesConDobleUm = (int) ($row['partes_con_doble_um'] ?? 0);
$partesConFactor = (int) ($row['partes_con_factor'] ?? 0);
$variantesLoteOk = (int) ($row['variantes_lote_ok'] ?? 0);
$variantesTotal = (int) ($row['variantes_total'] ?? 0);

echo "Partes con UM compra+uso: {$partesConDobleUm}\n";
echo "Partes con factor válido: {$partesConFactor}\n";
echo "Variantes con lote_minimo > 0: {$variantesLoteOk}/{$variantesTotal}\n";

check($partesConDobleUm >= $partesConFactor, 'Consistencia básica de factor por doble UM', 'Inconsistencia en factor de conversión');
check($variantesTotal === 0 || $variantesLoteOk === $variantesTotal, 'Todas las variantes tienen lote mínimo válido', 'Hay variantes con lote mínimo inválido');

echo "\nFin de verificación.\n";
