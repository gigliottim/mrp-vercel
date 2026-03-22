<?php

use App\Core\View\View;

/** @var array $variantes */
/** @var array $resumen */
/** @var string|null $filtro */

$variantes = $variantes ?? [];
$resumen   = $resumen ?? ['fabricables' => 0, 'parciales' => 0, 'sin_stock' => 0, 'total' => 0];
$filtro    = $filtro ?? null;

?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Planeamiento MRP</p>
            <h1 class="h3 mb-0">Sugerencias de Fabricación</h1>
            <p class="text-muted small mb-0">Variantes con BOM activa — cobertura de stock al día de hoy</p>
        </div>
        <a href="<?= url('planeamiento/sugerencias') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrows-rotate me-1"></i> Actualizar
        </a>
    </div>
</section>

<!-- Tarjetas resumen -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm"
            data-bs-toggle="tooltip"
            data-bs-placement="bottom"
            data-bs-html="true"
            title="<strong>Total de variantes</strong> que tienen al menos una BOM activa definida en el sistema.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Con BOM activa</p>
                        <h3 class="h4 mb-0"><?= $resumen['total'] ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded p-3">
                        <i class="fa-solid fa-sitemap fa-lg text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm"
            data-bs-toggle="tooltip"
            data-bs-placement="bottom"
            data-bs-html="true"
            title="<strong>✅ Fabricable</strong><br>Condición: <code>min_ratio &ge; 1.0</code><br>Todos los componentes tienen stock suficiente para cubrir al menos 1 unidad completa (100&nbsp;%).">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Fabricables al 100 %</p>
                        <h3 class="h4 mb-0 text-success"><?= $resumen['fabricables'] ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded p-3">
                        <i class="fa-solid fa-circle-check fa-lg text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm"
            data-bs-toggle="tooltip"
            data-bs-placement="bottom"
            data-bs-html="true"
            title="<strong>⚠️ Stock parcial</strong><br>Condición: <code>0 &lt; min_ratio &lt; 1</code><br>Al menos un componente tiene stock, pero insuficiente para completar una unidad entera.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Stock parcial</p>
                        <h3 class="h4 mb-0 text-warning"><?= $resumen['parciales'] ?></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 rounded p-3">
                        <i class="fa-solid fa-triangle-exclamation fa-lg text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm"
            data-bs-toggle="tooltip"
            data-bs-placement="bottom"
            data-bs-html="true"
            title="<strong>❌ Sin stock</strong><br>Condición: <code>min_ratio = 0</code><br>Al menos un componente no tiene ningún stock disponible.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Sin stock</p>
                        <h3 class="h4 mb-0 text-danger"><?= $resumen['sin_stock'] ?></h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 rounded p-3">
                        <i class="fa-solid fa-ban fa-lg text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="text-muted small fw-semibold me-1">Mostrar:</span>
            <div class="btn-group btn-group-sm" role="group">
                <a href="<?= url('planeamiento/sugerencias') ?>"
                    class="btn <?= $filtro === null ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Todos (<?= $resumen['total'] ?>)
                </a>
                <a href="<?= url('planeamiento/sugerencias?filtro=fabricable') ?>"
                    class="btn <?= $filtro === 'fabricable' ? 'btn-success' : 'btn-outline-success' ?>">
                    <i class="fa-solid fa-circle-check me-1"></i>Fabricables (<?= $resumen['fabricables'] ?>)
                </a>
                <a href="<?= url('planeamiento/sugerencias?filtro=parcial') ?>"
                    class="btn <?= $filtro === 'parcial' ? 'btn-warning' : 'btn-outline-warning' ?>">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>Parciales (<?= $resumen['parciales'] ?>)
                </a>
                <a href="<?= url('planeamiento/sugerencias?filtro=sin_stock') ?>"
                    class="btn <?= $filtro === 'sin_stock' ? 'btn-danger' : 'btn-outline-danger' ?>">
                    <i class="fa-solid fa-ban me-1"></i>Sin stock (<?= $resumen['sin_stock'] ?>)
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (empty($variantes)) : ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fa-solid fa-inbox fa-3x text-muted mb-3"></i>
            <p class="text-muted mb-0">
                <?= $filtro !== null ? 'No hay variantes con ese estado.' : 'No hay variantes con BOM activa definida.' ?>
            </p>
        </div>
    </div>
