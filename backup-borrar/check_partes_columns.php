<?php
require 'bootstrap/app.php';
try {
    // Attempt to connect to mrp database
    $pdo = \App\Core\Database\DatabaseManager::connection('tenant_demo'); // Try tenant_demo alias as seen before, or just connect to mrp
} catch (\Throwable $e) {
    // Fallback to manual connection as per previous attempts
    $pdo = new PDO('pgsql:host=localhost;dbname=mrp', 'mrp', 'ojp9Q6aYT3KHDE8sMS2u');
}

$stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'partes'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
