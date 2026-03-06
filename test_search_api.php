<?php

/**
 * Test de API de búsqueda
 * Ejecutar: php test_search_api.php
 */

$url = 'http://localhost/mrp/api/v1/search/variantes';
$data = [
    'query' => 'test',
    'limit' => 5,
    'format' => 'standard'
];

echo "\n";
echo "═══════════════════════════════════════════════\n";
echo "  TEST API BÚSQUEDA - SearchController\n";
echo "═══════════════════════════════════════════════\n";
echo "\n";

echo "🔗 URL: $url\n";
echo "📦 Payload: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
echo "\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "📊 HTTP Status: $httpCode\n";
echo "\n";

if ($httpCode === 200) {
    $result = json_decode($response, true);

    if (isset($result['success']) && $result['success']) {
        echo "✅ ÉXITO\n";
        echo "   Resultados encontrados: " . count($result['results'] ?? []) . "\n";
        echo "\n";

        if (!empty($result['results'])) {
            echo "📝 Primeros resultados:\n";
            foreach (array_slice($result['results'], 0, 3) as $item) {
                echo "   • [{$item['id']}] {$item['codigo_variante']} - {$item['detalle']}\n";
            }
        }
    } else {
        echo "⚠️  API respondió con success=false\n";
        echo "   Mensaje: " . ($result['message'] ?? 'Sin mensaje') . "\n";
    }
} else {
    echo "❌ ERROR HTTP $httpCode\n";
    echo "\n";
    echo "Respuesta:\n";
    echo $response;
    echo "\n";
}

echo "\n";
echo "═══════════════════════════════════════════════\n";
echo "\n";
