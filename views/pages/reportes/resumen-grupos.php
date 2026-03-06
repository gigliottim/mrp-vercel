<?php

use App\Core\View\View;

$grupos = $grupos ?? [];
$selectedGrupoId = $selectedGrupoId ?? null;
$grupoSeleccionado = $grupoSeleccionado ?? null;
$partesDelGrupo = $partesDelGrupo ?? [];
$partesSinGrupo = $partesSinGrupo ?? [];
$countSinGrupo = $countSinGrupo ?? 0;

?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Reportes</p>
            <h1 class="h3 mb-0">Resumen por Grupos de Partes</h1>
        </div>
    </div>
</section>

<!-- Selector de grupo -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-8">
                <label class="form-label fw-semibold">Seleccionar Grupo</label>
                <select class="form-select" name="id_grupo" onchange="this.form.submit()">
                    <option value="">-- Seleccione un grupo --</option>
                    <?php foreach ($grupos as $grupo) : ?>
                        <option value="<?= $grupo['id'] ?>" <?= $selectedGrupoId === (int) $grupo['id'] ? 'selected' : '' ?>>
                            <?= View::escape($grupo['codigo']) ?> - <?= View::escape($grupo['nombre']) ?>
                            (<?= $grupo['total_partes'] ?> partes, <?= $grupo['total_variantes'] ?> variantes)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-filter me-1"></i> Filtrar
                </button>
                <?php if ($selectedGrupoId) : ?>
                    <a href="<?= url('reportes/resumen-grupos') ?>" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-xmark me-1"></i> Limpiar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Vista de resumen general -->
<?php if ($selectedGrupoId === null) : ?>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3">Todos los Grupos</h5>
            <p class="text-muted mb-4">Seleccione un grupo arriba para ver el detalle de sus partes y variantes.</p>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Grupo</th>
                            <th>Descripción</th>
                            <th class="text-center">Color</th>
                            <th class="text-end">Partes</th>
                            <th class="text-end">Variantes</th>
                            <th class="text-end">Var. Activas</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grupos as $grupo) : ?>
                            <tr>
                                <td class="fw-semibold"><?= View::escape($grupo['codigo']) ?></td>
                                <td><?= View::escape($grupo['nombre']) ?></td>
                                <td class="text-muted small">
                                    <?= $grupo['descripcion'] ? View::escape($grupo['descripcion']) : '<span class="text-body-secondary">--</span>' ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge border" style="background-color: <?= View::escape($grupo['color'] ?? '#0d6efd') ?>; width: 40px;">&nbsp;</span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-primary"><?= $grupo['total_partes'] ?></span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-info"><?= $grupo['total_variantes'] ?></span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-success"><?= $grupo['variantes_activas'] ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('reportes/resumen-grupos?id_grupo=' . $grupo['id']) ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fa-solid fa-eye me-1"></i> Ver Detalle
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($grupos) === 0) : ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No hay grupos registrados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Alerta de partes sin grupo -->
    <?php if ($countSinGrupo > 0) : ?>
        <div class="alert alert-warning mt-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <strong>Atención:</strong> Hay <?= $countSinGrupo ?> partes sin grupo asignado.
        </div>
    <?php endif; ?>

