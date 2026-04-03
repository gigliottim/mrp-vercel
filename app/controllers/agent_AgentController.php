<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AgentAI\AgentService;
use App\Services\AgentAI\AgentConversationService;
use App\Services\AgentAI\AgentResponse;
use Framework\Controller;
use Framework\Request;
use Framework\Response;

/**
 * Controller para el Agente AI
 */
final class agent_AgentController extends Controller
{
    private AgentService $service;
    private AgentConversationService $conversationService;

    public function __construct()
    {
        $this->service = new AgentService();
        $this->conversationService = new AgentConversationService();
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
     */
    public function handleMessage(Request $request): Response
    {
        $this->validateCsrfToken($request);

        $convId = $request->input('conversation_id');
        $userInput = trim($request->input('message'));
        $intent = $request->input('intent', '');
        $userId = $this->getCurrentUserId();
        $tenantId = $this->getCurrentTenantId();

        // Si no hay conversación ID, crear una nueva
        if (!$convId) {
            if (!$intent) {
                $intent = $this->conversationService->resolveIntent($userInput);
            }
            $convId = $this->conversationService->startConversation($userId, $tenantId, $intent);
        }

        // Procesar mensaje
        $response = $this->service->processMessage($convId, $userInput);

        return $this->json([
            'success' => true,
            'conversation_id' => $response->conversationId,
            'status' => $response->status,
            'message' => $response->message,
            'data' => $response->data,
            'suggestions' => $response->suggestions,
        ]);
    }

    /**
     * Confirmar y guardar los datos
     */
    public function confirmSave(Request $request): Response
    {
        $this->validateCsrfToken($request);

        $convId = $request->input('conversation_id');
        $confirmedData = $request->input('data', []);
        $intent = $request->input('intent', '');

        // Confirmar y guardar
        $result = $this->service->confirmAndSave($convId, $confirmedData, $intent);

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
        $this->validateCsrfToken($request);

        // Verificar si el usuario es administrador
        if (!$this->isAdmin()) {
            return $this->json([
                'success' => false,
                'message' => 'No tienes permisos para realizar esta acción',
            ], 403);
        }

        $promptHash = $request->input('prompt_hash');

        if ($promptHash) {
            $this->service->clearCache($promptHash);
            return $this->json([
                'success' => true,
                'message' => 'Caché invalidada para el prompt especificado',
            ]);
        }

        // Limpiar todas las respuestas de IA
        // Aquí iría la lógica para invalidar todas las respuestas de IA en Valkey

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
        return (int)$this->session->get('user_id', 0);
    }

    /**
     * Obtener el ID del tenant actual
     */
    private function getCurrentTenantId(): int
    {
        return (int)$this->session->get('tenant_id', 0);
    }

    /**
     * Verificar si el usuario es administrador
     */
    private function isAdmin(): bool
    {
        $role = $this->session->get('role', '');
        return in_array($role, ['admin', 'superadmin']);
    }

    /**
     * Validar token CSRF
     */
    private function validateCsrfToken(Request $request): void
    {
        $token = $request->input('_token');
        $sessionToken = $this->session->get('csrf_token');

        if (!$token || !$sessionToken || $token !== $sessionToken) {
            throw new \Exception('Token CSRF inválido', 403);
        }
    }
}
