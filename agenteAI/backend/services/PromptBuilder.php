<?php

declare(strict_types=1);

namespace App\AgenteAI\Backend\Services;

/**
 * Constructor de Prompts y flujos guiados para el Agente AI.
 *
 * Flujos completos alineados con los ABM reales del MRP:
 *
 * Pieza/Material: Parte (codigo, detalle, tipo, grupo, UM compra, UM uso, dimensiones)
 *   → Variante (codigo, detalle, estado, lote min, punto pedido, peso+UM, ubicacion)
 *   → ¿Otra variante? → (bucle) o Finalizar
 *
 * Proveedor: entidad (razon_social, identificacion_tributaria, contacto_email, contacto_telefono, direccion)
 *
 * BOM: codigo padre, componentes (codigo, cantidad, UM) × N
 */
final class PromptBuilder
{
    /**
     * Fases del flujo guiado para Pieza/Material.
     * Parte primero, luego Variante (con opción de agregar más variantes).
     */
    private const PHASE_PARTE = 'parte';
    private const PHASE_VARIANTE = 'variante';
    private const PHASE_OTRA_VARIANTE = 'otra_variante';

    private const PARTE_FIELDS = [
        ['field' => 'codigo',         'message' => '¿Cuál es el código de la pieza? (máx 50 caracteres, ej: P-001)', 'required' => true],
        ['field' => 'id_tipo',        'message' => '¿Qué tipo de parte es?', 'required' => true, 'lookup' => 'tipos_partes'],
        ['field' => 'id_grupo',       'message' => '¿A qué grupo pertenece?', 'required' => true, 'lookup' => 'grupos_partes'],
        ['field' => 'detalle',        'message' => '¿Cuál es la descripción de la pieza?', 'required' => true],
        ['field' => 'id_um_compra',  'message' => '¿Cuál es la unidad de medida de compra?', 'required' => false, 'lookup' => 'unidades_medida_all'],
        ['field' => 'id_um_uso',     'message' => '¿Cuál es la unidad de medida de uso en producción?', 'required' => false, 'lookup' => 'unidades_medida_all'],
        ['field' => 'largo_alto',     'message' => '¿Cuál es el largo/alto? (dejar vacío si no aplica)', 'required' => false, 'lookup' => 'unidades_medida_longitud'],
        ['field' => 'ancho',          'message' => '¿Cuál es el ancho? (dejar vacío si no aplica)', 'required' => false, 'lookup' => 'unidades_medida_longitud'],
        ['field' => 'espesor_profundidad', 'message' => '¿Cuál es el espesor/profundidad? (dejar vacío si no aplica)', 'required' => false, 'lookup' => 'unidades_medida_longitud'],
    ];

    private const MATERIAL_PARTE_FIELDS = [
        ['field' => 'codigo',         'message' => '¿Cuál es el código del material? (máx 50 caracteres, ej: MP-001)', 'required' => true],
        ['field' => 'id_grupo',      'message' => '¿A qué grupo pertenece?', 'required' => true, 'lookup' => 'grupos_partes'],
        ['field' => 'detalle',        'message' => '¿Cuál es la descripción del material?', 'required' => true],
        ['field' => 'id_um_compra',  'message' => '¿Cuál es la unidad de medida de compra?', 'required' => true, 'lookup' => 'unidades_medida_all'],
        ['field' => 'id_um_uso',     'message' => '¿Cuál es la unidad de medida de uso en producción?', 'required' => false, 'lookup' => 'unidades_medida_all'],
    ];

    private const VARIANTE_FIELDS = [
        ['field' => 'codigo_variante',   'message' => '¿Cuál es el código de la variante? (se sugiere {suggested_code})', 'required' => true],
        ['field' => 'detalle_variante',  'message' => '¿Cuál es la descripción de la variante? (Enter para usar la misma que la parte)', 'required' => false],
        ['field' => 'estado',            'message' => '¿Cuál es el estado de la variante?', 'required' => false, 'lookup' => 'estados_variante'],
        ['field' => 'lote_minimo',      'message' => '¿Cuál es el lote mínimo? (número, default 1)', 'required' => false],
        ['field' => 'punto_pedido',      'message' => '¿Cuál es el punto de pedido? (número, default 0)', 'required' => false],
        ['field' => 'peso',             'message' => '¿Cuál es el peso unitario? (dejar vacío si no aplica)', 'required' => false, 'lookup' => 'unidades_medida_masa'],
        ['field' => 'ubicacion_cuerpo',  'message' => '¿Ubicación física - Cuerpo? (dejar vacío si no aplica)', 'required' => false],
        ['field' => 'ubicacion_pasillo',  'message' => '¿Ubicación física - Pasillo? (dejar vacío si no aplica)', 'required' => false],
        ['field' => 'ubicacion_estante',  'message' => '¿Ubicación física - Estante? (dejar vacío si no aplica)', 'required' => false],
    ];

