<?php
// Test the exact same logic as AgentController::testApiEndpoint
require_once '/app/bootstrap/autoload.php';

$config = config('agent_ai');
$apiConfig = $config['api'] ?? [];
$endpoint = $apiConfig['endpoint'] ?? 'MISSING';
$model = $apiConfig['model'] ?? 'MISSING';
$apiKey = $apiConfig['key'] ?? 'MISSING';

echo "Endpoint: $endpoint\n";
echo "Model: $model\n";
echo "API Key: " . substr($apiKey, 0, 10) . "...\n";

$payload = [
    'model' => $model,
    'messages' => [['role' => 'user', 'content' => 'ping']],
    'max_tokens' => 5,
    'temperature' => 0.1,
];

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
];
if ($apiKey && $apiKey !== 'MISSING') {
    $headers[] = 'Authorization: Bearer ' . $apiKey;
}

echo "Headers: " . json_encode($headers) . "\n";
echo "Payload: " . json_encode($payload) . "\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $endpoint);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "cURL Error: " . ($error ?: 'none') . "\n";
echo "Response length: " . strlen($response) . "\n";
echo "Response: " . substr($response, 0, 300) . "\n\n";

if ($httpCode >= 200 && $httpCode < 300) {
    $data = json_decode($response, true);
    $jsonOk = json_last_error() === JSON_ERROR_NONE;
    $hasContent = isset($data['choices'][0]['message']['content']);
    echo "JSON valid: " . ($jsonOk ? 'YES' : 'NO - ' . json_last_error_msg()) . "\n";
    echo "Has choices[0].message.content: " . ($hasContent ? 'YES' : 'NO') . "\n";
    echo "testApiEndpoint would return: " . ($jsonOk && $hasContent ? 'TRUE (online)' : 'FALSE (offline)') . "\n";
} else {
    echo "testApiEndpoint would return: FALSE (offline) - HTTP $httpCode\n";
}
