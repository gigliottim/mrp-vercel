<?php

declare(strict_types=1);

/**
 * Configuración del Agente AI para MRP
 *
 * Proveedores disponibles:
 * - 'local': Ollama local (sin costo, requiere Docker)
 * - 'api': DashScope/OpenRouter (API externa, requiere clave)
 */

return [
    /**
     * Modo de operación
     * 'local' para Ollama en VPS
     * 'api' para DashScope/OpenRouter
     */
    'mode' => env('AGENT_AI_MODE', 'local'),

    /**
     * Configuración para modo local (Ollama)
     */
    'local' => [
        'endpoint' => env('AGENT_AI_LOCAL_ENDPOINT', 'http://localhost:11434/v1/chat/completions'),
        'model' => env('AGENT_AI_LOCAL_MODEL', 'qwen2.5:1.5b'),
        'timeout' => 30, // segundos
    ],

    /**
     * Configuración para modo API (DashScope/OpenRouter)
     */
    'api' => [
        'endpoint' => env('AGENT_AI_API_ENDPOINT', 'https://api.dashscope.aliyuncs.com/compatible-mode/v1/chat/completions'),
        'model' => env('AGENT_AI_API_MODEL', 'qwen/qwen2.5-1.5b-instruct'),
        'key' => env('AGENT_AI_API_KEY'),
        'timeout' => 30, // segundos
    ],

    /**
     * Parámetros de generación
     */
    'generation' => [
        'temperature' => 0.1, // Baja para más precisión
        'max_tokens' => 800,
        'seed' => 42, // Reproducibilidad
        'num_ctx' => 4096, // Contexto máximo
    ],

    /**
     * Configuración de retry
     */
    'retry' => [
        'max_attempts' => 2,
        'delay_ms' => 500,
    ],

    /**
     * Opciones predefinidas (Suggestions)
     */
    'suggestions' => [
        [
            'intent' => 'create_part',
            'label' => 'Crear nueva pieza',
            'description' => 'Te guío para registrar código, descripción, UOM y materiales',
            'icon' => 'bi-gear',
        ],
        [
            'intent' => 'create_bom',
            'label' => 'Armar lista de materiales (BOM)',
            'description' => 'Definí la pieza padre y sus componentes de nivel 1',
            'icon' => 'bi-diagram-3',
        ],
        [
            'intent' => 'create_supplier',
            'label' => 'Registrar proveedor',
            'description' => 'Nombre, CUIT, contacto y condiciones comerciales',
            'icon' => 'bi-truck',
        ],
        [
            'intent' => 'create_material',
            'label' => 'Registrar materia prima',
            'description' => 'Material base con unidad de medida y stock mínimo',
            'icon' => 'bi-box-seam',
        ],
    ],

    /**
     * System prompts por intent
     */
    'system_prompts' => [
        'create_part' => 'Eres un asistente experto en registrar piezas para un sistema MRP. Tu tarea es guiar al usuario para que proporcione: código (formato alfanumérico, máximo 20 caracteres), descripción (máximo 200 caracteres), unidad de medida (UOM - debe ser una de: u, kg, m, l, g), tipo de parte (pieza, materia prima, producto terminado), y categoría (mecánica, eléctrica, otros). Pregúntale paso a paso, nunca asumas datos. Si el usuario menciona un código, pregunta para confirmar si es correcto. Devuélve siempre tu respuesta en formato JSON estricto con las siguientes claves: "status" (preview/clarify), "message" (texto para el usuario), "data" (objeto con los datos recolectados hasta ahora), "suggestions" (array de posibles preguntas o confirmaciones).',

        'create_bom' => 'Eres un asistente experto en crear listas de materiales (BOM) para un sistema MRP. Tu tarea es guiar al usuario para que proporcione: pieza padre (código existente), y componentes (código de pieza + cantidad + unidad de medida). Pregúntale paso a paso: primero la pieza padre, luego cada componente uno por uno. Si el usuario menciona un código, pregunta para confirmar si es correcto. Devuélve siempre tu respuesta en formato JSON estricto con las siguientes claves: "status" (preview/clarify), "message" (texto para el usuario), "data" (objeto con "parent_part" y "components" array), "suggestions" (array de posibles preguntas o confirmaciones).',

        'create_supplier' => 'Eres un asistente experto en registrar proveedores para un sistema MRP. Tu tarea es guiar al usuario para que proporcione: nombre (máximo 100 caracteres), CUIT (formato XX-XXXXXXXX-X), contacto (máximo 100 caracteres), email (formato válido), teléfono (máximo 20 caracteres), y condiciones comerciales (plazo de pago, descuentos, etc.). Pregúntale paso a paso, nunca asumas datos. Si el usuario menciona un CUIT, pregunta para confirmar si es correcto. Devuélve siempre tu respuesta en formato JSON estricto con las siguientes claves: "status" (preview/clarify), "message" (texto para el usuario), "data" (objeto con los datos recolectados hasta ahora), "suggestions" (array de posibles preguntas o confirmaciones).',

        'create_material' => 'Eres un asistente experto en registrar materias primas para un sistema MRP. Tu tarea es guiar al usuario para que proporcione: código (formato alfanumérico, máximo 20 caracteres), descripción (máximo 200 caracteres), unidad de medida (UOM - debe ser una de: u, kg, m, l, g), stock mínimo (número positivo), y proveedor por defecto (opcional). Pregúntale paso a paso, nunca asumas datos. Si el usuario menciona un código, pregunta para confirmar si es correcto. Devuélve siempre tu respuesta en formato JSON estricto con las siguientes claves: "status" (preview/clarify), "message" (texto para el usuario), "data" (objeto con los datos recolectados hasta ahora), "suggestions" (array de posibles preguntas o confirmaciones).',

        'general_query' => 'Eres un asistente útil para un sistema MRP. Responde preguntas generales sobre el sistema, ayudando al usuario a entender funcionalidades, navegación, o procesos. Mantén tus respuestas concisas y útiles. Devuélve siempre tu respuesta en formato JSON estricto con las siguientes claves: "status" (response), "message" (tu respuesta al usuario), "data" (null).',
    ],

    /**
     * Validación de respuestas
     */
    'validation' => [
        'code' => '/^[A-Za-z0-9\-]{1,20}$/',
        'uom' => '/^(u|kg|m|l|g)$/',
        'cuit' => '/^[0-9]{2}-[0-9]{8}-[0-9]{1}$/',
        'email' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
        'positive_number' => '/^[0-9]+(\.[0-9]+)?$/',

        'required_fields' => [
            'create_part' => ['code', 'description', 'uom', 'part_type', 'category'],
            'create_bom' => ['parent_part', 'components'],
            'create_supplier' => ['name', 'cuit', 'contact', 'email', 'phone'],
            'create_material' => ['code', 'description', 'uom', 'min_stock'],
        ],
    ],
];
