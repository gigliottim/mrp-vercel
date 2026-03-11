<?php

/**
 * fix-tunna-direct.php
 * Corrige mojibake CP437 directamente en mrp_tunna (partes y variantes).
 * Uso:  docker compose exec -T php-fpm php /app/scripts/fix-tunna-direct.php
 * Usar  --run   para aplicar cambios (sin flag es dry-run).
 */

declare(strict_types=1);

$dryRun = !in_array('--run', $argv ?? [], true);

// ── Leer .env ─────────────────────────────────────────────────────────────────
function readEnv(string $path): array
{
    if (!file_exists($path)) return [];
    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim(trim($v), '"\'');
    }
    return $env;
}

$envPath = file_exists('/app/.env') ? '/app/.env' : '/opt/mrp/.env';
$env = readEnv($envPath);

$host = $env['DB_AUTH_HOST']     ?? $env['DB_PGSQL_HOST']     ?? 'postgresql';
$port = $env['DB_AUTH_PORT']     ?? $env['DB_PGSQL_PORT']     ?? '5432';
$user = $env['DB_AUTH_USERNAME'] ?? $env['DB_PGSQL_USERNAME'] ?? 'mrp_unik_2026';
$pass = $env['DB_AUTH_PASSWORD'] ?? $env['DB_PGSQL_PASSWORD'] ?? '';
$dbName = 'mrp_tunna';

echo "\n=== Fix CP437 en $dbName " . ($dryRun ? "[DRY-RUN]" : "[APLICANDO]") . " ===\n\n";
echo "Host: $host:$port  Usuario: $user\n\n";

// ── Conectar ──────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbName;options='--client_encoding=UTF8'",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo "ERROR conexión: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Conexión OK\n\n";

// ── Función auxiliar ──────────────────────────────────────────────────────────
// Detecta si un texto tiene caracteres de box-drawing (U+2500-U+259F) o símbolos
// típicos del mojibake CP437. Usa bytes E2 94/95/96 que son el prefijo UTF-8 del
// rango 2500-25FF, y E2 8C para U+2310 (⌐) que aparece con é/É.
function hasMojibake(string $t): bool
{
    if ($t === '') return false;
    // Prefijo UTF-8 de box-drawing U+2500-U+25FF = E2 94 / E2 95 / E2 96...
    if (str_contains($t, "\xE2\x94") || str_contains($t, "\xE2\x95") || str_contains($t, "\xE2\x96")) return true;
    // U+2310 (⌐) aparece cuando é (C3 A9) se corrompe → ├⌐
    if (str_contains($t, "\xE2\x8C")) return true;
    // Bytes crudos no válidos como UTF-8
    if (!mb_check_encoding($t, 'UTF-8')) return true;
    return false;
}

// Convierte text mojibakeado (CP437 interpretado como UTF-8) de vuelta a texto correcto.
// Usa iconv UTF-8→CP437 para recuperar los bytes originales (que son UTF-8 válido).
function fixMojibake(string $t): string
{
    if (!hasMojibake($t)) return $t;
    // iconv: cada char Unicode → byte CP437 equivalente → resultado son bytes UTF-8 originales
    $fixed = @iconv('UTF-8', 'CP437//IGNORE', $t);
    if ($fixed === false || $fixed === '') return $t;
    if (!mb_check_encoding($fixed, 'UTF-8')) return $t;
    if (hasMojibake($fixed)) return $t;   // aún tiene problemas
    return $fixed;
}

// ── Tablas / columnas a reparar ───────────────────────────────────────────────
$tablasCols = [
    'partes'    => ['codigo', 'detalle'],
    'variantes' => ['codigo_variante', 'descripcion', 'detalle'],
];

$totalMojibake  = 0;
$totalFixed     = 0;