<?php else : ?>
    <!-- Vista de detalle del grupo -->
    <?php if ($grupoSeleccionado) : ?>
        <div class="card mb-4">
            <div class="card-header" style="background-color: <?= View::escape($grupoSeleccionado['color'] ?? '#0d6efd') ?>; color: white;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="fa-solid fa-layer-group me-2"></i>
                            <?= View::escape($grupoSeleccionado['codigo']) ?> - <?= View::escape($grupoSeleccionado['nombre']) ?>
                        </h5>
                        <?php if ($grupoSeleccionado['descripcion']) : ?>
                            <p class="mb-0 small opacity-75"><?= View::escape($grupoSeleccionado['descripcion']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-white text-dark fs-6">
                            <?= $grupoSeleccionado['total_partes'] ?> partes
                        </div>
                        <div class="badge bg-white text-dark fs-6 ms-2">
                            <?= $grupoSeleccionado['total_variantes'] ?> variantes
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($partesDelGrupo) === 0) : ?>
                    <div class="alert alert-info mb-0">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        No hay partes asignadas a este grupo.
                    </div>
                <?php else : ?>
                    <div class="accordion" id="accordionPartes">
                        <?php foreach ($partesDelGrupo as $index => $parte) : ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?= $parte['parte_id'] ?>">
                                    <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#collapse<?= $parte['parte_id'] ?>"
                                        aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>"
                                        aria-controls="collapse<?= $parte['parte_id'] ?>">
                                        <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                            <div>
                                                <span class="fw-bold"><?= View::escape($parte['parte_codigo']) ?></span>
                                                <span class="text-muted ms-2"><?= View::escape($parte['parte_detalle']) ?></span>
                                            </div>
                                            <div class="text-end">
                                                <?php if ($parte['tipo_nombre']) : ?>
                                                    <span class="badge bg-light text-dark border me-2">
                                                        <?= View::escape($parte['tipo_nombre']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="badge bg-secondary">
                                                    <?= count($parte['variantes']) ?> variantes
                                                </span>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse<?= $parte['parte_id'] ?>"
                                    class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
                                    aria-labelledby="heading<?= $parte['parte_id'] ?>"
                                    data-bs-parent="#accordionPartes">
                                    <div class="accordion-body">
                                        <?php if (count($parte['variantes']) === 0) : ?>
                                            <p class="text-muted mb-0">Esta parte no tiene variantes definidas.</p>
                                        <?php else : ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Código Variante</th>
                                                            <th>Detalle</th>
                                                            <th class="text-center">Estado</th>
                                                            <th class="text-end">Stock Actual</th>
                                                            <th class="text-end">Punto Pedido</th>
                                                            <th class="text-end">Lote Mín.</th>
                                                            <th class="text-center">Estado Stock</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($parte['variantes'] as $variante) :
                                                            $estadoStock = $variante['estado_stock'];
                                                            $badgeStock = match ($estadoStock) {
                                                                'critico' => 'bg-danger',
                                                                'advertencia' => 'bg-warning text-dark',
                                                                'normal' => 'bg-success',
                                                                default => 'bg-secondary',
                                                            };
                                                            $iconStock = match ($estadoStock) {
                                                                'critico' => 'fa-circle-exclamation',
                                                                'advertencia' => 'fa-triangle-exclamation',
                                                                'normal' => 'fa-circle-check',
                                                                default => 'fa-circle',
                                                            };
                                                        ?>
                                                            <tr>
                                                                <td>
                                                                    <span class="badge bg-secondary">
                                                                        <?= View::escape($variante['codigo_variante']) ?>
                                                                    </span>
                                                                </td>
                                                                <td><?= View::escape($variante['variante_detalle']) ?></td>
                                                                <td class="text-center">
                                                                    <span class="badge <?= $variante['variante_estado'] === 'activa' ? 'bg-success' : 'bg-secondary' ?>">
                                                                        <?= View::escape(ucfirst($variante['variante_estado'])) ?>
                                                                    </span>
                                                                </td>
                                                                <td class="text-end">
                                                                    <span class="fw-semibold">
                                                                        <?= number_format((float) $variante['stock_actual'], 2) ?>
                                                                    </span>
                                                                    <?php if ($variante['unidad_medida']) : ?>
                                                                        <small class="text-muted"><?= View::escape($variante['unidad_medida']) ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-end">
                                                                    <?= number_format((float) $variante['punto_pedido'], 2) ?>
                                                                    <?php if ($variante['unidad_medida']) : ?>
                                                                        <small class="text-muted"><?= View::escape($variante['unidad_medida']) ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-end text-muted">
                                                                    <?= number_format((float) $variante['lote_minimo'], 2) ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="badge <?= $badgeStock ?>">
                                                                        <i class="fa-solid <?= $iconStock ?>"></i>
                                                                    </span>
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
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
