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
    public function processMessage(string $convId, string $userInput): AgentResponse
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
            $welcome = $this->promptBuilder->buildStepByStepMessage($intent, []);
            $this->logAiCall($convId, [], $welcome, true, null, 'guided_welcome');
            return new AgentResponse(
                status: 'clarify',
                message: $welcome['message'],
                data: [],
                suggestions: $welcome['suggestions'],
                conversationId: $convId
            );
        }

        // Continuación de flujo guiado
        if ($this->isGuidedIntent($intent)) {
            return $this->processGuidedStep($convId, $userInput, $intent, $session['data'] ?? [], (int)($session['step'] ?? 1));
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
                'create_material' => $this->saveMaterial($confirmedData),
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
        return in_array($intent, ['create_part', 'create_material', 'create_supplier', 'create_bom'], true);
    }

    private function processGuidedStep(
        string $convId,
        string $userInput,
        string $intent,
        array $collectedData,
        int $step
    ): AgentResponse {
        $next = $this->promptBuilder->nextMissingField($intent, $collectedData);

        // Flujo especial para BOM: componentes múltiples
        if ($intent === 'create_bom' && $next !== null) {
            return $this->processBomStep($convId, $userInput, $intent, $collectedData, $step, $next);
        }

        if ($next !== null) {
            $field = $next['field'];
            $value = $this->extractFieldValue($field, $userInput, $intent);

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
                if (empty($suggestions) && ($next['lookup'] ?? null) !== null) {
                    $suggestions = $this->fetchLookupSuggestions($next['lookup']);
                }
            }

            $this->saveConversationState($convId, ['intent' => $intent, 'data' => $collectedData, 'step' => $step + 1]);

            return new AgentResponse(
                status: 'clarify',
                message: $message,
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
                suggestions: ['Crear otra pieza', 'Volver al menú'],
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
            'code', 'parent_part' => $this->extractCode($input),
            'description' => $input,
            'razon_social' => $input,
            'identificacion_tributaria' => $this->extractCuit($input) ?? $input,
            'contacto_email' => $this->extractEmail($input),
            'contacto_telefono' => $input,
            'direccion' => $input,
            'id_tipo', 'id_grupo', 'id_um_compra', 'id_um_uso' => $input,
            'stock_seguridad' => $this->extractPositiveNumber($input),
            'punto_pedido' => $this->extractPositiveNumber($input),
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

    private function buildFieldHelp(string $field, string $intent): string
    {
        $helps = [
            'code' => 'Indicá el código, por ejemplo: P-001.',
            'description' => 'Indicá una breve descripción.',
            'id_tipo' => 'Indicá el tipo de parte. Elegí una de las opciones.',
            'id_grupo' => 'Indicá el grupo. Elegí una de las opciones.',
            'id_um_compra' => 'Indicá la unidad de medida de compra. Elegí una de las opciones.',
            'id_um_uso' => 'Indicá la unidad de medida de uso. Elegí una de las opciones.',
            'razon_social' => 'Indicá la razón social o nombre del proveedor.',
            'identificacion_tributaria' => 'Indicá el CUIT (formato XX-XXXXXXXX-X) o número de identificación.',
            'contacto_email' => 'Indicá un email válido.',
            'contacto_telefono' => 'Indicá el teléfono.',
            'direccion' => 'Indicá la dirección.',
            'stock_seguridad' => 'Indicá el stock de seguridad como número.',
            'punto_pedido' => 'Indicá el punto de pedido como número.',
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
            default => [],
        };
    }

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
        $factorConversion = isset($data['factor_conversion']) ? (float) $data['factor_conversion'] : 1.0;

        $stmt = $db->prepare(
            "INSERT INTO partes (codigo, id_tipo, id_grupo, detalle, id_um_compra, id_um_uso, factor_conversion, activo, fecha_creacion)
             VALUES (?, ?, ?, ?, ?, ?, ?, true, NOW()) RETURNING id"
        );
        $stmt->execute([
            strtoupper($data['code']),
            $idTipo,
            $idGrupo,
            $data['description'],
            $idUmCompra,
            $idUmUso,
            $factorConversion,
        ]);
        $parteId = (int) $stmt->fetchColumn();

        $stmtVar = $db->prepare(
            "INSERT INTO variantes (id_parte, codigo_variante, detalle, estado, lote_minimo, punto_pedido, stock_seguridad, stock_actual, fecha_creacion)
             VALUES (?, ?, ?, 'activa', 1, 0, 0, 0, NOW()) RETURNING id"
        );
        $stmtVar->execute([
            $parteId,
            strtoupper($data['code']) . '-01',
            $data['description'],
        ]);
        $varianteId = (int) $stmtVar->fetchColumn();

        return [
            'success' => true,
            'message' => "Pieza '{$data['code']}' creada correctamente (ID: {$parteId}, Variante: {$varianteId})",
            'data' => ['parte_id' => $parteId, 'variante_id' => $varianteId, 'code' => strtoupper($data['code'])],
        ];
    }

    /**
     * Guardar Material (misma tabla partes, tipo materia prima)
     */
    private function saveMaterial(array $data): array
    {
        $db = $this->getDb();
        if (!$db) return ['success' => false, 'message' => 'Base de datos no disponible'];

        $idGrupo = (int) ($data['id_grupo'] ?? 1);
        $idUmCompra = !empty($data['id_um_compra']) ? (int) $data['id_um_compra'] : null;
        $idUmUso = !empty($data['id_um_uso']) ? (int) $data['id_um_uso'] : null;
        $factorConversion = isset($data['factor_conversion']) ? (float) $data['factor_conversion'] : 1.0;
        $stockSeguridad = isset($data['stock_seguridad']) ? (float) $data['stock_seguridad'] : 0;
        $puntoPedido = isset($data['punto_pedido']) ? (float) $data['punto_pedido'] : 0;

        $idTipo = 1;
        try {
            $stmtTipo = $db->prepare("SELECT id FROM tipos_partes WHERE codigo IN ('MP','materia_prima','raw_material') ORDER BY id LIMIT 1");
            $stmtTipo->execute();
            $idTipo = (int) ($stmtTipo->fetchColumn() ?: 1);
        } catch (\Throwable $e) { /* fallback */ }

        $stmt = $db->prepare(
            "INSERT INTO partes (codigo, id_tipo, id_grupo, detalle, id_um_compra, id_um_uso, factor_conversion, activo, fecha_creacion)
             VALUES (?, ?, ?, ?, ?, ?, ?, true, NOW()) RETURNING id"
        );
        $stmt->execute([
            strtoupper($data['code']),
            $idTipo,
            $idGrupo,
            $data['description'],
            $idUmCompra,
            $idUmUso,
            $factorConversion,
        ]);
        $parteId = (int) $stmt->fetchColumn();

        $stmtVar = $db->prepare(
            "INSERT INTO variantes (id_parte, codigo_variante, detalle, estado, lote_minimo, punto_pedido, stock_seguridad, stock_actual, fecha_creacion)
             VALUES (?, ?, ?, 'activa', 1, ?, ?, 0, NOW()) RETURNING id"
        );
        $stmtVar->execute([
            $parteId,
            strtoupper($data['code']) . '-01',
            $data['description'],
            $puntoPedido,
            $stockSeguridad,
        ]);
        $varianteId = (int) $stmtVar->fetchColumn();

        return [
            'success' => true,
            'message' => "Material '{$data['code']}' creado correctamente (ID: {$parteId})",
            'data' => ['parte_id' => $parteId, 'variante_id' => $varianteId, 'code' => strtoupper($data['code'])],
        ];
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