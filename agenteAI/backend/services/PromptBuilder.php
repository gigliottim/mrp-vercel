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
     * Detectar intent por palabras clave, incluyendo labels exactos de sugerencias
     */
    public function detectIntent(string $userInput): string
    {
        $input = mb_strtolower(trim($userInput));

        // Mapeo de frases exactas de las sugerencias del frontend
        $labels = [
            'create_part' => [
                'crear nueva pieza',
                'nueva pieza',
                'crear pieza',
                'pieza',
                'parte',
                'nueva parte',
                'crear parte',
                'crear nueva parte',
            ],
            'create_bom' => [
                'armar lista de materiales (bom)',
                'bom',
                'lista de materiales',
                'materiales',
                'componentes',
                'armar bom',
                'nueva bom',
            ],
            'create_supplier' => [
                'registrar proveedor',
                'proveedor',
                'nuevo proveedor',
                'empresa',
                'registrar empresa',
            ],
            'create_material' => [
                'registrar materia prima',
                'materia prima',
                'material',
                'nuevo material',
                'registrar material',
            ],
        ];

        foreach ($labels as $intent => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($input, $phrase)) {
                    return $intent;
                }
            }
        }

        return 'general_query';
    }

    /**
     * Determinar el siguiente campo vacío que se le debe preguntar al usuario.
     *
     * @return array{field: string, message: string}|null
     */
    public function nextMissingField(string $intent, array $data): ?array
    {
        $flows = [
            'create_part' => [
                ['code', '¿Cuál es el código de la pieza? (máximo 20 caracteres alfanuméricos)'],
                ['description', '¿Cuál es la descripción de la pieza?'],
                ['uom', '¿Cuál es la unidad de medida? Usa una de: u, kg, m, l, g'],
                ['part_type', '¿Qué tipo de parte es? pieza, materia_prima o producto_terminado'],
                ['category', '¿A qué categoría pertenece? mecanica, electrica u otros'],
            ],
            'create_material' => [
                ['code', '¿Cuál es el código del material? (máximo 20 caracteres alfanuméricos)'],
                ['description', '¿Cuál es la descripción del material?'],
                ['uom', '¿Cuál es la unidad de medida? Usa una de: u, kg, m, l, g'],
                ['min_stock', '¿Cuál es el stock mínimo? (número positivo)'],
            ],
            'create_supplier' => [
                ['name', '¿Cuál es el nombre o razón social del proveedor?'],
                ['cuit', '¿Cuál es el CUIT? Formato: XX-XXXXXXXX-X'],
                ['contact', '¿Cuál es el nombre del contacto?'],
                ['email', '¿Cuál es el email de contacto?'],
                ['phone', '¿Cuál es el teléfono de contacto?'],
            ],
            'create_bom' => [
                ['parent_part', '¿Cuál es el código de la pieza padre?'],
                ['components', '¿Cuál es el primer componente? Indicá código, cantidad y unidad de medida.'],
            ],
        ];

        $flow = $flows[$intent] ?? null;
        if (!$flow) {
            return null;
        }

        foreach ($flow as [$field, $message]) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                return ['field' => $field, 'message' => $message];
            }
        }

        return null;
    }

    /**
     * Construir un mensaje de guía paso a paso basado en el campo faltante.
     */
    public function buildStepByStepMessage(string $intent, array $data): array
    {
        $next = $this->nextMissingField($intent, $data);

        if ($next === null) {
            return [
                'status' => 'preview',
                'message' => 'Datos completos. ¿Confirmamos y guardamos?',
                'data' => $data,
                'suggestions' => ['Confirmar y guardar', 'Corregir'],
            ];
        }

        return [
            'status' => 'clarify',
            'message' => $next['message'],
            'data' => $data,
            'suggestions' => [],
        ];
    }
}
