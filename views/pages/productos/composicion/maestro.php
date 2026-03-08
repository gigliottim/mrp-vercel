<?php

use App\Core\Support\AssetHelper;
use App\Core\View\View;

$variantes = $variantes ?? [];
$materiales = $materiales ?? [];
$unidades = $unidades ?? [];
$tiposPartes = $tiposPartes ?? [];
$selectedVarianteId = $selectedVarianteId ?? null;
$treeData = $treeData ?? [];
$rootBOM = $rootBOM ?? null;

$selectedVariante = null;
if ($selectedVarianteId && isset($variantes[$selectedVarianteId])) {
    $selectedVariante = $variantes[$selectedVarianteId];
}

// Mensajes de validación
$bomError = $_SESSION['bom_error'] ?? null;
$bomSuccess = $_SESSION['bom_success'] ?? null;
unset($_SESSION['bom_error'], $_SESSION['bom_success']);

?>
<style>
    .tree-node {
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
    }

    .tree-node:hover {
        background-color: #f0f0f0;
    }

    .tree-node.selected {
        background-color: #e9ecef;
        font-weight: bold;
    }

    .tree-indent {
        margin-left: 20px;
        border-left: 1px solid #ccc;
        padding-left: 10px;
    }

    /* Estilos mejorados para botones de filtro activos */
    .btn-tipo-filter {
        transition: all 0.3s ease;
        font-weight: 500;
        min-width: 60px;
    }

    .btn-tipo-filter:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .btn-tipo-filter.active {
        font-weight: bold;
        box-shadow: inset 0 3px 8px rgba(0, 0, 0, 0.2), 0 0 0 3px rgba(0, 0, 0, 0.1);
        transform: scale(1.05);
    }

    .btn-outline-secondary.active {
        background-color: #6c757d !important;
        color: white !important;
        border-color: #6c757d !important;
    }

    .btn-outline-info.active {
        background-color: #0dcaf0 !important;
        color: white !important;
        border-color: #0dcaf0 !important;
    }

    .btn-outline-success.active {
        background-color: #198754 !important;
        color: white !important;
        border-color: #198754 !important;
    }

    .btn-outline-warning.active {
        background-color: #ffc107 !important;
        color: #000 !important;
        border-color: #ffc107 !important;
    }

    .btn-outline-danger.active {
        background-color: #dc3545 !important;
        color: white !important;
        border-color: #dc3545 !important;
    }

    .btn-outline-primary.active {
        background-color: #0d6efd !important;
        color: white !important;
        border-color: #0d6efd !important;
    }

    .btn-outline-dark.active {
        background-color: #212529 !important;
        color: white !important;
        border-color: #212529 !important;
    }

    /* Estilos para vista de árbol jerárquico */
    .tree-view-container {
        max-height: 600px;
        overflow-y: auto;
    }

    .tree-item {
        transition: all 0.2s ease;
    }

    .tree-item:hover {
        transform: translateX(4px);
    }

    .tree-item .border {
        border-color: #dee2e6 !important;
        transition: all 0.2s ease;
    }

    .tree-item:hover .border {
        border-color: #0d6efd !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Estilos para el buscador principal */
    #search-container {
        position: relative;
    }

    #main-search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1050;
        max-height: 400px;
        overflow-y: auto;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        margin-top: 0.25rem;
    }

    #main-search-results .search-result-item {
        padding: 0.5rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s;
    }

    #main-search-results .search-result-item:hover,
    #main-search-results .search-result-item.active {
        background-color: #f8f9fa;
    }

    #main-search-results .search-result-item:last-child {
        border-bottom: none;
    }

    #main-search-input:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    /* Estilos para el buscador del modal */
    #modal-search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1060;
        max-height: 300px;
        overflow-y: auto;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        margin-top: 0.25rem;
    }

    #modal-search-results .search-result-item {
        padding: 0.5rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s;
    }

    #modal-search-results .search-result-item:hover,
    #modal-search-results .search-result-item.active {
        background-color: #f8f9fa;
    }

    #modal-search-results .search-result-item:last-child {
        border-bottom: none;
    }

    #modal-search-input:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
</style>

