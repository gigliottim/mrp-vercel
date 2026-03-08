<?php

use App\Core\View\View;

$totalOrdenes = (int)($dashboard['total_activas'] ?? 0);
$atrasadas = (int)($dashboard['atrasadas'] ?? 0);
$urgentes = (int)($dashboard['urgentes'] ?? 0);
$operativas = (int)($dashboard['operativas_hoy'] ?? 0);
$cumplimiento = (int)($dashboard['cumplimiento'] ?? 0);
$porEstado = $dashboard['por_estado'] ?? [];
$sinEstado = empty($porEstado);
$stockTotal = (int)($dashboard['stock_total'] ?? 0);
$stockCritico = (int)($dashboard['stock_critico'] ?? 0);
$stockAdvertencia = (int)($dashboard['stock_advertencia'] ?? 0);
$comprasMes = (int)($dashboard['compras_mes'] ?? 0);
$gastoMes = (float)($dashboard['gasto_mes'] ?? 0);
$mrpTotal = (int)($dashboard['mrp_sugerencias_total'] ?? 0);
$mrpPendientes = (int)($dashboard['mrp_sugerencias_pendientes'] ?? 0);
$mrpResumen = (int)($dashboard['mrp_resumen_items'] ?? 0);
?>

<section class="mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <h1 class="h3 mb-1">Panel inicial</h1>
            <p class="text-muted mb-0">Resumen de lo mas importante para tomar decisiones rapidas.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= url('produccion/ordenes') ?>">
            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver ordenes de produccion
        </a>
    </div>
</section>

<section class="dashboard-grid mb-4">
    <article class="dashboard-kpi dashboard-span-3 p-3 p-lg-4">
        <p class="text-uppercase small text-muted mb-1">Ordenes activas</p>
        <div class="dashboard-kpi__value mb-1"><?= $totalOrdenes ?></div>
        <small class="text-muted">Total no cerradas ni canceladas</small>
    </article>

    <article class="dashboard-kpi dashboard-span-3 p-3 p-lg-4">
        <p class="text-uppercase small text-muted mb-1">Urgentes</p>
        <div class="dashboard-kpi__value mb-1 text-danger"><?= $urgentes ?></div>
        <small class="text-muted">Prioridad alta para seguimiento diario</small>
    </article>

    <article class="dashboard-kpi dashboard-span-3 p-3 p-lg-4">
        <p class="text-uppercase small text-muted mb-1">Atrasadas</p>
        <div class="dashboard-kpi__value mb-1 text-warning"><?= $atrasadas ?></div>
        <small class="text-muted">Ordenes con fecha fin vencida</small>
    </article>

    <article class="dashboard-kpi dashboard-span-3 p-3 p-lg-4">
        <p class="text-uppercase small text-muted mb-1">En operacion hoy</p>
        <div class="dashboard-kpi__value mb-1 text-success"><?= $operativas ?></div>
        <small class="text-muted">Liberadas + en proceso</small>
    </article>
</section>

<section class="dashboard-grid mb-4">
    <article class="dashboard-kpi dashboard-span-3 p-3 p-lg-4">
        <p class="text-uppercase small text-muted mb-1">Stock critico</p>
        <div class="dashboard-kpi__value mb-1 text-danger"><?= $stockCritico ?></div>
        <small class="text-muted">Sobre <?= $stockTotal ?> variantes activas</small>
    </article>

    <article class="dashboard-kpi dashboard-span-3 p-3 p-lg-4">
        <p class="text-uppercase small text-muted mb-1">Stock en advertencia</p>
        <div class="dashboard-kpi__value mb-1 text-warning"><?= $stockAdvertencia ?></div>
        <small class="text-muted">Variantes proximas a punto de pedido</small>
    </article>

    <article class="dashboard-kpi dashboard-span-3 p-3 p-lg-4">
        <p class="text-uppercase small text-muted mb-1">Compras del mes</p>
        <div class="dashboard-kpi__value mb-1 text-primary"><?= $comprasMes ?></div>
        <small class="text-muted">Movimientos de compra registrados</small>
    </article>

    <article class="dashboard-kpi dashboard-span-3 p-3 p-lg-4">
        <p class="text-uppercase small text-muted mb-1">Gasto del mes</p>
        <div class="dashboard-kpi__value mb-1 text-success">$<?= View::escape(app_format_number($gastoMes)) ?></div>
        <small class="text-muted">Estimado segun compras registradas</small>
    </article>
