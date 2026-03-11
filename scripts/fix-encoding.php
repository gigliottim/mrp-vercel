<?php

/**
 * fix-encoding.php
 * Corrige mojibake (caracteres corruptos por mezcla Latin-1/UTF-8) en todas las BDs tenant.
 *
 * Uso:
 *   php fix-encoding.php          → dry-run: solo muestra qué cambiaría
 *   php fix-encoding.php --run    → aplica los cambios
 *   php fix-encoding.php --db=mrp_tunna --run  → solo una BD
 */

declare(strict_types=1);

// ─── Config ──────────────────────────────────────────────────────────────────

$dryRun = !in_array('--run', $argv, true);
$onlyDb = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $onlyDb = substr($arg, 5);
    }
}

// Ruta al .env (ejecutar desde /opt/mrp o ajustar)
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    $envFile = '/opt/mrp/.env';
}

$env = parseEnv($envFile);

$authHost = $env['DB_AUTH_HOST']     ?? $env['DB_PGSQL_HOST']     ?? 'postgresql';
$authPort = $env['DB_AUTH_PORT']     ?? $env['DB_PGSQL_PORT']     ?? '5432';
$authUser = $env['DB_AUTH_USERNAME'] ?? $env['DB_PGSQL_USERNAME'] ?? 'mrp_unik_2026';
$authPass = $env['DB_AUTH_PASSWORD'] ?? $env['DB_PGSQL_PASSWORD'] ?? '';
$authDb   = $env['DB_AUTH_DATABASE'] ?? 'mrp_auth';

// Tablas y columnas de texto a revisar en cada BD tenant
// Clave = tabla, valor = array de columnas
$tableCols = [
    'partes'               => ['nombre', 'descripcion', 'observaciones', 'codigo'],
    'variantes'            => ['descripcion', 'codigo_variante'],
    'tipos_partes'         => ['nombre', 'descripcion'],
    'grupos_partes'        => ['nombre', 'descripcion'],
    'almacenes'            => ['nombre', 'descripcion'],
    'centros_trabajo'      => ['nombre', 'descripcion'],
    'unidades_medida'      => ['nombre', 'descripcion', 'simbolo'],
    'entidades'            => ['nombre', 'descripcion', 'observaciones'],
    'bom_cabecera'         => ['descripcion', 'observaciones'],
    'ordenes_produccion'   => ['descripcion', 'observaciones'],
    'movimientos_stock'    => ['observaciones', 'referencia_documento'],
    'movimientos_inventario' => ['observaciones', 'referencia_documento'],
    'configuracion_general' => ['valor'],
];

// ─── Conectar a mrp_auth y obtener lista de BDs tenant ───────────────────────

$authDsn = "pgsql:host=$authHost;port=$authPort;dbname=$authDb;options='--client_encoding=UTF8'";
try {
    $authPdo = new PDO($authDsn, $authUser, $authPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("[ERR] No se pudo conectar a mrp_auth: " . $e->getMessage() . "\n");
}

$tenantRows = $authPdo->query(
    "SELECT cd.database_name, cd.host, cd.port, cd.username, cd.password_encrypted
     FROM company_databases cd
     ORDER BY cd.database_name"
)->fetchAll(PDO::FETCH_ASSOC);

if (empty($tenantRows)) {
    die("[ERR] No hay registros en company_databases.\n");
}

// ─── Modo dry-run info ────────────────────────────────────────────────────────

echo str_repeat('=', 70) . "\n";
echo "FIX ENCODING - " . ($dryRun ? "DRY RUN (sin cambios)" : "APLICANDO CAMBIOS") . "\n";
echo str_repeat('=', 70) . "\n\n";

$totalFixed = 0;
$totalRows  = 0;

// ─── Procesar cada BD tenant ──────────────────────────────────────────────────

foreach ($tenantRows as $row) {
    $dbName  = $row['database_name'];
    $dbHost  = $row['host'];
    $dbPort  = $row['port'];
    $dbUser  = $row['username'];
    $dbPass  = $row['password_encrypted'];

    if ($onlyDb !== null && $dbName !== $onlyDb) {
        continue;
    }

    echo "── BD: $dbName ──────────────────────────────────────────────\n";

    $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName;options='--client_encoding=UTF8'";
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        echo "  [SKIP] No se pudo conectar: " . $e->getMessage() . "\n\n";
        continue;
    }

    // Obtener tablas reales que existen en esta BD
    $existingTables = $pdo->query(
        "SELECT table_name FROM information_schema.tables
         WHERE table_schema='public' AND table_type='BASE TABLE'"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tableCols as $table => $cols) {
        if (!in_array($table, $existingTables, true)) {
            continue;
        }

        // Obtener columnas reales que existen en esta tabla
        $existingCols = $pdo->query(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema='public' AND table_name=" . $pdo->quote($table)
        )->fetchAll(PDO::FETCH_COLUMN);

        $validCols = array_intersect($cols, $existingCols);
        if (empty($validCols)) {
            continue;
        }

        // Detectar mojibake: patron CP437 (├ ┬ ▓ ▒) y patron Latin-1 (Ã  â€)
        $whereMojibake = array_map(
            fn($c) => "$c LIKE '%├%' OR $c LIKE '%┬%' OR $c LIKE '%▓%' OR $c LIKE '%▒%'"
                . " OR $c LIKE '%Ã%' OR $c LIKE '%â€%' OR $c LIKE '%Â%'",
            $validCols
        );

        $sql = "SELECT id, " . implode(', ', $validCols)
            . " FROM $table WHERE " . implode(' OR ', $whereMojibake);

        try {
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // La tabla puede no tener columna 'id' — intentar con ctid
            echo "  [WARN] $table: " . $e->getMessage() . "\n";
            continue;
        }

        if (empty($rows)) {
            continue;
        }

        echo "  Tabla: $table — " . count($rows) . " fila(s) con encoding sospechoso\n";

        foreach ($rows as $r) {
            $id      = $r['id'];
            $updates = [];
            $preview = [];

            foreach ($validCols as $col) {
                $original = $r[$col] ?? null;
                if ($original === null) continue;

                $fixed = fixEncoding($original);
                if ($fixed !== $original) {
                    $updates[$col] = $fixed;
                    $preview[] = "    $col: " . truncate($original) . "\n"
                        . "       → " . truncate($fixed);
                }
            }

            if (empty($updates)) continue;

            $totalRows++;
            echo "  ID $id:\n" . implode("\n", $preview) . "\n";

            if (!$dryRun) {
                $setParts = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($updates)));
                $updates['_id'] = $id;
                $stmt = $pdo->prepare("UPDATE $table SET $setParts WHERE id = :_id");
                $stmt->execute($updates);
                $totalFixed++;
            }
        }
    }

    echo "\n";
}

