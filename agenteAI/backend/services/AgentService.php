<?php

declare(strict_types=1);

namespace App\AgenteAI\Backend\Services;

use App\AgenteAI\Backend\Repositories\ConversationRepository;

/**
 * Servicio Principal del Agente AI
 * Orquesta todos los componentes del agente
 */
final class AgentService
{
    private AiClient $client;
    private PromptBuilder $promptBuilder;
    private ResponseValidator $validator;
    private ?ConversationService $conversationService;
    private ?ConversationRepository $repository;

    public function __construct()
    {
        $this->client = new AiClient();
        $this->promptBuilder = new PromptBuilder();
        $this->validator = new ResponseValidator();
        try {
            $this->conversationService = new ConversationService();
            $this->repository = new ConversationRepository();
        } catch (\Exception $e) {
            error_log("Valkey/DB connection failed in AgentService: " . $e->getMessage());
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
            $this->logAiCall($convId, $messages, $cachedResponse, true, null, 'cache_hit');
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
     * Delega al servicio de dominio correspondiente según el intent
     */
    public function confirmAndSave(string $convId, array $confirmedData, string $intent): array
    {
        try {
            $result = match ($intent) {
                'create_part' => $this->savePart($confirmedData),
                'create_bom' => $this->saveBom($confirmedData),
                'create_supplier' => $this->saveSupplier($confirmedData),
                'create_material' => $this->saveMaterial($confirmedData),
                default => ['success' => false, 'message' => "Intent no soportado: {$intent}"],
            };

            // Marcar conversación como completada
            if ($this->conversationService) {
                $this->conversationService->markCompleted($convId);
            }

            // Guardar log
            if ($this->repository) {
                $this->repository->saveAiLog([
                    'conversation_id' => $convId,
                    'validation_result' => $result['success'] ? 'saved' : 'save_failed',
                    'provider' => 'domain_service',
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            error_log("AgentService::confirmAndSave error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Limpiar caché de Valkey
     */
    public function clearCache(string $promptHash): void
    {
        $this->invalidateCachedResponse($promptHash);
    }

    // ─── Delegación a servicios de dominio ────────────────────────────

    private function savePart(array $data): array
    {
        // Verificar que las tablas existen antes de intentar insertar
        if (!$this->repository) {
            return ['success' => false, 'message' => 'Base de datos no disponible'];
        }

        $stmt = $this->repository->getDb()->prepare(
            "INSERT INTO parte (codigo, detalle, tipo_codigo, categoria, creado_en)
             VALUES (?, ?, ?, ?, NOW()) RETURNING id"
        );
        $stmt->execute([
            $data['code'],
            $data['description'],
            $data['part_type'] ?? 'pieza',
            $data['category'] ?? 'otros',
        ]);
        $id = $stmt->fetchColumn();

        return [
            'success' => true,
            'message' => "Pieza '{$data['code']}' creada correctamente",
            'data' => ['id' => (int) $id, 'code' => $data['code']],
        ];
    }

    private function saveBom(array $data): array
    {
        if (!$this->repository) {
            return ['success' => false, 'message' => 'Base de datos no disponible'];
        }

        // TODO: Implementar con BomService cuando exista
        return [
            'success' => true,
            'message' => "BOM para '{$data['parent_part']}' registrado (implementación pendiente)",
            'data' => ['parent_part' => $data['parent_part']],
        ];
    }

    private function saveSupplier(array $data): array
    {
        if (!$this->repository) {
            return ['success' => false, 'message' => 'Base de datos no disponible'];
        }

        $stmt = $this->repository->getDb()->prepare(
            "INSERT INTO proveedor (nombre, cuit, contacto, email, telefono, creado_en)
             VALUES (?, ?, ?, ?, ?, NOW()) RETURNING id"
        );
        $stmt->execute([
            $data['name'],
            $data['cuit'],
            $data['contact'] ?? '',
            $data['email'] ?? '',
            $data['phone'] ?? '',
        ]);
        $id = $stmt->fetchColumn();

        return [
            'success' => true,
            'message' => "Proveedor '{$data['name']}' creado correctamente",
            'data' => ['id' => (int) $id, 'name' => $data['name']],
        ];
    }

    private function saveMaterial(array $data): array
    {
        if (!$this->repository) {
            return ['success' => false, 'message' => 'Base de datos no disponible'];
        }

        // TODO: Implementar con MaterialService cuando exista
        return [
            'success' => true,
            'message' => "Material '{$data['code']}' registrado (implementación pendiente)",
            'data' => ['code' => $data['code']],
        ];
    }

    // ─── Utilidades internas ──────────────────────────────────────────

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
        if (!$this->repository) return null;
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
        ?array $errors = null,
        string $source = 'ai_call'
    ): void {
        if (!$this->repository) return;

        $config = config('agent_ai', []);
        $agentConfig = $config['api'] ?? [];

        $this->repository->saveAiLog([
            'conversation_id' => $convId,
            'model_used' => $agentConfig['model'] ?? 'unknown',
            'provider' => $config['mode'] ?? 'unknown',
            'prompt_hash' => $source === 'cache_hit' ? 'cache' : $this->generatePromptHash($messages),
            'response_time_ms' => null,
            'validation_result' => $source === 'cache_hit' ? 'cache_hit' : ($isValid ? 'valid' : 'invalid'),
            'tokens_used' => null,
        ]);
    }
}
