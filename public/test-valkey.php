<?php

/**
 * Script de prueba para verificar la conexión a Valkey
 */

require_once __DIR__ . '/../bootstrap/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Framework\Kernel::class);
$kernel->bootstrap();

echo "=== Prueba de conexión a Valkey ===\n\n";

$valkeyConfig = config('valkey');

echo "Configuración de Valkey:\n";
echo "  Host: " . ($valkeyConfig['host'] ?? 'no configurado') . "\n";
echo "  Port: " . ($valkeyConfig['port'] ?? 'no configurado') . "\n";
echo "  Password: " . ($valkeyConfig['password'] ? 'configurado' : 'no configurado') . "\n";
echo "  Enabled: " . ($valkeyConfig['enabled'] ?? 'true') . "\n\n";

// Verificar si la extensión Valkey está cargada
if (!extension_loaded('valkey')) {
    echo "ERROR: La extensión Valkey no está cargada en PHP.\n";
    echo "Instalar con: pecl install valkey\n";
    exit(1);
}

echo "Extensión Valkey: CARGADA\n\n";

// Intentar conectar
$valkey = new ValkeyClient();

try {
    $host = $valkeyConfig['host'] ?? 'localhost';
    $port = (int)($valkeyConfig['port'] ?? 6379);

    echo "Intentando conectar a $host:$port...\n";

    $connected = $valkey->connect($host, $port, 2.0); // 2 segundos de timeout

    if ($connected) {
        echo "✓ Conexión exitosa!\n";

        // Verificar autenticación si hay password
        if ($password = $valkeyConfig['password']) {
            echo "Autenticando con contraseña...\n";
            $authResult = $valkey->auth($password);
            if ($authResult) {
                echo "✓ Autenticación exitosa!\n";
            } else {
                echo "✗ Autenticación fallida!\n";
                exit(1);
            }
        }

        // Hacer ping
        $pingResult = $valkey->ping();
        echo "Ping result: $pingResult\n";

        // Probar guardar y obtener
        $testKey = 'test:valkey:connection:' . time();
        $testValue = 'Valkey está operativo!';

        $valkey->set($testKey, $testValue);
        echo "Set: $testKey = $testValue\n";

        $retrieved = $valkey->get($testKey);
        echo "Get: $testKey = $retrieved\n";

        if ($retrieved === $testValue) {
            echo "\n✓ Valkey está operativo y funcional!\n";
        } else {
            echo "\n✗ Error: El valor recuperado no coincide!\n";
            exit(1);
        }

        // Limpiar
        $valkey->del($testKey);
    } else {
        echo "✗ Error: No se pudo conectar a Valkey.\n";
        echo "Verifica que el servicio Valkey esté corriendo.\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Prueba completada exitosamente ===\n";
