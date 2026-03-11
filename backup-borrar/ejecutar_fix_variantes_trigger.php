<?php

declare(strict_types=1);

/**
 * Ejecutar migración: Corregir trigger de variantes
 *
 * Problema: El trigger set_timestamp_variantes intenta actualizar el campo
 * 'updated_at' que no existe. La tabla usa 'fecha_modificacion'.
 */

require_once __DIR__ . '/bootstrap/app.php';

try {
    echo "🔧 === Corrigiendo trigger de variantes ===\n\n";

    // Leer el archivo de migración
    $migrationFile = __DIR__ . '/database/migrations/2026-02-15_fix_variantes_trigger.sql';

    if (!file_exists($migrationFile)) {
        echo "❌ Error: No se encontró el archivo de migración\n";
        exit(1);
    }

    $sql = file_get_contents($migrationFile);

    // Conectar a la base de datos tenant demo
    echo "🔌 Conectando a la base de datos tenant...\n";

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

    echo "✓ Conectado a la base de datos\n\n";

    // Ejecutar la migración
    echo "📦 Ejecutando migración...\n";
    $pdo->exec($sql);

    echo "\n✅ Migración ejecutada correctamente\n";
    echo "✓ Trigger 'set_timestamp_variantes' eliminado\n";
    echo "✓ Función 'update_fecha_modificacion_column' creada\n";
    echo "✓ Nuevo trigger creado correctamente\n";
    echo "✓ Ahora usa el campo 'fecha_modificacion' en lugar de 'updated_at'\n\n";

    echo "🎉 ¡Listo! Ahora puedes guardar variantes sin errores.\n";
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
