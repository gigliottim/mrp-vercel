<?php

/**
 * Script para insertar las unidades de medida con equivalencias
 *
 * Uso desde navegador (desarrollo):
 *   http://localhost/mrp/seed_unidades_medida.php
 *
 * IMPORTANTE: Requiere estar logueado para acceder a la BD del tenant
 */

require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Database\DatabaseManager;
use App\Core\Auth\TenantContext;
use App\Core\Auth\AuthManager;

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    \App\Core\Support\SessionManager::start();
}

// Función para verificar autenticación y obtener tenant
function getTenantInfoFromSession(): ?array
{
    // Obtener tenant del usuario logueado
    $tenant = AuthManager::tenant();

    if ($tenant === null) {
        return null;
    }

    return $tenant;
}

// Función para ejecutar el seed
function executeSeed(): array
{
    try {
        // Obtener tenant de la sesión
        $tenant = getTenantInfoFromSession();

        if ($tenant === null) {
            return [
                'success' => false,
                'error' => 'No hay sesión activa. Debes estar logueado para ejecutar este script.',
                'not_authenticated' => true
            ];
        }

        $dbName = $tenant['database']['name'] ?? 'desconocida';

        // Establecer el tenant en el contexto
        TenantContext::set($tenant);

        // Obtener conexión del tenant actual
        $connection = DatabaseManager::connection('tenant');

        // Leer el archivo SQL
        $sqlFile = __DIR__ . '/../database/seeds/unidades_medida_seed.sql';

        if (!file_exists($sqlFile)) {
            throw new RuntimeException("Archivo SQL no encontrado: {$sqlFile}");
        }

        $sql = file_get_contents($sqlFile);

        // Separar las consultas por líneas y ejecutarlas
        $statements = explode(';', $sql);
        $executed = 0;
        $errors = [];
        $skipped = 0;

        // NO usar transacción global - ejecutar cada INSERT independientemente
        // para que un error no aborte todas las inserciones siguientes

        foreach ($statements as $statement) {
            $statement = trim($statement);

            // Saltar comentarios y líneas vacías
            if (
                empty($statement) ||
                str_starts_with($statement, '--') ||
                str_starts_with($statement, '/*')
            ) {
                continue;
            }

            try {
                $affectedRows = $connection->exec($statement);
                if ($affectedRows > 0) {
                    $executed++;
                } else {
                    $skipped++; // Ya existe
                }
            } catch (\PDOException $e) {
                // Ignorar errores de conflicto (duplicados) y valores fuera de rango
                if (
                    str_contains($e->getMessage(), 'duplicate') ||
                    str_contains($e->getMessage(), 'violates unique constraint') ||
                    str_contains($e->getMessage(), 'Numeric value out of range')
                ) {
                    $skipped++;
                } else {
                    $errors[] = $e->getMessage();
                }
            }
        }

        // Obtener conteo por tipo
        $stmt = $connection->query("
            SELECT tipo, COUNT(*) as cantidad
            FROM unidades_medida
            GROUP BY tipo
            ORDER BY tipo
        ");
        $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'database' => $dbName,
            'statements_executed' => $executed,
            'statements_skipped' => $skipped,
            'errors' => $errors,
            'counts' => $counts,
            'company_name' => $tenant['company']['name'] ?? 'N/A'
        ];
    } catch (\Throwable $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

// Ejecutar el seed
$result = executeSeed();

// Salida para web
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seed Unidades de Medida</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }

        .success {
            color: #28a745;
            font-size: 24px;
            margin: 20px 0;
        }

        .error {
            color: #dc3545;
            font-size: 24px;
            margin: 20px 0;
        }

        .warning {
            color: #856404;
            font-size: 20px;
            margin: 20px 0;
        }

        .info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }

        .info-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }

        .info-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #007bff;
            color: white;
        }

        .back-link,
        .login-link {
            display: inline-block;
            margin-top: 20px;
            margin-right: 10px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .back-link:hover,
        .login-link:hover {
            background: #0056b3;
        }

        .login-link {
            background: #28a745;
        }

        .login-link:hover {
            background: #218838;
        }

        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            background: #e7f3ff;
            color: #0056b3;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🌱 Seed de Unidades de Medida</h1>

        <?php if (isset($result['not_authenticated']) && $result['not_authenticated']): ?>
            <div class="warning">⚠️ Acceso denegado</div>
            <div class="info-warning">
                <strong>🔒 Autenticación requerida</strong><br><br>
                Debes estar logueado en el sistema para ejecutar este script.<br>
                El script insertará las unidades en la base de datos de la empresa a la que perteneces.
            </div>
            <a href="<?= url('/auth/login') ?>" class="login-link">
                🔐 Iniciar Sesión
            </a>

        <?php elseif ($result['success']): ?>
            <div class="success">✅ Seed ejecutado exitosamente</div>

            <div class="info">
                <strong>🏢 Empresa:</strong> <?= htmlspecialchars($result['company_name'] ?? 'N/A') ?><br>
                <strong>📊 Base de datos:</strong> <?= htmlspecialchars($result['database']) ?><br>
                <strong>📝 Sentencias ejecutadas:</strong> <?= $result['statements_executed'] ?><br>
                <?php if (isset($result['statements_skipped']) && $result['statements_skipped'] > 0): ?>
                    <strong>⚠️ Omitidas (duplicadas):</strong> <?= $result['statements_skipped'] ?><br>
                <?php endif; ?>
            </div>

            <?php if (!empty($result['errors'])): ?>
                <div class="info-warning">
                    <strong>⚠️ Advertencias (ignoradas):</strong>
                    <ul>
                        <?php foreach ($result['errors'] as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <h2>📈 Unidades insertadas por tipo:</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th style="text-align: center;">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalUnidades = 0;
                    foreach ($result['counts'] as $count):
                        $totalUnidades += (int) $count['cantidad'];
                    ?>
                        <tr>
                            <td style="text-transform: capitalize;">
                                <?php
                                $tipoIconos = [
                                    'longitud' => '📏',
                                    'superficie' => '📐',
                                    'volumen' => '🧪',
                                    'masa' => '⚖️',
                                    'tiempo' => '⏱️',
                                    'temperatura' => '🌡️'
                                ];
                                $icono = $tipoIconos[$count['tipo']] ?? '📊';
                                echo $icono . ' ' . htmlspecialchars(ucfirst($count['tipo']));
                                ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge"><?= $count['cantidad'] ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="border-top: 2px solid #007bff;">
                        <td><strong>Total</strong></td>
                        <td style="text-align: center;"><strong><?= $totalUnidades ?></strong></td>
                    </tr>
                </tbody>
            </table>

        <?php else: ?>
            <div class="error">❌ Error al ejecutar seed</div>
            <div class="info-error">
                <strong>Error:</strong><br>
                <?= htmlspecialchars($result['error']) ?>
            </div>

            <?php if (isset($result['trace'])): ?>
                <details>
                    <summary style="cursor: pointer; padding: 10px; background: #f8f9fa; border-radius: 5px; margin-top: 10px;">
                        <strong>🔍 Ver Stack Trace completo</strong>
                    </summary>
                    <pre><?= htmlspecialchars($result['trace']) ?></pre>
                </details>
            <?php endif; ?>
        <?php endif; ?>

        <div style="margin-top: 30px;">
            <a href="<?= url('/configuracion/unidades') ?>" class="back-link">
                ← Volver a Unidades de Medida
            </a>
            <a href="<?= url('/') ?>" class="back-link">
                🏠 Ir al Inicio
            </a>
        </div>
    </div>
</body>

</html>
