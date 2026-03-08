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

$dryRun = in_array('--dry-run', $argv, true);

$tenant = AuthManager::tenant();
if ($tenant !== null) {
    TenantContext::set($tenant);
    $conn = DatabaseManager::connection('tenant');
    echo "[INFO] Tenant obtenido desde sesión activa.\n";
} else {
    echo "[WARN] No hay sesión tenant activa; usando conexión 'tenant' de configuración.\n";
    $conn = DatabaseManager::connection('tenant');
}

/**
 * Replica el redondeo del boton Recalcular en partes-manager.js.
 */
function roundLikeUi(float $value, int $decimals = 8): float
{
    $factor = 10 ** $decimals;
    return round($value * $factor) / $factor;
}

function toNullableInt(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $intValue = (int) $value;
    return $intValue > 0 ? $intValue : null;
}

function toFloatOrZero(mixed $value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }

    return (float) $value;
}

function nearlyEqual(?float $a, ?float $b, float $epsilon = 0.00000001): bool
{
    if ($a === null && $b === null) {
        return true;
    }

    if ($a === null || $b === null) {
        return false;
    }

    return abs($a - $b) <= $epsilon;
}

$unitsStmt = $conn->query("SELECT id, tipo, simbolo, equivalencia_base FROM unidades_medida WHERE tipo IN ('longitud','superficie','volumen')");
$units = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);

$longitudById = [];
$superficieUnits = [];
$volumenUnits = [];

foreach ($units as $unit) {
    $id = (int) ($unit['id'] ?? 0);
    $tipo = (string) ($unit['tipo'] ?? '');
    $simbolo = (string) ($unit['simbolo'] ?? '');
    $equivalencia = isset($unit['equivalencia_base']) ? (float) $unit['equivalencia_base'] : null;

    if ($tipo === 'longitud') {
        $longitudById[$id] = $equivalencia;
    } elseif ($tipo === 'superficie') {
        $superficieUnits[] = ['id' => $id, 'simbolo' => $simbolo];
    } elseif ($tipo === 'volumen') {
        $volumenUnits[] = ['id' => $id, 'simbolo' => $simbolo];
    }
}

$defaultSuperficieId = null;
foreach ($superficieUnits as $unit) {
    if ($unit['simbolo'] === 'm²' || $unit['simbolo'] === 'mts²') {
        $defaultSuperficieId = (int) $unit['id'];
        break;
    }
}

$defaultVolumenId = null;
foreach ($volumenUnits as $unit) {
    $simbolo = mb_strtolower((string) $unit['simbolo']);
    if ($unit['simbolo'] === 'cm³' || str_contains($simbolo, 'cm')) {
        $defaultVolumenId = (int) $unit['id'];
        break;
    }
}

$partsSql = 'SELECT id, codigo, largo_alto, id_um_largo_alto, ancho, id_um_ancho, espesor_profundidad, id_um_espesor, superficie, id_um_superficie, volumen, id_um_volumen FROM partes ORDER BY id';
$partsStmt = $conn->query($partsSql);
$parts = $partsStmt->fetchAll(PDO::FETCH_ASSOC);

$updateSql = 'UPDATE partes
SET superficie = :superficie,
    id_um_superficie = :id_um_superficie,
    volumen = :volumen,
    id_um_volumen = :id_um_volumen
WHERE id = :id';
$updateStmt = $conn->prepare($updateSql);

$total = count($parts);
$updated = 0;
$unchanged = 0;

if (!$dryRun) {
    $conn->beginTransaction();
}

try {
    foreach ($parts as $part) {
        $id = (int) $part['id'];

        $largo = toFloatOrZero($part['largo_alto'] ?? null);
        $ancho = toFloatOrZero($part['ancho'] ?? null);
        $espesor = toFloatOrZero($part['espesor_profundidad'] ?? null);

        $equivLargo = $longitudById[(int) ($part['id_um_largo_alto'] ?? 0)] ?? 1.0;
        $equivAncho = $longitudById[(int) ($part['id_um_ancho'] ?? 0)] ?? 1.0;
        $equivEspesor = $longitudById[(int) ($part['id_um_espesor'] ?? 0)] ?? 1.0;

        $largoM = $largo * $equivLargo;
        $anchoM = $ancho * $equivAncho;
        $espesorM = $espesor * $equivEspesor;

        $newSuperficie = isset($part['superficie']) ? (float) $part['superficie'] : null;
        $newVolumen = isset($part['volumen']) ? (float) $part['volumen'] : null;
        $newIdUmSuperficie = toNullableInt($part['id_um_superficie'] ?? null);
        $newIdUmVolumen = toNullableInt($part['id_um_volumen'] ?? null);

        if ($largo > 0 && $ancho > 0) {
            $newSuperficie = roundLikeUi($largoM * $anchoM, 8);
            if ($newIdUmSuperficie === null && $defaultSuperficieId !== null) {
                $newIdUmSuperficie = $defaultSuperficieId;
            }
        }

        if ($largo > 0 && $ancho > 0 && $espesor > 0) {
            $volumenCm3 = $largoM * $anchoM * $espesorM * 1000000;
            $newVolumen = roundLikeUi($volumenCm3, 8);
            if ($newIdUmVolumen === null && $defaultVolumenId !== null) {
                $newIdUmVolumen = $defaultVolumenId;
            }
        }

        $oldSuperficie = isset($part['superficie']) ? (float) $part['superficie'] : null;
        $oldVolumen = isset($part['volumen']) ? (float) $part['volumen'] : null;
        $oldIdUmSuperficie = toNullableInt($part['id_um_superficie'] ?? null);
        $oldIdUmVolumen = toNullableInt($part['id_um_volumen'] ?? null);

        $hasChanges = !nearlyEqual($oldSuperficie, $newSuperficie)
            || !nearlyEqual($oldVolumen, $newVolumen)
            || $oldIdUmSuperficie !== $newIdUmSuperficie
            || $oldIdUmVolumen !== $newIdUmVolumen;

        if (!$hasChanges) {
            $unchanged++;
            continue;
        }

        $updated++;

        if ($dryRun) {
            echo sprintf(
                "[DRY] Parte #%d (%s): superficie %s -> %s, UM sup %s -> %s, volumen %s -> %s, UM vol %s -> %s\n",
                $id,
                (string) ($part['codigo'] ?? ''),
                var_export($oldSuperficie, true),
                var_export($newSuperficie, true),
                var_export($oldIdUmSuperficie, true),
                var_export($newIdUmSuperficie, true),
                var_export($oldVolumen, true),
                var_export($newVolumen, true),
                var_export($oldIdUmVolumen, true),
                var_export($newIdUmVolumen, true)
            );
            continue;
        }

        $updateStmt->execute([
            'superficie' => $newSuperficie,
            'id_um_superficie' => $newIdUmSuperficie,
            'volumen' => $newVolumen,
            'id_um_volumen' => $newIdUmVolumen,
            'id' => $id,
        ]);
    }

    if (!$dryRun) {
        $conn->commit();
    }
} catch (Throwable $e) {
    if (!$dryRun && $conn->inTransaction()) {
        $conn->rollBack();
    }

    echo "[ERROR] Fallo durante el recalculo: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Recalculo finalizado.\n";
echo "- Partes evaluadas: {$total}\n";
echo "- Partes actualizadas: {$updated}\n";
echo "- Sin cambios: {$unchanged}\n";
echo $dryRun ? "- Modo: dry-run (sin persistir)\n" : "- Modo: aplicado en BD\n";
