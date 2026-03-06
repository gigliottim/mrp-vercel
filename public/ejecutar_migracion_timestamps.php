<?php

declare(strict_types=1);

/**
 * Script web para ejecutar la migración de timestamps en partes
 * Acceder desde: http://localhost/mrp/public/ejecutar_migracion_timestamps.php
 */

// Solo permitir en entorno de desarrollo
if (!isset($_SERVER['HTTP_HOST']) || strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
    die('Este script solo puede ejecutarse en localhost');
}

require_once __DIR__ . '/../bootstrap/app.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    // Leer el archivo de migración
    $migrationFile = __DIR__ . '/../database/migrations/2026-02-15_add_timestamps_to_partes.sql';

    if (!file_exists($migrationFile)) {
        echo "❌ Error: No se encontró el archivo de migración\n";
        exit(1);
    }

    $sql = file_get_contents($migrationFile);

    if ($sql === false) {
        echo "❌ Error: No se pudo leer el archivo de migración\n";
        exit(1);
    }

    echo "🔌 Conectando a la base de datos tenant...\n";

    // Obtener conexión del tenant actual
    // Intentar primero con la BD configurada en .env, si falla usar mrp_tenant_demo
    $dbConfig = config('database.connections.pgsql');
    $dbName = env('DB_PGSQL_DATABASE', 'mrp');

    echo "  Host: {$dbConfig['host']}\n";
    echo "  Base de datos: $dbName\n\n";

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbName
    );

    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "✓ Conectado exitosamente\n\n";

    // Ejecutar la migración
    echo "📦 Ejecutando migración: 2026-02-15_add_timestamps_to_partes\n";
    $pdo->exec($sql);

    echo "\n✅ Migración ejecutada exitosamente\n\n";
    echo "Cambios realizados:\n";
    echo "  ✓ Campo created_at agregado a tabla partes\n";
    echo "  ✓ Campo updated_at agregado a tabla partes\n";
    echo "  ✓ Trigger de actualización automática configurado\n";
    echo "  ✓ Valores inicializados para registros existentes\n\n";

    // Verificar que los campos existen
    $check = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'partes'
        AND column_name IN ('created_at', 'updated_at')
        ORDER BY column_name
    ")->fetchAll(PDO::FETCH_COLUMN);

    echo "Columnas verificadas en tabla partes:\n";
    foreach ($check as $col) {
        echo "  ✓ $col\n";
    }

    echo "\n";
    echo "🎉 Ahora puedes actualizar la parte 62 sin errores!\n";
    echo "\n";
    echo "⚠️  IMPORTANTE: Elimina este archivo después de usarlo por seguridad.\n";
} catch (PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
    exit(1);
}
