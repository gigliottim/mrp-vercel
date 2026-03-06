<?php

/**
 * Verificar qué BD tiene la tabla variantes
 */

require __DIR__ . '/../bootstrap/app.php';

echo "<h2>🔍 Verificar estructura de BDs</h2><hr>";

$config = config('database.connections.tenant');
$databases = ['mrp', 'mrp_tenant_demo', 'mrp_tenant_template'];

foreach ($databases as $dbName) {
    echo "<h3>📦 Base de datos: <strong>$dbName</strong></h3>";

    try {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $config['host'],
            $config['port'],
            $dbName
        );

        $pdo = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        echo "<p style='color:green;'>✅ Conexión exitosa</p>";

        // Verificar tabla variantes
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM variantes");
            $result = $stmt->fetch();
            echo "<p style='color:green;font-weight:bold;'>✅ Tabla 'variantes' existe - Total: {$result['total']} registros</p>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>❌ Tabla 'variantes' NO existe</p>";
        }

        // Verificar tabla partes
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM partes");
            $result = $stmt->fetch();
            echo "<p style='color:green;'>✅ Tabla 'partes' existe - Total: {$result['total']} registros</p>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>❌ Tabla 'partes' NO existe</p>";
        }

        // Listar todas las tablas
        $stmt = $pdo->query("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_type = 'BASE TABLE'
            ORDER BY table_name
        ");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo "<details><summary>📋 Tablas disponibles (" . count($tables) . ")</summary>";
        echo "<ul style='column-count:2;'>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul></details>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
    }

    echo "<hr>";
}

echo "<h3>💡 Conclusión</h3>";
echo "<p>La BD correcta para usar es la que contiene las tablas <strong>variantes</strong> y <strong>partes</strong>.</p>";
