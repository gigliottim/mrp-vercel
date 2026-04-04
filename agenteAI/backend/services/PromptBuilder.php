<?php

declare(strict_types=1);

namespace App\AgenteAI\Backend\Services;

/**
 * Constructor de Prompts para el Agente AI
 */
final class PromptBuilder
{
    /**
     * Obtener el system prompt base según el intent
     */
    public function systemPrompt(string $intent): string
    {
        $prompts = config('agent_ai')['system_prompts'];

        // Si el intent no existe, usar el prompt general
        if (!isset($prompts[$intent])) {
            $intent = 'general_query';
        }

        return $prompts[$intent];
    }

    /**
     * Construir el array de mensajes para la API
     */
    public function buildMessages(array $history, string $userInput, string $intent): array
    {
        $messages = [];

        // Agregar system prompt
        $messages[] = [
            'role' => 'system',
            'content' => $this->systemPrompt($intent),
        ];

        // Agregar historial de conversación
        foreach ($history as $message) {
            $messages[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        // Agregar mensaje del usuario
        $messages[] = [
            'role' => 'user',
            'content' => $userInput,
        ];

        return $messages;
    }

    /**
     * Detectar intent por palabras clave
     */
    public function detectIntent(string $userInput): string
    {
        $input = mb_strtolower($userInput);

        // Palabras clave para cada intent
        $keywords = [
            'create_part' => ['pieza', 'parte', 'nueva', 'crear', 'agregar', 'código', 'descripción'],
            'create_bom' => ['bom', 'lista', 'materiales', 'componentes', 'armar', 'padre'],
            'create_supplier' => ['proveedor', 'proveedora', 'empresa', 'cuit', 'contacto'],
            'create_material' => ['material', 'materia prima', 'base', 'stock', 'mínimo'],
        ];

        // Buscar coincidencias
        foreach ($keywords as $intent => $words) {
            foreach ($words as $word) {
                if (strpos($input, $word) !== false) {
                    return $intent;
                }
            }
        }

        // Si no hay coincidencias, devolver general_query
        return 'general_query';
    }
}
