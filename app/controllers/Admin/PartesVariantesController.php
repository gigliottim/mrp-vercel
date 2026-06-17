<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\GrupoParte;
use App\Models\ConfiguracionGeneral;
use App\Models\Parte;
use App\Models\TipoParte;
use App\Models\UnidadMedida;
use App\Models\Variante;
use App\Services\Partes\VarianteDeletionService;
use Throwable;

final class PartesVariantesController extends Controller
{
    private const DEFAULT_DECIMAL_PLACES = 4;

    private Parte $partes;
    private Variante $variantes;
    private TipoParte $tipos;
    private GrupoParte $grupos;
    private UnidadMedida $unidades;
    private ?int $cachedDecimalPlaces = null;
    private ?bool $partesPrecisionChecked = null;

    public function __construct(
        ?Parte $partes = null,
        ?Variante $variantes = null,
        ?TipoParte $tipos = null,
        ?GrupoParte $grupos = null,
        ?UnidadMedida $unidades = null
    ) {
        $this->partes = $partes ?? new Parte();
        $this->variantes = $variantes ?? new Variante();
        $this->tipos = $tipos ?? new TipoParte();
        $this->grupos = $grupos ?? new GrupoParte();
        $this->unidades = $unidades ?? new UnidadMedida();
    }

    public function index(Request $request): Response
    {
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPageParam = strtolower(trim((string) ($request->query['per_page'] ?? '15')));
        $perPage = $perPageParam === 'all' ? 0 : (int) $perPageParam;
        $perPage = in_array($perPage, [0, 15, 30, 50], true) ? $perPage : 15;
        $tab = $request->query['tab'] ?? 'partes';
        $idParte = isset($request->query['id_parte']) ? (int) $request->query['id_parte'] : null;
        $search = trim((string) ($request->query['q'] ?? ''));
        return $this->renderIndex([
            'page' => $page,
            'perPage' => $perPage,
            'tab' => $tab,
            'filteredParteId' => $idParte,
            'search' => $search,
        ]);
    }

    public function editPart(Request $request, $id): Response
    {
        $id = (int) $id;
        $record = $this->partes->find($id);
        if ($record === null) {
            return Response::redirect(url('/productos/partes'));
        }

        return $this->renderIndex([
            'oldPart' => $record,
            'editingPartId' => $id,
            'tab' => 'partes',
        ]);
    }

    public function editVariant(Request $request, $idParte, $id): Response
    {
        $id = (int) $id;
        $idParte = (int) $idParte;
        $record = $this->variantes->find($id);
        if ($record === null || (int) $record['id_parte'] !== $idParte) {
            return Response::redirect(url('/productos/partes?tab=variantes'));
        }

        return $this->renderIndex([
            'oldVariant' => $record,
            'editingVariantId' => $id,
            'filteredParteId' => $idParte,
            'tab' => 'variantes',
        ]);
    }

    public function storePart(Request $request): Response
    {
        [$data, $errors] = $this->validatePart($request);
        if ($errors !== []) {
            return $this->renderIndex([
                'errors' => $errors,
                'oldPart' => $request->body,
                'tab' => 'partes',
            ]);
        }

        try {
            $this->createParteWithDefaultVariant($data);
        } catch (Throwable $exception) {
            return $this->renderIndex([
                'errors' => ['general' => 'No se pudo crear la parte y su variante inicial.'],
                'oldPart' => $request->body,
                'tab' => 'partes',
            ]);
        }

        return Response::redirect(url('/productos/partes'));
    }

    public function updatePart(Request $request, $id): Response
    {
        $id = (int) $id;
        if ($this->partes->find($id) === null) {
            return Response::redirect(url('/productos/partes'));
        }

        [$data, $errors] = $this->validatePart($request);
        if ($errors !== []) {
            return $this->renderIndex([
                'errors' => $errors,
                'oldPart' => $request->body,
                'editingPartId' => $id,
                'tab' => 'partes',
            ]);
        }

        $this->partes->update($id, $data);
        return Response::redirect(url('/productos/partes'));
    }

    public function destroyPart(Request $request, $id): Response
    {
        $this->partes->delete((int) $id);
        return Response::redirect(url('/productos/partes'));
    }

