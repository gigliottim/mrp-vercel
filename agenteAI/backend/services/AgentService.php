<?php

declare(strict_types=1);

namespace App\AgenteAI\Backend\Services;

use App\AgenteAI\Backend\Repositories\ConversationRepository;

/**
 * Servicio Principal del Agente AI
 * Orquesta el flujo guiado paso a paso y el guardado en las tablas del MRP.
 *
 * Tablas reales:
 * - Partes: tabla `partes` (campos: codigo, id_tipo, id_grupo, detalle, id_um_compra, id_um_uso, factor_conversion, activo)
 * - Variantes: tabla `variantes` (campos: id_parte, codigo_variante, detalle, estado, stock_seguridad, punto_pedido)
 * - Proveedores: tabla `entidades` (campos: razon_social, tipo='PROVEEDOR', identificacion_tributaria, contacto_email, contacto_telefono, direccion)
 * - BOM: tablas `bom_cabecera` + `bom_detalle`
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
            $this->conversationService = null;
            $this->repository = null;
        }
    }

    /**
     * Procesar un mensaje del usuario
     */
    public function processMessage(string $convId, string $userInput, ?string $fieldValue = null, ?string $fieldLabel = null): AgentResponse
    {
        $history = $this->conversationService ? $this->conversationService->getHistory($convId) : [];
        $session = $this->getConversationState($convId);
        $intent = $session['intent'] ?? '';

        if ($intent === '') {
            $intent = $this->promptBuilder->detectIntent($userInput);
        }

        // Primera interacción con intent guiado: iniciar flujo paso a paso
        if (($session['step'] ?? 0) === 0 && $this->isGuidedIntent($intent)) {
            $this->saveConversationState($convId, ['intent' => $intent, 'data' => [], 'step' => 1]);
            $welcomeResponse = $this->askNextField($convId, [], $intent);
            $this->logAiCall($convId, [], ['message' => $welcomeResponse->message], true, null, 'guided_welcome');
            return $welcomeResponse;
        }

        // Continuación de flujo guiado
        if ($this->isGuidedIntent($intent)) {
            return $this->processGuidedStep($convId, $userInput, $intent, $session['data'] ?? [], (int)($session['step'] ?? 1), $fieldValue, $fieldLabel);
        }

        // Flujo general (consultas no guiadas) — usa IA
        $messages = $this->promptBuilder->buildMessages($history, $userInput, $intent);
        $promptHash = $this->generatePromptHash($messages);

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

        $aiResponse = $this->client->chat($messages, $intent);
        $validationResult = $this->validator->validate($aiResponse['data'] ?? [], $intent);

        if ($validationResult->failed()) {
            $response = new AgentResponse(
                status: 'clarify',
                message: $aiResponse['message'] ?? $this->buildClarifyMessage($validationResult->errors),
                data: $aiResponse['data'] ?? null,
                suggestions: $aiResponse['suggestions'] ?? [],
                conversationId: $convId
            );
            $this->logAiCall($convId, $messages, $aiResponse, false, $validationResult->errors);
            return $response;
        }

        $this->setCachedResponse($promptHash, $aiResponse);

        $response = new AgentResponse(
            status: 'preview',
            message: $aiResponse['message'] ?? '',
            data: $validationResult->data,
            suggestions: $aiResponse['suggestions'] ?? [],
            conversationId: $convId
        );

        $this->logAiCall($convId, $messages, $aiResponse, true);
        return $response;
    }

    /**
     * Confirmar y guardar los datos
     */
    public function confirmAndSave(string $convId, array $confirmedData, string $intent): array
    {
        try {
            $result = match ($intent) {
                'create_part' => $this->savePart($confirmedData),
                'create_bom' => $this->saveBom($confirmedData),
                'create_supplier' => $this->saveSupplier($confirmedData),
                default => ['success' => false, 'message' => "Intent no soportado: {$intent}"],
            };

            if ($this->conversationService) {
                $this->conversationService->markCompleted($convId);
            }

            try {
                if ($this->repository) {
                    $this->repository->saveAiLog([
                        'conversation_id' => $convId,
                        'validation_result' => $result['success'] ? 'saved' : 'save_failed',
                        'provider' => 'domain_service',
                    ]);
                }
            } catch (\Throwable $e) {
                error_log("AgentService::confirmAndSave - saveAiLog failed (non-critical): " . $e->getMessage());
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

    public function clearCache(string $promptHash): void
    {
        $this->invalidateCachedResponse($promptHash);
    }

    // ─── Conversaciones guiadas paso a paso ───────────────────────────

    private function isGuidedIntent(string $intent): bool
    {
        return in_array($intent, ['create_part', 'create_supplier', 'create_bom'], true);
    }

    private function isLookupField(string $field): bool
    {
        return in_array($field, ['id_tipo', 'id_grupo', 'id_um_compra', 'id_um_uso', 'estado'], true);
    }

    private function processGuidedStep(
        string $convId,
        string $userInput,
        string $intent,
        array $collectedData,
        int $step,
        ?string $fieldValue = null,
        ?string $fieldLabel = null
    ): AgentResponse {
        $phase = $collectedData['_phase'] ?? '';

        // Fase: esperando respuesta "¿Otra variante?" — manejar directamente
        if ($intent === 'create_part' && $phase === 'ask_more_variante') {
            return $this->processParteVarianteStep($convId, $userInput, $intent, $collectedData, $step, ['field' => 'add_more_variante'], $fieldValue, $fieldLabel);
        }

        $next = $this->promptBuilder->nextMissingField($intent, $collectedData);

        // Flujo especial para BOM
        if ($intent === 'create_bom' && $next !== null) {
            return $this->processBomStep($convId, $userInput, $intent, $collectedData, $step, $next);
        }

        // Flujo especial para Parte+Variante
        if ($intent === 'create_part' && $next !== null) {
            return $this->processParteVarianteStep($convId, $userInput, $intent, $collectedData, $step, $next, $fieldValue, $fieldLabel);
        }

        // Flujo genérico (proveedor, etc.)
        if ($next !== null) {
            $field = $next['field'];
            $effectiveInput = ($fieldValue !== null && $this->isLookupField($field)) ? $fieldValue : $userInput;
            $value = $this->extractFieldValue($field, $effectiveInput, $intent);

            if ($value === null && $field !== 'add_more') {
                $help = $this->buildFieldHelp($field, $intent);
                return new AgentResponse(
                    status: 'clarify',
                    message: "No entendí bien ese dato. {$help}",
                    data: $collectedData,
                    suggestions: $next['suggestions'] ?? [],
                    conversationId: $convId
                );
            }

            if ($field !== 'add_more') {
                $collectedData[$field] = $value;
                if ($fieldLabel !== null && $this->isLookupField($field)) {
                    $collectedData['_label_' . $field] = $fieldLabel;
                }
            }
        }

        $validationResult = $this->validator->validate($collectedData, $intent);

        if ($validationResult->failed()) {
            $next = $this->promptBuilder->nextMissingField($intent, $collectedData);
            $message = $next !== null
                ? $next['message']
                : $this->buildClarifyMessage($validationResult->errors);

            $suggestions = [];
            if ($next !== null) {
                $suggestions = $next['suggestions'] ?? [];
                $lookup = $next['lookup'] ?? null;
                if (empty($suggestions) && $lookup !== null) {
                    if ($lookup === 'unidades_medida_all') {
                        $suggestions = [['lookup' => 'unidades_medida_all']];
                    } else {
                        $suggestions = $this->fetchLookupSuggestions($lookup);
                    }
                }
            }

            $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 1]);

            $confirmation = '';
            if ($field !== 'add_more' && $field !== '') {
                $confirmation = $this->getDisplayValue($field, $collectedData) . ' ';
            }

            return new AgentResponse(
                status: 'clarify',
                message: $confirmation . $message,
                data: $collectedData,
                suggestions: $suggestions,
                conversationId: $convId
            );
        }

        // Datos completos: guardar
        $saveResult = $this->confirmAndSave($convId, $validationResult->data, $intent);

        if ($saveResult['success']) {
            $this->clearConversationState($convId);

            return new AgentResponse(
                status: 'saved',
                message: $saveResult['message'],
                data: $saveResult['data'] ?? null,
                suggestions: ['Crear otra parte', 'Volver al menú'],
                conversationId: $convId
            );
        }

        return new AgentResponse(
            status: 'clarify',
            message: "No pude guardar: {$saveResult['message']}. ¿Querés corregir algún dato?",
            data: $collectedData,
            suggestions: ['Reintentar', 'Cancelar'],
            conversationId: $convId
        );
    }

    /**
     * Flujo guiado especial para Parte+Variante.
     * Maneja 3 fases: parte → variante → ¿otra variante?
     */
    private function processParteVarianteStep(
        string $convId,
        string $userInput,
        string $intent,
        array $collectedData,
        int $step,
        array $next,
        ?string $fieldValue = null,
        ?string $fieldLabel = null
    ): AgentResponse {
        $phase = $collectedData['_phase'] ?? 'parte';

        // Fase: Esperando respuesta "¿Otra variante?" — el usuario respondió
        if ($phase === 'ask_more_variante') {
            $lower = mb_strtolower(trim($userInput));
            if (str_contains($lower, 'si') || str_contains($lower, 'sí') || str_contains($lower, 'otra') || str_contains($lower, 'agregar')) {
                $collectedData['_current_variante'] = [];
                $collectedData['_phase'] = 'otra_variante';
                $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 1]);

                $variantIndex = count($collectedData['variantes'] ?? []) + 2;
                $suggestedCode = strtoupper(($collectedData['codigo'] ?? 'P')) . '-' . str_pad((string) $variantIndex, 2, '0', STR_PAD_LEFT);

                return new AgentResponse(
                    status: 'clarify',
                    message: "Variante {$variantIndex}. ¿Cuál es el código de la variante? (se sugiere {$suggestedCode})",
                    data: $collectedData,
                    suggestions: [['label' => $suggestedCode, 'value' => $suggestedCode]],
                    conversationId: $convId
                );
            }
            // Finalizar: guardar todo
            $collectedData['_phase'] = 'done';
            $validationResult = $this->validator->validate($collectedData, $intent);
            if ($validationResult->failed()) {
                $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 1]);
                return new AgentResponse(
                    status: 'clarify',
                    message: $this->buildClarifyMessage($validationResult->errors),
                    data: $collectedData,
                    suggestions: [],
                    conversationId: $convId
                );
            }
            $saveResult = $this->confirmAndSave($convId, $validationResult->data, $intent);
            if ($saveResult['success']) {
                $this->clearConversationState($convId);
                return new AgentResponse(
                    status: 'saved',
                    message: $saveResult['message'],
                    data: $saveResult['data'] ?? null,
                    suggestions: ['Crear otra parte', 'Volver al menú'],
                    conversationId: $convId
                );
            }
            return new AgentResponse(
                status: 'clarify',
                message: "No pude guardar: {$saveResult['message']}. ¿Querés corregir algún dato?",
                data: $collectedData,
                suggestions: ['Reintentar', 'Cancelar'],
                conversationId: $convId
            );
        }

        $field = $next['field'];

        // Fase: campos de variante
        if ($phase === 'variante' || $phase === 'otra_variante') {
            return $this->processVarianteField($convId, $userInput, $intent, $collectedData, $step, $next, $fieldValue, $fieldLabel);
        }

        // Fase: campos de parte
        // Use fieldValue for lookup fields, userInput otherwise
        $effectiveInput = ($fieldValue !== null && $this->isLookupField($field)) ? $fieldValue : $userInput;
        $isSkip = ($effectiveInput === '(omitir)' || trim($effectiveInput) === '');
        if ($isSkip) {
            $defaultValue = $this->getDefaultForField($field, $collectedData);
            if ($defaultValue !== null) {
                $collectedData[$field] = $defaultValue;
                $collectedData = $this->propagateLabels($field, $defaultValue, $collectedData);
                $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 1]);

                $displayValue = $this->getDisplayValue($field, $collectedData);
                $nextResponse = $this->askNextField($convId, $collectedData);
                $nextResponse->message = $displayValue . ' ' . $nextResponse->message;
                return $nextResponse;
            }
            // Required field — cannot skip
            $help = $this->buildFieldHelp($field, $intent);
            return new AgentResponse(
                status: 'clarify',
                message: "Este dato es obligatorio. {$help}",
                data: $collectedData,
                suggestions: $next['suggestions'] ?? [],
                conversationId: $convId
            );
        }

        $value = $this->extractFieldValue($field, $effectiveInput, $intent);

        if ($value === null) {
            $help = $this->buildFieldHelp($field, $intent);
            return new AgentResponse(
                status: 'clarify',
                message: "No entendí bien ese dato. {$help}",
                data: $collectedData,
                suggestions: $next['suggestions'] ?? [],
                conversationId: $convId
            );
        }

        // Verificar duplicado de código de parte
        if ($field === 'codigo' && $this->parteCodigoExists((string) $value)) {
            $help = $this->buildFieldHelp($field, $intent);
            return new AgentResponse(
                status: 'clarify',
                message: "El código \"{$value}\" ya existe en otra parte. Por favor, elegí un código diferente. {$help}",
                data: $collectedData,
                suggestions: $next['suggestions'] ?? [],
                conversationId: $convId
            );
        }

        $collectedData[$field] = $value;
        if ($fieldLabel !== null && $this->isLookupField($field)) {
            $collectedData['_label_' . $field] = $fieldLabel;
        }
        $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 1]);

        $nextResponse = $this->askNextField($convId, $collectedData);
        if ($fieldLabel !== null && $this->isLookupField($field)) {
            $nextResponse->message = '✓ ' . $fieldLabel . '. ' . $nextResponse->message;
        }
        return $nextResponse;
    }

    /**
     * Procesar un campo de variante.
     * Campos opcionales con Enter vacío reciben valor por defecto.
     */
    private function processVarianteField(
        string $convId,
        string $userInput,
        string $intent,
        array $collectedData,
        int $step,
        array $next,
        ?string $fieldValue = null,
        ?string $fieldLabel = null
    ): AgentResponse {
        $field = $next['field'];
        $current = $collectedData['_current_variante'] ?? [];
        $effectiveInput = ($fieldValue !== null && $this->isLookupField($field)) ? $fieldValue : $userInput;
        $isSkip = ($effectiveInput === '(omitir)' || trim($effectiveInput) === '');

        // Handle skip for optional variante fields
        if ($isSkip && $field !== 'codigo_variante') {
            $defaultValue = $this->getDefaultForField($field, $collectedData);
            if ($defaultValue !== null) {
                $current[$field] = $defaultValue;
                $collectedData = $this->propagateLabels($field, $defaultValue, $collectedData);
                if (isset($collectedData['_label_' . $field])) {
                    $current['_label_' . $field] = $collectedData['_label_' . $field];
                }
            }
            // If null, required field — cannot skip
            if ($defaultValue === null) {
                $help = $this->buildFieldHelp($field, $intent);
                return new AgentResponse(
                    status: 'clarify',
                    message: "Este dato es obligatorio. {$help}",
                    data: $collectedData,
                    suggestions: $next['suggestions'] ?? [],
                    conversationId: $convId
                );
            }
            $collectedData['_current_variante'] = $current;
            $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 1]);

            // Check if variante is complete
            $nextField = $this->promptBuilder->nextMissingField($intent, $collectedData);
            if ($nextField === null || ($nextField['field'] ?? '') === 'add_more_variante') {
                $variantes = $collectedData['variantes'] ?? [];
                $variantes[] = $current;
                $collectedData['variantes'] = $variantes;
                $collectedData['_current_variante'] = [];
                $collectedData['_phase'] = 'ask_more_variante';
                $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 2]);

                return new AgentResponse(
                    status: 'clarify',
                    message: 'Variante completada. ¿Querés agregar otra variante o finalizar?',
                    data: $collectedData,
                    suggestions: [['label' => 'Agregar otra variante', 'value' => 'si'], ['label' => 'Finalizar', 'value' => 'no']],
                    conversationId: $convId
                );
            }

            return $this->askNextField($convId, $collectedData, $intent);
        }

        if ($field === 'codigo_variante') {
            $value = $this->extractCode(trim($userInput));
            if ($value === null) $value = strtoupper(trim($userInput));

            // Verificar duplicado de código de variante
            if ($this->varianteCodigoExists($value)) {
                $help = $this->buildFieldHelp($field, 'create_part');
                $suggested = strtoupper(($collectedData['codigo'] ?? 'P')) . '-' . str_pad((string)(count($collectedData['variantes'] ?? []) + 1), 2, '0', STR_PAD_LEFT);
                return new AgentResponse(
                    status: 'clarify',
                    message: "El código de variante \"{$value}\" ya existe. Por favor, elegí un código diferente. {$help}",
                    data: $collectedData,
                    suggestions: [['label' => $suggested, 'value' => $suggested]],
                    conversationId: $convId
                );
            }

            $current['codigo_variante'] = $value;
        $collectedData['_current_variante'] = $current;
        $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 1]);

        // Check if all variante fields are done → push completed variante and ask "add more?"
        $nextField = $this->promptBuilder->nextMissingField($intent, $collectedData);

        if ($nextField === null || ($nextField['field'] ?? '') === 'add_more_variante') {
            $variantes = $collectedData['variantes'] ?? [];
            $variantes[] = $current;
            $collectedData['variantes'] = $variantes;
            $collectedData['_current_variante'] = [];
            $collectedData['_phase'] = 'ask_more_variante';
            $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 2]);

            return new AgentResponse(
                status: 'clarify',
                message: 'Variante completada. ¿Querés agregar otra variante o finalizar?',
                data: $collectedData,
                suggestions: [['label' => 'Agregar otra variante', 'value' => 'si'], ['label' => 'Finalizar', 'value' => 'no']],
                conversationId: $convId
            );
        }

        $nextResponse = $this->askNextField($convId, $collectedData, $intent);
        if ($fieldLabel !== null && $this->isLookupField($field)) {
            $nextResponse->message = '✓ ' . $fieldLabel . '. ' . $nextResponse->message;
        }
        return $nextResponse;
        }

        if ($field === 'detalle_variante') {
            $value = trim($effectiveInput);
            if ($value === '') $value = $collectedData['detalle'] ?? $collectedData['description'] ?? '';
            $current['detalle_variante'] = $value;
        } elseif ($field === 'estado') {
            $value = trim($effectiveInput);
            $current['estado'] = ($value !== '') ? $value : 'activa';
            if ($fieldLabel !== null) {
                $current['_label_estado'] = $fieldLabel;
            }
        } elseif ($field === 'lote_minimo') {
            $value = $this->extractPositiveNumber($effectiveInput);
            $current['lote_minimo'] = $value ?? 1;
        } elseif ($field === 'punto_pedido') {
            $value = $this->extractPositiveNumber($effectiveInput);
            $current['punto_pedido'] = $value ?? 0;
        } elseif ($field === 'peso') {
            $value = $this->extractPositiveNumber($effectiveInput);
            if ($value !== null) {
                $current['peso'] = $value;
            }
        } elseif (str_starts_with($field, 'ubicacion_')) {
            $value = trim($effectiveInput);
            if ($value !== '') {
                $current[$field] = $value;
            }
        } else {
            $value = trim($effectiveInput);
            if ($value !== '') {
                $current[$field] = $value;
            }
        }

        $collectedData['_current_variante'] = $current;
        $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 1]);

        // Check if all variante fields are done → push completed variante and ask "add more?"
        $nextField = $this->promptBuilder->nextMissingField($intent, $collectedData);

        if ($nextField === null || ($nextField['field'] ?? '') === 'add_more_variante') {
            $variantes = $collectedData['variantes'] ?? [];
            $variantes[] = $current;
            $collectedData['variantes'] = $variantes;
            $collectedData['_current_variante'] = [];
            $collectedData['_phase'] = 'ask_more_variante';
            $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 2]);

            return new AgentResponse(
                status: 'clarify',
                message: 'Variante completada. ¿Querés agregar otra variante o finalizar?',
                data: $collectedData,
                suggestions: [['label' => 'Agregar otra variante', 'value' => 'si'], ['label' => 'Finalizar', 'value' => 'no']],
                conversationId: $convId
            );
        }

        return $this->askNextField($convId, $collectedData);
    }

    /**
     * Helper: preguntar el siguiente campo faltante con suggestions y lookup.
     */
    private function askNextField(string $convId, array $collectedData, string $intent = ''): AgentResponse
    {
        if ($intent === '') {
            $intent = $collectedData['_intent'] ?? 'create_part';
        }
        $nextField = $this->promptBuilder->nextMissingField($intent, $collectedData);

        if ($nextField === null) {
            return new AgentResponse(
                status: 'preview',
                message: 'Datos completos. ¿Confirmamos y guardamos?',
                data: $collectedData,
                suggestions: ['Confirmar y guardar', 'Corregir'],
                conversationId: $convId
            );
        }

        // Transición de parte a variante
        if (($nextField['field'] ?? '') === 'codigo_variante' && ($collectedData['_phase'] ?? 'parte') === 'parte') {
            $collectedData['_phase'] = 'variante';
            $collectedData['_current_variante'] = [];
            $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => ($collectedData['_step'] ?? 0) + 1]);
        }

        $field = $nextField['field'] ?? '';
        $suggestions = $nextField['suggestions'] ?? [];
        $lookup = $nextField['lookup'] ?? null;
        if (empty($suggestions) && $lookup !== null) {
            if ($lookup === 'unidades_medida_all') {
                $suggestions = [['lookup' => 'unidades_medida_all']];
            } else {
                $suggestions = $this->fetchLookupSuggestions($lookup);
            }
        }

        $message = $nextField['message'];
        $calculatedValue = $this->calculateDimension($field, $collectedData);
        if ($calculatedValue !== null && $calculatedValue > 0) {
            $unit = $field === 'superficie' ? 'm²' : 'cm³';
            $message .= " (calculado: {$calculatedValue} {$unit})";
            if (empty($suggestions) || !isset($suggestions[0]['lookup'])) {
                array_unshift($suggestions, ['label' => (string) $calculatedValue, 'value' => (string) $calculatedValue]);
            }
        }

        return new AgentResponse(
            status: 'clarify',
            message: $message,
            data: $collectedData,
            suggestions: $suggestions,
            conversationId: $convId
        );
    }

    /**
     * Flujo guiado especial para BOM (componentes múltiples).
     */
    private function processBomStep(string $convId, string $userInput, string $intent, array $collectedData, int $step, array $next): AgentResponse
    {
        $field = $next['field'];
        $components = $collectedData['components'] ?? [];

        // Campo: pieza padre
        if ($field === 'parent_part') {
            $value = $this->extractFieldValue('parent_part', $userInput, $intent);
            if ($value === null) {
                return new AgentResponse(
                    status: 'clarify',
                    message: "No entendí el código. Indicá el código de la pieza padre.",
                    data: $collectedData,
                    suggestions: [],
                    conversationId: $convId
                );
            }
            $collectedData['parent_part'] = $value;
            $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 1]);
            $nextField = $this->promptBuilder->nextMissingField($intent, $collectedData);
            return new AgentResponse(
                status: 'clarify',
                message: $nextField['message'] ?? "Indicá el código del primer componente.",
                data: $collectedData,
                suggestions: $nextField['suggestions'] ?? [],
                conversationId: $convId
            );
        }

        // Campo: código de componente
        if ($field === 'component_code') {
            $value = strtoupper(trim($userInput));
            $collectedData['_current_component'] = ['code' => $value];
            $collectedData['_bom_step'] = 'qty';
            $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 1]);
            return new AgentResponse(
                status: 'clarify',
                message: "¿Cuál es la cantidad necesaria para el componente {$value}?",
                data: $collectedData,
                suggestions: [],
                conversationId: $convId
            );
        }

        return new AgentResponse(
            status: 'clarify',
            message: $next['message'],
            data: $collectedData,
            suggestions: $next['suggestions'] ?? [],
            conversationId: $convId
        );
    }

    // ─── Extractores de valores ────────────────────────────────────────

    private function extractFieldValue(string $field, string $userInput, string $intent): mixed
    {
        $input = trim($userInput);

        return match ($field) {
            'codigo', 'code', 'parent_part' => $this->extractCode($input),
            'detalle', 'description' => $input,
            'detalle_variante' => $input,
            'razon_social' => $input,
            'identificacion_tributaria' => $this->extractCuit($input) ?? $input,
            'contacto_email' => $this->extractEmail($input),
            'contacto_telefono' => $input,
            'direccion' => $input,
            'id_tipo', 'id_grupo', 'id_um_compra', 'id_um_uso' => $input,
            'largo_alto', 'ancho', 'espesor_profundidad' => $this->extractPositiveNumber($input),
            'superficie', 'volumen' => $this->extractPositiveNumber($input),
            'stock_seguridad' => $this->extractPositiveNumber($input),
            'punto_pedido' => $this->extractPositiveNumber($input),
            'lote_minimo' => $this->extractPositiveNumber($input),
            'peso' => $this->extractPositiveNumber($input),
            'estado' => $input,
            'ubicacion_cuerpo', 'ubicacion_pasillo', 'ubicacion_estante' => $input,
            'codigo_variante' => $this->extractCode($input) ?? strtoupper($input),
            'component_code' => strtoupper($input),
            'component_qty' => $this->extractPositiveNumber($input),
            'component_um' => $input,
            default => $input,
        };
    }

    private function extractCode(string $input): ?string
    {
        if (preg_match('/\b([A-Za-z0-9\-]{1,50})\b/', trim($input), $m)) {
            return strtoupper($m[1]);
        }
        return trim($input) !== '' ? strtoupper(substr(trim($input), 0, 50)) : null;
    }

    private function extractPositiveNumber(string $input): ?float
    {
        if (preg_match('/[0-9]+(?:[.,][0-9]+)?/', str_replace(',', '.', $input), $m)) {
            return (float) $m[0];
        }
        return null;
    }

    private function extractCuit(string $input): ?string
    {
        if (preg_match('/\b(\d{2}-?\d{8}-?\d{1})\b/', $input, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractEmail(string $input): ?string
    {
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $input, $m)) {
            return $m[0];
        }
         return null;
    }

    private function propagateLabels(string $field, mixed $value, array $collectedData): array
    {
        if ($field === 'id_um_uso' && isset($collectedData['_label_id_um_compra'])) {
            $collectedData['_label_id_um_uso'] = $collectedData['_label_id_um_compra'];
        }
        if (($field === 'superficie' || $field === 'volumen') && $value !== null && $value > 0) {
            $unit = $field === 'superficie' ? 'm²' : 'cm³';
            $collectedData['_label_' . $field] = number_format((float) $value, 6, ',', '.') . ' ' . $unit;
        }
        return $collectedData;
    }

    private function getDisplayValue(string $field, array $collectedData): string
    {
        $label = $collectedData['_label_' . $field] ?? null;
        if ($label !== null) {
            return '✓ ' . $label . '.';
        }
        $value = $collectedData[$field] ?? null;
        if ($value !== null) {
            if ($field === 'superficie') {
                return '✓ ' . number_format((float) $value, 6, ',', '.') . ' m².';
            }
            if ($field === 'volumen') {
                return '✓ ' . number_format((float) $value, 4, ',', '.') . ' cm³.';
            }
            if (is_numeric($value) && !$this->isLookupField($field)) {
                return '✓ ' . $value . '.';
            }
        }
        return '✓ Valor asignado.';
    }

    private function getDefaultForField(string $field, array $collectedData): mixed
    {
        return match ($field) {
            'id_um_uso' => $collectedData['id_um_compra'] ?? null,
            'largo_alto', 'ancho', 'espesor_profundidad', 'peso' => 0,
            'superficie', 'volumen' => $this->calculateDimension($field, $collectedData),
            'lote_minimo' => 1,
            'punto_pedido' => 0,
            'stock_seguridad' => 0,
            'detalle_variante' => $collectedData['detalle'] ?? $collectedData['description'] ?? '',
            'estado' => 'activa',
            'ubicacion_cuerpo', 'ubicacion_pasillo', 'ubicacion_estante' => '',
            'identificacion_tributaria', 'contacto_email', 'contacto_telefono', 'direccion' => '',
            'component_um' => '',
            default => null,
        };
    }

    private function calculateDimension(string $field, array $collectedData): ?float
    {
        $largo = (float) ($collectedData['largo_alto'] ?? 0);
        $ancho = (float) ($collectedData['ancho'] ?? 0);
        $espesor = (float) ($collectedData['espesor_profundidad'] ?? 0);

        if ($field === 'superficie' && $largo > 0 && $ancho > 0) {
            return round(($largo / 1000) * ($ancho / 1000), 6);
        }
        if ($field === 'volumen' && $largo > 0 && $ancho > 0 && $espesor > 0) {
            return round(($largo / 10) * ($ancho / 10) * ($espesor / 10), 3);
        }
        return null;
    }

    private function buildFieldHelp(string $field, string $intent): string
    {
        $helps = [
            'codigo' => 'Indicá el código de la parte, por ejemplo: P-001.',
            'code' => 'Indicá el código, por ejemplo: P-001.',
            'detalle' => 'Indicá una breve descripción.',
            'description' => 'Indicá una breve descripción.',
            'id_tipo' => 'Indicá el tipo de parte. Elegí una de las opciones.',
            'id_grupo' => 'Indicá el grupo. Elegí una de las opciones.',
            'id_um_compra' => 'Indicá la unidad de medida de compra. Elegí una de las opciones.',
            'id_um_uso' => 'Indicá la unidad de medida de uso. Elegí una de las opciones.',
            'largo_alto' => 'Indicá el largo/alto en número (ej: 150).',
            'ancho' => 'Indicá el ancho en número (ej: 50).',
            'espesor_profundidad' => 'Indicá el espesor/profundidad en número (ej: 2).',
            'codigo_variante' => 'Indicá el código de la variante (ej: P-001-01).',
            'detalle_variante' => 'Indicá la descripción de la variante.',
            'estado' => 'Indicá el estado de la variante.',
            'lote_minimo' => 'Indicá el lote mínimo como número entero.',
            'punto_pedido' => 'Indicá el punto de pedido como número.',
            'peso' => 'Indicá el peso unitario como número.',
            'ubicacion_cuerpo' => 'Indicá el cuerpo de ubicación física.',
            'ubicacion_pasillo' => 'Indicá el pasillo de ubicación física.',
            'ubicacion_estante' => 'Indicá el estante de ubicación física.',
            'razon_social' => 'Indicá la razón social o nombre del proveedor.',
            'identificacion_tributaria' => 'Indicá el CUIT (formato XX-XXXXXXXX-X) o número de identificación.',
            'contacto_email' => 'Indicá un email válido.',
            'contacto_telefono' => 'Indicá el teléfono.',
            'direccion' => 'Indicá la dirección.',
            'stock_seguridad' => 'Indicá el stock de seguridad como número.',
            'parent_part' => 'Indicá el código de la pieza padre.',
            'component_code' => 'Indicá el código del componente.',
            'component_qty' => 'Indicá la cantidad necesaria.',
        ];
        return $helps[$field] ?? 'Intentá de nuevo con un valor válido.';
    }

    // ─── Obtener sugerencias de lookup desde la BD ────────────────────

    /**
     * Obtener sugerencias de lookup desde la BD del tenant.
     */
    public function getLookupData(string $lookupType): array
    {
        $db = $this->getDb();
        if (!$db) return [];

        return match ($lookupType) {
            'tipos_partes' => $this->fetchTiposPartes($db),
            'grupos_partes' => $this->fetchGruposPartes($db),
            'unidades_medida_all' => $this->fetchUnidadesMedida($db),
            'unidades_medida_tipos' => $this->fetchUnidadesMedidaTipos($db),
            'unidades_medida_longitud' => $this->fetchUnidadesMedidaByTipo($db, 'longitud'),
            'unidades_medida_masa' => $this->fetchUnidadesMedidaByTipo($db, 'masa'),
            'unidades_medida_volumen' => $this->fetchUnidadesMedidaByTipo($db, 'volumen'),
            'unidades_medida_superficie' => $this->fetchUnidadesMedidaByTipo($db, 'superficie'),
            'unidades_medida_unidad' => $this->fetchUnidadesMedidaByTipo($db, 'unidad'),
            'estados_variante' => self::ESTADOS_VARIANTE,
            default => [],
        };
    }

    private const ESTADOS_VARIANTE = [
        ['value' => 'activa', 'label' => 'Activa'],
        ['value' => 'desarrollo', 'label' => 'En desarrollo'],
        ['value' => 'obsoleta', 'label' => 'Obsoleta'],
        ['value' => 'descontinuada', 'label' => 'Descontinuada'],
    ];

    private function fetchTiposPartes(\PDO $db): array
    {
        try {
            $stmt = $db->query("SELECT id, codigo, nombre FROM tipos_partes WHERE activo = true ORDER BY orden, nombre");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return array_map(fn($r) => ['value' => (int) $r['id'], 'label' => $r['nombre']], $rows);
        } catch (\Throwable $e) {
            error_log("fetchTiposPartes failed: " . $e->getMessage());
            return [
                ['value' => 1, 'label' => 'Pieza'],
                ['value' => 2, 'label' => 'Materia Prima'],
                ['value' => 3, 'label' => 'Producto Terminado'],
            ];
        }
    }

    private function fetchGruposPartes(\PDO $db): array
    {
        try {
            $stmt = $db->query("SELECT id, nombre FROM grupos_partes WHERE activo = true ORDER BY nombre");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return array_map(fn($r) => ['value' => (int) $r['id'], 'label' => $r['nombre']], $rows);
        } catch (\Throwable $e) {
            error_log("fetchGruposPartes failed: " . $e->getMessage());
            return [
                ['value' => 1, 'label' => 'General'],
                ['value' => 2, 'label' => 'Mecánica'],
                ['value' => 3, 'label' => 'Eléctrica'],
            ];
        }
    }

    private function fetchUnidadesMedida(\PDO $db): array
    {
        try {
            $stmt = $db->query("SELECT id, simbolo, unidad, tipo FROM unidades_medida WHERE activo = true ORDER BY tipo, simbolo");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return array_map(fn($r) => [
                'value' => (int) $r['id'],
                'label' => "{$r['simbolo']} - {$r['unidad']}",
                'tipo' => $r['tipo'],
            ], $rows);
        } catch (\Throwable $e) {
            error_log("fetchUnidadesMedida failed: " . $e->getMessage());
            return [
                ['value' => 1, 'label' => 'u - Unidad', 'tipo' => 'unidad'],
                ['value' => 2, 'label' => 'kg - Kilogramo', 'tipo' => 'masa'],
                ['value' => 3, 'label' => 'm - Metro', 'tipo' => 'longitud'],
                ['value' => 4, 'label' => 'l - Litro', 'tipo' => 'volumen'],
                ['value' => 5, 'label' => 'g - Gramo', 'tipo' => 'masa'],
            ];
        }
    }

    private function fetchUnidadesMedidaTipos(\PDO $db): array
    {
        try {
            $stmt = $db->query("SELECT DISTINCT tipo FROM unidades_medida WHERE activo = true ORDER BY tipo");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $labels = [
                'unidad' => 'Unidad',
                'masa' => 'Masa',
                'longitud' => 'Longitud',
                'volumen' => 'Volumen',
                'superficie' => 'Superficie',
            ];
            return array_map(fn($r) => [
                'value' => $r['tipo'],
                'label' => $labels[$r['tipo']] ?? ucfirst($r['tipo']),
                'lookup' => 'unidades_medida_' . $r['tipo'],
            ], $rows);
        } catch (\Throwable $e) {
            error_log("fetchUnidadesMedidaTipos failed: " . $e->getMessage());
            return [
                ['value' => 'unidad', 'label' => 'Unidad', 'lookup' => 'unidades_medida_unidad'],
                ['value' => 'masa', 'label' => 'Masa', 'lookup' => 'unidades_medida_masa'],
                ['value' => 'longitud', 'label' => 'Longitud', 'lookup' => 'unidades_medida_longitud'],
                ['value' => 'volumen', 'label' => 'Volumen', 'lookup' => 'unidades_medida_volumen'],
                ['value' => 'superficie', 'label' => 'Superficie', 'lookup' => 'unidades_medida_superficie'],
            ];
        }
    }

    private function fetchUnidadesMedidaByTipo(\PDO $db, string $tipo): array
    {
        try {
            $stmt = $db->prepare("SELECT id, simbolo, unidad, tipo FROM unidades_medida WHERE activo = true AND tipo = ? ORDER BY simbolo");
            $stmt->execute([$tipo]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return array_map(fn($r) => [
                'value' => (int) $r['id'],
                'label' => "{$r['simbolo']} - {$r['unidad']}",
                'tipo' => $r['tipo'],
            ], $rows);
        } catch (\Throwable $e) {
            error_log("fetchUnidadesMedidaByTipo failed: " . $e->getMessage());
            $fallbacks = [
                'longitud' => [
                    ['value' => 3, 'label' => 'm - Metro', 'tipo' => 'longitud'],
                    ['value' => 6, 'label' => 'mm - Milímetro', 'tipo' => 'longitud'],
                    ['value' => 7, 'label' => 'cm - Centímetro', 'tipo' => 'longitud'],
                ],
                'masa' => [
                    ['value' => 2, 'label' => 'kg - Kilogramo', 'tipo' => 'masa'],
                    ['value' => 5, 'label' => 'g - Gramo', 'tipo' => 'masa'],
                ],
            ];
            return $fallbacks[$tipo] ?? [];
        }
    }

    private function fetchLookupSuggestions(string $lookup): array
    {
        $items = $this->getLookupData($lookup);
        return array_map(fn($item) => [
            'label' => $item['label'],
            'value' => (string) $item['value'],
        ], $items);
    }

    // ─── Guardado en BD ────────────────────────────────────────────────

    /**
     * Guardar Parte (tabla: partes + variantes)
     */
    private function savePart(array $data): array
    {
        $db = $this->getDb();
        if (!$db) return ['success' => false, 'message' => 'Base de datos no disponible'];

        $idTipo = (int) ($data['id_tipo'] ?? 1);
        $idGrupo = (int) ($data['id_grupo'] ?? 1);
        $idUmCompra = !empty($data['id_um_compra']) ? (int) $data['id_um_compra'] : null;
        $idUmUso = !empty($data['id_um_uso']) ? (int) $data['id_um_uso'] : null;
        $factorConversion = isset($data['factor_conversion']) ? (float) $data['factor_conversion'] : null;
        if ($factorConversion === null && $idUmCompra && $idUmUso && $idUmCompra === $idUmUso) {
            $factorConversion = 1.0;
        }
        $largoAlto = isset($data['largo_alto']) && $data['largo_alto'] !== '' && $data['largo_alto'] !== 0 ? (float) $data['largo_alto'] : null;
        $ancho = isset($data['ancho']) && $data['ancho'] !== '' && $data['ancho'] !== 0 ? (float) $data['ancho'] : null;
        $espesorProfundidad = isset($data['espesor_profundidad']) && $data['espesor_profundidad'] !== '' && $data['espesor_profundidad'] !== 0 ? (float) $data['espesor_profundidad'] : null;

        // Default UM for dimensions if not specified
        $idUmLargoAlto = $this->resolveDefaultUmId($db, 'longitud', 'mm');
        $idUmAncho = $idUmLargoAlto;
        $idUmEspesor = $idUmLargoAlto;
        $superficie = null;
        $idUmSuperficie = $this->resolveDefaultUmId($db, 'superficie', 'm²');
        $volumen = null;
        $idUmVolumen = $this->resolveDefaultUmId($db, 'volumen', 'cm³');

        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                "INSERT INTO partes (codigo, id_tipo, id_grupo, detalle, id_um_compra, id_um_uso, factor_conversion, largo_alto, id_um_largo_alto, ancho, id_um_ancho, espesor_profundidad, id_um_espesor, superficie, id_um_superficie, volumen, id_um_volumen, activo, fecha_creacion)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, true, NOW()) RETURNING id"
            );
            $stmt->execute([
                strtoupper($data['codigo'] ?? $data['code'] ?? ''),
                $idTipo,
                $idGrupo,
                $data['detalle'] ?? $data['description'] ?? '',
                $idUmCompra,
                $idUmUso,
                $factorConversion,
                $largoAlto,
                $largoAlto !== null ? $idUmLargoAlto : null,
                $ancho,
                $ancho !== null ? $idUmAncho : null,
                $espesorProfundidad,
                $espesorProfundidad !== null ? $idUmEspesor : null,
                $superficie,
                $superficie !== null ? $idUmSuperficie : null,
                $volumen,
                $volumen !== null ? $idUmVolumen : null,
            ]);
            $parteId = (int) $stmt->fetchColumn();

            $variantes = $data['variantes'] ?? [];
            if (empty($variantes)) {
                $variantes = [['codigo_variante' => strtoupper($data['codigo'] ?? $data['code'] ?? 'P') . '-01', 'detalle_variante' => $data['detalle'] ?? $data['description'] ?? '']];
            }

            $varianteIds = [];
            foreach ($variantes as $var) {
                $codigoVar = $var['codigo_variante'] ?? strtoupper(($data['codigo'] ?? $data['code'] ?? 'P')) . '-' . str_pad((string)(count($varianteIds) + 1), 2, '0', STR_PAD_LEFT);
                $detalleVar = $var['detalle_variante'] ?? $var['detalle'] ?? $data['detalle'] ?? $data['description'] ?? '';
                $estado = $var['estado'] ?? 'activa';
                $loteMinimo = isset($var['lote_minimo']) ? (float) $var['lote_minimo'] : 1;
                $puntoPedido = isset($var['punto_pedido']) ? (float) $var['punto_pedido'] : 0;
                $stockSeguridad = isset($var['stock_seguridad']) ? (float) $var['stock_seguridad'] : 0;
                $peso = isset($var['peso']) && $var['peso'] !== '' ? (float) $var['peso'] : null;
                $idUmPeso = $peso !== null ? $this->resolveDefaultUmId($db, 'masa', 'kg') : null;
                $ubicacionCuerpo = $var['ubicacion_cuerpo'] ?? null;
                $ubicacionPasillo = $var['ubicacion_pasillo'] ?? null;
                $ubicacionEstante = $var['ubicacion_estante'] ?? null;

                $stmtVar = $db->prepare(
                    "INSERT INTO variantes (id_parte, codigo_variante, detalle, estado, lote_minimo, punto_pedido, stock_seguridad, stock_actual, peso, id_um_peso, ubicacion_cuerpo, ubicacion_pasillo, ubicacion_estante, fecha_creacion)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW()) RETURNING id"
                );
                $stmtVar->execute([
                    $parteId,
                    $codigoVar,
                    $detalleVar,
                    $estado,
                    $loteMinimo,
                    $puntoPedido,
                    $stockSeguridad,
                    $peso,
                    $idUmPeso,
                    $ubicacionCuerpo,
                    $ubicacionPasillo,
                    $ubicacionEstante,
                ]);
                $varianteIds[] = (int) $stmtVar->fetchColumn();
            }

            $db->commit();

            $code = strtoupper($data['codigo'] ?? $data['code'] ?? '');
            return [
                'success' => true,
                'message' => "Parte '{$code}' creada correctamente con " . count($varianteIds) . " variante(s) (ID: {$parteId})",
                'data' => ['parte_id' => $parteId, 'variante_ids' => $varianteIds, 'code' => $code],
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log("savePart error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al guardar la parte: ' . $e->getMessage()];
        }
    }

    /**
     * Guardar Proveedor (tabla: entidades, tipo='PROVEEDOR')
     */
    private function saveSupplier(array $data): array
    {
        $db = $this->getDb();
        if (!$db) return ['success' => false, 'message' => 'Base de datos no disponible'];

        $stmt = $db->prepare(
            "INSERT INTO entidades (razon_social, tipo, identificacion_tributaria, contacto_email, contacto_telefono, direccion, created_at, updated_at)
             VALUES (?, 'PROVEEDOR', ?, ?, ?, ?, NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([
            $data['razon_social'],
            $data['identificacion_tributaria'] ?? null,
            $data['contacto_email'] ?? null,
            $data['contacto_telefono'] ?? null,
            $data['direccion'] ?? null,
        ]);
        $id = (int) $stmt->fetchColumn();

        return [
            'success' => true,
            'message' => "Proveedor '{$data['razon_social']}' creado correctamente (ID: {$id})",
            'data' => ['id' => $id, 'razon_social' => $data['razon_social']],
        ];
    }

    /**
     * Guardar BOM (tablas: bom_cabecera + bom_detalle)
     */
    private function saveBom(array $data): array
    {
        $db = $this->getDb();
        if (!$db) return ['success' => false, 'message' => 'Base de datos no disponible'];

        $stmtFind = $db->prepare(
            "SELECT v.id FROM variantes v
             INNER JOIN partes p ON p.id = v.id_parte
             WHERE p.codigo = ? OR v.codigo_variante = ?
             LIMIT 1"
        );
        $stmtFind->execute([$data['parent_part'], $data['parent_part']]);
        $variantePadreId = $stmtFind->fetchColumn();

        if (!$variantePadreId) {
            return [
                'success' => false,
                'message' => "No se encontró la pieza padre '{$data['parent_part']}'. Creéla primero.",
            ];
        }

        $stmt = $db->prepare(
            "INSERT INTO bom_cabecera (variante_padre_id, version, activa, fecha_efectiva, created_at, updated_at)
             VALUES (?, '1.0', true, CURRENT_DATE, NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([$variantePadreId]);
        $bomId = (int) $stmt->fetchColumn();

        $components = $data['components'] ?? [];
        $componentIds = [];
        foreach ($components as $comp) {
            $stmtComp = $db->prepare(
                "SELECT v.id FROM variantes v
                 INNER JOIN partes p ON p.id = v.id_parte
                 WHERE p.codigo = ? OR v.codigo_variante = ?
                 LIMIT 1"
            );
            $stmtComp->execute([$comp['code'] ?? $comp['component_code'] ?? '', $comp['code'] ?? $comp['component_code'] ?? '']);
            $varianteCompId = $stmtComp->fetchColumn();

            if (!$varianteCompId) {
                continue;
            }

            $stmtDet = $db->prepare(
                "INSERT INTO bom_detalle (bom_id, variante_componente_id, cantidad_necesaria, unidad_medida_id, created_at)
                 VALUES (?, ?, ?, 1, NOW()) RETURNING id"
            );
            $stmtDet->execute([
                $bomId,
                $varianteCompId,
                $comp['quantity'] ?? $comp['cantidad'] ?? 1,
            ]);
            $componentIds[] = (int) $stmtDet->fetchColumn();
        }

        return [
            'success' => true,
            'message' => "BOM creado para '{$data['parent_part']}' (BOM ID: {$bomId}, componentes: " . count($componentIds) . ")",
            'data' => ['bom_id' => $bomId, 'parent_part' => $data['parent_part'], 'components_count' => count($componentIds)],
        ];
    }

    // ─── Utilidades internas ──────────────────────────────────────────

    private function getDb(): ?\PDO
    {
        return $this->repository ? $this->repository->getDb() : null;
    }

    private function parteCodigoExists(string $codigo): bool
    {
        $db = $this->getDb();
        if (!$db) return false;

        try {
            $stmt = $db->prepare("SELECT 1 FROM partes WHERE codigo = ? LIMIT 1");
            $stmt->execute([strtoupper($codigo)]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            error_log("parteCodigoExists failed: " . $e->getMessage());
            return false;
        }
    }

    private function varianteCodigoExists(string $codigoVariante): bool
    {
        $db = $this->getDb();
        if (!$db) return false;

        try {
            $stmt = $db->prepare("SELECT 1 FROM variantes WHERE codigo_variante = ? LIMIT 1");
            $stmt->execute([strtoupper($codigoVariante)]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            error_log("varianteCodigoExists failed: " . $e->getMessage());
            return false;
        }
    }

    private function resolveDefaultUmId(\PDO $db, string $tipo, string $simboloPreferido): ?int
    {
        try {
            $stmt = $db->prepare("SELECT id FROM unidades_medida WHERE tipo = ? AND simbolo = ? AND activo = true LIMIT 1");
            $stmt->execute([$tipo, $simboloPreferido]);
            $id = $stmt->fetchColumn();
            if ($id !== false) return (int) $id;

            $stmt = $db->prepare("SELECT id FROM unidades_medida WHERE tipo = ? AND activo = true ORDER BY id LIMIT 1");
            $stmt->execute([$tipo]);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int) $id : null;
        } catch (\Throwable $e) {
            error_log("resolveDefaultUmId failed: " . $e->getMessage());
            return null;
        }
    }

    private function generatePromptHash(array $messages): string
    {
        return hash('sha256', json_encode($messages, JSON_UNESCAPED_UNICODE));
    }

    private function getCachedResponse(string $promptHash): ?array
    {
        return $this->repository ? $this->repository->getCachedResponse($promptHash) : null;
    }

    private function setCachedResponse(string $promptHash, array $response): void
    {
        if ($this->repository) {
            $this->repository->setCachedResponse($promptHash, $response);
        }
    }

    private function invalidateCachedResponse(string $promptHash): void
    {
        if ($this->repository) {
            $this->repository->invalidateCachedResponse($promptHash);
        }
    }

    private function buildClarifyMessage(array $errors): string
    {
        if (empty($errors)) {
            return 'Faltan algunos datos. Completá la información solicitada.';
        }
        return "Faltan datos o hay errores: " . implode(", ", $errors) . ". Corregí o completá la información.";
    }

    private function getConversationState(string $convId): array
    {
        if (!$this->conversationService) return [];
        return $this->conversationService->getState($convId);
    }

    private function saveConversationState(string $convId, array $state): void
    {
        if (!$this->conversationService) return;
        $this->conversationService->saveState($convId, $state);
     }

    public function cancelGuidedConversation(string $convId): void
    {
        $this->clearConversationState($convId);
    }

    private function clearConversationState(string $convId): void
    {
        if (!$this->conversationService) return;
        $this->conversationService->clearState($convId);
    }

    private function logAiCall(
        string $convId,
        array $messages,
        array $response,
        bool $isValid,
        ?array $errors = null,
        string $source = 'ai_call'
    ): void {
        if (!$this->repository) return;

        try {
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
        } catch (\Throwable $e) {
            error_log("AgentService::logAiCall failed (non-critical): " . $e->getMessage());
        }
    }
}