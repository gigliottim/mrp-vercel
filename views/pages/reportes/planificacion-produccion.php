<?php

use App\Core\View\View;

$variantes = $variantes ?? [];
$productosProgramados = $productosProgramados ?? [];
$requerimientos = $requerimientos ?? [];
$fechaCosto = $fechaCosto ?? date('Y-m-d');

?>

<style>
    #search-results {
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

    #search-results .search-result-item {
        padding: 0.5rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s;
    }

    #search-results .search-result-item:hover,
    #search-results .search-result-item.active {
        background-color: #f8f9fa;
    }

    #search-results .search-result-item:last-child {
        border-bottom: none;
    }

    #search-input:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .card-body:has(#search-container) {
        overflow: visible !important;
    }

    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }

    .badge-lote-minimo {
        font-size: 0.75rem;
        padding: 0.25em 0.5em;
    }
</style>

<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Reportes</p>
            <h1 class="h3 mb-0">Planificación de la Producción</h1>
            <p class="text-muted small mb-0">Calcula requerimientos de materiales considerando lotes mínimos de compra</p>
        </div>
    </div>
</section>

<!-- Formulario para agregar productos -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h2 class="h5 mb-0">
            <i class="fa-solid fa-plus-circle me-2"></i>
            Agregar productos a programar
        </h2>
    </div>
    <div class="card-body">
        <form method="GET" action="<?= url('reportes/planificacion-produccion') ?>" id="form-agregar">
            <?php foreach ($productosProgramados as $vid => $cant): ?>
                <input type="hidden" name="productos[<?= $vid ?>]" value="<?= $cant ?>">
            <?php endforeach; ?>

            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Buscar variante</label>
                    <div id="search-container" class="position-relative">
                        <input type="text"
                            id="search-input"
                            class="form-control"
                            placeholder="Buscar variante por código o descripción..."
                            autocomplete="off">
                        <input type="hidden" name="variante_id" id="variante_id">
                        <div id="search-results"></div>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Cantidad</label>
                    <input type="number"
                        class="form-control"
                        name="cantidad"
                        id="cantidad"
                        value="1"
                        step="0.01"
                        min="0.01"
                        required>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Fecha costo</label>
                    <input type="date"
                        class="form-control"
                        name="fecha_costo"
                        id="fecha_costo"
                        value="<?= View::escape((string) $fechaCosto) ?>"
                        required>
                </div>

                <div class="col-md-2 d-grid gap-2">
                    <button type="submit" name="add_variante" value="1" class="btn btn-success w-100">
                        <i class="fa-solid fa-plus"></i> Agregar
                    </button>
                    <button type="submit" name="recalcular" value="1" class="btn btn-outline-primary w-100">
                        <i class="fa-solid fa-rotate"></i> Recalcular
                    </button>
                </div>
            </div>

            <small class="text-muted d-block mt-2">
                <i class="fa-solid fa-info-circle"></i> Agregue todos los productos que desea fabricar con sus cantidades
            </small>
        </form>
    </div>
</div>