    private const SUPPLIER_FIELDS = [
        ['field' => 'razon_social',              'message' => '¿Cuál es la razón social del proveedor?', 'required' => true],
        ['field' => 'identificacion_tributaria', 'message' => '¿Cuál es el CUIT o número de identificación tributaria? (dejar vacío si no tiene)', 'required' => false],
        ['field' => 'contacto_email',           'message' => '¿Cuál es el email de contacto? (dejar vacío si no tiene)', 'required' => false],
        ['field' => 'contacto_telefono',         'message' => '¿Cuál es el teléfono de contacto? (dejar vacío si no tiene)', 'required' => false],
        ['field' => 'direccion',                'message' => '¿Cuál es la dirección? (dejar vacío si no tiene)', 'required' => false],
    ];

    private const BOM_FIELDS = [
        ['field' => 'parent_part',    'message' => '¿Cuál es el código de la pieza padre (producto terminado)?', 'required' => true],
        ['field' => 'component_code',  'message' => '¿Cuál es el código del primer componente?', 'required' => true],
        ['field' => 'component_qty',   'message' => '¿Cuál es la cantidad necesaria?', 'required' => true],
        ['field' => 'component_um',     'message' => '¿Cuál es la unidad de medida del componente?', 'required' => false, 'lookup' => 'unidades_medida_all'],
        ['field' => 'add_more',        'message' => '¿Querés agregar otro componente?', 'required' => false],
    ];

    private const ESTADOS_VARIANTE = [
        ['value' => 'activa', 'label' => 'Activa'],
        ['value' => 'desarrollo', 'label' => 'En desarrollo'],
        ['value' => 'obsoleta', 'label' => 'Obsoleta'],
        ['value' => 'descontinuada', 'label' => 'Descontinuada'],
    ];

    public function systemPrompt(string $intent): string
    {
        $prompts = config('agent_ai')['system_prompts'];
        if (!isset($prompts[$intent])) {
            $intent = 'general_query';
        }
        return $prompts[$intent];
    }

