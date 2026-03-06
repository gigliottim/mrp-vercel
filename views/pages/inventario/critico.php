<?php

use App\Core\View\View;

$items = $items ?? [];
$estadisticas = $estadisticas ?? ['total' => 0, 'critico' => 0, 'advertencia' => 0, 'normal' => 0];
$filtroEstado = $filtroEstado ?? 'todos';

?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Inventario</p>
            <h1 class="h3 mb-0">Análisis de Stock Crítico</h1>
        </div>
    </div>
</section>

<!-- Tarjetas de estadísticas -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Total Items</p>
                        <h3 class="h4 mb-0"><?= $estadisticas['total'] ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded p-3">
                        <i class="fa-solid fa-boxes-stacked fa-lg text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Stock Crítico</p>
                        <h3 class="h4 mb-0 text-danger"><?= $estadisticas['critico'] ?></h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 rounded p-3">
                        <i class="fa-solid fa-circle-exclamation fa-lg text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">En Advertencia</p>
                        <h3 class="h4 mb-0 text-warning"><?= $estadisticas['advertencia'] ?></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 rounded p-3">
                        <i class="fa-solid fa-triangle-exclamation fa-lg text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Stock Normal</p>
                        <h3 class="h4 mb-0 text-success"><?= $estadisticas['normal'] ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded p-3">
                        <i class="fa-solid fa-circle-check fa-lg text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="text-muted small">Filtrar por estado:</span>
            <div class="btn-group btn-group-sm" role="group">
                <a href="<?= url('inventario/critico') ?>"
                    class="btn <?= $filtroEstado === 'todos' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    Todos
                </a>
                <a href="<?= url('inventario/critico?estado=critico') ?>"
                    class="btn <?= $filtroEstado === 'critico' ? 'btn-danger' : 'btn-outline-danger' ?>">
                    Crítico
                </a>
                <a href="<?= url('inventario/critico?estado=advertencia') ?>"
                    class="btn <?= $filtroEstado === 'advertencia' ? 'btn-warning' : 'btn-outline-warning' ?>">
                    Advertencia
                </a>
                <a href="<?= url('inventario/critico?estado=normal') ?>"
                    class="btn <?= $filtroEstado === 'normal' ? 'btn-success' : 'btn-outline-success' ?>">
                    Normal
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de items -->
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Items de Inventario</h2>
            <span class="text-muted small"><?= count($items) ?> registros</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Estado</th>
                        <th>Código Parte</th>
                        <th>Variante</th>
                        <th>Descripción</th>
                        <th>Tipo</th>
                        <th>Grupo</th>
                        <th class="text-end">Stock Actual</th>
                        <th class="text-end">Punto Pedido</th>
                        <th class="text-end">Faltante</th>
                        <th class="text-end">Lote Mín.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($items) === 0) : ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <?php if ($filtroEstado !== 'todos') : ?>
                                    No hay items en estado "<?= View::escape($filtroEstado) ?>"
                                <?php else : ?>
                                    No hay items para mostrar
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($items as $item) :
                            $estadoStock = $item['estado_stock'];
                            $badgeClass = match ($estadoStock) {
                                'critico' => 'bg-danger',
                                'advertencia' => 'bg-warning text-dark',
                                'normal' => 'bg-success',
                                default => 'bg-secondary',
                            };
                            $iconClass = match ($estadoStock) {
                                'critico' => 'fa-circle-exclamation',
                                'advertencia' => 'fa-triangle-exclamation',
                                'normal' => 'fa-circle-check',
                                default => 'fa-circle',
                            };
                            $estadoLabel = match ($estadoStock) {
                                'critico' => 'Crítico',
                                'advertencia' => 'Advertencia',
                                'normal' => 'Normal',
                                default => 'Desconocido',
                            };
                        ?>
                            <tr>
                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <i class="fa-solid <?= $iconClass ?> me-1"></i>
                                        <?= $estadoLabel ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= View::escape($item['codigo_parte']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= View::escape($item['codigo_variante']) ?></span>
                                </td>
                                <td>
                                    <div>
                                        <div class="fw-medium"><?= View::escape($item['detalle_parte']) ?></div>
                                        <?php if ($item['detalle'] !== $item['detalle_parte']) : ?>
                                            <small class="text-muted"><?= View::escape($item['detalle']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= View::escape($item['tipo_nombre'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['grupo_nombre']) : ?>
                                        <span class="badge" style="background-color: <?= View::escape($item['grupo_color'] ?? '#6c757d') ?>">
                                            <?= View::escape($item['grupo_nombre']) ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="text-muted small">Sin grupo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <span class="fw-semibold"><?= number_format((float) $item['stock_actual'], 2) ?></span>
                                    <?php if ($item['unidad_medida']) : ?>
                                        <small class="text-muted"><?= View::escape($item['unidad_medida']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <span><?= number_format((float) $item['punto_pedido'], 2) ?></span>
                                    <?php if ($item['unidad_medida']) : ?>
                                        <small class="text-muted"><?= View::escape($item['unidad_medida']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ((float) $item['faltante'] > 0) : ?>
                                        <span class="text-danger fw-semibold"><?= number_format((float) $item['faltante'], 2) ?></span>
                                    <?php else : ?>
                                        <span class="text-success">--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <span class="text-muted"><?= number_format((float) $item['lote_minimo'], 2) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Leyenda -->
<div class="card mt-4">
    <div class="card-body">
        <h6 class="card-title mb-3">Leyenda de Estados</h6>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-danger">
                        <i class="fa-solid fa-circle-exclamation"></i> Crítico
                    </span>
                    <small class="text-muted">Stock actual menor al punto de pedido</small>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark">
                        <i class="fa-solid fa-triangle-exclamation"></i> Advertencia
                    </span>
                    <small class="text-muted">Stock entre punto de pedido y 1.5x punto de pedido</small>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success">
                        <i class="fa-solid fa-circle-check"></i> Normal
                    </span>
                    <small class="text-muted">Stock mayor o igual a 1.5x punto de pedido</small>
                </div>
            </div>
        </div>
    </div>
</div>