    public function storeVariant(Request $request, $idParte): Response
    {
        $idParte = (int) $idParte;
        [$data, $errors] = $this->validateVariant($request, $idParte);
        if ($errors !== []) {
            return $this->renderIndex([
                'variantErrors' => $errors,
                'oldVariant' => $request->body,
                'filteredParteId' => $idParte,
                'tab' => 'variantes',
            ]);
        }

        $newVariantId = $this->variantes->create($data);

        // Check if coming from Manager (via Context param)
        $context = $request->input('context');
        if ($context === 'manager') {
            return Response::redirect(url("/productos/partes/manager/{$idParte}/variantes/{$newVariantId}"));
        }

        return Response::redirect(url('/productos/partes?tab=variantes&id_parte=' . $idParte));
    }

    public function updateVariant(Request $request, $idParte, $id): Response
    {
        $id = (int) $id;
        $idParte = (int) $idParte;
        $record = $this->variantes->find($id);
        if ($record === null || (int) $record['id_parte'] !== $idParte) {
            return Response::redirect(url('/productos/partes?tab=variantes'));
        }

        [$data, $errors] = $this->validateVariant($request, $idParte);
        if ($errors !== []) {
            return $this->renderIndex([
                'variantErrors' => $errors,
                'oldVariant' => $request->body,
                'editingVariantId' => $id,
                'filteredParteId' => $idParte,
                'tab' => 'variantes',
            ]);
        }

        $this->variantes->update($id, $data);

        // Check if coming from Manager (via Context param)
        $context = $request->input('context');
        if ($context === 'manager') {
            return Response::redirect(url("/productos/partes/manager/{$idParte}/variantes/{$id}"));
        }

        return Response::redirect(url('/productos/partes?tab=variantes&id_parte=' . $idParte));
    }

    public function destroyVariant(Request $request, $idParte, $id): Response
    {
        $idParte = (int) $idParte;
        $id = (int) $id;
        $context = (string) $request->input('context', '');
        $forceDeleteParte = $request->input('force_delete_parte') === '1';

        $variant = $this->variantes->find($id);
        if ($variant === null || (int) ($variant['id_parte'] ?? 0) !== $idParte) {
            if ($context === 'manager') {
                return Response::json([
                    'status' => 'error',
                    'message' => 'La variante no existe para la parte indicada.',
                ], 404);
            }

            return Response::redirect(url('/productos/partes?tab=variantes&id_parte=' . $idParte));
        }

        $deletionService = new VarianteDeletionService();
        $result = $deletionService->delete($id, $idParte, $forceDeleteParte);

        if (!$result['success']) {
            if ($context === 'manager') {
                $statusCode = isset($result['needs_parte_deletion']) ? 409 : 422;
                return Response::json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'needs_parte_deletion' => $result['needs_parte_deletion'] ?? false,
                ], $statusCode);
            }

