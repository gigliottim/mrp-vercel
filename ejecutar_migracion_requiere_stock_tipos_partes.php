<?php

declare(strict_types=1);

/**
 * Script para ejecutar la migración de tipos_partes (agregar campo requiere_stock)
 * Ejecutar con: php ejecutar_migracion_requiere_stock_tipos_partes.php
 */

require_once __DIR__ . '/bootstrap/app.php';

try {
    // Leer el archivo de migración
    $migrationFile = __DIR__ . '/database/migrations/add_requiere_stock_to_tipos_partes.sql';

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

    // Override host if running locally outside docker
    if ($dbConfig['host'] === 'lemp-postgresql') {
        $dbConfig['host'] = 'localhost';
    }

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
    echo "📦 Ejecutando migración: add_requiere_stock_to_tipos_partes.sql\n";
    try {
        $pdo->exec($sql);
        echo "✅ Migración ejecutada exitosamente\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'duplicate column name') !== false) {
            echo "⚠️  La columna 'requiere_stock' ya existe en 'tipos_partes'.\n";
        } else {
            throw $e;
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
