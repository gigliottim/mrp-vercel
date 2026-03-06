<?php

// Script temporal para probar actualización del campo activo
require __DIR__ . '/../bootstrap/app.php';

header('Content-Type: text/plain');

echo "=== TEST ACTUALIZACIÓN CAMPO ACTIVO ===\n\n";

// Simular lo que envía el formulario
$_POST = [
    'codigo' => 'SC1',
    'id_tipo' => '2',
    'id_grupo' => '1',
    'detalle' => 'CUADRO PRINCIPAL E-FRAME-AL01',
    'activo' => '0', // Probando con 0 (desactivado)
    '_method' => 'PUT'
];

echo "Datos enviados:\n";
print_r($_POST);
echo "\n";

// Simular validación
$activo = (int) ($_POST['activo'] ?? 0);
echo "Valor procesado de activo: $activo (tipo: " . gettype($activo) . ")\n\n";

// Verificar estado actual
$conn = App\Core\Database\DatabaseManager::connection('pgsql');
$stmt = $conn->query('SELECT id, codigo, activo FROM partes WHERE id = 62');
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Estado ANTES de UPDATE:\n";
echo "  activo = " . var_export($result['activo'], true) . " (tipo: " . gettype($result['activo']) . ")\n\n";

// Ejecutar UPDATE
$updateStmt = $conn->prepare('UPDATE partes SET activo = :activo WHERE id = 62');
$updateStmt->execute(['activo' => $activo]);

echo "UPDATE ejecutado con activo = $activo\n\n";

// Verificar estado después
$stmt = $conn->query('SELECT id, codigo, activo FROM partes WHERE id = 62');
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Estado DESPUÉS de UPDATE:\n";
echo "  activo = " . var_export($result['activo'], true) . " (tipo: " . gettype($result['activo']) . ")\n\n";

echo "✅ Test completado\n";
