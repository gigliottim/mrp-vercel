<?php

declare(strict_types=1);

namespace App\AgenteAI\Backend\Services;

/**
 * Constructor de Prompts y flujos guiados para el Agente AI
 */
final class PromptBuilder
{
    /**
     * Definición de campos por intent, alineados con las tablas reales del MRP.
     * Cada campo tiene: field, message, required, suggestions (closed options from DB),
     * y extractor (method name in AgentService).
     */
    private const FLOWS = [
        'create_part' => [
            ['field' => 'code',           'message' => '¿Cuál es el código de la pieza? (ej: P-001, máximo 50 caracteres alfanuméricos)', 'required' => true],
            ['field' => 'description',    'message' => '¿Cuál es la descripción de la pieza?', 'required' => true],
            ['field' => 'id_tipo',         'message' => '¿Qué tipo de parte es?', 'required' => true,  'lookup' => 'tipos_partes'],
            ['field' => 'id_grupo',        'message' => '¿A qué grupo pertenece?', 'required' => true,  'lookup' => 'grupos_partes'],
            ['field' => 'id_um_compra',   'message' => '¿Cuál es la unidad de medida de compra?', 'required' => false, 'lookup' => 'unidades_medida_all'],
            ['field' => 'id_um_uso',      'message' => '¿Cuál es la unidad de medida de uso en producción?', 'required' => false, 'lookup' => 'unidades_medida_all'],
        ],
        'create_material' => [
            ['field' => 'code',           'message' => '¿Cuál es el código del material? (ej: MP-001)', 'required' => true],
            ['field' => 'description',    'message' => '¿Cuál es la descripción del material?', 'required' => true],
            ['field' => 'id_grupo',        'message' => '¿A qué grupo pertenece?', 'required' => true,  'lookup' => 'grupos_partes'],
            ['field' => 'id_um_compra',   'message' => '¿Cuál es la unidad de medida de compra?', 'required' => true,  'lookup' => 'unidades_medida_all'],
            ['field' => 'id_um_uso',      'message' => '¿Cuál es la unidad de medida de uso en producción?', 'required' => false, 'lookup' => 'unidades_medida_all'],
            ['field' => 'stock_seguridad', 'message' => '¿Cuál es el stock de seguridad? (número positivo)', 'required' => false],
            ['field' => 'punto_pedido',   'message' => '¿Cuál es el punto de pedido? (número positivo)', 'required' => false],
        ],
        'create_supplier' => [
            ['field' => 'razon_social',              'message' => '¿Cuál es la razón social del proveedor?', 'required' => true],
            ['field' => 'identificacion_tributaria', 'message' => '¿Cuál es el CUIT o número de identificación tributaria?', 'required' => false],
            ['field' => 'contacto_email',           'message' => '¿Cuál es el email de contacto?', 'required' => false],
            ['field' => 'contacto_telefono',        'message' => '¿Cuál es el teléfono de contacto?', 'required' => false],
            ['field' => 'direccion',                'message' => '¿Cuál es la dirección?', 'required' => false],
        ],
        'create_bom' => [
            ['field' => 'parent_part',    'message' => '¿Cuál es el código de la pieza padre (producto terminado)?', 'required' => true],
            ['field' => 'component_code',  'message' => '¿Cuál es el código del componente?', 'required' => true],
            ['field' => 'component_qty',   'message' => '¿Cuál es la cantidad necesaria?', 'required' => true],
            ['field' => 'component_um',    'message' => '¿Cuál es la unidad de medida del componente?', 'required' => false, 'lookup' => 'unidades_medida_all'],
            ['field' => 'add_more',        'message' => '¿Querés agregar otro componente? Respondé "sí" o "no".', 'required' => false],
        ],
    ];