// ─── Resumen ──────────────────────────────────────────────────────────────────

echo str_repeat('=', 70) . "\n";
if ($dryRun) {
    echo "DRY RUN completado. $totalRows fila(s) serían modificadas.\n";
    echo "Para aplicar: php fix-encoding.php --run\n";
} else {
    echo "COMPLETADO. $totalFixed fila(s) corregidas.\n";
}
echo str_repeat('=', 70) . "\n";

// ─── Funciones ────────────────────────────────────────────────────────────────

/**
 * Detecta mojibake patrón CP437: caracteres de box-drawing Unicode que
 * provienen de bytes UTF-8 interpretados como CP437.
 * Ej: ├æ (U+251C U+00E6) = bytes 0xC3 0x91 en CP437 = Ñ en UTF-8.
 */
function hasCp437Mojibake(string $text): bool
{
    // Box-drawing (U+2500–U+257F) y block elements (U+2580–U+259F)
    // no aparecen en texto normal en español
    return preg_match('/[\x{2500}-\x{259F}]/u', $text) === 1;
}

/**
 * Detecta mojibake patrón Latin-1/Windows-1252: texto UTF-8 leído como Latin-1.
 * Ej: ñ (0xC3 0xB1 en UTF-8) mostrado como Ã± cuando se lee en Latin-1.
 */
function hasLatin1Mojibake(string $text): bool
{
    return str_contains($text, 'Ã')
        || str_contains($text, 'â€')
        || str_contains($text, 'Â°')
        || str_contains($text, 'Â²')
        || str_contains($text, 'Â³');
}

/**
 * Corrige mojibake. Soporta dos patrones:
 * 1) CP437: bytes UTF-8 almacenados/mostrados como CP437 (━ ├ ┬ ▓ æ etc.)
 *    Fix: mb_convert_encoding($text, 'CP437', 'UTF-8')
 *    Mapea los code points CP437 de vuelta a sus bytes, que son UTF-8 correcto.
 * 2) Latin-1/Windows-1252: texto Latin-1 almacenado en columna UTF-8.
 *    Fix: mb_convert_encoding($text, 'UTF-8', 'Windows-1252')
 */
function fixEncoding(string $text): string
{
    if ($text === '') return $text;

    // Patrón 1: CP437 (caso del usuario: ├æ→Ñ, ┬▓→²)
    if (hasCp437Mojibake($text)) {
        // mb_convert_encoding no soporta CP437; iconv sí lo hace (glibc/Linux)
        $candidate = function_exists('iconv') ? @iconv('UTF-8', 'CP437//IGNORE', $text) : false;
        if (
            $candidate !== false
            && $candidate !== ''
            && mb_check_encoding($candidate, 'UTF-8')
            && !hasCp437Mojibake($candidate)
        ) {
            return $candidate;
        }
    }

    // Patrón 2: Latin-1/Windows-1252 (Ã± etc.)
    if (hasLatin1Mojibake($text)) {
        $candidate = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        if ($candidate !== false && mb_check_encoding($candidate, 'UTF-8') && !hasMojibake($candidate)) {
            return $candidate;
        }
        $candidate = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        if ($candidate !== false && mb_check_encoding($candidate, 'UTF-8') && !hasMojibake($candidate)) {
            return $candidate;
        }
    }

    // No se pudo corregir
    return $text;
}

/**
 * Detecta cualquier tipo de mojibake conocido.
 */
function hasMojibake(string $text): bool
{
    return hasCp437Mojibake($text) || hasLatin1Mojibake($text);
}

function truncate(string $s, int $len = 60): string
{
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len) . '…' : $s;
}

function parseEnv(string $path): array
{
    $result = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $val = trim($val, '"\'');
        $result[trim($key)] = $val;
    }
    return $result;
}
