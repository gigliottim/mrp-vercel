<?php

use App\Core\View\View;

$variantes = $variantes ?? [];
$selectedVarianteId = $selectedVarianteId ?? null;
$varianteSeleccionada = $varianteSeleccionada ?? null;
$cantidad = $cantidad ?? 1.0;
$tipoSalida = $tipoSalida ?? 'arbol';
$conPrecios = $conPrecios ?? false;
$datosReporte = $datosReporte ?? [];

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

    .card-body:has(#search-container-reporte) {
        overflow: visible !important;
    }

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

<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Reportes</p>
            <h1 class="h3 mb-0">Listado de Ingeniería</h1>
            <p class="text-muted small mb-0">Genera listados de materiales con múltiples vistas y opciones</p>
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
                <a href="<?= url('reportes/listado-ingenieria') ?>" class="btn btn-outline-primary">
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

<!-- Opciones del reporte -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h2 class="h5 mb-0">
            <i class="fa-solid fa-sliders me-2"></i>
            Opciones del reporte
        </h2>
    </div>
    <div class="card-body">
        <?php if (!$varianteSeleccionada): ?>
            <div class="text-center text-muted py-3">
                <i class="fa-solid fa-sliders fa-2x mb-2 opacity-25"></i>
                <p class="mb-0">Selecciona una variante para configurar las opciones del reporte.</p>
            </div>
        <?php else: ?>
            <form method="GET" action="<?= url('reportes/listado-ingenieria') ?>" class="row g-3">
                <input type="hidden" name="id_variante" value="<?= $selectedVarianteId ?>">
                <input type="hidden" name="filtros_aplicados" value="1">

                <div class="col-md-2">
                    <label for="cantidad" class="form-label">Cantidad</label>
                    <input type="number"
                        class="form-control"
                        id="cantidad"
                        name="cantidad"
                        value="<?= $cantidad ?>"
                        step="0.01"
                        min="0.01"
                        required>
                </div>

                <div class="col-md-2">
                    <label for="tipo_salida" class="form-label">Tipo de salida</label>
                    <select class="form-select" id="tipo_salida" name="tipo_salida">
                        <option value="arbol" <?= $tipoSalida === 'arbol' ? 'selected' : '' ?>>Árbol</option>
                        <option value="plana" <?= $tipoSalida === 'plana' ? 'selected' : '' ?>>Plana</option>
                        <option value="rama1" <?= $tipoSalida === 'rama1' ? 'selected' : '' ?>>Rama 1</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="con_precios" class="form-label">Precios</label>
                    <select class="form-select" id="con_precios" name="con_precios">
                        <option value="0" <?= !$conPrecios ? 'selected' : '' ?>>No</option>
                        <option value="1" <?= $conPrecios ? 'selected' : '' ?>>Si</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="agrupar_tipo">
                        Agrupación
                        <?php if ($tipoSalida === 'arbol'): ?>
                            <i class="fa-solid fa-lock text-secondary ms-1" title="Bloqueado en vista Árbol para preservar la jerarquía"></i>
                        <?php endif; ?>
                    </label>
                    <select class="form-select" id="agrupar_tipo" name="agrupar_tipo"
                        <?= $tipoSalida === 'arbol' ? 'disabled' : '' ?>>
                        <option value="0" selected>Sin agrupar</option>
                        <?php if ($tipoSalida !== 'arbol'): ?>
                            <option value="1" <?= isset($agruparTipo) && $agruparTipo ? 'selected' : '' ?>>Agrupar por Tipo</option>
                        <?php endif; ?>
                    </select>
                    <?php if ($tipoSalida === 'arbol'): ?>
                        <small class="text-muted">
                            <i class="fa-solid fa-info-circle me-1"></i>Bloqueado para preservar los niveles del árbol.
                        </small>
                    <?php endif; ?>
                    <!-- Campo oculto necesario cuando disabled, para que el valor se envíe igual -->
                    <?php if ($tipoSalida === 'arbol'): ?>
                        <input type="hidden" name="agrupar_tipo" value="0">
                    <?php endif; ?>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="ordenar_tipo">
                        Ordenamiento
                        <?php if ($tipoSalida === 'arbol'): ?>
                            <i class="fa-solid fa-lock text-secondary ms-1" title="Bloqueado en vista Árbol para preservar la jerarquía"></i>
                        <?php endif; ?>
                    </label>
                    <select class="form-select" id="ordenar_tipo" name="ordenar_tipo"
                        <?= $tipoSalida === 'arbol' ? 'disabled' : '' ?>>
                        <option value="0" selected>Orden predeterminado</option>
                        <?php if ($tipoSalida !== 'arbol'): ?>
                            <option value="1" <?= isset($ordenarTipo) && $ordenarTipo ? 'selected' : '' ?>>Ordenar por Tipo</option>
                        <?php endif; ?>
                    </select>
                    <?php if ($tipoSalida === 'arbol'): ?>
                        <small class="text-muted">
                            <i class="fa-solid fa-info-circle me-1"></i>Bloqueado para preservar los niveles del árbol.
                        </small>
                    <?php endif; ?>
                    <!-- Campo oculto necesario cuando disabled, para que el valor se envíe igual -->
                    <?php if ($tipoSalida === 'arbol'): ?>
                        <input type="hidden" name="ordenar_tipo" value="0">
                    <?php endif; ?>
                </div>

                <div class="col-md-12">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label d-block">Mostrar solo:</label>
                            <div class="d-flex flex-wrap gap-2" role="group">
                                <?php foreach ($tiposPartesList ?? [] as $tp):
                                    $isChecked = in_array($tp['id'], $mostrarTipos ?? []);
                                ?>
                                    <input type="checkbox" class="btn-check"
                                        id="tipo_<?= $tp['id'] ?>"
                                        name="mostrar_tipos[]"
                                        value="<?= $tp['id'] ?>"
                                        <?= $isChecked ? 'checked' : '' ?>
                                        autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm" for="tipo_<?= $tp['id'] ?>">
                                        <?= View::escape($tp['nombre']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted mt-1 d-block">Click para filtrar por tipos específicos</small>
                        </div>
                    </div>
                </div>

                <!-- Botón removido para auto-update -->
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Resumen -->
<div class="card mb-4">
    <div class="card-body">
        <?php if (!$varianteSeleccionada): ?>
            <div class="text-center text-muted py-3">
                <i class="fa-solid fa-chart-bar fa-2x mb-2 opacity-25"></i>
                <p class="mb-0">El resumen del reporte aparecerá aquí.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-box fs-3 text-primary me-3"></i>
                        <div>
                            <div class="text-muted small">Variante</div>
                            <div class="fw-bold"><?= View::escape($varianteSeleccionada['codigo_variante']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-calculator fs-3 text-success me-3"></i>
                        <div>
                            <div class="text-muted small">Cantidad</div>
                            <div class="fw-bold"><?= View::escape(app_format_number((float) $cantidad)) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-sitemap fs-3 text-info me-3"></i>
                        <div>
                            <div class="text-muted small">Vista</div>
                            <div class="fw-bold text-capitalize"><?= View::escape($tipoSalida) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-dollar-sign fs-3 text-warning me-3"></i>
                        <div>
                            <div class="text-muted small">Precios</div>
                            <div class="fw-bold"><?= $conPrecios ? 'Incluidos' : 'No incluidos' ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Datos del reporte -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h3 class="h5 mb-0">
            <i class="fa-solid fa-list-check me-2"></i>
            Listado de materiales
        </h3>
    </div>
    <div class="card-body">
        <?php if (!$varianteSeleccionada): ?>
            <div class="text-center text-muted py-5">
                <i class="fa-solid fa-list-check fa-3x mb-3 opacity-25"></i>
                <p>Busca y selecciona una variante para generar el listado de materiales.</p>
            </div>
        <?php elseif (empty($datosReporte)) : ?>
            <div class="alert alert-warning mb-0">
                <i class="fa-solid fa-info-circle me-2"></i>
                No hay datos disponibles para esta variante
            </div>
        <?php else : ?>
            <?php
            // Function to render table content
            $renderTableContent = function ($items) use ($tipoSalida, $conPrecios) {
                // Pre-calculate hierarchical codes if tree view
                $codigosJerarquicos = [];
                if ($tipoSalida === 'arbol') {
                    $contadoresPorNivel = [];
                    $pilaCodigos = ['0'];
                    foreach ($items as $index => $item) {
                        $nivelActual = (int)($item['nivel'] ?? 0);
                        while (count($pilaCodigos) > $nivelActual + 1) array_pop($pilaCodigos);
                        if (!isset($contadoresPorNivel[$nivelActual])) $contadoresPorNivel[$nivelActual] = 0;
                        $contadoresPorNivel[$nivelActual]++;
                        foreach ($contadoresPorNivel as $nivel => $contador) {
                            if ($nivel > $nivelActual) $contadoresPorNivel[$nivel] = 0;
                        }
                        if ($nivelActual === 0) {
                            $codigoJerarquico = sprintf('%03d', $contadoresPorNivel[$nivelActual]);
                            $pilaCodigos = [$codigoJerarquico];
                        } else {
                            $codigoJerarquico = $pilaCodigos[$nivelActual - 1] . '.' . sprintf('%03d', $contadoresPorNivel[$nivelActual]);
                            if (count($pilaCodigos) === $nivelActual + 1) $pilaCodigos[$nivelActual] = $codigoJerarquico;
                            else $pilaCodigos[] = $codigoJerarquico;
                        }
                        $codigosJerarquicos[$index] = $codigoJerarquico;
                    }
                }

                $totalGeneral = 0;
            ?>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <?php if ($tipoSalida === 'arbol'): ?>
                                    <th>Nivel</th>
                                <?php endif; ?>
                                <th>Código</th>
                                <th>Detalle</th>
                                <th>Tipo</th>
                                <th class="text-end">Cantidad</th>
                                <th>Unidad</th>
                                <?php if ($conPrecios) : ?>
                                    <th class="text-end">Precio Unit.</th>
                                    <th class="text-end">Subtotal</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item) :
                                $precioUnitario = 0;
                                $subtotal = $precioUnitario * ($item['cantidad_ajustada'] ?? 0);
                                $totalGeneral += $subtotal;
                            ?>
                                <tr>
                                    <?php if ($tipoSalida === 'arbol'): ?>
                                        <td><code class="text-muted"><?= $codigosJerarquicos[$index] ?? '' ?></code></td>
                                    <?php endif; ?>
                                    <td>
                                        <strong><?= View::escape($item['codigo_variante'] ?? $item['componente_codigo'] ?? 'N/A') ?></strong>
                                    </td>
                                    <td class="text-muted small">
                                        <?= View::escape($item['variante_detalle'] ?? $item['componente_detalle'] ?? '') ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['tipo_codigo'])): ?>
                                            <span class="badge bg-info"><?= View::escape($item['tipo_codigo']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?= View::escape(app_format_number((float) ($item['cantidad_ajustada'] ?? 0))) ?>
                                    </td>
                                    <td>
                                        <?= View::escape($item['unidad'] ?? $item['unidad_simbolo'] ?? 'UN') ?>
                                    </td>
                                    <?php if ($conPrecios) : ?>
                                        <td class="text-end">$<?= View::escape(app_format_number((float) $precioUnitario)) ?></td>
                                        <td class="text-end">$<?= View::escape(app_format_number((float) $subtotal)) ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($conPrecios) : ?>
                                <tr class="table-secondary fw-bold">
                                    <td colspan="<?= $conPrecios ? ($tipoSalida === 'arbol' ? 7 : 6) : ($tipoSalida === 'arbol' ? 5 : 4) ?>" class="text-end">TOTAL:</td>
                                    <td class="text-end">$<?= View::escape(app_format_number((float) $totalGeneral)) ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php }; ?>

            <?php if (isset($agruparTipo) && $agruparTipo): ?>
                <?php if ($tipoSalida === 'arbol'): ?>
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                        <strong>Nota:</strong> Al agrupar por tipo en vista árbol, la jerarquía visual se pierde.
                    </div>
                <?php endif; ?>

                <?php foreach ($datosReporte as $grupoNombre => $items): ?>
                    <h4 class="mt-4 mb-2 border-bottom pb-2 text-primary">
                        <i class="fa-solid fa-layer-group me-2"></i><?= View::escape($grupoNombre) ?>
                    </h4>
                    <?php $renderTableContent($items); ?>
                <?php endforeach; ?>
            <?php else: ?>
                <?php
                if ($tipoSalida === 'arbol') {
                    // Show hierarchy info
                    echo '<div class="alert alert-info mb-3">
                                <i class="fa-solid fa-info-circle me-2"></i>
                                <strong>Vista jerárquica:</strong> La columna "Nivel" contiene códigos que permiten mantener la estructura (ej: 001, 001.001).
                            </div>';
                } elseif ($tipoSalida === 'plana') {
                    echo '<div class="alert alert-info mb-3">
                                <i class="fa-solid fa-layer-group me-2"></i>
                                <strong>Vista consolidada:</strong> Componentes únicos consolidados de todos los niveles.
                            </div>';
                }
                $renderTableContent($datosReporte);
                ?>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

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
                    window.location.href = '<?= url('reportes/listado-ingenieria') ?>?id_variante=' + item.id;
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

        // Auto-update report on options change
        const reportForm = document.querySelector('form[action*="reportes/listado-ingenieria"]');
        if (reportForm) {
            const tipoSalidaSelect = document.getElementById('tipo_salida');
            const agruparSelect = document.getElementById('agrupar_tipo');
            const ordenarSelect = document.getElementById('ordenar_tipo');

            /**
             * Bloquea/desbloquea Agrupación y Ordenamiento según el Tipo de salida.
             * Cuando es Árbol ambos quedan en su valor por defecto ("0") y deshabilitados.
             */
            function actualizarRestriccionArbol() {
                const esArbol = tipoSalidaSelect && tipoSalidaSelect.value === 'arbol';

                if (agruparSelect) {
                    agruparSelect.disabled = esArbol;
                    if (esArbol) agruparSelect.value = '0';
                }
                if (ordenarSelect) {
                    ordenarSelect.disabled = esArbol;
                    if (esArbol) ordenarSelect.value = '0';
                }
            }

            // Aplicar al cargar (por si el estado ya es árbol)
            actualizarRestriccionArbol();

            if (tipoSalidaSelect) {
                tipoSalidaSelect.addEventListener('change', actualizarRestriccionArbol);
            }

            const inputs = reportForm.querySelectorAll('input, select');
            inputs.forEach(input => {
                // Evitar submit al escribir en inputs de texto (usar debounce si fuera necesario, pero aquí es number)
                if (input.type === 'text') return;

                input.addEventListener('change', () => {
                    // Mostrar indicador de carga si se desea
                    document.body.style.cursor = 'wait';
                    reportForm.submit();
                });
            });
        }
    });
</script>