    /**
     * Obtener el system prompt base según el intent
     */
    public function systemPrompt(string $intent): string
    {
        $prompts = config('agent_ai')['system_prompts'];

        if (!isset($prompts[$intent])) {
            $intent = 'general_query';
        }

        return $prompts[$intent];
    }

    /**
     * Construir el array de mensajes para la API (solo para general_query)
     */
    public function buildMessages(array $history, string $userInput, string $intent): array
    {
        $messages = [];

        $messages[] = [
            'role' => 'system',
            'content' => $this->systemPrompt($intent),
        ];

        foreach ($history as $message) {
            $messages[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

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
        $input = mb_strtolower(trim($userInput));

        $labels = [
            'create_part' => [
                'crear nueva pieza', 'nueva pieza', 'crear pieza', 'pieza',
                'nueva parte', 'crear parte', 'crear nueva parte',
            ],
            'create_bom' => [
                'armar lista de materiales', 'bom', 'lista de materiales',
                'materiales', 'componentes', 'armar bom', 'nueva bom',
            ],
            'create_supplier' => [
                'registrar proveedor', 'proveedor', 'nuevo proveedor',
                'registrar empresa',
            ],
            'create_material' => [
                'registrar materia prima', 'materia prima', 'material',
                'nuevo material', 'registrar material',
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
     * Determinar el siguiente campo faltante en el flujo guiado.
     *
     * @return array{field: string, message: string, suggestions: array, lookup: string|null}|null
     */
    public function nextMissingField(string $intent, array $data): ?array
    {
        $flow = self::FLOWS[$intent] ?? null;
        if (!$flow) {
            return null;
        }

        foreach ($flow as $fieldDef) {
            $field = $fieldDef['field'];

            if ($field === 'add_more') {
                if (isset($data['components']) && count($data['components']) > 0 && !isset($data['_asked_add_more'])) {
                    return [
                        'field' => 'add_more',
                        'message' => $fieldDef['message'],
                        'suggestions' => [
                            ['label' => 'Sí, agregar otro', 'value' => 'si'],
                            ['label' => 'No, finalizar', 'value' => 'no'],
                        ],
                        'lookup' => null,
                    ];
                }
                continue;
            }

            if ($field === 'component_code' || $field === 'component_qty' || $field === 'component_um') {
                continue;
            }

            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                if ($fieldDef['required'] === false && !$this->hasRequiredFieldsLeft($intent, $data)) {
                    continue;
                }
                $suggestions = $this->getFieldSuggestions($fieldDef);
                return [
                    'field' => $field,
                    'message' => $fieldDef['message'],
                    'suggestions' => $suggestions,
                    'lookup' => $fieldDef['lookup'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Construir mensaje de bienvenida para el flujo guiado.
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
            'suggestions' => $next['suggestions'],
        ];
    }

    /**
     * Obtener la definición completa de campos para un intent.
     */
    public function getFieldDefinitions(string $intent): array
    {
        return self::FLOWS[$intent] ?? [];
    }

    /**
     * Verificar si quedan campos requeridos por completar.
     */
    private function hasRequiredFieldsLeft(string $intent, array $data): bool
    {
        $flow = self::FLOWS[$intent] ?? [];
        foreach ($flow as $fieldDef) {
            if (($fieldDef['required'] ?? false) === true) {
                $field = $fieldDef['field'];
                if ($field === 'add_more' || $field === 'component_code' || $field === 'component_qty' || $field === 'component_um') {
                    continue;
                }
                if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Obtener sugerencias estáticas para un campo (fallback cuando no hay lookup).
     */
    private function getFieldSuggestions(array $fieldDef): array
    {
        $lookup = $fieldDef['lookup'] ?? null;
        $field = $fieldDef['field'];

        if ($lookup !== null) {
            return [];
        }

        if ($field === 'add_more') {
            return [
                ['label' => 'Sí, agregar otro', 'value' => 'si'],
                ['label' => 'No, finalizar', 'value' => 'no'],
            ];
        }

        return [];
    }
}