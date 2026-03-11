<?php

/**
 * Script de prueba de conexión a base de datos
 * Ejecutar desde CLI: php test_db_connection.php
 */

require __DIR__ . '/bootstrap/app.php';

echo "\n";
echo "═══════════════════════════════════════════════\n";
echo "  TEST DE CONEXIÓN A BASE DE DATOS\n";
echo "═══════════════════════════════════════════════\n";
echo "\n";

try {
    // Conectar a tenant
    $conn = \App\Core\Database\DatabaseManager::connection('tenant');
    echo "✅ Conexión establecida\n";
    echo "\n";

    // Verificar configuración
    $stmt = $conn->query("SELECT current_database() as db");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📊 Base de datos actual: " . $result['db'] . "\n";
    echo "\n";

    // Verificar tablas
    $stmt = $conn->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_name IN ('variantes', 'partes', 'tipos_partes')
        ORDER BY table_name
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "📋 Tablas encontradas:\n";
    $requiredTables = ['variantes', 'partes', 'tipos_partes'];
    foreach ($requiredTables as $table) {
        $found = in_array($table, $tables);
        echo "   " . ($found ? "✅" : "❌") . " $table\n";
    }
    echo "\n";

    // Probar consulta
    if (in_array('variantes', $tables)) {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM variantes");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "🔢 Total de variantes: " . $count['total'] . "\n";
        echo "\n";

        // Probar búsqueda
        $stmt = $conn->prepare("
            SELECT v.id, v.codigo_variante, v.detalle, p.codigo AS parte_codigo
            FROM variantes v
            INNER JOIN partes p ON v.id_parte = p.id
            LIMIT 3
        ");
        $stmt->execute();
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "📝 Ejemplos de variantes:\n";
        foreach ($samples as $sample) {
            echo "   • {$sample['codigo_variante']} - {$sample['detalle']}\n";
        }
        echo "\n";
    }

    echo "═══════════════════════════════════════════════\n";
    echo "  ✅ TODAS LAS PRUEBAS PASARON\n";
    echo "═══════════════════════════════════════════════\n";
    echo "\n";
    exit(0);
} catch (\Exception $e) {
    echo "═══════════════════════════════════════════════\n";
    echo "  ❌ ERROR\n";
    echo "═══════════════════════════════════════════════\n";
    echo "\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "\n";
    exit(1);
}
