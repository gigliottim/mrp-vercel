<?php

use App\Core\View\View;

$variantes = $variantes ?? [];
$selectedVarianteId = $selectedVarianteId ?? null;
$varianteSeleccionada = $varianteSeleccionada ?? null;
$composicionRama1 = $composicionRama1 ?? [];
$composicionPlana = $composicionPlana ?? [];
$composicionArbol = $composicionArbol ?? [];
$dondeSeUtiliza = $dondeSeUtiliza ?? [];

?>

<style>
    .tree-node {
        cursor: default;
        padding: 8px 10px;
        border-radius: 6px;
        transition: background-color 0.2s;
        display: flex;
        align-items: center;
        margin-bottom: 4px;
    }

    .tree-node:hover {
        background-color: #f8f9fa;
    }

    .reporte-tree {
        font-size: 0.95rem;
    }

    .reporte-tree .fa-solid {
        font-size: 1.1rem;
    }

    .reporte-tree .fa-folder {
        color: #ffc107 !important;
    }

    .reporte-tree .fa-cube {
        color: #0dcaf0 !important;
    }

    .reporte-tree .badge {
        font-size: 0.75rem;
        padding: 0.25em 0.6em;
    }
</style>

<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Reportes</p>
            <h1 class="h3 mb-0">Destino de Partes</h1>
            <p class="text-muted small mb-0">Visualiza cómo se compone una parte y dónde se utiliza</p>
        </div>
    </div>
</section>

<!-- Selector de Variante con SearchClient -->
<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Seleccionar variante</h2>
        <?php if ($varianteSeleccionada): ?>
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="flex-grow-1">
                    <div class="alert alert-success mb-0 py-2 px-3">
                        <i class="fa-solid fa-check-circle me-2"></i>
                        <span class="fw-semibold">Parte:</span>
                        <strong><?= View::escape((string) ($varianteSeleccionada['parte_codigo'] ?? 'N/A')) ?></strong>
                        <span class="text-muted mx-1">-</span>
                        <span><?= View::escape((string) ($varianteSeleccionada['parte_detalle'] ?? '')) ?></span>
                        <span class="text-muted mx-2">|</span>
                        <span class="fw-semibold">Variante:</span>
                        <strong><?= View::escape((string) ($varianteSeleccionada['codigo_variante'] ?? 'N/A')) ?></strong>
                        <span class="text-muted mx-1">-</span>
                        <span><?= View::escape((string) ($varianteSeleccionada['detalle'] ?? '')) ?></span>
                    </div>
                </div>
                <a href="<?= url('reportes/destino-partes') ?>" class="btn btn-outline-primary">
                    <i class="fa-solid fa-exchange-alt"></i> Cambiar
                </a>
            </div>
        <?php endif; ?>

        <div id="search-container-reporte" style="<?= $varianteSeleccionada ? 'display:none' : '' ?>; overflow: visible;">
            <div class="position-relative" style="overflow: visible;">
                <input type="text"
                    id="reporte-search-input"
                    class="form-control form-control-lg"
                    placeholder="Buscar variante por código o descripción..."
                    autocomplete="off">
                <div id="reporte-search-results" class="search-results"></div>
            </div>
            <small class="text-muted d-block mt-2">
                <i class="fa-solid fa-info-circle"></i> Escribe al menos 2 caracteres para buscar
            </small>
        </div>
    </div>
</div>

