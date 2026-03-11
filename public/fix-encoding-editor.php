<?php

/**
 * fix-encoding-editor.php
 * Editor manual para corregir texto con mojibake en BDs tenant.
 * Acceso protegido: ?token=mrp_fix_2026
 */

declare(strict_types=1);

const EDIT_TOKEN = 'mrp_fix_2026';
if (($_GET['token'] ?? '') !== EDIT_TOKEN) {
    http_response_code(403);
    exit('<p style="font-family:sans-serif;color:red">Acceso denegado. Agregar <code>?token=mrp_fix_2026</code> a la URL.</p>');
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function parseEnvFile(string $path): array
{
    if (!file_exists($path)) return [];
    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim(trim($v), '"\'');
    }
    return $env;
}

function hasMojibakedText(string $text): bool
{
    if ($text === '') return false;
    // Bytes iniciales de box-drawing U+2500-U+259F en UTF-8: E2 94 xx / E2 95 xx / E2 96 xx
    if (str_contains($text, "\xE2\x94") || str_contains($text, "\xE2\x95") || str_contains($text, "\xE2\x96")) return true;
    // Bytes de Latin-1 mojibake raw (\xC3 o \xC2 solos sin par UTF-8 valido)
    if (!mb_check_encoding($text, 'UTF-8')) return true;
    // UTF-8 valido pero con box-drawing
    if (preg_match('/[\x{2500}-\x{259F}]/u', $text) === 1) return true;
    // Patron Latin-1: Ã seguido de letra, Â seguido de simbolo
    if (preg_match('/\xC3[\x82-\xBF]/', $text) === 1) return true;
    if (preg_match('/\xC2[\xA0-\xBF]/', $text) === 1) return true;
    return false;
}

function suggestFix(string $text): string
{
    if ($text === '' || !hasMojibakedText($text)) return $text;
    foreach (['CP437', 'IBM437', 'CP850', 'IBM850'] as $enc) {
        $out = @iconv('UTF-8', $enc . '//IGNORE', $text);
        if ($out !== false && $out !== '' && mb_check_encoding($out, 'UTF-8') && !hasMojibakedText($out)) {
            return $out;
        }
    }
    foreach (['Windows-1252', 'ISO-8859-1'] as $enc) {
        $out = @mb_convert_encoding($text, 'UTF-8', $enc);
        if ($out !== false && $out !== '' && !hasMojibakedText($out)) {
            return $out;
        }
    }
    return $text;
}

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── Config ───────────────────────────────────────────────────────────────────

$envFile = file_exists(__DIR__ . '/../.env') ? __DIR__ . '/../.env' : '/opt/mrp/.env';
$env     = parseEnvFile($envFile);

$authHost = $env['DB_AUTH_HOST']     ?? $env['DB_PGSQL_HOST']     ?? 'postgresql';
$authPort = $env['DB_AUTH_PORT']     ?? $env['DB_PGSQL_PORT']     ?? '5432';
$authUser = $env['DB_AUTH_USERNAME'] ?? $env['DB_PGSQL_USERNAME'] ?? 'mrp_unik_2026';
$authPass = $env['DB_AUTH_PASSWORD'] ?? $env['DB_PGSQL_PASSWORD'] ?? '';
$authDb   = $env['DB_AUTH_DATABASE'] ?? 'mrp_auth';

$tableCols = [
    'partes'                 => ['nombre', 'descripcion', 'observaciones', 'codigo'],
    'variantes'              => ['descripcion', 'codigo_variante'],
    // 'tipos_partes'           => ['nombre', 'descripcion'],
    // 'grupos_partes'          => ['nombre', 'descripcion'],
    // 'almacenes'              => ['nombre', 'descripcion'],
    // 'centros_trabajo'        => ['nombre', 'descripcion'],
    // 'unidades_medida'        => ['nombre', 'descripcion', 'simbolo'],
    // 'entidades'              => ['nombre', 'descripcion', 'observaciones'],
    // 'bom_cabecera'           => ['descripcion', 'observaciones'],
    // 'ordenes_produccion'     => ['descripcion', 'observaciones'],
    // 'movimientos_stock'      => ['observaciones', 'referencia_documento'],
    // 'movimientos_inventario' => ['observaciones', 'referencia_documento'],
    // 'configuracion_general'  => ['valor'],
];

$ALLOWED_TABLES = array_keys($tableCols);
$ALLOWED_COLS   = array_unique(array_merge(...array_values($tableCols)));

// ─── Conectar a mrp_auth ──────────────────────────────────────────────────────

