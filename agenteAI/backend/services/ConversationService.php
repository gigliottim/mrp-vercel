<?php

declare(strict_types=1);

namespace App\AgenteAI\Backend\Services;

use Exception;
use App\Core\Database\ValkeyClient;

/**
 * Servicio de Conversación para el Agente AI
 */
final class ConversationService
{
    private ?ValkeyClient $valkey;
    private PromptBuilder $promptBuilder;
    private ResponseValidator $validator;

    public function __construct()
    {
        $valkeyConfig = config('valkey');

        // Si Valkey está deshabilitado, no conectar
        if (!($valkeyConfig['enabled'] ?? true)) {
            $this->valkey = null;
            $this->promptBuilder = new AgentPromptBuilder();
            $this->validator = new AgentResponseValidator();
            return;
        }

        $this->valkey = new ValkeyClient();

        try {
            $this->valkey->connect(
                $valkeyConfig['host'],
                (int)$valkeyConfig['port'],
                2.0  // timeout de 2 segundos
            );

            if ($password = $valkeyConfig['password']) {
                $this->valkey->auth($password);
            }
        } catch (Exception $e) {
            // Si Valkey no está disponible, continuar sin caché
            $this->valkey = null;
            error_log("Valkey connection failed: " . $e->getMessage());
        }

        $this->promptBuilder = new AgentPromptBuilder();
        $this->validator = new AgentResponseValidator();
    }

    /**
     * Iniciar una nueva conversación
     */
    public function startConversation(int $userId, int $tenantId, string $intent): string
    {
        // Crear registro en la base de datos
        $conversationId = $this->createConversation($userId, $tenantId, $intent);

        // Guardar sesión en Valkey
        $this->saveSession($userId, $tenantId, [
            'conversationId' => $conversationId,
            'intent' => $intent,
            'lastActivity' => time(),
        ]);

        return $conversationId;
    }

    /**
     * Agregar un mensaje a la conversación
     */
    public function addMessage(string $convId, string $role, string $content, array $metadata = []): void
    {
        $this->saveMessage($convId, $role, $content, $metadata);
    }

    /**
     * Obtener el historial de una conversación
     */
    public function getHistory(string $convId): array
    {
        return $this->getMessagesByConversation($convId);
    }

    /**
     * Detectar intent por palabras clave si el usuario no usa opciones predefinidas
     */
    public function resolveIntent(string $userInput): string
    {
        return $this->promptBuilder->detectIntent($userInput);
    }

    /**
     * Marcar una conversación como completada
     */
    public function markCompleted(string $convId): void
    {
        $this->updateConversationStatus($convId, 'completed');
    }

    /**
     * Obtener sesión activa desde Valkey
     */
    public function getActiveSession(int $userId, int $tenantId): ?array
    {
        if (!$this->valkey) {
            return null;
        }

        $key = $this->getSessionKey($userId, $tenantId);
        $session = $this->valkey->hGetAll($key);

        if (!$session) {
            return null;
        }

        // Verificar si la sesión ha expirado (TTL 30 min)
        if (isset($session['lastActivity'])) {
            $lastActivity = (int)$session['lastActivity'];
            if (time() - $lastActivity > 1800) {
                $this->invalidateSession($userId, $tenantId);
                return null;
            }
        }

        return $session;
    }

    /**
     * Guardar sesión en Valkey con TTL 1800s (30 min)
     */
    public function saveSession(int $userId, int $tenantId, array $sessionData): void
    {
        if (!$this->valkey) {
            return;
        }

        $key = $this->getSessionKey($userId, $tenantId);
        $this->valkey->hMSet($key, $sessionData);
        $this->valkey->expire($key, 1800);
    }

    /**
     * Invalidar sesión de Valkey
     */
    public function invalidateSession(int $userId, int $tenantId): void
    {
        if (!$this->valkey) {
            return;
        }

        $key = $this->getSessionKey($userId, $tenantId);
        $this->valkey->del($key);
    }

    /**
     * Obtener clave de sesión
     */
    private function getSessionKey(int $userId, int $tenantId): string
    {
        return "session:{$userId}:{$tenantId}";
    }

    // --- Métodos de base de datos ---

    private function createConversation(int $userId, int $tenantId, string $intent): string
    {
        // Aquí iría la lógica para crear la conversación en la base de datos
        // Usando el AgentConversationRepository
        return 'new_conversation_id';
    }

    private function saveMessage(string $convId, string $role, string $content, array $metadata = []): void
    {
        // Aquí iría la lógica para guardar el mensaje en la base de datos
        // Usando el AgentConversationRepository
    }

    private function getMessagesByConversation(string $convId): array
    {
        // Aquí iría la lógica para obtener los mensajes de la base de datos
        // Usando el AgentConversationRepository
        return [];
    }

    private function updateConversationStatus(string $convId, string $status): void
    {
        // Aquí iría la lógica para actualizar el estado de la conversación
        // Usando el AgentConversationRepository
    }
}