            $_SESSION['form_error'] = $result['message'];
            $_SESSION['needs_parte_deletion'] = $result['needs_parte_deletion'] ?? false;
            $_SESSION['delete_variante_id'] = $id;
            $_SESSION['delete_parte_id'] = $idParte;
            return Response::redirect(url('/productos/partes?tab=variantes&id_parte=' . $idParte));
        }

        if ($context === 'manager') {
            return Response::json([
                'status' => 'ok',
                'deleted_parte' => $result['deleted_parte'] ?? false,
            ]);
        }

        if ($result['deleted_parte'] ?? false) {
            $_SESSION['form_success'] = $result['message'];
            return Response::redirect(url('/productos/partes'));
        }

        $_SESSION['form_success'] = $result['message'];
        return Response::redirect(url('/productos/partes?tab=variantes&id_parte=' . $idParte));
    }

    public function manager(Request $request): Response
    {
        return $this->renderManager([
            'mode' => 'create',
        ]);
    }

    public function managerShow(Request $request, $id): Response
    {
        $id = (int) $id;
        $record = $this->partes->find($id);
        if ($record === null) {
            return Response::redirect(url('/productos/partes/manager'));
        }

        $variants = $this->variantes->byParteIds([$id]);

        return $this->renderManager([
            'parte' => $record,
            'parteVariants' => $variants[$id] ?? [],
            'mode' => 'view',
        ]);
    }

    public function managerEdit(Request $request, $id): Response
    {
        $id = (int) $id;
        $record = $this->partes->find($id);
        if ($record === null) {
            return Response::redirect(url('/productos/partes/manager'));
        }

        $variants = $this->variantes->byParteIds([$id]);

        return $this->renderManager([
            'parte' => $record,
            'parteVariants' => $variants[$id] ?? [],
            'mode' => 'edit',
        ]);
    }

    public function managerShowVariant(Request $request, $idParte, $idVariante): Response
    {
        $idParte = (int) $idParte;
        $idVariante = (int) $idVariante;

        $record = $this->partes->find($idParte);
        if ($record === null) {
            return Response::redirect(url('/productos/partes/manager'));
        }

        $variantRecord = $this->variantes->find($idVariante);
        if ($variantRecord === null || (int) $variantRecord['id_parte'] !== $idParte) {
            return Response::redirect(url("/productos/partes/manager/{$idParte}"));
        }

        $variants = $this->variantes->byParteIds([$idParte]);

        return $this->renderManager([
            'parte' => $record,
            'parteVariants' => $variants[$idParte] ?? [],
            'mode' => 'view',
            'editingVariantId' => $idVariante,
            'editingVariant' => $variantRecord,
        ]);
    }

    public function managerEditVariant(Request $request, $idParte, $idVariante): Response
    {
        $idParte = (int) $idParte;
        $idVariante = (int) $idVariante;

        $record = $this->partes->find($idParte);
        if ($record === null) {
            return Response::redirect(url('/productos/partes/manager'));
        }

        $variantRecord = $this->variantes->find($idVariante);
        if ($variantRecord === null || (int) $variantRecord['id_parte'] !== $idParte) {
            return Response::redirect(url("/productos/partes/manager/{$idParte}"));
        }

        $variants = $this->variantes->byParteIds([$idParte]);

        return $this->renderManager([
            'parte' => $record,
            'parteVariants' => $variants[$idParte] ?? [],
            'mode' => 'edit',
            'editingVariantId' => $idVariante,
            'editingVariant' => $variantRecord,
        ]);
    }

    public function managerStorePart(Request $request): Response
    {
        [$data, $errors] = $this->validatePart($request);
        if ($errors !== []) {
            if ($this->isAjaxRequest($request)) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Revisa los datos obligatorios de la parte.',
                    'errors' => $errors,
                ], 422);
            }

            return $this->renderManager([
                'errors' => $errors,
                'oldPart' => $request->body,
            ]);
        }

        try {
            $newId = $this->createParteWithDefaultVariant($data);
        } catch (Throwable $exception) {
            $errorMessage = $this->resolvePartCreateErrorMessage($exception);

            if ($this->isAjaxRequest($request)) {
                return Response::json([
                    'status' => 'error',
                    'message' => $errorMessage,
                ], 409);
            }

            return $this->renderManager([
                'errors' => ['general' => $errorMessage],
                'oldPart' => $request->body,
            ]);
        }

        if ($this->isAjaxRequest($request)) {
            return Response::json([
                'status' => 'ok',
                'redirect_url' => url("/productos/partes/manager/{$newId}"),
            ]);
        }

        return Response::redirect(url("/productos/partes/manager/{$newId}"));
    }

    private function createParteWithDefaultVariant(array $partData): int
    {
        $connection = $this->partes->getConnection();

        $connection->beginTransaction();
        try {
            $newParteId = $this->partes->create($partData);

            $variantModel = new Variante($connection);
            $variantModel->create($this->buildDefaultVariantData($partData, $newParteId));

            $connection->commit();
            return $newParteId;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    private function buildDefaultVariantData(array $partData, int $parteId): array
    {
        $parteDetalle = trim((string) ($partData['detalle'] ?? ''));
        $parteCodigo = strtoupper(trim((string) ($partData['codigo'] ?? '')));
        $defaultCode = $parteCodigo !== '' ? $parteCodigo : 'BASE-' . $parteId;

        return [
            'id_parte' => $parteId,
            'codigo_variante' => substr($defaultCode, 0, 50),
            'detalle' => $parteDetalle !== '' ? $parteDetalle : 'Variante base',
            'estado' => 'activa',
            'lote_minimo' => 1,
            'punto_pedido' => 0,
        ];
    }

    public function managerUpdatePart(Request $request, $id): Response
    {
        $id = (int) $id;
        if ($this->partes->find($id) === null) {
            return Response::redirect(url('/productos/partes/manager'));
        }

        [$data, $errors] = $this->validatePart($request);
        if ($errors !== []) {
            if ($this->isAjaxRequest($request)) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Revisa los datos obligatorios de la parte.',
                    'errors' => $errors,
                ], 422);
            }

            return $this->renderManager([
                'errors' => $errors,
                'oldPart' => $request->body,
                'parte' => array_merge(['id' => $id], $request->body),
            ]);
        }

        $this->partes->update($id, $data);
        return Response::redirect(url("/productos/partes/manager/{$id}"));
    }

    private function isAjaxRequest(Request $request): bool
    {
        $xRequestedWith = (string) ($request->headers['X-Requested-With'] ?? $request->headers['x-requested-with'] ?? '');
        if (strtolower($xRequestedWith) === 'xmlhttprequest') {
            return true;
        }

        $accept = (string) ($request->headers['Accept'] ?? $request->headers['accept'] ?? '');
        return str_contains(strtolower($accept), 'application/json');
    }

    private function resolvePartCreateErrorMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'partes_codigo_key')) {
            return 'Ya existe una parte con ese codigo.';
        }

        if (str_contains($message, 'variantes_id_parte_codigo_variante_key')) {
            return 'No se pudo crear la variante inicial por codigo duplicado.';
        }

        return 'No se pudo crear la parte y su variante inicial.';
    }

    private function renderManager(array $overrides = []): Response
    {
        $defaults = [
            'parte' => null,
            'parteVariants' => [],
            'editingVariant' => null,
            'partesList' => $this->partes->listAll(),
            'tipos' => $this->tipos->activos(),
            'grupos' => $this->grupos->activos(),
            'unidadesLongitud' => $this->unidades->byTipo('longitud'),
            'unidadesSuperficie' => $this->unidades->byTipo('superficie'),
            'unidadesVolumen' => $this->unidades->byTipo('volumen'),
            'unidadesMasa' => $this->unidades->byTipo('masa'),
            'unidadesTodas' => $this->unidades->allActive(500, 0),
            'errors' => [],
            'oldPart' => [],
        ];

        return $this->render('pages/admin/partes/manager', array_merge($defaults, $overrides));
    }

    private function renderIndex(array $overrides = []): Response
    {
        $page = max(1, (int) ($overrides['page'] ?? 1));
        $perPage = (int) ($overrides['perPage'] ?? 15);
        $perPage = in_array($perPage, [0, 15, 30, 50], true) ? $perPage : 15;
        $search = $overrides['search'] ?? '';
        $listing = $this->partes->paginated($page, $perPage, $search);

        // Novedad: Buscamos variantes pre-cargadas para las partes
        $partIds = array_column($listing['items'], 'id');
        $variantsGrouped = $this->variantes->byParteIds($partIds);

        foreach ($listing['items'] as &$parteItem) {
            $parteItem['variantes'] = $variantsGrouped[$parteItem['id']] ?? [];
        }
        unset($parteItem);

        // Estadísticas globales de variantes
        $variantStats = $this->variantes->getGlobalStats();

        $defaults = [
            'title' => 'Partes y variantes',
            'parts' => $listing,
            'partOptions' => $this->partes->options(),
            'tipos' => $this->tipos->activos(),
            'grupos' => $this->grupos->activos(),
            'unidadesLongitud' => $this->unidades->byTipo('longitud'),
            'unidadesSuperficie' => $this->unidades->byTipo('superficie'),
            'unidadesVolumen' => $this->unidades->byTipo('volumen'),
            'unidadesMasa' => $this->unidades->byTipo('masa'),
            'unidadesTodas' => $this->unidades->allActive(500, 0),
            'totalVariantesGlobal' => (int) ($variantStats['total'] ?? 0),
            'totalActivasGlobal' => (int) ($variantStats['activas'] ?? 0),
            'totalStockBajoGlobal' => (int) ($variantStats['stock_bajo'] ?? 0),
            'errors' => [],
            'variantErrors' => [],
            'oldPart' => [],
            'oldVariant' => [],
            'page' => $page,
            'perPage' => $perPage,
            'tab' => 'partes',
            'editingPartId' => null,
            'editingVariantId' => null,
            'filteredParteId' => null,
            'search' => '',
        ];

        return $this->render('pages/admin/partes/index', array_merge($defaults, $overrides));
    }

    private function validatePart(Request $request): array
    {
        $this->ensurePartesDimensionsPrecision();

        $body = $request->body;
        $decimalPlaces = $this->getConfiguredDecimalPlaces();
        $data = [
            'codigo' => strtoupper(trim((string) ($body['codigo'] ?? ''))),
            'id_tipo' => (int) ($body['id_tipo'] ?? 0),
            'id_grupo' => (int) ($body['id_grupo'] ?? 0),
            'detalle' => trim((string) ($body['detalle'] ?? '')),
            'activo' => (int) ($body['activo'] ?? 0),
            'id_um_compra' => $body['id_um_compra'] === '' || $body['id_um_compra'] === null ? null : (int) $body['id_um_compra'],
            'id_um_uso' => $body['id_um_uso'] === '' || $body['id_um_uso'] === null ? null : (int) $body['id_um_uso'],
            'factor_conversion' => isset($body['factor_conversion']) && $body['factor_conversion'] !== ''
                ? $this->roundConfiguredDecimal((float) $body['factor_conversion'], $decimalPlaces)
                : null,
        ];

        $optionalNumeric = [
            'largo_alto' => 'longitud',
            'ancho' => 'longitud',
            'espesor_profundidad' => 'longitud',
            'superficie' => 'superficie',
            'volumen' => 'volumen',
        ];

        foreach ($optionalNumeric as $field => $type) {
            $value = $body[$field] ?? null;
            $unitKey = 'id_um_' . ($field === 'espesor_profundidad' ? 'espesor' : $field);
            $unitValue = $body[$unitKey] ?? null;
            $data[$field] = $value === '' || $value === null
                ? null
                : $this->roundConfiguredDecimal((float) $value, $decimalPlaces);
            $data[$unitKey] = $unitValue === '' || $unitValue === null ? null : (int) $unitValue;
        }

        $errors = [];

        if (
            ($data['factor_conversion'] === null || $data['factor_conversion'] <= 0)
            && $this->isLinearToAreaConversion($data['id_um_compra'], $data['id_um_uso'])
        ) {
            $anchoMetros = $this->toMeters((float) ($data['ancho'] ?? 0), $data['id_um_ancho'] ?? null);
            if ($anchoMetros !== null && $anchoMetros > 0) {
                $data['factor_conversion'] = $this->roundConfiguredDecimal($anchoMetros, $decimalPlaces);
            }
        }

        // Validar Factor de Conversión
        if ($data['id_um_compra'] && $data['id_um_uso'] && $data['id_um_compra'] !== $data['id_um_uso']) {
            if (empty($data['factor_conversion']) || $data['factor_conversion'] <= 0) {
                $errors['factor_conversion'] = 'El factor de conversión es requerido cuando las unidades son diferentes.';
            }
        } elseif ($data['id_um_compra'] && $data['id_um_uso'] && $data['id_um_compra'] === $data['id_um_uso']) {
            // Si son iguales, forzar factor a 1
            $data['factor_conversion'] = 1.0;
        }

        if ($data['codigo'] === '') {
            $errors['codigo'] = 'Código requerido.';
        }
        if ($data['detalle'] === '') {
            $errors['detalle'] = 'Detalle requerido.';
        }
        if ($data['id_tipo'] <= 0) {
            $errors['id_tipo'] = 'Selecciona un tipo.';
        }
        if ($data['id_grupo'] <= 0) {
            $errors['id_grupo'] = 'Selecciona un grupo.';
        }

        return [$data, $errors];
    }

    private function isLinearToAreaConversion(?int $idUmCompra, ?int $idUmUso): bool
    {
        if (empty($idUmCompra) || empty($idUmUso)) {
            return false;
        }

        $umCompra = $this->unidades->find((int) $idUmCompra);
        $umUso = $this->unidades->find((int) $idUmUso);

        if ($umCompra === null || $umUso === null) {
            return false;
        }

        return ($umCompra['tipo'] ?? null) === 'longitud'
            && ($umUso['tipo'] ?? null) === 'superficie'
            && ($umCompra['simbolo'] ?? '') === 'mL'
            && ($umUso['simbolo'] ?? '') === 'm²';
    }

    private function toMeters(float $value, ?int $unitId): ?float
    {
        if ($value <= 0 || empty($unitId)) {
            return null;
        }

        $unit = $this->unidades->find((int) $unitId);
        if ($unit === null) {
            return null;
        }

        if (($unit['tipo'] ?? null) !== 'longitud') {
            return null;
        }

        $equivalencia = (float) ($unit['equivalencia_base'] ?? 0);
        if ($equivalencia <= 0) {
            return null;
        }

        return $value * $equivalencia;
    }

    private function validateVariant(Request $request, ?int $idParte = null): array
    {
        $body = $request->body;
        $decimalPlaces = $this->getConfiguredDecimalPlaces();
        $data = [
            'id_parte' => $idParte ?? (int) ($body['id_parte'] ?? 0),
            'codigo_variante' => strtoupper(trim((string) ($body['codigo_variante'] ?? ''))),
            'detalle' => trim((string) ($body['detalle'] ?? '')),
            'estado' => trim((string) ($body['estado'] ?? 'activa')),
            'lote_minimo' => $this->roundConfiguredDecimal((float) ($body['lote_minimo'] ?? 1), $decimalPlaces),
            'punto_pedido' => $this->roundConfiguredDecimal((float) ($body['punto_pedido'] ?? 0), $decimalPlaces),
            // 'stock_actual' => (float) ($body['stock_actual'] ?? 0), // Stock es calculado o solo lectura
            'peso' => ($body['peso'] ?? '') === ''
                ? null
                : $this->roundConfiguredDecimal((float) $body['peso'], $decimalPlaces),
            'id_um_peso' => ($body['id_um_peso'] ?? '') === '' || (int) ($body['id_um_peso'] ?? 0) <= 0
                ? null
                : (int) $body['id_um_peso'],
            'ubicacion_cuerpo' => trim((string) ($body['ubicacion_cuerpo'] ?? '')),
            'ubicacion_pasillo' => trim((string) ($body['ubicacion_pasillo'] ?? '')),
            'ubicacion_estante' => trim((string) ($body['ubicacion_estante'] ?? '')),
        ];

        $errors = [];
        if ($data['id_parte'] <= 0) {
            $errors['id_parte'] = 'Selecciona la parte.';
        }
        if ($data['codigo_variante'] === '') {
            $errors['codigo_variante'] = 'Código requerido.';
        }
        if ($data['detalle'] === '') {
            $errors['detalle'] = 'Detalle requerido.';
        }
        if (!in_array($data['estado'], ['activa', 'obsoleta', 'descontinuada', 'desarrollo'], true)) {
            $errors['estado'] = 'Estado inválido.';
        }
        if ($data['peso'] !== null && $data['id_um_peso'] === null) {
            $errors['id_um_peso'] = 'Selecciona la unidad de peso.';
        }

        return [$data, $errors];
    }

    private function getConfiguredDecimalPlaces(): int
    {
        if ($this->cachedDecimalPlaces !== null) {
            return $this->cachedDecimalPlaces;
        }

        try {
            $settings = (new ConfiguracionGeneral($this->partes->getConnection()))->getSettings();
            $configured = isset($settings['decimal_places']) ? (int) $settings['decimal_places'] : self::DEFAULT_DECIMAL_PLACES;
        } catch (\Throwable $exception) {
            $configured = self::DEFAULT_DECIMAL_PLACES;
        }

        $this->cachedDecimalPlaces = max(1, min(10, $configured));

        return $this->cachedDecimalPlaces;
    }

    private function roundConfiguredDecimal(float $value, int $decimals): float
    {
        $safeDecimals = max(0, min(10, $decimals));
        $factor = 10 ** $safeDecimals;

        return round($value * $factor) / $factor;
    }

    private function ensurePartesDimensionsPrecision(): void
    {
        if ($this->partesPrecisionChecked === true) {
            return;
        }

        $connection = $this->partes->getConnection();
        $meta = $connection
            ->query(
                "SELECT column_name, data_type, numeric_scale
                                 FROM information_schema.columns
                                 WHERE table_schema = current_schema()
                                     AND table_name = 'partes'
                                     AND column_name IN ('largo_alto', 'ancho', 'espesor_profundidad', 'superficie', 'volumen')"
            )
            ->fetchAll(\PDO::FETCH_ASSOC);

        $needsUpgrade = false;
        foreach ($meta as $columnMeta) {
            $dataType = strtolower((string) ($columnMeta['data_type'] ?? ''));
            $scale = isset($columnMeta['numeric_scale']) ? (int) $columnMeta['numeric_scale'] : null;

            if (($dataType === 'numeric' || $dataType === 'decimal') && $scale !== null && $scale < 10) {
                $needsUpgrade = true;
                break;
            }
        }

        if ($needsUpgrade) {
            $connection->exec(
                'ALTER TABLE partes
                    ALTER COLUMN largo_alto TYPE numeric(18,10),
                    ALTER COLUMN ancho TYPE numeric(18,10),
                    ALTER COLUMN espesor_profundidad TYPE numeric(18,10),
                    ALTER COLUMN superficie TYPE numeric(18,10),
                    ALTER COLUMN volumen TYPE numeric(18,10)'
            );
        }

        $this->partesPrecisionChecked = true;
    }
}
