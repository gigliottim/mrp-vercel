<?php

/**
 * Listar bases de datos disponibles en PostgreSQL
 */

require __DIR__ . '/../bootstrap/app.php';

echo "<h2>Bases de Datos Disponibles en PostgreSQL</h2>";
echo "<hr>";

try {
    $config = config('database.connections.tenant');

    echo "<h3>Configuración actual (.env)</h3>";
    echo "<pre>";
    echo "Host: " . $config['host'] . "\n";
    echo "Port: " . $config['port'] . "\n";
    echo "Database: " . $config['database'] . "\n";
    echo "Username: " . $config['username'] . "\n";
    echo "</pre>";

    // Conectar a postgres (BD por defecto)
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=postgres',
        $config['host'],
        $config['port']
    );

    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "<h3>✅ Conexión a PostgreSQL exitosa</h3>";

    // Listar todas las BDs
    $stmt = $pdo->query("
        SELECT datname
        FROM pg_database
        WHERE datistemplate = false
        ORDER BY datname
    ");

    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<h3>Bases de datos disponibles:</h3>";
    echo "<ul style='font-size: 16px;'>";
    foreach ($databases as $db) {
        $isTenant = str_starts_with($db, 'mrp_tenant');
        $style = $isTenant ? "color: green; font-weight: bold;" : "";
        echo "<li style='$style'>$db</li>";
    }
    echo "</ul>";

    // Verificar si mrp_tenant_demo existe
    if (in_array('mrp_tenant_demo', $databases)) {
        echo "<h3>✅ mrp_tenant_demo existe - probar conexión</h3>";

        $dsnDemo = sprintf(
            'pgsql:host=%s;port=%s;dbname=mrp_tenant_demo',
            $config['host'],
            $config['port']
        );

        $pdoDemo = new PDO(
            $dsnDemo,
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdoDemo->query("SELECT COUNT(*) as total FROM variantes");
        $result = $stmt->fetch();

        echo "<pre>";
        echo "✅ Conexión exitosa a mrp_tenant_demo\n";
        echo "Total variantes: " . $result['total'] . "\n";
        echo "</pre>";

        echo "<h3>🔧 Solución:</h3>";
        echo "<p style='background: #ffffcc; padding: 15px; border-left: 4px solid #ffcc00;'>";
        echo "<strong>Actualiza company_databases.database_name de 'mrp' a 'mrp_tenant_demo'</strong><br>";
        echo "Ejecuta en mrp_auth:<br>";
        echo "<code>UPDATE company_databases SET database_name = 'mrp_tenant_demo' WHERE database_name = 'mrp';</code>";
        echo "</p>";
    }
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error</h3>";
    echo "<pre>";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
    echo "</pre>";
}
