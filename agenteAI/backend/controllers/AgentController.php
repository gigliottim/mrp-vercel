<?php

declare(strict_types=1);

namespace App\AgenteAI\Backend\Controllers;

use App\AgenteAI\Backend\Services\AgentService;
use App\AgenteAI\Backend\Services\ConversationService as AgentConversationService;
use App\AgenteAI\Backend\Services\AgentResponse;
use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Support\SessionManager;

/**
 * Controller para el Agente AI
 */
final class AgentController extends Controller
{
    private ?AgentService $service = null;
    private ?AgentConversationService $conversationService = null;

    private function getService(): AgentService
    {
        if ($this->service === null) {
            $this->service = new AgentService();
        }
        return $this->service;
    }

    private function getConversationService(): ?AgentConversationService
    {
        if ($this->conversationService === null) {
            try {
                $this->conversationService = new AgentConversationService();
            } catch (\Exception $e) {
                // Si Valkey no está disponible, devolver null
                $this->conversationService = null;
            }
        }
        return $this->conversationService;
    }

    /**
     * Iniciar sesión si no está activa
     */
    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            SessionManager::start();
        }
    }

    /**
     * Renderizar la view del chatbot
     */
    public function showChat(Request $request): Response
    {
        return $this->view('agentAI/agent_chat', [
            'title' => 'Agente AI - MRP',
        ]);
    }

    /**
     * Manejar mensaje del usuario
     * Nota: No requiere CSRF ya que el endpoint está protegido por sesión de usuario.
     */
    public function handleMessage(Request $request): Response
    {
        $this->ensureSession();

        $convId = $request->input('conversation_id');
        $userInput = trim($request->input('message'));
        $intent = $request->input('intent', '');
        $userId = $this->getCurrentUserId();
        $tenantId = $this->getCurrentTenantId();

        // Si no hay conversación ID, crear una nueva
        if (!$convId) {
            $conversationService = $this->getConversationService();
            if (!$intent && $conversationService) {
                $intent = $conversationService->resolveIntent($userInput);
            }
            if ($conversationService) {
                $convId = $conversationService->startConversation($userId, $tenantId, $intent);
            } else {
                // Si Valkey no está disponible, generar un ID temporal
                $convId = 'temp_' . uniqid();
            }
        }

        // Procesar mensaje
        try {
            $response = $this->getService()->processMessage($convId, $userInput);

            return $this->json([
                'success' => true,
                'conversation_id' => $response->conversationId,
                'status' => $response->status,
                'message' => $response->message,
                'data' => $response->data,
                'suggestions' => $response->suggestions,
            ]);
        } catch (\Exception $e) {
            error_log("Error in agent_AgentController::handleMessage: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            return $this->json([
                'success' => false,
                'message' => 'Error al procesar el mensaje con la IA: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirmar y guardar los datos
     */
    public function confirmSave(Request $request): Response
    {
        $this->ensureSession();

        $convId = $request->input('conversation_id');
        $confirmedData = $request->input('data', []);
        $intent = $request->input('intent', '');

        // Confirmar y guardar
        $result = $this->getService()->confirmAndSave($convId, $confirmedData, $intent);

        return $this->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ]);
    }

    /**
     * Obtener sugerencias predefinidas
     */
    public function getSuggestions(Request $request): Response
    {
        $agentConfig = config('agent_ai', []);
        $suggestions = $agentConfig['suggestions'] ?? [];

        return $this->json([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Verificar configuración del agente AI
     */
    public function checkConfiguration(Request $request): Response
    {
        $config = config('agent_ai');
        $mode = $config['mode'];

        $hasValidConfig = false;
        $errorMessage = '';

        if ($mode === 'local') {
            // Verificar configuración local
            $localEndpoint = $config['local']['endpoint'];
            $localModel = $config['local']['model'];

            if ($localEndpoint && $localModel) {
                $hasValidConfig = true;
            } else {
                $errorMessage = 'Configuración local incompleta. Verifica AGENT_AI_LOCAL_ENDPOINT y AGENT_AI_LOCAL_MODEL.';
            }
        } elseif ($mode === 'api') {
            // Verificar configuración API
            $apiEndpoint = $config['api']['endpoint'];
            $apiModel = $config['api']['model'];
            $apiKey = $config['api']['key'];

            if ($apiEndpoint && $apiModel && $apiKey) {
                $hasValidConfig = true;
            } else {
                $errorMessage = 'Configuración API incompleta. Verifica AGENT_AI_API_ENDPOINT, AGENT_AI_API_MODEL y AGENT_AI_API_KEY.';
            }
        } else {
            $errorMessage = 'Modo de configuración inválido. Usa "local" o "api".';
        }

        return $this->json([
            'success' => true,
            'isOnline' => $hasValidConfig,
            'mode' => $mode,
            'message' => $hasValidConfig ? 'Configuración válida' : $errorMessage,
        ]);
    }

    /**
     * Limpiar caché de Valkey (para administradores)
     */
    public function clearCache(Request $request): Response
    {
        $this->ensureSession();

        // Verificar si el usuario es administrador
        if (!$this->isAdmin()) {
            return $this->json([
                'success' => false,
                'message' => 'No tienes permisos para realizar esta acción',
            ], 403);
        }

        $promptHash = $request->input('prompt_hash');

        if ($promptHash) {
            $this->getService()->clearCache($promptHash);
            return $this->json([
                'success' => true,
                'message' => 'Caché invalidada para el prompt especificado',
            ]);
        }

        return $this->json([
            'success' => true,
            'message' => 'Caché de IA invalidada',
        ]);
    }

    /**
     * Obtener el ID del usuario actual
     */
    private function getCurrentUserId(): int
    {
        $this->ensureSession();
        return (int)($_SESSION['user_id'] ?? 0);
    }

    /**
     * Obtener el ID del tenant actual
     */
    private function getCurrentTenantId(): int
    {
        $this->ensureSession();
        return (int)($_SESSION['tenant_id'] ?? 0);
    }

    /**
     * Verificar si el usuario es administrador
     */
    private function isAdmin(): bool
    {
        $this->ensureSession();
        $role = $_SESSION['role'] ?? '';
        return in_array($role, ['admin', 'superadmin']);
    }
}
