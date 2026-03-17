<?php

/**
 * Script para actualizar la contraseña del usuario demo
 */

require_once __DIR__ . '/../bootstrap/autoload.php';

use App\Core\Support\Env;
use App\Core\Config\Config;
use App\Core\Database\DatabaseManager;

Env::load(base_path('.env'));
Config::load(base_path('config'));

$password = 'demo123';
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Actualizando contraseña del usuario demo...\n";
echo "Hash generado: $hash\n\n";

try {
    $pdo = DatabaseManager::connection('mrp_auth');

    $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = 'demo@mrp.local'");
    $stmt->execute(['password' => $hash]);

    echo "✓ Contraseña actualizada exitosamente\n\n";

    // Verificar
    echo "Verificando...\n";
    $stmt = $pdo->query("SELECT id, name, email FROM users WHERE email = 'demo@mrp.local'");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "Usuario encontrado:\n";
        echo "  ID: {$user['id']}\n";
        echo "  Nombre: {$user['name']}\n";
        echo "  Email: {$user['email']}\n\n";

        // Verificar que el hash funciona
        $stmt = $pdo->prepare("SELECT password FROM users WHERE email = 'demo@mrp.local'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($password, $result['password'])) {
            echo "✓ Verificación exitosa: La contraseña '$password' funciona correctamente\n";
        } else {
            echo "✗ Error: La verificación de contraseña falló\n";
            exit(1);
        }
    } else {
        echo "✗ Usuario no encontrado\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Error: {$e->getMessage()}\n";
    exit(1);
}