try {
    $authPdo = new PDO(
        "pgsql:host=$authHost;port=$authPort;dbname=$authDb;options='--client_encoding=UTF8'",
        $authUser,
        $authPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('<p style="color:red;font-family:sans-serif">Error conexion mrp_auth: ' . esc($e->getMessage()) . '</p>');
}

$tenantRows = $authPdo->query(
    "SELECT database_name, host, port, username, password_encrypted
     FROM company_databases ORDER BY database_name"
)->fetchAll(PDO::FETCH_ASSOC);

// ─── Handle POST: guardar cambios ─────────────────────────────────────────────

$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['fields'])) {
    foreach ((array)$_POST['fields'] as $dbName => $tables) {
        if (!preg_match('/^[a-z0-9_]+$/', (string)$dbName)) continue;

        $conf = null;
        foreach ($tenantRows as $t) {
            if ($t['database_name'] === $dbName) {
                $conf = $t;
                break;
            }
        }
        if ($conf === null) continue;

        try {
            $pdo = new PDO(
                "pgsql:host={$conf['host']};port={$conf['port']};dbname=$dbName",
                $conf['username'],
                $conf['password_encrypted'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec("SET client_encoding = 'UTF8'");

            foreach ((array)$tables as $table => $rows) {
                if (!in_array($table, $ALLOWED_TABLES, true)) continue;

                foreach ((array)$rows as $rowId => $cols) {
                    $id = (int)$rowId;
                    if ($id <= 0) continue;

                    foreach ((array)$cols as $col => $newVal) {
                        if (!in_array($col, $ALLOWED_COLS, true)) continue;
                        if (!is_string($newVal)) continue;

                        $stmt = $pdo->prepare("UPDATE \"$table\" SET \"$col\" = :val WHERE id = :id");
                        $stmt->execute(['val' => $newVal, 'id' => $id]);
                        $messages[] = ['ok', "$dbName › $table #$id › $col guardado"];
                    }
                }
            }
        } catch (PDOException $e) {
            $messages[] = ['err', "$dbName: " . $e->getMessage()];
        }
    }
}

// ─── Escanear mojibake en todas las BDs ───────────────────────────────────────

$results      = [];
$totalItems   = 0;
$scanErrors   = [];

foreach ($tenantRows as $conf) {
    $dbName = $conf['database_name'];

    try {
        $pdo = new PDO(
            "pgsql:host={$conf['host']};port={$conf['port']};dbname=$dbName",
            $conf['username'],
            $conf['password_encrypted'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        // SQL_ASCII: recibe bytes crudos sin conversión (evita que PG rechace bytes inválidos)
        $pdo->exec("SET client_encoding = 'SQL_ASCII'");

        $existingTables = $pdo->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tableCols as $table => $cols) {
            if (!in_array($table, $existingTables, true)) continue;

            $existingCols = $pdo->query(
                "SELECT column_name FROM information_schema.columns
                 WHERE table_schema='public' AND table_name=" . $pdo->quote($table)
            )->fetchAll(PDO::FETCH_COLUMN);

            $validCols = array_filter($cols, fn($c) => in_array($c, $existingCols, true));
            if (empty($validCols)) continue;

            $colSelect = '"id", ' . implode(', ', array_map(fn($c) => '"' . $c . '"', $validCols));

            // Traer todas las filas y filtrar en PHP (evita problemas de LIKE con bytes especiales)
            $rows = $pdo->query(
                "SELECT $colSelect FROM \"$table\" ORDER BY id"
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $id = (int)$row['id'];
                foreach ($validCols as $col) {
                    $val = (string)($row[$col] ?? '');
                    if ($val !== '' && hasMojibakedText($val)) {
                        $results[$dbName][$table][$id][$col] = [
                            'original'  => $val,
                            'suggested' => suggestFix($val),
                        ];
                        $totalItems++;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $scanErrors[$dbName] = $e->getMessage();
    }
}

$tokenQ = esc(EDIT_TOKEN);

// ─── Diagnóstico: primeras filas de partes con hex dump ───────────────────────
$diagRows = [];
if (isset($_GET['debug'])) {
    foreach ($tenantRows as $conf) {
        $dbName = $conf['database_name'];
        try {
            $pdoDiag = new PDO(
                "pgsql:host={$conf['host']};port={$conf['port']};dbname=$dbName",
                $conf['username'],
                $conf['password_encrypted'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdoDiag->exec("SET client_encoding = 'SQL_ASCII'");
            $stmt = $pdoDiag->query("SELECT id, nombre FROM partes ORDER BY id LIMIT 8");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $hex = strtoupper(bin2hex((string)$r['nombre']));
                $diagRows[] = [
                    'db'      => $dbName,
                    'id'      => $r['id'],
                    'nombre'  => $r['nombre'],
                    'hex'     => chunk_split($hex, 4, ' '),
                    'is_utf8' => mb_check_encoding((string)$r['nombre'], 'UTF-8'),
                    'detect'  => hasMojibakedText((string)$r['nombre']),
                ];
            }
        } catch (PDOException $e) {
            $diagRows[] = ['db' => $dbName, 'err' => $e->getMessage()];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Fix Encoding Editor — MRP</title>
    <style>
        * {
            box-sizing: border-box
        }

        body {
            font-family: system-ui, sans-serif;
            font-size: 14px;
            margin: 0;
            background: #f0f2f5;
            color: #222
        }

        .hdr {
            background: #1a237e;
            color: #fff;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px
        }

        .hdr h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 700
        }

        .hdr small {
            opacity: .75;
            font-size: 12px
        }

        .wrap {
            padding: 24px;
            max-width: 1300px;
            margin: 0 auto
        }

        .msg {
            padding: 8px 14px;
            margin-bottom: 6px;
            border-radius: 5px;
            font-size: 13px
        }

        .msg.ok {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #43a047
        }

        .msg.err {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #e53935
        }

        .db-block {
            background: #fff;
            border-radius: 8px;
            margin-bottom: 28px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .1);
            overflow: hidden
        }

        .db-hdr {
            background: #1a237e;
            color: #fff;
            padding: 10px 16px;
            font-weight: 700;
            font-size: 14px
        }

        .tbl-hdr {
            background: #e8eaf6;
            color: #283593;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 13px;
            border-top: 1px solid #c5cae9
        }

        .badge {
            background: #283593;
            color: #fff;
            border-radius: 10px;
            padding: 1px 8px;
            font-size: 11px;
            margin-left: 8px
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        th {
            background: #fafafa;
            font-size: 11px;
            color: #777;
            text-transform: uppercase;
            padding: 7px 10px;
            text-align: left;
            border-bottom: 2px solid #eee
        }

        td {
            padding: 7px 10px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: top
        }

        .original {
            color: #c62828;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all
        }

        .suggested {
            color: #2e7d32;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all
        }

        .inp {
            width: 100%;
            padding: 5px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit
        }

        .inp.has-sug {
            border-color: #a5d6a7;
            background: #f1f8e9
        }

        .inp.no-sug {
            border-color: #ffcc80;
            background: #fff8e1
        }

        .btn-sug {
            font-size: 11px;
            margin-top: 3px;
            padding: 2px 7px;
            border-radius: 3px;
            border: 1px solid #a5d6a7;
            background: #e8f5e9;
            color: #2e7d32;
            cursor: pointer
        }

        .btn-sug:hover {
            background: #c8e6c9
        }

        .save-bar {
            position: sticky;
            bottom: 0;
            background: #fff;
            border-top: 2px solid #1a237e;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            z-index: 10
        }

        .btn-save {
            background: #1a237e;
            color: #fff;
            border: none;
            padding: 10px 28px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer
        }

        .btn-save:hover {
            background: #283593
        }

        .btn-all {
            background: #e8eaf6;
            color: #283593;
            border: 1px solid #9fa8da;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer
        }

        .btn-all:hover {
            background: #c5cae9
        }

        .empty {
            background: #fff;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            color: #666;
            font-size: 16px
        }
    </style>
</head>

<body>
    <div class="hdr">
        <div>
            <h1>🔧 Fix Encoding Editor</h1>
            <small><?= $totalItems ?> campo(s) con mojibake · <?= count($results) ?> BD(s) afectada(s)</small>
        </div>
    </div>

    <div class="wrap">

        <?php foreach ($messages as [$type, $text]): ?>
            <div class="msg <?= $type ?>"><?= esc($text) ?></div>
        <?php endforeach; ?>

        <?php foreach ($scanErrors as $db => $err): ?>
            <div class="msg err">Error al escanear <?= esc($db) ?>: <?= esc($err) ?></div>
        <?php endforeach; ?>

        <?php if (!empty($diagRows)): ?>
            <div style="background:#fff3e0;border:2px solid #ff9800;border-radius:8px;padding:16px;margin-bottom:20px;font-family:monospace;font-size:12px">
                <strong style="font-size:14px">🔬 Diagnóstico hexadecimal — partes.nombre (primeras 8 filas)</strong>
                <p style="color:#555;font-family:sans-serif;font-size:12px;margin:6px 0">Agrega <code>?token=mrp_fix_2026&debug=1</code> a la URL para ver este bloque.</p>
                <table style="width:100%;border-collapse:collapse;margin-top:8px">
                    <tr style="background:#ffe0b2">
                        <th style="padding:4px 8px;text-align:left">BD</th>
                        <th>ID</th>
                        <th style="text-align:left">nombre (raw)</th>
                        <th style="text-align:left">hex bytes</th>
                        <th>is_utf8</th>
                        <th>detecta?</th>
                    </tr>
                    <?php foreach ($diagRows as $d): ?>
                        <?php if (isset($d['err'])): ?>
                            <tr>
                                <td colspan="6" style="color:red;padding:4px 8px"><?= esc($d['db']) ?>: <?= esc($d['err']) ?></td>
                            </tr>
                        <?php else: ?>
                            <tr style="border-bottom:1px solid #ffe0b2">
                                <td style="padding:4px 8px"><?= esc($d['db']) ?></td>
                                <td style="padding:4px 8px"><?= (int)$d['id'] ?></td>
                                <td style="padding:4px 8px;max-width:250px;overflow:hidden;white-space:nowrap"><?= esc($d['nombre']) ?></td>
                                <td style="padding:4px 8px;color:#555;word-break:break-all"><?= esc($d['hex']) ?></td>
                                <td style="padding:4px 8px;color:<?= $d['is_utf8'] ? 'green' : 'red' ?>"><?= $d['is_utf8'] ? '✅' : '❌' ?></td>
                                <td style="padding:4px 8px;color:<?= $d['detect'] ? 'green' : 'red' ?>"><?= $d['detect'] ? '✅' : '❌ NO' ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($totalItems === 0): ?>
            <div class="empty">✅ No se detectaron campos con mojibake en ninguna BD.</div>
        <?php else: ?>

            <form method="post" action="?token=<?= $tokenQ ?>">

                <div style="margin-bottom:16px;display:flex;gap:10px;align-items:center">
                    <button type="button" class="btn-all" onclick="applyAll()">⚡ Aplicar todas las sugerencias</button>
                    <span style="color:#888;font-size:12px">Los campos sin sugerencia quedan igual — editarlos manualmente</span>
                </div>

                <?php foreach ($results as $dbName => $tables): ?>
                    <div class="db-block">
                        <div class="db-hdr">📦 <?= esc($dbName) ?></div>

                        <?php foreach ($tables as $table => $rows): ?>
                            <div class="tbl-hdr">
                                <?= esc($table) ?>
                                <span class="badge"><?= array_sum(array_map('count', $rows)) ?></span>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:50px">ID</th>
                                        <th style="width:130px">Columna</th>
                                        <th style="width:28%">Valor original (corrupto)</th>
                                        <th style="width:24%">Sugerencia automática</th>
                                        <th>Editar / Guardar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $rowId => $cols): ?>
                                        <?php foreach ($cols as $col => $data): ?>
                                            <?php
                                            $hasSug   = $data['suggested'] !== $data['original'];
                                            $fieldKey = esc($dbName) . '-' . esc($table) . '-' . (int)$rowId . '-' . esc($col);
                                            $sugId    = 'sug-' . $fieldKey;
                                            $inpId    = 'inp-' . $fieldKey;
                                            $prefill  = $hasSug ? $data['suggested'] : $data['original'];
                                            ?>
                                            <tr>
                                                <td><?= (int)$rowId ?></td>
                                                <td><code><?= esc($col) ?></code></td>
                                                <td class="original"><?= esc($data['original']) ?></td>
                                                <td>
                                                    <?php if ($hasSug): ?>
                                                        <span class="suggested" id="<?= $sugId ?>"><?= esc($data['suggested']) ?></span><br>
                                                        <button type="button" class="btn-sug" onclick="apply('<?= $sugId ?>','<?= $inpId ?>')">↓ Aplicar</button>
                                                    <?php else: ?>
                                                        <span style="color:#bbb;font-size:11px">Sin sugerencia — editar manualmente</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <input type="text"
                                                        id="<?= $inpId ?>"
                                                        data-sug-id="<?= $hasSug ? $sugId : '' ?>"
                                                        name="fields[<?= esc($dbName) ?>][<?= esc($table) ?>][<?= (int)$rowId ?>][<?= esc($col) ?>]"
                                                        value="<?= esc($prefill) ?>"
                                                        class="inp <?= $hasSug ? 'has-sug' : 'no-sug' ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <div class="save-bar">
                    <button type="submit" class="btn-save">💾 Guardar todos los cambios</button>
                    <span style="color:#666;font-size:13px">Se actualizan directamente en la base de datos</span>
                </div>
            </form>

        <?php endif; ?>
    </div>

    <script>
        function apply(sugId, inpId) {
            const s = document.getElementById(sugId);
            const i = document.getElementById(inpId);
            if (s && i) i.value = s.textContent;
        }

        function applyAll() {
            document.querySelectorAll('.inp[data-sug-id]').forEach(inp => {
                const sugId = inp.dataset.sugId;
                if (!sugId) return;
                const s = document.getElementById(sugId);
                if (s) inp.value = s.textContent;
            });
        }
    </script>
</body>

</html>