    public function buildMessages(array $history, string $userInput, string $intent): array
    {
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $this->systemPrompt($intent)];
        foreach ($history as $message) {
            $messages[] = ['role' => $message['role'], 'content' => $message['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $userInput];
        return $messages;
    }

    public function detectIntent(string $userInput): string
    {
        $input = mb_strtolower(trim($userInput));
        $labels = [
            'create_part' => ['crear nueva pieza', 'nueva pieza', 'crear pieza', 'pieza', 'nueva parte', 'crear parte', 'crear nueva parte'],
            'create_bom' => ['armar lista de materiales', 'bom', 'lista de materiales', 'materiales', 'componentes', 'armar bom', 'nueva bom'],
            'create_supplier' => ['registrar proveedor', 'proveedor', 'nuevo proveedor', 'registrar empresa'],
            'create_material' => ['registrar materia prima', 'materia prima', 'material', 'nuevo material', 'registrar material'],
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
     * Soporta fases: parte → variante → otra_variante (bucle).
     */
    public function nextMissingField(string $intent, array $data): ?array
    {
        $phase = $data['_phase'] ?? self::PHASE_PARTE;

        if ($intent === 'create_part' || $intent === 'create_material') {
            return $this->nextMissingParteField($intent, $data, $phase);
        }
        if ($intent === 'create_supplier') {
            return $this->nextMissingFromFields(self::SUPPLIER_FIELDS, $data);
        }
        if ($intent === 'create_bom') {
            return $this->nextMissingBomField($data);
        }
        return null;
    }

    private function nextMissingParteField(string $intent, array $data, string $phase): ?array
    {
        if ($phase === self::PHASE_PARTE) {
            $fields = ($intent === 'create_material') ? self::MATERIAL_PARTE_FIELDS : self::PARTE_FIELDS;
            foreach ($fields as $f) {
                if (!isset($data[$f['field']]) || $data[$f['field']] === '' || $data[$f['field']] === null) {
                    if (!$f['required'] && !$this->hasRequiredFieldsLeft($fields, $data)) {
                        continue;
                    }
                    $suggestions = $this->getFieldSuggestions($f);
                    return [
                        'field' => $f['field'],
                        'message' => $this->personalizeMessage($f['message'], $data),
                        'suggestions' => $suggestions,
                        'lookup' => $f['lookup'] ?? null,
                    ];
                }
            }
            // Todos los campos de parte completados, pasar a variante
            return $this->firstVarianteField($data);
        }

        if ($phase === self::PHASE_VARIANTE || $phase === self::PHASE_OTRA_VARIANTE) {
            $variantes = $data['variantes'] ?? [];
            $currentVariantIndex = count($variantes);
            $prefix = "Variante " . ($currentVariantIndex + 1) . ": ";

            foreach (self::VARIANTE_FIELDS as $f) {
                $key = $f['field'];
                $currentVal = $data['_current_variante'][$key] ?? null;
                if (($key === 'codigo_variante' || $key === 'detalle_variante') && $currentVal === null) {
                    $suggestions = $this->getFieldSuggestions($f);
                    if ($key === 'codigo_variante') {
                        $suggested = strtoupper(($data['codigo'] ?? 'P')) . '-' . str_pad((string)($currentVariantIndex + 1), 2, '0', STR_PAD_LEFT);
                        $suggestions[] = ['label' => $suggested, 'value' => $suggested];
                    }
                    return [
                        'field' => $key,
                        'message' => $prefix . $this->personalizeMessage($f['message'], $data),
                        'suggestions' => $suggestions,
                        'lookup' => $f['lookup'] ?? null,
                    ];
                }
                if ($currentVal === null && $f['required']) {
                    $suggestions = $this->getFieldSuggestions($f);
                    return [
                        'field' => $key,
                        'message' => $prefix . $f['message'],
                        'suggestions' => $suggestions,
                        'lookup' => $f['lookup'] ?? null,
                    ];
                }
            }

            // Variante completa: preguntar si quiere otra
            return [
                'field' => 'add_more_variante',
                'message' => 'Variante completada. ¿Querés agregar otra variante o finalizar?',
                'suggestions' => [
                    ['label' => 'Agregar otra variante', 'value' => 'si'],
                    ['label' => 'Finalizar', 'value' => 'no'],
                ],
                'lookup' => null,
            ];
        }

        return null;
    }

    private function firstVarianteField(array $data): array
    {
        $suggested = strtoupper(($data['codigo'] ?? 'P')) . '-01';
        return [
            'field' => 'codigo_variante',
            'message' => 'Ahora vamos a crear la primer variante. ¿Cuál es el código de la variante? (se sugiere ' . $suggested . ')',
            'suggestions' => [['label' => $suggested, 'value' => $suggested]],
            'lookup' => null,
        ];
    }

    private function nextMissingFromFields(array $fields, array $data): ?array
    {
        foreach ($fields as $f) {
            if (!isset($data[$f['field']]) || $data[$f['field']] === '' || $data[$f['field']] === null) {
                if (!$f['required'] && !$this->hasRequiredFieldsLeft($fields, $data)) {
                    continue;
                }
                return [
                    'field' => $f['field'],
                    'message' => $f['message'],
                    'suggestions' => $this->getFieldSuggestions($f),
                    'lookup' => $f['lookup'] ?? null,
                ];
            }
        }
        return null;
    }

    private function nextMissingBomField(array $data): ?array
    {
        if (empty($data['parent_part'])) {
            return ['field' => 'parent_part', 'message' => '¿Cuál es el código de la pieza padre (producto terminado)?', 'suggestions' => [], 'lookup' => null];
        }
        if (empty($data['_current_component']['code'])) {
            return ['field' => 'component_code', 'message' => '¿Cuál es el código del componente?', 'suggestions' => [], 'lookup' => null];
        }
        if (empty($data['_current_component']['quantity'])) {
            return ['field' => 'component_qty', 'message' => '¿Cuál es la cantidad necesaria?', 'suggestions' => [], 'lookup' => null];
        }
        return [
            'field' => 'add_more',
            'message' => 'Componente agregado. ¿Querés agregar otro componente?',
            'suggestions' => [['label' => 'Sí, agregar otro', 'value' => 'si'], ['label' => 'No, finalizar', 'value' => 'no']],
            'lookup' => null,
        ];
    }

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

    public function getFieldDefinitions(string $intent): array
    {
        return match ($intent) {
            'create_part' => array_merge(self::PARTE_FIELDS, self::VARIANTE_FIELDS),
            'create_material' => array_merge(self::MATERIAL_PARTE_FIELDS, self::VARIANTE_FIELDS),
            'create_supplier' => self::SUPPLIER_FIELDS,
            'create_bom' => self::BOM_FIELDS,
            default => [],
        };
    }

    private function hasRequiredFieldsLeft(array $fields, array $data): bool
    {
        foreach ($fields as $f) {
            if (($f['required'] ?? false) && (!isset($data[$f['field']]) || $data[$f['field']] === '' || $data[$f['field']] === null)) {
                return true;
            }
        }
        return false;
    }

    private function getFieldSuggestions(array $fieldDef): array
    {
        if (isset($fieldDef['lookup']) && $fieldDef['lookup'] === 'estados_variante') {
            return self::ESTADOS_VARIANTE;
        }
        return [];
    }

    private function personalizeMessage(string $message, array $data): string
    {
        if (str_contains($message, '{suggested_code}')) {
            $suggested = strtoupper(($data['codigo'] ?? 'P')) . '-01';
            return str_replace('{suggested_code}', $suggested, $message);
        }
        return $message;
    }
}