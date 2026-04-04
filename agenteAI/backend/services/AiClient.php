<?php

declare(strict_types=1);

namespace App\AgenteAI\Backend\Services;

use Exception;

/**
 * Cliente HTTP para interactuar con proveedores de IA (Ollama, DashScope, OpenRouter)
 */
final class AgentAiClient
{
    private string $mode;
    private string $localEndpoint;
    private string $localModel;
    private string $apiEndpoint;
    private string $apiModel;
    private ?string $apiKey;
    private int $timeout;
    private int $maxRetries;
    private float $delayMs;
    private array $apiModels;

    public function __construct()
    {
        $config = config('agent_ai');

        $this->mode = $config['mode'];
        $this->localEndpoint = $config['local']['endpoint'];
        $this->localModel = $config['local']['model'];
        $this->apiEndpoint = $config['api']['endpoint'];
        $this->apiModel = $config['api']['model'];
        $this->apiKey = $config['api']['key'];
        $this->timeout = $this->mode === 'local' ? ($config['local']['timeout'] ?? 30) : ($config['api']['timeout'] ?? 30);
        $this->maxRetries = $config['retry']['max_attempts'];
        $this->delayMs = $config['retry']['delay_ms'];

        // Modelos disponibles en modo API
        $this->apiModels = $config['models'] ?? [
            'create_part' => 'qwen3.5:cloud',
            'create_bom' => 'qwen3.5:cloud',
            'create_supplier' => 'qwen3.5:cloud',
            'create_material' => 'qwen3.5:cloud',
            'general_query' => 'gemini-3-flash-preview:cloud',
        ];
    }

    /**
     * Enviar mensaje a la API de IA
     *
     * @param array $messages Array de mensajes en formato OpenAI
     * @param string $intent Intent de la conversación
     * @return array Respuesta de la IA
     * @throws AgentAiException
     */
    public function chat(array $messages, string $intent): array
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            try {
                $response = $this->sendRequest($messages, $intent);
                return $response;
            } catch (Exception $e) {
                $lastError = $e;
                $attempt++;

                if ($attempt < $this->maxRetries) {
                    // Esperar antes de reintentar
                    usleep((int)($this->delayMs * 1000));
                }
            }
        }

        throw new AgentAiException(
            "Failed to get response from AI after {$this->maxRetries} attempts. Last error: " . $lastError->getMessage(),
            500
        );
    }

    /**
     * Enviar solicitud al endpoint de IA
     */
    private function sendRequest(array $messages, string $intent): array
    {
        $endpoint = $this->mode === 'local' ? $this->localEndpoint : $this->apiEndpoint;

        // Usar modelo específico según el intent en modo API
        if ($this->mode === 'api' && isset($this->apiModels[$intent])) {
            $model = $this->apiModels[$intent];
        } else {
            $model = $this->mode === 'local' ? $this->localModel : $this->apiModel;
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => config('agent_ai')['generation']['temperature'],
            'max_tokens' => config('agent_ai')['generation']['max_tokens'],
            'seed' => config('agent_ai')['generation']['seed'],
            'num_ctx' => config('agent_ai')['generation']['num_ctx'],
        ];

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        // Agregar auth header si es modo API
        if ($this->mode === 'api' && $this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $responseTimeMs = (microtime(true) - $startTime) * 1000;

        if ($error) {
            error_log("AgentAiClient cURL Error: $error");
            throw new AgentAiException("cURL Error: $error", 500);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("AgentAiClient API Error (HTTP $httpCode): $response");
            throw new AgentAiException(
                "API Error (HTTP $httpCode): $response",
                $httpCode
            );
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AgentAiException(
                "Invalid JSON response: " . json_last_error_msg() . " - Response: $response",
                500
            );
        }

        // Extraer el contenido de la respuesta
        if (isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];

            // Intentar decodificar el JSON del contenido
            $jsonContent = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonContent)) {
                return $jsonContent;
            }

            // Si no es JSON válido, devolver el contenido como mensaje
            return [
                'status' => 'response',
                'message' => $content,
                'data' => null,
            ];
        }

        throw new AgentAiException("Invalid response format from AI", 500);
    }
}