<div x-data="maestroApp()" class="h-100 d-flex flex-column">
    <section class="mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <p class="text-uppercase text-muted small mb-1">Productos</p>
                <h1 class="h3 mb-0">Composición de variantes</h1>
                <p class="text-muted small mb-0">Define los materiales y cantidades que componen cada variante</p>
            </div>
        </div>
    </section>

    <!-- Mensajes de validación -->
    <?php if ($bomError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <strong>Error:</strong> <?= View::escape($bomError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($bomSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>
            <?= View::escape($bomSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Selector Global con Búsqueda -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="row align-items-center g-3">
                <div class="col-auto">
                    <label for="variante-search-input" class="col-form-label fw-bold">Producto Maestro:</label>
                </div>
                <div class="col">
                    <?php if ($selectedVariante): ?>
                        <!-- Producto actual seleccionado -->
                        <div class="d-flex align-items-center gap-3">
                            <div class="flex-grow-1">
                                <div class="alert alert-info mb-0 py-2 px-3 d-flex align-items-center">
                                    <i class="fa-solid fa-sitemap me-2"></i>
                                    <div>
                                        <strong>
                                            <?= View::escape((string) ($selectedVariante['parte_codigo'] ?? 'N/A')) ?>-<?= View::escape((string) ($selectedVariante['codigo_variante'] ?? 'N/A')) ?>
                                        </strong>
                                        <span class="text-muted mx-1"> </span>
                                        <span>
                                            <?= View::escape((string) ($selectedVariante['parte_detalle'] ?? '')) ?> - <?= View::escape((string) ($selectedVariante['detalle'] ?? '')) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= url('reportes/destino-partes?id_variante=' . $selectedVarianteId) ?>" class="btn btn-outline-secondary" title="Destino de partes">
                                    <i class="fa-solid fa-layer-group"></i> Destino de partes
                                </a>
                                <button type="button" class="btn btn-outline-primary" onclick="window.location.href = '<?= url('productos/maestro') ?>';">
                                    <i class="fa-solid fa-exchange-alt"></i> Cambiar producto
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Input de búsqueda (visible solo cuando se va a cambiar o no hay selección) -->
                    <div id="search-container" style="<?= $selectedVariante ? 'display:none' : '' ?>">
                        <!-- Filtros por tipo -->
                        <div class="btn-group btn-group-sm mb-3 w-100" role="group" id="tipo-filters">
                            <button type="button"
                                class="btn btn-tipo-filter btn-outline-secondary active"
                                data-tipo=""
                                onclick="updateSearchFilter('')">
                                <i class="fa-solid fa-border-all"></i> Todos
                            </button>
                            <?php
                            $colores = ['info', 'success', 'warning', 'danger', 'primary', 'dark'];
                            foreach ($tiposPartes as $index => $tipo):
                                $color = $colores[$index % count($colores)];
                            ?>
                                <button type="button"
                                    class="btn btn-tipo-filter btn-outline-<?= $color ?>"
                                    data-tipo="<?= View::escape($tipo['codigo']) ?>"
                                    onclick="updateSearchFilter('<?= View::escape($tipo['codigo']) ?>')"
                                    title="<?= View::escape($tipo['nombre']) ?>">
                                    <strong><?= View::escape($tipo['codigo']) ?></strong>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="position-relative">
                            <input type="text"
                                id="main-search-input"
                                class="form-control form-control-lg"
                                placeholder="Buscar producto maestro por código o descripción..."
                                autocomplete="off">
                            <div id="main-search-results" class="search-results"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 flex-grow-1">
        <!-- Izquierda: Árbol -->
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Estructura</h5>
                </div>
                <div class="card-body overflow-auto" style="max-height: 600px;">
                    <!-- Tree Container -->
                    <template x-if="flatTree.length === 0">
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-sitemap fa-3x mb-3 opacity-25"></i>
                            <p>Busca y selecciona un producto maestro para ver su estructura.</p>
                        </div>
                    </template>

                    <ul class="list-unstyled">
                        <!-- Using a different strategy: Render flat list with indentation based on 'nivel' -->
                        <template x-for="(item, index) in flatTree" :key="getNodeInstanceKey(item) + '-' + index">
                            <div class="tree-node"
                                :style="`margin-left: ${item.nivel * 20}px`"
                                :class="{'selected': isSelectedNode(item)}"
                                @click="selectNode(item)">
                                <i class="fa-solid" :class="item.icon_class"></i>
                                <span class="fw-bold" x-text="(item.parte_codigo || 'N/A') + '-' + (item.codigo_variante || 'N/A')"></span>
                                <small class="text-muted d-block" x-text="(item.parte_detalle || '') + ' - ' + (item.variante_detalle || '')"></small>
                            </div>
                        </template>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Derecha: Detalles del nodo seleccionado -->
        <div class="col-12 col-md-8">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0" x-text="selectedNode ? getItemCode(selectedNode) + ' - ' + getItemDetail(selectedNode) : 'Seleccione un nodo'"></h5>

                    <!-- Botones de vista y acciones -->
                    <div class="d-flex gap-2" x-show="selectedNode">
                        <!-- Botones de cambio de vista -->
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button"
                                class="btn"
                                :class="viewMode === 'list' ? 'btn-primary' : 'btn-outline-primary'"
                                @click="setViewMode('list')"
                                title="Vista de lista">
                                <i class="fa-solid fa-list"></i> Lista
                            </button>
                            <button type="button"
                                class="btn"
                                :class="viewMode === 'tree' ? 'btn-primary' : 'btn-outline-primary'"
                                @click="setViewMode('tree')"
                                title="Vista de árbol">
                                <i class="fa-solid fa-sitemap"></i> Árbol
                            </button>
                        </div>

                        <!-- Botón agregar componente -->
                        <button class="btn btn-sm btn-success" @click="openAddModal()" :disabled="!selectedNode">
                            <i class="fa-solid fa-plus"></i> Agregar
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <template x-if="!selectedNode">
                        <div class="text-center text-muted p-5">
                            <i class="fa-solid fa-sitemap fa-3x mb-3"></i>
                            <p>Selecciona un elemento del árbol para ver sus componentes.</p>
                        </div>
                    </template>

                    <template x-if="selectedNode">
                        <div>
                            <!-- Vista de Lista -->
                            <div x-show="viewMode === 'list'">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Detalle</th>
                                                <th>Cant.</th>
                                                <th>Unidad</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(child, childIndex) in getChildren(selectedNode)" :key="getNodeInstanceKey(child) + '-' + (child.bom_detalle_id || child.variante_id || childIndex)">
                                                <tr>
                                                    <td x-text="getItemCode(child)"></td>
                                                    <td>
                                                        <div x-text="getItemDetail(child)"></div>
                                                        <small class="text-muted" x-text="child.tipo_codigo"></small>
                                                    </td>
                                                    <td x-text="formatQuantity(child.cantidad)"></td>
                                                    <td x-text="child.unidad"></td>
                                                    <td class="text-end">
                                                        <?php
                                                        $options = [
                                                            'variante_id' => 0,
                                                            'parte_id' => 0,
                                                            'context' => 'alpine',
                                                            'alpine_data' => 'child',
                                                            'show_edit' => true,
                                                            'show_replace' => true,
                                                            'show_delete' => true,
                                                            'show_gestionar' => true,
                                                            'show_maestro' => true,
                                                            'show_destino' => true,
                                                            'size' => 'sm'
                                                        ];
                                                        // Ajustar ruta relativa desde views/pages/productos/composicion/
                                                        include __DIR__ . '/../../../partials/_variante_action_buttons.php';
                                                        ?>
                                                    </td>
                                                </tr>
                                            </template>
                                            <template x-if="getChildren(selectedNode).length === 0">
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">
                                                        Este ítem no tiene componentes definidos.
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Vista de Árbol -->
                            <div x-show="viewMode === 'tree'">
                                <div class="tree-view-container">
                                    <template x-if="getNodeTree(selectedNode).length === 0">
                                        <div class="text-center text-muted p-4">
                                            <i class="fa-solid fa-tree fa-2x mb-2"></i>
                                            <p>Este ítem no tiene componentes definidos.</p>
                                        </div>
                                    </template>

                                    <template x-if="getNodeTree(selectedNode).length > 0">
                                        <div class="p-3">
                                            <template x-for="(item, index) in getNodeTree(selectedNode)" :key="getNodeInstanceKey(item) + '-' + index">
                                                <div class="tree-item mb-2"
                                                    :style="`margin-left: ${item.level * 24}px`">
                                                    <div class="d-flex align-items-center gap-2 p-2 border rounded bg-light">
                                                        <i class="fa-solid" :class="hasChildrenInTree(item) ? 'fa-folder text-warning' : 'fa-cube text-info'"></i>
                                                        <div class="flex-grow-1">
                                                            <strong x-text="getItemCode(item)"></strong>
                                                            <small class="text-muted ms-2" x-text="getItemDetail(item)"></small>
                                                        </div>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="badge bg-secondary" x-text="formatQuantity(item.cantidad) + ' ' + item.unidad"></span>
                                                            <span class="badge bg-info" x-text="item.tipo_codigo"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Modales -->
    <?php include __DIR__ . '/_modalEditar.php'; ?>
    <?php include __DIR__ . '/_modalAgregar.php'; ?>
    <?php include __DIR__ . '/_modalReemplazar.php'; ?>

</div>

<!-- Scripts -->
<script src="<?= AssetHelper::js('modules/SearchClient.js') ?>"></script>
<script src="<?= AssetHelper::js('composicion-maestro.js') ?>"></script>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('maestroApp', () => createComposicionMaestroApp({
            treeData: <?= json_encode($treeData ?: []) ?>,
            variantes: <?= json_encode($variantes ?: []) ?>,
            apiSearchUrl: '<?= url('api/v1/search/variantes') ?>',
            baseActionUrl: '<?= url('productos/maestro/materiales') ?>',
            selectedVarianteId: <?= (int) ($selectedVarianteId ?? 0) ?>
        }));
    });

    // Inicializar buscador principal de productos
    let currentMainFilters = {};
    let mainSearchInstance = null;

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('main-search-input');
        const searchResults = document.getElementById('main-search-results');

        if (searchInput && searchResults) {
            mainSearchInstance = new SearchClient({
                endpoint: '<?= url('api/v1/search/variantes') ?>',
                inputElement: searchInput,
                resultsContainer: searchResults,
                minChars: 2,
                debounceDelay: 300,
                maxResults: 15,
                format: 'detailed',
                filters: currentMainFilters,
                onSelect: (item) => {
                    // Redirigir a la misma página con el nuevo ID de variante
                    window.location.href = '<?= url('productos/maestro') ?>?id_variante=' + item.id;
                },
                customItemRender: undefined // Usar renderizado por defecto moderno
            });

            // Auto-focus en el campo de búsqueda si no hay variante seleccionada
            <?php if (!$selectedVariante): ?>
                setTimeout(() => {
                    searchInput.focus();
                }, 100);
            <?php endif; ?>
        }
    });

    // Función para actualizar filtro de tipo
    function updateSearchFilter(tipoFilter) {
        // Actualizar botones activos
        document.querySelectorAll('#tipo-filters .btn-tipo-filter').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.closest('.btn-tipo-filter').classList.add('active');

        // Actualizar filtros
        currentMainFilters = tipoFilter ? {
            tipo_codigo: tipoFilter
        } : {};

        // Actualizar filtros en SearchClient
        if (mainSearchInstance) {
            mainSearchInstance.filters = currentMainFilters;
            // Si hay texto, reejecutar búsqueda
            const searchInput = document.getElementById('main-search-input');
            if (searchInput && searchInput.value.trim().length >= 2) {
                searchInput.dispatchEvent(new Event('input'));
            }
        }
    }

    // SearchClient para Modal Agregar Componente
    let modalSearchInstance = null;
    let currentModalFilters = {};

    // Inicializar cuando se abre el modal
    document.addEventListener('DOMContentLoaded', () => {
        const modalElement = document.getElementById('modalAgregar');
        if (modalElement) {
            modalElement.addEventListener('shown.bs.modal', function() {
                // Inicializar SearchClient del modal
                if (!modalSearchInstance) {
                    const modalInput = document.getElementById('modal-search-input');
                    const modalResults = document.getElementById('modal-search-results');
                    const modalHiddenInput = document.getElementById('modal-id-material');

                    if (modalInput && modalResults && modalHiddenInput) {
                        // Obtener ID de variante actual para excluirla
                        const currentVarianteId = <?= $selectedVarianteId ?? 0 ?>;

                        modalSearchInstance = new SearchClient({
                            endpoint: '<?= url('api/v1/search/variantes') ?>',
                            inputElement: modalInput,
                            resultsContainer: modalResults,
                            hiddenInput: modalHiddenInput,
                            minChars: 2,
                            debounceDelay: 300,
                            maxResults: 10,
                            format: 'detailed',
                            filters: {
                                exclude_ids: currentVarianteId > 0 ? [currentVarianteId] : []
                            },
                            onSelect: (item) => {
                                // Actualizar campo oculto
                                modalHiddenInput.value = item.id;

                                const modalForm = modalElement.querySelector('form');
                                const unidadSelect = modalForm?.querySelector('select[name="id_unidad"]');
                                const cantidadInput = modalForm?.querySelector('input[name="cantidad"]');

                                const selectedUmUsoId = Number.parseInt(item.id_um_uso, 10);
                                if (unidadSelect && Number.isInteger(selectedUmUsoId) && selectedUmUsoId > 0) {
                                    unidadSelect.value = String(selectedUmUsoId);
                                }

                                const umUsoTipo = String(item.um_uso_tipo || '').toLowerCase();
                                const umUsoCodigo = String(item.um_uso_codigo || '').toLowerCase().replace(/\s+/g, '');
                                const usoEsSuperficie = umUsoTipo === 'superficie' || ['m2', 'm²'].includes(umUsoCodigo);

                                if (cantidadInput) {
                                    const parentSuperficie = Number.parseFloat(modalElement.dataset.parentSuperficie || '');
                                    if (usoEsSuperficie && Number.isFinite(parentSuperficie) && parentSuperficie > 0) {
                                        cantidadInput.value = String(parentSuperficie);
                                    }
                                }

                                // Mostrar selección con variante completa (codigo + detalle)
                                const codigoCompleto = [item.parte_codigo, item.codigo_variante]
                                    .map(value => String(value || '').trim())
                                    .filter(Boolean)
                                    .join('-') || String(item.codigo_variante || 'Sin codigo');

                                const detalleCompleto = [item.parte_detalle, item.detalle]
                                    .map(value => String(value || '').trim())
                                    .filter(Boolean)
                                    .join(' - ') || 'Sin detalle';

                                document.getElementById('modal-selected-codigo').textContent = codigoCompleto;
                                document.getElementById('modal-selected-detalle').textContent = detalleCompleto;
                                document.getElementById('modal-selected-display').style.display = 'block';

                                // Limpiar input y ocultar resultados
                                modalInput.value = '';
                                modalResults.style.display = 'none';
                            }
                        });
                    }
                }

                // Focus en el input
                setTimeout(() => {
                    document.getElementById('modal-search-input')?.focus();
                }, 150);
            });

            // Limpiar cuando se cierra el modal
            modalElement.addEventListener('hidden.bs.modal', function() {
                clearModalSelection();
            });
        }
    });

    // Función para actualizar filtro de tipo en modal
    function updateModalSearchFilter(tipoFilter) {
        // Actualizar botones activos
        document.querySelectorAll('#modal-tipo-filters .btn-tipo-filter').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.closest('.btn-tipo-filter').classList.add('active');

        // Actualizar filtros
        const currentVarianteId = <?= $selectedVarianteId ?? 0 ?>;
        currentModalFilters = tipoFilter ? {
            tipo_codigo: tipoFilter,
            exclude_ids: currentVarianteId > 0 ? [currentVarianteId] : []
        } : {
            exclude_ids: currentVarianteId > 0 ? [currentVarianteId] : []
        };

        // Actualizar filtros en SearchClient
        if (modalSearchInstance) {
            modalSearchInstance.filters = currentModalFilters;
            // Si hay texto, reejecutar búsqueda
            const modalInput = document.getElementById('modal-search-input');
            if (modalInput && modalInput.value.trim().length >= 2) {
                modalInput.dispatchEvent(new Event('input'));
            }
        }
    }

    // Función para limpiar selección del modal
    function clearModalSelection() {
        document.getElementById('modal-search-input').value = '';
        document.getElementById('modal-id-material').value = '';
        document.getElementById('modal-selected-display').style.display = 'none';
        document.getElementById('modal-search-results').style.display = 'none';
    }
</script>
