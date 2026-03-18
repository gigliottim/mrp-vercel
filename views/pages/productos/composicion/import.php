<?php

use App\Core\View\View;

$report = $report ?? null;

?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Productos</p>
            <h1 class="h3 mb-0">Importar / Exportar Maestro BOM</h1>
        </div>
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link" href="<?= url('productos/maestro') ?>">Maestro</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="<?= url('productos/maestro/importar') ?>">Importar / Exportar</a>
            </li>
        </ul>
    </div>
</section>

<div class="row g-4">

    <!-- Panel Importar -->
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body">
                <h2 class="h5 mb-1">Importar relaciones BOM desde CSV</h2>
                <p class="text-muted small mb-4">
                    Descarga la plantilla, completala con las relaciones padre→hijo y cargala como
                    <strong>CSV UTF-8</strong>. Se validarán bucles infinitos antes de insertar.
                </p>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a class="btn btn-outline-primary btn-sm"
                        href="<?= url('productos/maestro/importar/template') ?>">
                        <i class="fa-solid fa-file-arrow-down me-1"></i> Descargar plantilla
                    </a>
                    <a class="btn btn-outline-success btn-sm"
                        href="<?= url('productos/maestro/exportar') ?>">
                        <i class="fa-solid fa-file-arrow-up me-1"></i> Exportar BOM completo
                    </a>
                    <a class="btn btn-outline-secondary btn-sm"
                        href="<?= url('productos/maestro') ?>">
                        <i class="fa-solid fa-arrow-left me-1"></i> Volver al Maestro
                    </a>
                </div>

                <form method="post"
                    action="<?= url('productos/maestro/importar') ?>"
                    enctype="multipart/form-data"
                    class="row g-3">
                    <div class="col-12">
                        <label for="archivo_importacion" class="form-label">Archivo CSV</label>
                        <input class="form-control"
                            type="file"
                            id="archivo_importacion"
                            name="archivo_importacion"
                            accept=".csv,text/csv"
                            required>
                        <small class="text-muted">Solo se admite formato CSV (coma o punto y coma).</small>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-success" type="submit">
                            <i class="fa-solid fa-upload me-2"></i>Importar archivo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel Referencia -->
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase mb-3">Columnas del CSV</h2>
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Columna</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>padre_parte_codigo</code></td>
                            <td>Código de la parte padre</td>
                        </tr>
                        <tr>
                            <td><code>padre_variante_codigo</code></td>
                            <td>Código de la variante padre</td>
                        </tr>
                        <tr>
                            <td><code>hijo_parte_codigo</code></td>
                            <td>Código de la parte componente</td>
                        </tr>
                        <tr>
                            <td><code>hijo_variante_codigo</code></td>
                            <td>Código de la variante componente</td>
                        </tr>
                        <tr>
                            <td><code>cantidad</code></td>
                            <td>Cantidad necesaria (decimal)</td>
                        </tr>
                        <tr>
                            <td><code>unidad_codigo</code></td>
                            <td>Símbolo de unidad de medida</td>
                        </tr>
                    </tbody>
                </table>

                <div class="alert alert-info mt-3 mb-0 py-2 small">
                    <i class="fa-solid fa-shield-halved me-1"></i>
                    <strong>Validación de bucles:</strong> si el CSV contiene relaciones que
                    generarían ciclos (A→B→C→A), esas filas se omiten con error detallado.
                    Las demás filas válidas se importan normalmente.
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Reporte de importación -->
<?php if ($report !== null): ?>
    <div class="mt-4">

        <?php if ($report['fatal_error'] ?? null): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <strong>Error:</strong> <?= View::escape((string) $report['fatal_error']) ?>
            </div>
        <?php else: ?>

            <!-- Resumen -->
            <div class="card mb-3">
                <div class="card-header bg-light fw-semibold">
                    Resultado de la importación
                </div>
                <div class="card-body p-0">
                    <div class="row row-cols-2 row-cols-md-4 g-0 text-center">
                        <div class="col border-end py-3">
                            <div class="fs-4 fw-bold"><?= (int) $report['total_rows'] ?></div>
                            <div class="text-muted small">Filas procesadas</div>
                        </div>
                        <div class="col border-end py-3">
                            <div class="fs-4 fw-bold text-success"><?= (int) $report['ok_rows'] ?></div>
                            <div class="text-muted small">Importadas</div>
                        </div>
                        <div class="col border-end py-3">
                            <div class="fs-4 fw-bold text-warning"><?= (int) $report['skipped_rows'] ?></div>
                            <div class="text-muted small">Omitidas (ya existían)</div>
                        </div>
                        <div class="col py-3">
                            <div class="fs-4 fw-bold text-danger"><?= (int) $report['error_rows'] ?></div>
                            <div class="text-muted small">Con error</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detalle por fila -->
            <?php if (!empty($report['rows'])): ?>
                <div class="card">
                    <div class="card-header bg-light fw-semibold">Detalle fila a fila</div>
                    <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light" style="position: sticky; top: 0;">
                                <tr>
                                    <th style="width:60px">Fila</th>
                                    <th style="width:90px">Estado</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report['rows'] as $r): ?>
                                    <?php
                                    $badgeClass = match ($r['status']) {
                                        'ok'    => 'bg-success',
                                        'skip'  => 'bg-warning text-dark',
                                        default => 'bg-danger',
                                    };
                                    $label = match ($r['status']) {
                                        'ok'    => 'OK',
                                        'skip'  => 'Omitida',
                                        default => 'Error',
                                    };
                                    ?>
                                    <tr>
                                        <td><?= (int) $r['line'] ?></td>
                                        <td><span class="badge <?= $badgeClass ?>"><?= $label ?></span></td>
                                        <td><?= View::escape((string) $r['message']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
<?php endif; ?>
