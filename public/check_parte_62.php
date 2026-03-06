<?php

require __DIR__ . '/../bootstrap/app.php';

$conn = App\Core\Database\DatabaseManager::connection('pgsql');
$stmt = $conn->query('SELECT id, codigo, activo FROM partes WHERE id = 62');
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "ID: {$result['id']}\n";
echo "Código: {$result['codigo']}\n";
echo "Activo (valor): " . var_export($result['activo'], true) . "\n";
echo "Activo (tipo): " . gettype($result['activo']) . "\n";
echo "Activo (int): " . (int)$result['activo'] . "\n";
echo "Activo (bool): " . (int)$result['activo'] === 1 ? 'true' : 'false' . "\n";
