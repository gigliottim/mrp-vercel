<?php

declare(strict_types=1);

namespace App\AgenteAI\Backend\Services;

use App\AgenteAI\Backend\Repositories\ConversationRepository;

/**
 * Servicio Principal del Agente AI
 * Orquesta todos los componentes del agente
 *
 * Schema real verificado:
 * - Partes: tabla `partes` + `variantes`
 * - Proveedores: tabla `entidades` (tipo='PROVEEDOR')
 * - BOM: tablas `bom_cabecera` + `bom_detalle`
 * - Materiales: tabla `variantes` (no hay tabla dedicada)
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
            error_log("Valkey/DB connection failed in AgentService: " . $e->getMessage());
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
        $intent = $this->promptBuilder->detectIntent($userInput);
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
                suggestions: $aiResponse['suggestions'] ?? $this->buildSuggestions($aiResponse['data'] ?? []),
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
     * Delega al servicio de dominio correspondiente según el intent
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

            if ($this->repository) {
                $this->repository->saveAiLog([
                    'conversation_id' => $convId,
                    'validation_result' => $result['success'] ? 'saved' : 'save_failed',
                    'provider' => 'domain_service',
                ]);
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

    // ─── Guardar Parte (tabla: partes + variantes) ────────────────────

    private function savePart(array $data): array
    {
        $db = $this->getDb();
        if (!$db) return ['success' => false, 'message' => 'Base de datos no disponible'];

        // 1) Obtener o crear tipo de parte
        $stmtTipo = $db->prepare("SELECT id FROM tipos_partes WHERE codigo = ?");
        $stmtTipo->execute([$data['part_type'] ?? 'pieza']);
        $idTipo = $stmtTipo->fetchColumn() ?: 1;

        // 2) Obtener o crear grupo de partes
        $stmtGrupo = $db->prepare("SELECT id FROM grupos_partes WHERE nombre = ?");
        $stmtGrupo->execute([$data['category'] ?? 'General']);
        $idGrupo = $stmtGrupo->fetchColumn() ?: 1;

        // 3) Insertar parte
        $stmt = $db->prepare(
            "INSERT INTO partes (codigo, id_tipo, id_grupo, detalle, activo, fecha_creacion)
             VALUES (?, ?, ?, ?, true, NOW()) RETURNING id"
        );
        $stmt->execute([
            strtoupper($data['code']),
            $idTipo,
            $idGrupo,
            $data['description'],
        ]);
        $parteId = (int) $stmt->fetchColumn();

        // 4) Crear variante por defecto
        $stmtVar = $db->prepare(
            "INSERT INTO variantes (id_parte, codigo_variante, detalle, estado, stock_actual, fecha_creacion)
             VALUES (?, ?, ?, 'activa', 0, NOW()) RETURNING id"
        );
        $stmtVar->execute([
            $parteId,
            strtoupper($data['code']) . '-01',
            $data['description'],
        ]);
        $varianteId = (int) $stmtVar->fetchColumn();

        return [
            'success' => true,
            'message' => "Parte '{$data['code']}' creada (ID: {$parteId}, Variante: {$varianteId})",
            'data' => ['parte_id' => $parteId, 'variante_id' => $varianteId, 'code' => strtoupper($data['code'])],
        ];
    }

    // ─── Guardar Proveedor (tabla: entidades, tipo='PROVEEDOR') ──────

    private function saveSupplier(array $data): array
    {
        $db = $this->getDb();
        if (!$db) return ['success' => false, 'message' => 'Base de datos no disponible'];

        $stmt = $db->prepare(
            "INSERT INTO entidades (razon_social, tipo, identificacion_tributaria, contacto_email, contacto_telefono, direccion, created_at, updated_at)
             VALUES (?, 'PROVEEDOR', ?, ?, ?, '', NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([
            $data['name'],
            $data['cuit'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
        ]);
        $id = (int) $stmt->fetchColumn();

        return [
            'success' => true,
            'message' => "Proveedor '{$data['name']}' creado (ID: {$id})",
            'data' => ['id' => $id, 'name' => $data['name']],
        ];
    }

    // ─── Guardar BOM (tablas: bom_cabecera + bom_detalle) ────────────

    private function saveBom(array $data): array
    {
        $db = $this->getDb();
        if (!$db) return ['success' => false, 'message' => 'Base de datos no disponible'];

        // Buscar variante padre por código
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
                'message' => "No se encontró la parte padre '{$data['parent_part']}'. Créela primero.",
            ];
        }

        // Crear cabecera BOM
        $stmt = $db->prepare(
            "INSERT INTO bom_cabecera (variante_padre_id, version, activa, fecha_efectiva, created_at, updated_at)
             VALUES (?, '1.0', true, CURRENT_DATE, NOW(), NOW()) RETURNING id"
        );
        $stmt->execute([$variantePadreId]);
        $bomId = (int) $stmt->fetchColumn();

        // Insertar componentes
        $components = $data['components'] ?? [];
        $componentIds = [];
        foreach ($components as $comp) {
            // Buscar variante componente
            $stmtComp = $db->prepare(
                "SELECT v.id FROM variantes v
                 INNER JOIN partes p ON p.id = v.id_parte
                 WHERE p.codigo = ? OR v.codigo_variante = ?
                 LIMIT 1"
            );
            $stmtComp->execute([$comp['code'] ?? $comp['component_code'] ?? '', $comp['code'] ?? $comp['component_code'] ?? '']);
            $varianteCompId = $stmtComp->fetchColumn();

            if (!$varianteCompId) {
                continue; // Saltar componente no encontrado
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

    // ─── Guardar Material (tabla: variantes) ─────────────────────────

    private function saveMaterial(array $data): array
    {
        $db = $this->getDb();
        if (!$db) return ['success' => false, 'message' => 'Base de datos no disponible'];

        // Obtener id_tipo para 'materia prima'
        $stmtTipo = $db->prepare("SELECT id FROM tipos_partes WHERE codigo IN ('materia_prima','mp','raw_material') LIMIT 1");
        $stmtTipo->execute([]);
        $idTipo = $stmtTipo->fetchColumn() ?: 1;

        $stmtGrupo = $db->prepare("SELECT id FROM grupos_partes WHERE nombre = ? OR nombre ILIKE '%material%' LIMIT 1");
        $stmtGrupo->execute([$data['category'] ?? 'Materiales']);
        $idGrupo = $stmtGrupo->fetchColumn() ?: 1;

        // Crear parte
        $stmt = $db->prepare(
            "INSERT INTO partes (codigo, id_tipo, id_grupo, detalle, activo, fecha_creacion)
             VALUES (?, ?, ?, ?, true, NOW()) RETURNING id"
        );
        $stmt->execute([
            strtoupper($data['code']),
            $idTipo,
            $idGrupo,
            $data['description'],
        ]);
        $parteId = (int) $stmt->fetchColumn();

        // Crear variante con stock mínimo como atributo
        $minStock = $data['min_stock'] ?? 0;
        $stmtVar = $db->prepare(
            "INSERT INTO variantes (id_parte, codigo_variante, detalle, estado, stock_actual, stock_seguridad, atributos, fecha_creacion)
             VALUES (?, ?, ?, 'activa', 0, ?, ?, NOW()) RETURNING id"
        );
        $stmtVar->execute([
            $parteId,
            strtoupper($data['code']) . '-01',
            $data['description'],
            $minStock,
            json_encode(['min_stock' => (float) $minStock, 'uom' => $data['uom'] ?? 'u']),
        ]);
        $varianteId = (int) $stmtVar->fetchColumn();

        return [
            'success' => true,
            'message' => "Material '{$data['code']}' creado (ID: {$parteId}, Variante: {$varianteId})",
            'data' => ['parte_id' => $parteId, 'variante_id' => $varianteId, 'code' => strtoupper($data['code'])],
        ];
    }

    // ─── Utilidades internas ──────────────────────────────────────────

    /**
     * Obtener conexión PDO
     */
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
            return 'Faltan algunos datos. Por favor, completá la información solicitada.';
        }
        return "Faltan datos o hay errores: " . implode(", ", $errors) . ". Por favor, corregí o completá la información.";
    }

    private function buildSuggestions(array $data): array
    {
        $suggestions = [];
        if (isset($data['code'])) {
            $suggestions[] = "¿El código es correcto: {$data['code']}?";
        }
        if (isset($data['description'])) {
            $suggestions[] = "¿La descripción es correcta: {$data['description']}?";
        }
        if (isset($data['uom']) && $data['uom'] === '') {
            $suggestions[] = "Indicá la unidad de medida (u, kg, m, l, g)";
        }
        return $suggestions;
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
    }
}
