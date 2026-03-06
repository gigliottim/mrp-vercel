<?php

declare(strict_types=1);

/**
 * Script para ejecutar la migración de tipos_depositos
 * Ejecutar con: php ejecutar_migracion_tipos_depositos.php
 */

require_once __DIR__ . '/bootstrap/app.php';

try {
    // Leer el archivo de migración
    $migrationFile = __DIR__ . '/database/migrations/2026-02-15_create_tipos_depositos.sql';

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

    // Ejecutar la migración
    echo "📦 Ejecutando migración: 2026-02-15_create_tipos_depositos\n";
    $pdo->exec($sql);

    echo "✅ Migración ejecutada exitosamente\n";
    echo "\n";
    echo "Se creó la tabla tipos_depositos con los siguientes tipos de depósito:\n";
    echo "  - ALMACEN\n";
    echo "  - PRODUCCION\n";
    echo "  - PRE-PRODUCCION\n";
    echo "  - PROVEEDOR\n";
    echo "  - CLIENTE\n";
    echo "  - AJUSTE\n";
    echo "\n";

    // Verificar que se crearon los registros
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM tipos_depositos');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📊 Total de tipos de depósito creados: " . $result['total'] . "\n";
} catch (PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