<?php else : ?>
    <div class="card">
        <div class="card-body px-0 pb-0">
            <div class="d-flex justify-content-between align-items-center px-3 mb-2">
                <h2 class="h5 mb-0">Detalle por variante</h2>
                <span class="text-muted small"><?= count($variantes) ?> resultado<?= count($variantes) !== 1 ? 's' : '' ?></span>
            </div>

            <div class="accordion accordion-flush" id="sugerenciasAccordion">
                <?php foreach ($variantes as $idx => $v) : ?>
                    <?php
                    $accordionId  = 'bom-' . $v['bom_id'];
                    $collapseId   = 'collapse-' . $v['bom_id'];
                    $statusClass  = match ($v['status']) {
                        'fabricable' => 'success',
                        'parcial'    => 'warning',
                        default      => 'danger',
                    };
                    $statusLabel  = match ($v['status']) {
                        'fabricable' => 'Fabricable',
                        'parcial'    => 'Stock parcial',
                        default      => 'Sin stock',
                    };
                    $statusIcon   = match ($v['status']) {
                        'fabricable' => 'circle-check',
                        'parcial'    => 'triangle-exclamation',
                        default      => 'ban',
                    };
                    // Solo expandir automáticamente las fabricables
                    $expanded = $v['status'] === 'fabricable' ? 'true' : 'false';
                    $showClass = $v['status'] === 'fabricable' ? 'show' : '';
                    ?>
                    <div class="accordion-item border-start border-4 border-<?= $statusClass ?> mb-2 mx-3 rounded shadow-sm">
                        <h2 class="accordion-header" id="heading-<?= $accordionId ?>">
                            <button class="accordion-button <?= $expanded === 'false' ? 'collapsed' : '' ?> py-3"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#<?= $collapseId ?>"
                                aria-expanded="<?= $expanded ?>"
                                aria-controls="<?= $collapseId ?>">
                                <div class="d-flex flex-wrap justify-content-between align-items-center w-100 gap-2 pe-3">
                                    <div>
                                        <span class="fw-semibold me-2">
                                            <?= View::escape($v['parte_codigo']) ?>
                                            <span class="text-muted">- <?= View::escape($v['variante_codigo']) ?></span>
                                        </span>
                                        <span class="text-muted small"><?= View::escape($v['parte_detalle']) ?></span>
                                        <?php if ($v['variante_detalle']) : ?>
                                            <span class="text-muted small"> — <?= View::escape($v['variante_detalle']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 flex-shrink-0">
                                        <?php if ($v['status'] === 'fabricable') : ?>
                                            <span class="text-success small fw-semibold">
                                                <i class="fa-solid fa-cubes me-1"></i>
                                                <?= number_format($v['max_unidades'], 0, ',', '.') ?> ud. posibles
                                            </span>
                                        <?php endif; ?>
                                        <!-- Barra de cobertura -->
                                        <div class="d-none d-md-flex align-items-center gap-2" style="min-width:120px">
                                            <div class="progress flex-grow-1" style="height:8px;min-width:80px">
                                                <div class="progress-bar bg-<?= $statusClass ?>"
                                                    role="progressbar"
                                                    style="width:<?= min($v['cobertura_pct'], 100) ?>%"
                                                    aria-valuenow="<?= $v['cobertura_pct'] ?>"
                                                    aria-valuemin="0"
                                                    aria-valuemax="100">
                                                </div>
                                            </div>
                                            <span class="small text-<?= $statusClass ?> fw-semibold" style="min-width:36px;text-align:right">
                                                <?= $v['cobertura_pct'] ?>%
                                            </span>
                                        </div>
                                        <span class="badge bg-<?= $statusClass ?> bg-opacity-15 text-<?= $statusClass ?> border border-<?= $statusClass ?>">
                                            <i class="fa-solid fa-<?= $statusIcon ?> me-1"></i><?= $statusLabel ?>
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="<?= $collapseId ?>"
                            class="accordion-collapse collapse <?= $showClass ?>"
                            aria-labelledby="heading-<?= $accordionId ?>"
                            data-bs-parent="">
                            <div class="accordion-body pt-0 pb-3 px-3">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Componente</th>
                                                <th>Descripción</th>
                                                <th class="text-end">Necesario</th>
                                                <th class="text-end">Stock disponible</th>
                                                <th class="text-center" style="min-width:120px">Cobertura</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($v['componentes'] as $comp) : ?>
                                                <?php
                                                $compClass = match (true) {
                                                    $comp['ratio'] >= 1.0 => 'success',
                                                    $comp['ratio'] > 0.0  => 'warning',
                                                    default               => 'danger',
                                                };
                                                ?>
                                                <tr>
                                                    <td>
                                                        <span class="fw-semibold"><?= View::escape($comp['comp_parte_codigo']) ?></span>
                                                        <span class="text-muted"> - <?= View::escape($comp['comp_codigo']) ?></span>
                                                    </td>
                                                    <td class="text-muted small"><?= View::escape($comp['comp_detalle']) ?></td>
                                                    <td class="text-end">
                                                        <?= app_format_number($comp['cantidad_necesaria']) ?>
                                                        <span class="text-muted small ms-1"><?= View::escape($comp['unidad_simbolo']) ?></span>
                                                    </td>
                                                    <td class="text-end fw-semibold text-<?= $compClass ?>">
                                                        <?= app_format_number($comp['stock_disponible']) ?>
                                                        <span class="text-muted fw-normal small ms-1"><?= View::escape($comp['unidad_simbolo']) ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="progress flex-grow-1" style="height:6px">
                                                                <div class="progress-bar bg-<?= $compClass ?>"
                                                                    role="progressbar"
                                                                    style="width:<?= min($comp['cobertura_pct'], 100) ?>%">
                                                                </div>
                                                            </div>
                                                            <span class="small text-<?= $compClass ?> fw-semibold" style="min-width:36px;text-align:right">
                                                                <?= $comp['cobertura_pct'] ?>%
                                                            </span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="pb-3"></div>
        </div>
    </div>
<?php endif; ?>

<script>
    (function() {
        'use strict';

        function initTooltips() {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
                new bootstrap.Tooltip(el, {
                    html: true,
                    sanitize: false,
                    trigger: 'hover focus'
                });
            });
        }
        // Bootstrap se carga con defer; DOMContentLoaded garantiza que ya ejecutó
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initTooltips);
        } else {
            initTooltips();
        }
    })();
</script>