foreach ($tablasCols as $tabla => $cols) {
    echo "── Tabla: $tabla ──────────────────────────────────────\n";

    // Verificar que la tabla existe
    $existe = $pdo->query(
        "SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name=" . $pdo->quote($tabla)
    )->fetchColumn();
    if (!$existe) {
        echo "  (tabla no existe, se omite)\n\n";
        continue;
    }

    // Columnas que realmente existen en la tabla
    $colsExistentes = $pdo->query(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema='public' AND table_name=" . $pdo->quote($tabla)
    )->fetchAll(PDO::FETCH_COLUMN);

    echo "  Columnas encontradas en $tabla: " . implode(', ', $colsExistentes) . "\n";

    $colsValidas = array_values(array_filter($cols, fn($c) => in_array($c, $colsExistentes, true)));
    echo "  Columnas a revisar: " . (empty($colsValidas) ? '(ninguna)' : implode(', ', $colsValidas)) . "\n";

    if (empty($colsValidas)) {
        echo "\n";
        continue;
    }

    // Muestra primera fila para debug
    $firstRow = $pdo->query("SELECT id, " . $pdo->quote($colsValidas[0]) . " AS campo FROM \"$tabla\" LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($firstRow) {
        $val = (string)$firstRow['campo'];
        $hex = strtoupper(bin2hex($val));
        echo "  Primera fila id={$firstRow['id']} {$colsValidas[0]} HEX: " . chunk_split($hex, 4, ' ') . "\n";
        echo "  hasMojibake({$colsValidas[0]}): " . (hasMojibake($val) ? 'SÍ' : 'NO') . "\n";
    }
    echo "\n";

    foreach ($colsValidas as $col) {
        // Traer todas las filas y filtrar/convertir en PHP con iconv
        // (PostgreSQL 18 Bitnami no tiene CP437 disponible en convert_to/convert_from)
        try {
            $stmt = $pdo->prepare(
                "SELECT id, \"$col\" FROM \"$tabla\"
                 WHERE \"$col\" IS NOT NULL AND \"$col\" <> ''
                 ORDER BY id"
            );
            $stmt->execute();
            $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "  [$col] ERROR en SELECT: " . $e->getMessage() . "\n";
            continue;
        }

        // Filtrar en PHP los que tienen mojibake
        $affected = [];
        foreach ($allRows as $r) {
            $original = (string)($r[$col] ?? '');
            if (!hasMojibake($original)) continue;
            $fixed = fixMojibake($original);
            if ($fixed === $original) continue; // sin cambio posible
            $affected[] = ['id' => (int)$r['id'], 'original' => $original, 'fixed' => $fixed];
        }

        if (empty($affected)) {
            echo "  [$col] Sin mojibake detectado\n";
            continue;
        }

        echo "  [$col] Filas con mojibake: " . count($affected) . "\n";
        $totalMojibake += count($affected);

        foreach ($affected as $item) {
            echo "    ID {$item['id']}: " . mb_substr($item['original'], 0, 60) . "\n";
            echo "          => " . mb_substr($item['fixed'], 0, 60) . "\n";
        }

        if (!$dryRun) {
            $countUpdated = 0;
            $stmtUpd = $pdo->prepare("UPDATE \"$tabla\" SET \"$col\" = :val WHERE id = :id");
            foreach ($affected as $item) {
                try {
                    $stmtUpd->execute(['val' => $item['fixed'], 'id' => $item['id']]);
                    $countUpdated++;
                } catch (PDOException $e) {
                    echo "  [$col] ❌ ERROR UPDATE id={$item['id']}: " . $e->getMessage() . "\n";
                }
            }
            echo "  [$col] ✅ $countUpdated filas actualizadas\n";
            $totalFixed += $countUpdated;
        }
    }
    echo "\n";
}

echo "────────────────────────────────────────────────────\n";
echo "Total filas con mojibake detectadas : $totalMojibake\n";
if (!$dryRun) {
    echo "Total filas actualizadas            : $totalFixed\n";
    echo "✅ Fix completado en $dbName\n";
} else {
    echo "Modo DRY-RUN — ningún cambio escrito.\n";
    echo "Para aplicar: php /app/scripts/fix-tunna-direct.php --run\n";
}
echo "\n";
