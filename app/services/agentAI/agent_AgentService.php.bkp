<?php

declare(strict_types=1);

namespace App\Services\AgentAI;

use App\Repositories\AgentConversationRepository;

/**
 * Servicio Principal del Agente AI
 * Orquesta todos los componentes del agente
 */
final class AgentService
{
    private AgentAiClient $client;
    private AgentPromptBuilder $promptBuilder;
    private AgentResponseValidator $validator;
    private ?AgentConversationService $conversationService;
    private ?AgentConversationRepository $repository;

    public function __construct()
    {
        $this->client = new AgentAiClient();
        $this->promptBuilder = new AgentPromptBuilder();
        $this->validator = new AgentResponseValidator();
        try {
            $this->conversationService = new AgentConversationService();
            $this->repository = new AgentConversationRepository();
        } catch (\Exception $e) {
            error_log("Valkey connection failed in AgentService: " . $e->getMessage());
            $this->conversationService = null;
            $this->repository = null;
        }
    }

    /**
     * Procesar un mensaje del usuario
     */
    public function processMessage(string $convId, string $userInput): AgentResponse
    {
        // Obtener historial de conversación (si Valkey está disponible)
        $history = $this->conversationService ? $this->conversationService->getHistory($convId) : [];

        // Detectar intent (si no se especificó)
        $intent = $this->promptBuilder->detectIntent($userInput);

        // Construir mensajes
        $messages = $this->promptBuilder->buildMessages($history, $userInput, $intent);

        // Generar hash del prompt para caché
        $promptHash = $this->generatePromptHash($messages);

        // Verificar caché de Valkey
        $cachedResponse = $this->getCachedResponse($promptHash);
        if ($cachedResponse) {
            return new AgentResponse(
                status: $cachedResponse['status'] ?? 'preview',
                message: $cachedResponse['message'] ?? '',
                data: $cachedResponse['data'] ?? null,
                suggestions: $cachedResponse['suggestions'] ?? [],
                conversationId: $convId
            );
        }

        // Llamar a la IA
        $aiResponse = $this->client->chat($messages, $intent);

        // Validar respuesta
        $validationResult = $this->validator->validate($aiResponse, $intent);

        if ($validationResult->failed()) {
            // Si la validación falla, devolver respuesta para aclarar
            $response = new AgentResponse(
                status: 'clarify',
                message: $this->buildClarifyMessage($validationResult->errors),
                data: $aiResponse,
                suggestions: $this->buildSuggestions($aiResponse),
                conversationId: $convId
            );

            // Guardar log de validación fallida
            $this->logAiCall($convId, $messages, $aiResponse, false, $validationResult->errors);

            return $response;
        }

        // Si la validación pasa, guardar en caché y devolver respuesta
        $this->setCachedResponse($promptHash, $aiResponse);

        $response = new AgentResponse(
            status: 'preview',
            message: $aiResponse['message'] ?? '',
            data: $validationResult->data,
            suggestions: $aiResponse['suggestions'] ?? [],
            conversationId: $convId
        );

        // Guardar log de validación exitosa
        $this->logAiCall($convId, $messages, $aiResponse, true);

        return $response;
    }

    /**
     * Confirmar y guardar los datos
     */
    public function confirmAndSave(string $convId, array $confirmedData, string $intent): array
    {
        // Aquí iría la lógica para llamar al servicio real del dominio
        // Ejemplo: PartService::create(), BomService::create(), etc.

        // Marcar conversación como completada (si Valkey está disponible)
        if ($this->conversationService) {
            $this->conversationService->markCompleted($convId);
        }

        return [
            'success' => true,
            'message' => 'Datos guardados correctamente',
            'data' => $confirmedData,
        ];
    }

    /**
     * Limpiar caché de Valkey
     */
    public function clearCache(string $promptHash): void
    {
        $this->invalidateCachedResponse($promptHash);
    }

    /**
     * Generar hash del prompt
     */
    private function generatePromptHash(array $messages): string
    {
        $json = json_encode($messages, JSON_UNESCAPED_UNICODE);
        return hash('sha256', $json);
    }

    /**
     * Obtener respuesta de la caché de Valkey
     */
    private function getCachedResponse(string $promptHash): ?array
    {
        if (!$this->repository) {
            return null;
        }
        return $this->repository->getCachedResponse($promptHash);
    }

    /**
     * Guardar respuesta en la caché de Valkey
     */
    private function setCachedResponse(string $promptHash, array $response): void
    {
        if ($this->repository) {
            $this->repository->setCachedResponse($promptHash, $response);
        }
    }

    /**
     * Invalidar respuesta de la caché de Valkey
     */
    private function invalidateCachedResponse(string $promptHash): void
    {
        if ($this->repository) {
            $this->repository->invalidateCachedResponse($promptHash);
        }
    }

    /**
     * Construir mensaje de aclaración
     */
    private function buildClarifyMessage(array $errors): string
    {
        return "Faltan datos o hay errores: " . implode(", ", $errors) . ". Por favor, corregí o completá la información.";
    }

    /**
     * Construir sugerencias
     */
    private function buildSuggestions(array $data): array
    {
        // Generar sugerencias basadas en los datos recolectados
        $suggestions = [];

        if (isset($data['code'])) {
            $suggestions[] = "¿El código es correcto: {$data['code']}?";
        }

        if (isset($data['description'])) {
            $suggestions[] = "¿La descripción es correcta: {$data['description']}?";
        }

        return $suggestions;
    }

    /**
     * Guardar log de llamada a IA
     */
    private function logAiCall(
        string $convId,
        array $messages,
        array $response,
        bool $isValid,
        ?array $errors = null
    ): void {
        // Aquí iría la lógica para guardar el log en la base de datos
        // Usando el AgentConversationRepository
    }
}
