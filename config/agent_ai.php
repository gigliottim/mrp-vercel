<?php

declare(strict_types=1);

/**
 * Configuración del Agente AI para MRP
 *
 * Proveedor: Ollama Cloud (API compatible con OpenAI)
 * Endpoint: https://ollama.com/v1/chat/completions
 *
 * Los valores sensibles (API key) se leen del .env vía env()
 * que usa Env::get() cargado por bootstrap/app.php.
 * El endpoint y modelo por defecto están hardcodeados porque
 * solo usamos un proveedor.
 */

return [
    /**
     * Modo de operación (solo API)
     */
    'mode' => 'api',

    /**
     * Conexión API (Ollama Cloud — OpenAI Compatible)
     */
    'api' => [
        'endpoint' => 'https://ollama.com/v1/chat/completions',
        'model'    => env('AGENT_AI_API_MODEL', 'gemini-3-flash-preview'),
        'key'      => env('AGENT_AI_API_KEY'),
        'timeout'  => 30, // segundos
    ],

    /**
     * Modelos específicos por intent
     */
    'models' => [
        'create_part'     => env('AGENT_AI_API_MODEL_PART', 'gemini-3-flash-preview'),
        'create_bom'      => env('AGENT_AI_API_MODEL_BOM', 'gemini-3-flash-preview'),
        'create_supplier' => env('AGENT_AI_API_MODEL_SUPPLIER', 'gemini-3-flash-preview'),
        'create_material' => env('AGENT_AI_API_MODEL_MATERIAL', 'gemini-3-flash-preview'),
        'general_query'   => env('AGENT_AI_API_MODEL_GENERAL', 'gemini-3-flash-preview'),
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
            'offline_safe' => true,
        ],
        [
            'intent' => 'create_bom',
            'label' => 'Armar lista de materiales (BOM)',
            'description' => 'Definí la pieza padre y sus componentes de nivel 1',
            'icon' => 'bi-diagram-3',
            'offline_safe' => true,
        ],
        [
            'intent' => 'create_supplier',
            'label' => 'Registrar proveedor',
            'description' => 'Nombre, CUIT, contacto y condiciones comerciales',
            'icon' => 'bi-truck',
            'offline_safe' => true,
        ],
        [
            'intent' => 'create_material',
            'label' => 'Registrar materia prima',
            'description' => 'Material base con unidad de medida y stock mínimo',
            'icon' => 'bi-box-seam',
            'offline_safe' => true,
        ],
    ],

    /**
     * System prompts por intent
     */
    'system_prompts' => [
        'create_part' => "You are an MRP assistant. ALWAYS reply with ONLY a valid JSON object, no text, no markdown, no backticks, no explanations. Strict format: {\"status\":\"clarify\"|\"preview\",\"message\":\"...\",\"data\":{\"code\":\"\",\"description\":\"\",\"uom\":\"\",\"part_type\":\"\",\"category\":\"\"},\"suggestions\":[\"...\"]}. Ask for ONE field at a time in this exact order: 1) code, 2) description, 3) uom (u/kg/m/l/g), 4) part_type (pieza/materia_prima/producto_terminado), 5) category (mecanica/electrica/otros). status=preview only when all required fields are valid and complete; otherwise status=clarify with the next missing field.",

        'create_bom' => "You are an MRP assistant. ALWAYS reply with ONLY a valid JSON object, no text, no markdown, no backticks, no explanations. Strict format: {\"status\":\"clarify\"|\"preview\",\"message\":\"...\",\"data\":{\"parent_part\":\"\",\"components\":[]},\"suggestions\":[\"...\"]}. Ask for parent_part first, then components one by one. status=preview only when all required fields are valid and complete; otherwise status=clarify with the next missing field.",

        'create_supplier' => "You are an MRP assistant. ALWAYS reply with ONLY a valid JSON object, no text, no markdown, no backticks, no explanations. Strict format: {\"status\":\"clarify\"|\"preview\",\"message\":\"...\",\"data\":{\"name\":\"\",\"cuit\":\"\",\"contact\":\"\",\"email\":\"\",\"phone\":\"\"},\"suggestions\":[\"...\"]}. Ask for ONE field at a time in this exact order: 1) name, 2) CUIT (XX-XXXXXXXX-X), 3) contact, 4) email, 5) phone. status=preview only when all required fields are valid and complete; otherwise status=clarify with the next missing field.",

        'create_material' => "You are an MRP assistant. ALWAYS reply with ONLY a valid JSON object, no text, no markdown, no backticks, no explanations. Strict format: {\"status\":\"clarify\"|\"preview\",\"message\":\"...\",\"data\":{\"code\":\"\",\"description\":\"\",\"uom\":\"\",\"min_stock\":\"\"},\"suggestions\":[\"...\"]}. Ask for ONE field at a time in this exact order: 1) code, 2) description, 3) uom (u/kg/m/l/g), 4) min_stock (positive number). status=preview only when all required fields are valid and complete; otherwise status=clarify with the next missing field.",

        'general_query' => "You are an MRP assistant. ALWAYS reply with ONLY a valid JSON object, no text, no markdown, no backticks, no explanations. Strict format: {\"status\":\"response\",\"message\":\"...\",\"data\":null,\"suggestions\":[\"...\"]}.",
    ],

    /**
     * Validación de respuestas
     */
    'validation' => [
        'code' => '/^[A-Za-z0-9\-]{1,50}$/',
        'cuit' => '/^[0-9]{2}-?[0-9]{8}-?[0-9]{1}$/',
        'email' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
        'positive_number' => '/^[0-9]+(\.[0-9]+)?$/',

        'required_fields' => [
            'create_part' => ['code', 'description', 'id_tipo', 'id_grupo'],
            'create_bom' => ['parent_part', 'components'],
            'create_supplier' => ['razon_social'],
            'create_material' => ['code', 'description', 'id_um_compra'],
        ],
    ],
];
