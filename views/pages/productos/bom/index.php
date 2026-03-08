<?php

use App\Core\View\View;

$boms = $boms ?? [];
?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Productos</p>
            <h1 class="h3 mb-0">Listado de BOMs Activas</h1>
            <p class="text-muted small mb-0">Listas de Materiales activas y vigentes</p>
        </div>
        <div>
            <a href="<?= url('productos/maestro') ?>" class="btn btn-primary">
                <i class="fa-solid fa-plus me-2"></i>Nueva BOM
            </a>
        </div>
    </div>
</section>

<div class="card">
    <div class="card-body">
        <?php if (count($boms) > 0) : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Variante</th>
                            <th>Versión</th>
                            <th>Fecha Efectiva</th>
                            <th>Observaciones</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($boms as $bom) : ?>
                            <tr>
                                <td>
                                    <?php $codigoCompuesto = trim((string) ($bom['parte_codigo'] ?? '')) . '-' . trim((string) ($bom['variante_codigo'] ?? '')); ?>
                                    <?php $detalleCompuesto = trim((string) ($bom['parte_detalle'] ?? '')) . ' - ' . trim((string) ($bom['variante_detalle'] ?? '')); ?>
                                    <strong><?= View::escape(trim($codigoCompuesto, '-')) ?></strong>
                                    <br>
                                    <small class="text-muted"><?= View::escape(trim($detalleCompuesto, ' -')) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= View::escape($bom['version']) ?></span>
                                </td>
                                <td>
                                    <?= View::escape($bom['fecha_efectiva']) ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= View::escape($bom['observaciones'] ?? '--') ?></small>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('productos/maestro?id_variante=' . (int) $bom['variante_padre_id']) ?>" class="btn btn-outline-primary" title="Ver Composición">
                                            <i class="fa-solid fa-eye"></i> Detalle
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fa-solid fa-clipboard-list fa-3x text-muted opacity-50"></i>
                </div>
                <h3 class="h5">No hay BOMs activas</h3>
                <p class="text-muted mb-3">Comienza creando una nueva lista de materiales para tus productos.</p>
                <a href="<?= url('productos/maestro') ?>" class="btn btn-outline-primary">
                    Crear primera BOM
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
