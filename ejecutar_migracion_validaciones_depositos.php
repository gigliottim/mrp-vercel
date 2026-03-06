<?php

declare(strict_types=1);

/**
 * Script para ejecutar la migración de validaciones de movimientos entre tipos de depósitos
 * Ejecutar con: php ejecutar_migracion_validaciones_depositos.php
 */

require_once __DIR__ . '/bootstrap/app.php';

try {
    // Leer el archivo de migración
    $migrationFile = __DIR__ . '/database/migrations/2026-02-15_create_tipos_depositos_movimientos.sql';

    if (!file_exists($migrationFile)) {
        echo "❌ Error: No se encontró el archivo de migración\n";
        exit(1);
    }

    $sql = file_get_contents($migrationFile);

    if ($sql === false) {
        echo "❌ Error: No se pudo leer el archivo de migración\n";
        exit(1);
    }

    // Conectar a la base de datos
    $dbConfig = config('database.connections.pgsql');
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['database']
    );

    echo "🔌 Conectando a la base de datos...\n";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Verificar si la tabla ya existe
    $checkTable = $pdo->query(
        "SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_name = 'tipos_depositos_movimientos'
        ) as exists"
    );
    $tableExists = $checkTable->fetch(PDO::FETCH_ASSOC)['exists'];

    if ($tableExists === 't' || $tableExists === true) {
        echo "⚠️  La tabla tipos_depositos_movimientos ya existe.\n";
        echo "¿Desea recrearla? Esto eliminará todos los datos existentes. (s/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (strtolower(trim($line)) !== 's') {
            echo "❌ Migración cancelada.\n";
            exit(0);
        }
        fclose($handle);

        echo "🗑️  Eliminando tabla existente...\n";
        $pdo->exec('DROP TABLE IF EXISTS tipos_depositos_movimientos CASCADE');
    }

    // Ejecutar la migración
    echo "📦 Ejecutando migración: 2026-02-15_create_tipos_depositos_movimientos\n";
    $pdo->exec($sql);

    echo "✅ Migración ejecutada exitosamente!\n";
    echo "\n";
    echo "Se creó la tabla tipos_depositos_movimientos con ejemplos de configuración por defecto:\n";
    echo "  • ALMACEN → PRODUCCION\n";
    echo "  • ALMACEN → PRE-PRODUCCION\n";
    echo "  • ALMACEN → CLIENTE\n";
    echo "  • PRODUCCION → ALMACEN\n";
    echo "  • PROVEEDOR → ALMACEN\n";
    echo "  • AJUSTE ↔ Todos los tipos\n";
    echo "\n";

    // Mostrar estadísticas
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM tipos_depositos_movimientos');
    $count = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "📊 Total de movimientos configurados: " . $count['total'] . "\n";
    echo "\n";
    echo "🔗 Acceder a la configuración en: " . config('app.url') . "/configuracion/depositos-validaciones\n";
} catch (PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