<!-- Productos programados -->
<?php if (!empty($productosProgramados)): ?>
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h2 class="h5 mb-0">
                <i class="fa-solid fa-clipboard-check me-2"></i>
                Productos programados (<?= count($productosProgramados) ?>)
            </h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Detalle</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productosProgramados as $varianteId => $cantidad): ?>
                            <?php $variante = $variantes[$varianteId] ?? null; ?>
                            <?php if ($variante): ?>
                                <tr>
                                    <td>
                                        <strong><?= View::escape($variante['codigo_variante']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= View::escape($variante['parte_codigo'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <strong><?= View::escape($variante['detalle']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= View::escape($variante['parte_detalle'] ?? '') ?></small>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-primary"><?= View::escape(app_format_number((float) $cantidad)) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php
                                            // Usar botones estándar
                                            $options = [
                                                'variante_id' => $varianteId,
                                                'parte_id' => $variante['parte_id'] ?? 0,
                                                'codigo_variante' => $variante['codigo_variante'],
                                                'context' => 'php',
                                                'show_gestionar' => true,
                                                'show_maestro' => true,
                                                'show_destino' => true,
                                                'size' => 'sm'
                                            ];
                                            include __DIR__ . '/../../partials/_variante_action_buttons.php';
                                            ?>
                                            <!-- Botón adicional: Quitar de planificación -->
                                            <button type="button"
                                                class="btn btn-outline-danger"
                                                onclick="eliminarProducto(<?= $varianteId ?>)"
                                                title="Quitar de la planificación">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Requerimientos calculados -->
    <div class="card">
        <div class="card-header bg-info text-white">
            <h2 class="h5 mb-0">
                <i class="fa-solid fa-calculator me-2"></i>
                Requerimientos de materiales (<?= count($requerimientos) ?> componentes)
            </h2>
        </div>
        <div class="card-body">
            <?php if (empty($requerimientos)): ?>
                <div class="alert alert-info mb-0">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    No hay BOM definidas para los productos programados
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Detalle</th>
                                <th>UM</th>
                                <th>Tipo</th>
                                <th class="text-end">Programado</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Faltante</th>
                                <th class="text-end">A Comprar (UM Compra)</th>
                                <th class="text-end">Stock Final</th>
                                <th class="text-end">A Comprar $</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalAComprar = 0;
                            foreach ($requerimientos as $item):
                                $totalAComprar += $item['a_comprar_precio'];
                                $necesitaCompra = $item['a_comprar'] > 0;
                            ?>
                                <tr class="<?= $necesitaCompra ? 'table-warning' : '' ?>">
                                    <td>
                                        <strong><?= View::escape($item['codigo']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= View::escape($item['parte_codigo']) ?></small>
                                        <?php if ($item['lote_minimo'] > 1): ?>
                                            <br>
                                            <span class="badge badge-lote-minimo bg-secondary">
                                                Lote: <?= View::escape(app_format_number((float) $item['lote_minimo'], 0)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= View::escape($item['detalle']) ?></td>
                                    <td><?= View::escape($item['unidad']) ?></td>
                                    <td>
                                        <?php if ($item['tipo']): ?>
                                            <span class="badge bg-info"><?= View::escape($item['tipo']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= View::escape(app_format_number((float) $item['programado'])) ?></td>
                                    <td class="text-end"><?= View::escape(app_format_number((float) $item['stock'])) ?></td>
                                    <td class="text-end">
                                        <?php if ($item['faltante'] > 0): ?>
                                            <span class="text-danger fw-bold">
                                                <?= View::escape(app_format_number((float) $item['faltante'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-success">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($item['a_comprar'] > 0): ?>
                                            <span class="badge bg-warning text-dark">
                                                <?= View::escape(app_format_number((float) $item['a_comprar'])) ?> <?= View::escape($item['a_comprar_um'] ?? '') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="<?= $item['stock_final'] < 0 ? 'text-danger' : 'text-success' ?>">
                                            <?= View::escape(app_format_number((float) $item['stock_final'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($item['a_comprar'] > 0): ?>
                                            $<?= View::escape(app_format_number((float) $item['a_comprar_precio'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $options = [
                                            'variante_id' => $item['variante_id'],
                                            'parte_id' => $item['parte_id'] ?? 0,
                                            'codigo_variante' => $item['codigo'],
                                            'context' => 'php',
                                            'show_gestionar' => true,
                                            'show_maestro' => true,
                                            'show_destino' => true,
                                            'size' => 'sm'
                                        ];
                                        include __DIR__ . '/../../partials/_variante_action_buttons.php';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr class="fw-bold">
                                <td colspan="10" class="text-end">TOTAL A INVERTIR:</td>
                                <td class="text-end">$<?= View::escape(app_format_number((float) $totalAComprar)) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="alert alert-info mt-3 mb-0">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            <strong>Leyenda:</strong>
                        </div>
                        <div class="col-md-8">
                            <span class="badge bg-warning text-dark me-2">Fila amarilla</span> = Requiere compra
                            <span class="ms-3">•</span>
                            <span class="badge bg-secondary ms-2">Lote</span> = Lote mínimo de compra
                            <span class="ms-3">•</span>
                            <span class="text-danger ms-2">Rojo</span> = Faltante o stock negativo
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="text-center py-5">
                <i class="fa-solid fa-clipboard-list fa-4x text-muted mb-3"></i>
                <h3 class="h5 text-muted">No hay productos programados</h3>
                <p class="text-muted">Agregue productos con sus cantidades para calcular los requerimientos de materiales</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php

use App\Core\Support\AssetHelper;
?>
<script src="<?= AssetHelper::js('modules/SearchClient.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('search-input');
        const searchResults = document.getElementById('search-results');
        const varianteIdInput = document.getElementById('variante_id');
        const cantidadInput = document.getElementById('cantidad');
        const fechaCostoInput = document.getElementById('fecha_costo');

        if (searchInput && searchResults) {
            const searchInstance = new SearchClient({
                endpoint: '<?= url('api/v1/search/variantes') ?>',
                inputElement: searchInput,
                resultsContainer: searchResults,
                minChars: 2,
                debounceDelay: 300,
                maxResults: 15,
                format: 'detailed',
                onSelect: (item) => {
                    const parteCodigo = item.parte_codigo || 'N/A';
                    const parteDetalle = item.parte_detalle || 'Sin detalle';
                    const varianteCodigo = item.codigo_variante || 'N/A';
                    const varianteDetalle = item.detalle || item.variante_detalle || 'Sin detalle';

                    varianteIdInput.value = item.id;
                    searchInput.value = `Parte: ${parteCodigo} - ${parteDetalle} | Variante: ${varianteCodigo} - ${varianteDetalle}`;
                    cantidadInput.focus();
                }
                // customItemRender removido para usar el nuevo default moderno de SearchClient.js
            });
        }

        // Validar antes de enviar
        document.getElementById('form-agregar').addEventListener('submit', (e) => {
            const submitter = e.submitter;
            const isAddAction = !!(submitter && submitter.name === 'add_variante');
            const varianteId = varianteIdInput.value;
            const cantidad = cantidadInput.value;

            if (isAddAction && (!varianteId || varianteId === '0')) {
                e.preventDefault();
                alert('Por favor seleccione una variante de la lista de búsqueda');
                searchInput.focus();
                return false;
            }

            if (isAddAction && (!cantidad || parseFloat(cantidad) <= 0)) {
                e.preventDefault();
                alert('Por favor ingrese una cantidad válida');
                cantidadInput.focus();
                return false;
            }
        });

        // Función para eliminar producto programado
        window.eliminarProducto = function(varianteId) {
            if (!confirm('¿Está seguro que desea eliminar este producto de la planificación?')) {
                return;
            }

            // Crear formulario dinámicamente
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = '<?= url('reportes/planificacion-produccion') ?>';

            const fechaCostoHidden = document.createElement('input');
            fechaCostoHidden.type = 'hidden';
            fechaCostoHidden.name = 'fecha_costo';
            fechaCostoHidden.value = (fechaCostoInput && fechaCostoInput.value) ? fechaCostoInput.value : '<?= View::escape((string) $fechaCosto) ?>';
            form.appendChild(fechaCostoHidden);

            // Agregar productos actuales excepto el que se elimina
            <?php foreach ($productosProgramados as $vid => $cant): ?>
                if (<?= $vid ?> !== varianteId) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'productos[<?= $vid ?>]';
                    input.value = '<?= $cant ?>';
                    form.appendChild(input);
                }
            <?php endforeach; ?>

            // Agregar parámetro de eliminación
            const removeInput = document.createElement('input');
            removeInput.type = 'hidden';
            removeInput.name = 'remove_variante';
            removeInput.value = varianteId;
            form.appendChild(removeInput);

            // Enviar formulario
            document.body.appendChild(form);
            form.submit();
        };
    });
</script>
