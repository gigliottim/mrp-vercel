<?php

declare(strict_types=1);

/**
 * Script para ejecutar la migración de timestamps en partes
 * Ejecutar con: php ejecutar_migracion_timestamps_partes.php
 */

require_once __DIR__ . '/bootstrap/app.php';

try {
    // Leer el archivo de migración
    $migrationFile = __DIR__ . '/database/migrations/2026-02-15_add_timestamps_to_partes.sql';

    if (!file_exists($migrationFile)) {
        echo "❌ Error: No se encontró el archivo de migración\n";
        exit(1);
    }

    $sql = file_get_contents($migrationFile);

    if ($sql === false) {
        echo "❌ Error: No se pudo leer el archivo de migración\n";
        exit(1);
    }

    // Conectar a la base de datos tenant demo
    echo "🔌 Conectando a la base de datos tenant...\n";

    // IMPORTANTE: Conectar a la base de datos del tenant, no a la central
    $dbConfig = config('database.connections.tenant');
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        'tenant_demo' // Base de datos del tenant demo
    );

    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Ejecutar la migración
    echo "📦 Ejecutando migración: 2026-02-15_add_timestamps_to_partes\n";
    $pdo->exec($sql);

    echo "✅ Migración ejecutada exitosamente\n";
    echo "\n";
    echo "Cambios realizados:\n";
    echo "  ✓ Campo created_at agregado a tabla partes\n";
    echo "  ✓ Campo updated_at agregado a tabla partes\n";
    echo "  ✓ Trigger de actualización automática configurado\n";
    echo "  ✓ Valores inicializados para registros existentes\n";
} catch (PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