<!-- Contenedor Principal: 50% + 50% -->
<div class="row g-4">
    <!-- SECCIÓN IZQUIERDA: CÓMO SE COMPONE -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h3 class="h5 mb-0">
                    <i class="fa-solid fa-layer-group me-2"></i>
                    Cómo se compone
                </h3>
            </div>
            <div class="card-body">
                <?php if (!$varianteSeleccionada): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fa-solid fa-layer-group fa-3x mb-3 opacity-25"></i>
                        <p>Busca y selecciona una variante para ver su composición.</p>
                    </div>
                <?php else: ?>
                    <!-- Tabs para Rama 1, Vista Plana y Vista Árbol -->
                    <ul class="nav nav-tabs mb-3" id="composicionTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="vista-rama1-tab" data-bs-toggle="tab"
                                data-bs-target="#vista-rama1" type="button" role="tab"
                                aria-controls="vista-rama1" aria-selected="true">
                                <i class="fa-solid fa-level-down-alt me-1"></i>
                                Rama 1
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="vista-plana-tab" data-bs-toggle="tab"
                                data-bs-target="#vista-plana" type="button" role="tab"
                                aria-controls="vista-plana" aria-selected="false">
                                <i class="fa-solid fa-list me-1"></i>
                                Plana
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="vista-arbol-tab" data-bs-toggle="tab"
                                data-bs-target="#vista-arbol" type="button" role="tab"
                                aria-controls="vista-arbol" aria-selected="false">
                                <i class="fa-solid fa-sitemap me-1"></i>
                                Árbol
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="composicionTabsContent">
                        <!-- VISTA RAMA 1 (SOLO PRIMER NIVEL) -->
                        <div class="tab-pane fade show active" id="vista-rama1" role="tabpanel"
                            aria-labelledby="vista-rama1-tab">
                            <?php if (empty($composicionRama1)) : ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="fa-solid fa-info-circle me-2"></i>
                                    No hay componentes de primer nivel para esta variante
                                </div>
                            <?php else : ?>
                                <div class="alert alert-info alert-sm mb-3">
                                    <i class="fa-solid fa-info-circle me-2"></i>
                                    <small>Muestra solo los componentes directos del primer nivel</small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Componente</th>
                                                <th>Detalle</th>
                                                <th class="text-end">Cantidad</th>
                                                <th>Unidad</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($composicionRama1 as $item) : ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= View::escape($item['componente_codigo'] ?? 'N/A') ?></strong>
                                                    </td>
                                                    <td class="text-muted small">
                                                        <?= View::escape($item['componente_detalle'] ?? '') ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <?= number_format((float)($item['cantidad_necesaria'] ?? 0), 4) ?>
                                                    </td>
                                                    <td>
                                                        <?= View::escape($item['unidad_simbolo'] ?? 'UN') ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- VISTA PLANA (TODOS LOS NIVELES CONSOLIDADOS) -->
                        <div class="tab-pane fade" id="vista-plana" role="tabpanel"
                            aria-labelledby="vista-plana-tab">
                            <?php if (empty($composicionPlana)) : ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="fa-solid fa-info-circle me-2"></i>
                                    No hay componentes definidos para esta variante
                                </div>
                            <?php else : ?>
                                <div class="alert alert-info alert-sm mb-3">
                                    <i class="fa-solid fa-info-circle me-2"></i>
                                    <small>Consolida todos los componentes de todos los niveles (cantidades sumadas)</small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Componente</th>
                                                <th>Detalle</th>
                                                <th class="text-end">Cantidad Total</th>
                                                <th>Unidad</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($composicionPlana as $item) : ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= View::escape($item['componente_codigo'] ?? 'N/A') ?></strong>
                                                    </td>
                                                    <td class="text-muted small">
                                                        <?= View::escape($item['componente_detalle'] ?? '') ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <?= number_format((float)($item['cantidad_necesaria'] ?? 0), 4) ?>
                                                    </td>
                                                    <td>
                                                        <?= View::escape($item['unidad_simbolo'] ?? 'UN') ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- VISTA ÁRBOL (ESTRUCTURA JERÁRQUICA) -->
                        <div class="tab-pane fade" id="vista-arbol" role="tabpanel"
                            aria-labelledby="vista-arbol-tab">
                            <?php if (empty($composicionArbol)) : ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="fa-solid fa-info-circle me-2"></i>
                                    No hay estructura jerárquica para esta variante
                                </div>
                            <?php else : ?>
                                <div class="alert alert-info alert-sm mb-3">
                                    <i class="fa-solid fa-info-circle me-2"></i>
                                    <small>Muestra la estructura jerárquica completa con todos los niveles</small>
                                </div>
                                <?php
                                // Crear mapa de padres para determinar qué nodos tienen hijos
                                $hasChildren = [];
                                foreach ($composicionArbol as $node) {
                                    if (isset($node['parent_id']) && $node['parent_id'] !== null) {
                                        $hasChildren[$node['parent_id']] = true;
                                    }
                                }
                                ?>
                                <div class="reporte-tree">
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($composicionArbol as $item) : ?>
                                            <?php
                                            // Determinar si este nodo tiene hijos
                                            $itemHasChildren = isset($hasChildren[$item['variante_id']]);

                                            // Asignar icono y color según si tiene hijos
                                            if ($itemHasChildren) {
                                                $iconClass = 'fa-folder text-warning';
                                            } else {
                                                $iconClass = 'fa-cube text-info';
                                            }
                                            ?>
                                            <li>
                                                <div class="tree-node d-flex align-items-start"
                                                    style="margin-left: <?= ($item['nivel'] ?? 0) * 20 ?>px">
                                                    <i class="fa-solid <?= $iconClass ?> me-2"></i>
                                                    <div class="flex-grow-1">
                                                        <div>
                                                            <strong><?= View::escape($item['codigo_variante'] ?? 'N/A') ?></strong>
                                                            <?php if (isset($item['cantidad']) && $item['cantidad'] > 0) : ?>
                                                                <span class="badge bg-secondary ms-2">
                                                                    <?= number_format((float)$item['cantidad'], 2) ?>
                                                                    <?= View::escape($item['unidad'] ?? 'UN') ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($item['tipo_codigo'])) : ?>
                                                                <span class="badge bg-info ms-1"><?= View::escape($item['tipo_codigo']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-muted small"><?= View::escape($item['variante_detalle'] ?? '') ?></div>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SECCIÓN DERECHA: DÓNDE SE UTILIZA -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h3 class="h5 mb-0">
                    <i class="fa-solid fa-arrow-up-right-from-square me-2"></i>
                    Dónde se utiliza
                </h3>
            </div>
            <div class="card-body">
                <?php if (!$varianteSeleccionada): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fa-solid fa-arrow-up-right-from-square fa-3x mb-3 opacity-25"></i>
                        <p>Busca y selecciona una variante para ver dónde se utiliza.</p>
                    </div>
                <?php elseif (empty($dondeSeUtiliza)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        Esta variante no se utiliza como componente en ninguna otra BOM
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto/Padre</th>
                                    <th>Detalle</th>
                                    <th class="text-end">Cantidad</th>
                                    <th>Unidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dondeSeUtiliza as $uso) : ?>
                                    <tr>
                                        <td>
                                            <strong><?= View::escape($uso['padre_codigo'] ?? 'N/A') ?></strong>
                                            <div class="text-muted small">
                                                <?= View::escape($uso['padre_parte'] ?? '') ?>
                                            </div>
                                        </td>
                                        <td class="text-muted small">
                                            <?= View::escape($uso['padre_detalle'] ?? '') ?>
                                        </td>
                                        <td class="text-end">
                                            <?= number_format((float)($uso['cantidad_necesaria'] ?? 0), 4) ?>
                                        </td>
                                        <td>
                                            <?= View::escape($uso['unidad_codigo'] ?? 'UN') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    /* Estilos para el árbol */
    .reporte-tree {
        padding: 1rem;
        background-color: #f8f9fa;
        border-radius: 0.25rem;
        max-height: 500px;
        overflow-y: auto;
    }

    .tree-list {
        padding-left: 1.5rem;
        margin-top: 0.5rem;
    }

    .tree-list>li:not(:last-child) {
        padding-bottom: 0.5rem;
        border-left: 2px solid #dee2e6;
        margin-left: 0.5rem;
        padding-left: 1rem;
    }

    .tree-node {
        padding: 0.5rem;
        background-color: #fff;
        border-radius: 0.25rem;
        border: 1px solid #dee2e6;
        transition: all 0.2s;
    }

    .tree-node:hover {
        background-color: #f1f3f5;
        border-color: #adb5bd;
    }

    /* Ajustes para las cards */
    .card.h-100 {
        min-height: 600px;
    }

    .card-body {
        overflow-y: auto;
    }

    /* Permitir que SearchClient se visualice completamente */
    .card-body:has(#search-container-reporte) {
        overflow: visible !important;
    }

    /* Estilos para SearchClient */
    #reporte-search-results {
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

    #reporte-search-results .search-result-item {
        padding: 0.5rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s;
    }

    #reporte-search-results .search-result-item:hover,
    #reporte-search-results .search-result-item.active {
        background-color: #f8f9fa;
    }

    #reporte-search-results .search-result-item:last-child {
        border-bottom: none;
    }

    #reporte-search-input:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
</style>

<?php

use App\Core\Support\AssetHelper;
?>
<script src="<?= AssetHelper::js('modules/SearchClient.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('reporte-search-input');
        const searchResults = document.getElementById('reporte-search-results');

        if (searchInput && searchResults) {
            const reporteSearchInstance = new SearchClient({
                endpoint: '<?= url('api/v1/search/variantes') ?>',
                inputElement: searchInput,
                resultsContainer: searchResults,
                minChars: 2,
                debounceDelay: 300,
                maxResults: 15,
                format: 'detailed',
                onSelect: (item) => {
                    window.location.href = '<?= url('reportes/destino-partes') ?>?id_variante=' + item.id;
                }
                // customItemRender removido para usar el nuevo default moderno de SearchClient.js
            });

            // Auto-focus si no hay variante seleccionada
            <?php if (!$varianteSeleccionada): ?>
                setTimeout(() => {
                    searchInput.focus();
                }, 100);
            <?php endif; ?>
        }
    });
</script>
