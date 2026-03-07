<?php

use App\Core\View\View;

$tipos = $tipos ?? [];
$grupos = $grupos ?? [];
$unidades = $unidades ?? [];
$report = $report ?? null;

?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Productos</p>
            <h1 class="h3 mb-0">Importar Partes y Variantes</h1>
        </div>
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link" href="<?= url('productos/partes?tab=partes') ?>">Partes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?= url('productos/partes?tab=variantes') ?>">Variantes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="<?= url('productos/partes/importar') ?>">Importar</a>
            </li>
        </ul>
    </div>
</section>

<div class="row g-4">
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Carga masiva desde CSV (compatible con Excel)</h2>
                <p class="text-muted mb-4">
                    Descarga la plantilla, completala en Excel y guardala como <strong>CSV UTF-8</strong>.
                    La importacion actualiza registros existentes por codigo y crea los que no existan.
                </p>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a class="btn btn-outline-primary" href="<?= url('productos/partes/importar/template') ?>">
                        <i class="fa-solid fa-file-arrow-down me-2"></i>
                        Descargar plantilla
                    </a>
                    <a class="btn btn-outline-secondary" href="<?= url('productos/partes?tab=partes') ?>">
                        <i class="fa-solid fa-list me-2"></i>
                        Volver al listado
                    </a>
                </div>

                <form method="post" action="<?= url('productos/partes/importar') ?>" enctype="multipart/form-data" class="row g-3">
                    <div class="col-12">
                        <label for="archivo_importacion" class="form-label">Archivo CSV</label>
                        <input
                            class="form-control"
                            type="file"
                            id="archivo_importacion"
                            name="archivo_importacion"
                            accept=".csv,text/csv"
                            required>
                        <small class="text-muted">Solo se admite formato CSV.</small>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-success" type="submit">
                            <i class="fa-solid fa-upload me-2"></i>
                            Importar archivo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase">Columnas obligatorias</h2>
                <ul class="mb-3">
                    <li><code>parte_codigo</code></li>
                    <li><code>parte_detalle</code></li>
                    <li><code>tipo_codigo</code></li>
                    <li><code>grupo_codigo</code></li>
                    <li><code>variante_codigo</code></li>
                    <li><code>variante_detalle</code></li>
                </ul>

                <h2 class="h6 text-muted text-uppercase">Codigos UM</h2>
                <div class="table-responsive" style="max-height: 280px;">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th>Codigo</th>
                                <th>Unidad</th>
                                <th>Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unidades as $unidad) : ?>
                                <tr>
                                    <td><code><?= View::escape((string) ($unidad['simbolo'] ?? '')) ?></code></td>
                                    <td><?= View::escape((string) ($unidad['unidad'] ?? '')) ?></td>
                                    <td><?= View::escape((string) ($unidad['tipo'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($unidades === []) : ?>
                                <tr>
                                    <td colspan="3" class="text-muted">No hay unidades activas.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (is_array($report)) : ?>
    <section class="mt-4">
        <?php if (!empty($report['fatal_error'])) : ?>
            <div class="alert alert-danger" role="alert">
                <?= View::escape((string) $report['fatal_error']) ?>
            </div>
        <?php else : ?>
            <div class="alert <?= ((int) ($report['error_rows'] ?? 0) > 0) ? 'alert-warning' : 'alert-success' ?>" role="alert">
                <div class="d-flex flex-wrap gap-3">
                    <span><strong>Total filas:</strong> <?= (int) ($report['total_rows'] ?? 0) ?></span>
                    <span><strong>OK:</strong> <?= (int) ($report['ok_rows'] ?? 0) ?></span>
                    <span><strong>Con error:</strong> <?= (int) ($report['error_rows'] ?? 0) ?></span>
                    <span><strong>Partes creadas:</strong> <?= (int) ($report['created_parts'] ?? 0) ?></span>
                    <span><strong>Partes actualizadas:</strong> <?= (int) ($report['updated_parts'] ?? 0) ?></span>
                    <span><strong>Variantes creadas:</strong> <?= (int) ($report['created_variants'] ?? 0) ?></span>
                    <span><strong>Variantes actualizadas:</strong> <?= (int) ($report['updated_variants'] ?? 0) ?></span>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 class="h6 mb-3">Detalle por fila</h3>
                    <div class="table-responsive" style="max-height: 360px;">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th>Linea</th>
                                    <th>Estado</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($report['rows'] ?? []) as $item) : ?>
                                    <tr>
                                        <td><?= (int) ($item['line'] ?? 0) ?></td>
                                        <td>
                                            <?php if (($item['status'] ?? '') === 'ok') : ?>
                                                <span class="badge text-bg-success">OK</span>
                                            <?php else : ?>
                                                <span class="badge text-bg-danger">Error</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= View::escape((string) ($item['message'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (($report['rows'] ?? []) === []) : ?>
                                    <tr>
                                        <td colspan="3" class="text-muted">Sin filas procesadas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