</section>

<section class="dashboard-grid">
    <article class="dashboard-chart dashboard-span-6 p-3 p-lg-4">
        <h2 class="h5 mb-3">Distribucion por estado</h2>
        <?php if ($sinEstado) : ?>
            <p class="text-muted mb-0">Aun no hay datos de ordenes para mostrar.</p>
        <?php else : ?>
            <div class="dashboard-bars">
                <?php foreach ($porEstado as $row) :
                    $estado = (string)($row['estado'] ?? 'sin_dato');
                    $cantidad = (int)($row['cantidad'] ?? 0);
                    $pct = $totalOrdenes > 0 ? (int)round(($cantidad / $totalOrdenes) * 100) : 0;
                ?>
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong><?= View::escape(ucfirst(str_replace('_', ' ', $estado))) ?></strong>
                            <span class="text-muted small"><?= $cantidad ?> (<?= $pct ?>%)</span>
                        </div>
                        <div class="dashboard-bar__track">
                            <div class="dashboard-bar__fill" style="width: <?= $pct ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="dashboard-chart dashboard-span-6 p-3 p-lg-4">
        <h2 class="h5 mb-3">Cumplimiento operativo</h2>
        <div class="dashboard-ring" style="--progress: <?= $cumplimiento ?>%;">
            <div class="dashboard-ring__inner">
                <div class="text-center">
                    <div class="fs-4"><?= $cumplimiento ?>%</div>
                    <small>Cumplimiento</small>
                </div>
            </div>
        </div>
        <p class="text-muted small text-center mt-3 mb-0">Indicador estimado con base en ordenes sin atraso.</p>
    </article>

    <article class="dashboard-chart dashboard-span-12 p-3 p-lg-4">
        <h2 class="h5 mb-3">Estado MRP sugerencias</h2>
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <div class="p-3 rounded border bg-light-subtle">
                    <div class="small text-muted text-uppercase">Sugerencias totales</div>
                    <div class="fs-4 fw-semibold"><?= $mrpTotal ?></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-3 rounded border bg-light-subtle">
                    <div class="small text-muted text-uppercase">Pendientes</div>
                    <div class="fs-4 fw-semibold text-warning"><?= $mrpPendientes ?></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-3 rounded border bg-light-subtle">
                    <div class="small text-muted text-uppercase">Items en resumen MRP</div>
                    <div class="fs-4 fw-semibold text-primary"><?= $mrpResumen ?></div>
                </div>
            </div>
        </div>

        <hr>

        <h2 class="h5 mb-3">Checklist ejecutivo diario</h2>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="info-card h-100">
                    <div class="info-card__icon bg-danger-subtle text-danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div class="info-card__body">
                        <h2>Prioridades</h2>
                        <p>Revisa urgentes y atrasadas antes de liberar nuevas ordenes.</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="info-card h-100">
                    <div class="info-card__icon bg-primary-subtle text-primary"><i class="fa-solid fa-boxes-stacked"></i></div>
                    <div class="info-card__body">
                        <h2>Abastecimiento</h2>
                        <p>Valida faltantes criticos y ejecuta compras sugeridas.</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="info-card h-100">
                    <div class="info-card__icon bg-success-subtle text-success"><i class="fa-solid fa-chart-line"></i></div>
                    <div class="info-card__body">
                        <h2>Rendimiento</h2>
                        <p>Monitorea avance de produccion y capacidad utilizada.</p>
                    </div>
                </div>
            </div>
        </div>
    </article>
</section>
