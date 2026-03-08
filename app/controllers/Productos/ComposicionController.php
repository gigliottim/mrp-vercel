<?php

declare(strict_types=1);

namespace App\Controllers\Productos;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\Variante;
use App\Models\UnidadMedida;
use App\Models\Bom;
use App\Models\TipoParte;

final class ComposicionController extends Controller
{
    private Variante $variantes;
    private UnidadMedida $unidades;
    private Bom $bomModel;
    private TipoParte $tiposPartes;

    public function __construct(
        ?Variante $variantes = null,
        ?UnidadMedida $unidades = null,
        ?Bom $bomModel = null,
        ?TipoParte $tiposPartes = null
    ) {
        $this->variantes = $variantes ?? new Variante();
        $this->unidades = $unidades ?? new UnidadMedida();
        $this->bomModel = $bomModel ?? new Bom();
        $this->tiposPartes = $tiposPartes ?? new TipoParte();
    }

    public function index(Request $request): Response
    {
        $selectedVarianteId = isset($request->query['id_variante']) ? (int) $request->query['id_variante'] : null;

        // Obtener todas las variantes disponibles
        $variantesList = $this->variantes->allWithPartes();
        $variantes = [];
        foreach ($variantesList as $v) {
            $variantes[$v['id']] = $v;
        }

        // Obtener composición de la variante seleccionada
        $composicion = [];
        $bom = null;

        if ($selectedVarianteId) {
            $bom = $this->bomModel->getActiveByVariante($selectedVarianteId);
            if ($bom) {
                // Si se pide vista árbol, obtener estructura completa
                if ($request->query['view'] === 'tree') {
                    $composicion = $this->bomModel->getTree($selectedVarianteId);
                } else {
                    $composicion = $this->bomModel->getDetalles((int) $bom['id']);
                }
            }
        }

        // Materiales disponibles
        // Filtramos para evitar recursividad directa (el padre no puede ser hijo)
        $materiales = array_filter($variantesList, fn($v) => (int)$v['id'] !== $selectedVarianteId);

        return $this->render('pages/productos/composicion/index', [
            'title' => 'Composición de Variantes',
            'variantes' => $variantes,
            'materiales' => $materiales, // Usamos las variantes reales como materiales
            'unidades' => $this->unidades->all(),
            'tiposPartes' => $this->tiposPartes->activos(),
            'selectedVarianteId' => $selectedVarianteId,
            'composicion' => $composicion,
            'activeBom' => $bom,
            'viewMode' => $request->query['view'] ?? 'list',
            'editingMaterialId' => null,
            'editingMaterial' => null,
            'errors' => [],
            'materialOldValue' => fn($k, $d = '') => $d
        ]);
    }

    public function maestro(Request $request): Response
    {
        $selectedVarianteId = isset($request->query['id_variante']) ? (int) $request->query['id_variante'] : null;

        // Obtener todas las variantes disponibles
        $variantesList = $this->variantes->allWithPartes();
        $variantes = [];
        foreach ($variantesList as $v) {
            $variantes[$v['id']] = $v;
        }

        $treeData = [];
        $rootBOM = null;

        if ($selectedVarianteId) {
            // Obtenemos el árbol completo para construir la vista
            $treeData = $this->bomModel->getTree($selectedVarianteId);
            $rootBOM = $this->bomModel->getActiveByVariante($selectedVarianteId);
        }

        // Materiales disponibles
        $materiales = array_filter($variantesList, fn($v) => (int)$v['id'] !== $selectedVarianteId);

        return $this->render('pages/productos/composicion/maestro', [
            'title' => 'Maestro de Productos',
            'variantes' => $variantes,
            'materiales' => $materiales,
            'unidades' => $this->unidades->all(),
            'tiposPartes' => $this->tiposPartes->activos(),
            'selectedVarianteId' => $selectedVarianteId,
            'treeData' => $treeData,
            'rootBOM' => $rootBOM
        ]);
    }

    public function addItem(Request $request): Response
    {
        $varianteId = (int) $request->input('id_variante');
        $materialId = (int) $request->input('id_material');
        $cantidad = $this->normalizeCantidad((float) $request->input('cantidad'));
        $unidadId = (int) $request->input('id_unidad');

        $redirectUrl = url('/productos/maestro?id_variante=' . $varianteId);

        if (!$varianteId || !$materialId || !$cantidad || !$unidadId) {
            // TODO: Handle validation errors properly
            return Response::redirect($redirectUrl);
        }

        // VALIDAR antes de agregar
        $validation = $this->bomModel->validateAddComponent($varianteId, $materialId);
        if (!$validation['valid']) {
            // Redirigir con error en sesión
            $_SESSION['bom_error'] = $validation['error'];
            return Response::redirect($redirectUrl);
        }

        // Get or Create BOM Header
        $bom = $this->bomModel->getActiveByVariante($varianteId);
        $bomId = $bom ? (int)$bom['id'] : $this->bomModel->createHeader($varianteId);

        $this->bomModel->addDetail($bomId, $materialId, $cantidad, $unidadId);

        $_SESSION['bom_success'] = 'Componente agregado exitosamente';
        return Response::redirect($redirectUrl);
    }

    public function editItem(Request $request, $id): Response
    {
        $id = (int) $id;
        // We reuse index logic but pass editing data
        // For simplicity, we can redirect to index with a query param if creating the same view logic is complex,
        // OR properly fetch data here. Given the context, properly fetching is better.

        $item = $this->bomModel->getDetalleById($id);
        if (!$item) {
            return Response::redirect(url('/productos/maestro'));
        }

        $selectedVarianteId = (int) $item['variante_padre_id'];

        // --- Reuse Index Logic (Ideally Refactor this) ---
        $variantesList = $this->variantes->allWithPartes();
        $variantes = [];
        foreach ($variantesList as $v) {
            $variantes[$v['id']] = $v;
        }

        $composicion = [];
        $bom = $this->bomModel->getActiveByVariante($selectedVarianteId);
        if ($bom) {
            $composicion = $this->bomModel->getDetalles((int) $bom['id']);
        }

        $materiales = array_filter($variantesList, fn($v) => (int)$v['id'] !== $selectedVarianteId);
        // --------------------------------------------------

        return $this->render('pages/productos/composicion/index', [
            'title' => 'Editar Componente',
            'variantes' => $variantes,
            'materiales' => $materiales,
            'unidades' => $this->unidades->all(),
            'selectedVarianteId' => $selectedVarianteId, // From item
            'composicion' => $composicion,
            'activeBom' => $bom,
            'viewMode' => 'list',
            'editingMaterialId' => $id,
            'editingMaterial' => $item,
            'errors' => [],
            'materialOldValue' => function ($key, $default = '') use ($item) {
                if ($key === 'id_material') return $item['variante_componente_id'];
                if ($key === 'cantidad') return $item['cantidad_necesaria'];
                if ($key === 'id_unidad') return $item['unidad_medida_id'];
                return $default;
            }
        ]);
    }

    public function updateItem(Request $request, $id): Response
    {
        $id = (int) $id;
        $varianteId = (int) $request->input('id_variante'); // Contexto del padre

        // Logic for replacement
        if ($request->input('action') === 'replace_variant') {
            $newVarianteId = (int) $request->input('new_variante_id');
            if ($newVarianteId) {
                $this->bomModel->replaceComponent($id, $newVarianteId);
            }
        } else {
            // Logic for standard update
            $cantidad = $this->normalizeCantidad((float) $request->input('cantidad'));
            $unidadId = (int) $request->input('id_unidad');
            if ($cantidad) { // Only update if provided
                $this->bomModel->updateDetail($id, $cantidad, $unidadId);
            }
        }

        $redirectUrl = url('/productos/maestro?id_variante=' . $varianteId);

        return Response::redirect($redirectUrl);
    }

    private function normalizeCantidad(float $cantidad): float
    {
        $settings = app_general_settings();
        $decimals = (int) ($settings['decimal_places'] ?? 4);
        $decimals = max(1, min(6, $decimals));
        $mode = (string) ($settings['rounding_mode'] ?? 'half_up');

        return app_round_decimal($cantidad, $decimals, $mode);
    }

    public function deleteItem(Request $request, $id): Response
    {
        $id = (int) $id;
        $varianteId = (int) $request->input('id_variante'); // Assuming sent via query or hidden

        // If not sent, we might lose context where to redirect, so we try to find it first if crucial
        if (!$varianteId) {
            $item = $this->bomModel->getDetalleById($id);
            $varianteId = $item['variante_padre_id'] ?? null;
        }

        $this->bomModel->deleteDetail($id);

        $redirectUrl = url('/productos/maestro?id_variante=' . $varianteId);

        return Response::redirect($redirectUrl);
    }
}
