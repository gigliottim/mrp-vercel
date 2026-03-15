<?php

use App\Core\View\View;

$whereUsed          = $whereUsed          ?? [];
$origenInfo         = $origenInfo         ?? null;
$nuevaInfo          = $nuevaInfo          ?? null;
$resultado          = $resultado          ?? null;
$errors             = $errors             ?? [];
$step               = $step               ?? 'form';
$id_variante_origen = $id_variante_origen ?? null;
$id_variante_nueva  = $id_variante_nueva  ?? null;

$origenLabel = $origenInfo !== null
    ? View::escape(($origenInfo['codigo_variante'] ?? '') . ' — ' . ($origenInfo['detalle'] ?? ''))
    : '';
$nuevaLabel  = $nuevaInfo !== null
    ? View::escape(($nuevaInfo['codigo_variante'] ?? '') . ' — ' . ($nuevaInfo['detalle'] ?? ''))
    : '';
?>

<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Productos y BOM</p>
            <h1 class="h3 mb-1">
                <i class="fa-solid fa-shuffle me-2 text-warning"></i>Reemplazar Partes en el Maestro
            </h1>
            <p class="text-muted small mb-0">
                Sustituye una pieza por otra en los maestros de todos o algunos productos.
                Ideal para cambiar piezas de fabricación local por tercerizadas o importadas.
            </p>
        </div>
        <a href="<?= url('productos/maestro') ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Maestro de Productos
        </a>
    </div>
</section>

<?php if ($errors !== []) : ?>
    <div class="alert alert-danger">
        <strong><i class="fa-solid fa-triangle-exclamation me-1"></i>Error:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $e) : ?>
                <li><?= View::escape($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($step === 'resultado' && $resultado !== null) : ?>
    <div class="alert alert-success mb-4">
        <h5 class="alert-heading mb-2">
            <i class="fa-solid fa-check-circle me-1"></i>Reemplazo ejecutado
        </h5>
        <p class="mb-0">
            Se realizaron <strong><?= (int) $resultado['reemplazados'] ?> reemplazos</strong>
            en <strong><?= (int) $resultado['boms_afectadas'] ?> maestro(s)</strong>.
        </p>
        <?php if ($origenInfo && $nuevaInfo) : ?>
            <p class="mb-0 mt-1 small text-muted">
                <?= $origenLabel ?> → <?= $nuevaLabel ?>
            </p>
        <?php endif; ?>
    </div>
    <a href="<?= url('productos/reemplazar-partes') ?>" class="btn btn-outline-primary">
        <i class="fa-solid fa-arrow-rotate-left me-1"></i>Nuevo reemplazo
    </a>
<?php else : ?>

    <!-- Formulario principal (step: form o preview) -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="fa-solid fa-magnifying-glass me-2 text-secondary"></i>
                Paso 1: Seleccionar piezas
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info py-2 small mb-4">
                <i class="fa-solid fa-circle-info me-1"></i>
                Seleccione la <strong>Pieza X</strong> a reemplazar y la <strong>Pieza H</strong> con la que
                desea sustituirla. Luego haga clic en <em>Buscar usos</em> para ver en qué maestros aparece X.
            </div>

            <form method="post" action="<?= url('productos/reemplazar-partes') ?>" id="form-reemplazar">
                <input type="hidden" name="action" id="input-action" value="preview">

                <div class="row g-4">
                    <!-- Pieza X (origen) -->
                    <div class="col-md-6">
                        <div class="card border-danger h-100">
                            <div class="card-header bg-danger bg-opacity-10 border-danger">
                                <h6 class="mb-0 text-danger">
                                    <i class="fa-solid fa-xmark me-1"></i>Pieza X — A reemplazar
                                </h6>
                            </div>
                            <div class="card-body">
                                <label class="form-label">Buscar pieza a reemplazar</label>
                                <div style="position:relative;">
                                    <input type="text"
                                        id="search-origen-input"
                                        class="form-control"
                                        placeholder="Código o descripción…"
                                        autocomplete="off"
                                        value="<?= $origenLabel ?>">
                                    <div id="search-origen-results"></div>
                                </div>
                                <input type="hidden" name="id_variante_origen" id="id-variante-origen"
                                    value="<?= (int) ($id_variante_origen ?? 0) ?: '' ?>">
                                <div id="origen-badge" class="mt-2">
                                    <?php if ($origenInfo) : ?>
                                        <span class="badge bg-danger text-white py-2 px-3 rounded-pill">
                                            <i class="fa-solid fa-check me-1"></i><?= $origenLabel ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pieza H (nueva) -->
                    <div class="col-md-6">
                        <div class="card border-success h-100">
                            <div class="card-header bg-success bg-opacity-10 border-success">
                                <h6 class="mb-0 text-success">
                                    <i class="fa-solid fa-check me-1"></i>Pieza H — Reemplazo
                                </h6>
                            </div>
                            <div class="card-body">
                                <label class="form-label">Buscar pieza de reemplazo</label>
                                <div style="position:relative;">
                                    <input type="text"
                                        id="search-nueva-input"
                                        class="form-control"
                                        placeholder="Código o descripción…"
                                        autocomplete="off"
                                        value="<?= $nuevaLabel ?>">
                                    <div id="search-nueva-results"></div>
                                </div>
                                <input type="hidden" name="id_variante_nueva" id="id-variante-nueva"
                                    value="<?= (int) ($id_variante_nueva ?? 0) ?: '' ?>">
                                <div id="nueva-badge" class="mt-2">
                                    <?php if ($nuevaInfo) : ?>
                                        <span class="badge bg-success text-white py-2 px-3 rounded-pill">
                                            <i class="fa-solid fa-check me-1"></i><?= $nuevaLabel ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-outline-secondary" id="btn-buscar"
                        onclick="document.getElementById('input-action').value='preview'; document.getElementById('form-reemplazar').submit();">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>Buscar usos de Pieza X
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($step === 'preview' && $whereUsed !== []) : ?>
        <!-- Paso 2: Seleccionar maestros y ejecutar -->
        <form method="post" action="<?= url('productos/reemplazar-partes') ?>">
            <input type="hidden" name="action" value="ejecutar">
            <input type="hidden" name="id_variante_origen" value="<?= (int) ($id_variante_origen ?? 0) ?>">
            <input type="hidden" name="id_variante_nueva" value="<?= (int) ($id_variante_nueva ?? 0) ?>">

            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-list-check me-2 text-secondary"></i>
                        Paso 2: Confirmar maestros (<?= count($whereUsed) ?>)
                    </h5>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="check-all" checked
                            onchange="document.querySelectorAll('.bom-check').forEach(c => c.checked = this.checked)">
                        <label class="form-check-label small" for="check-all">Seleccionar todos</label>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>Maestro (padre)</th>
                                    <th>Parte padre</th>
                                    <th class="text-end">Cantidad actual</th>
                                    <th>UM</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($whereUsed as $row) : ?>
                                    <tr>
                                        <td class="text-center">
                                            <input class="form-check-input bom-check" type="checkbox"
                                                name="bom_ids[]"
                                                value="<?= (int) $row['bom_id'] ?>"
                                                checked>
                                        </td>
                                        <td>
                                            <strong><?= View::escape((string) ($row['padre_codigo'] ?? '')) ?></strong>
                                            <small class="text-muted d-block"><?= View::escape((string) ($row['padre_detalle'] ?? '')) ?></small>
                                        </td>
                                        <td class="text-muted small"><?= View::escape((string) ($row['padre_parte'] ?? '')) ?></td>
                                        <td class="text-end"><?= View::escape(app_format_number((float) ($row['cantidad_necesaria'] ?? 0))) ?></td>
                                        <td class="text-muted small"><?= View::escape((string) ($row['unidad_codigo'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="<?= url('productos/reemplazar-partes') ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-warning"
                        <?= $id_variante_nueva ? '' : 'disabled title="Seleccione la Pieza H primero"' ?>>
                        <i class="fa-solid fa-shuffle me-1"></i>Ejecutar Reemplazo
                    </button>
                </div>
            </div>
        </form>
    <?php elseif ($step === 'preview' && $whereUsed === [] && $origenInfo !== null) : ?>
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            La pieza <strong><?= $origenLabel ?></strong> no aparece como componente en ningún maestro activo.
        </div>
    <?php endif; ?>

<?php endif; // end step !== resultado 
?>

<?php if (class_exists('App\Core\Support\AssetHelper')) : ?>
    <script src="<?= \App\Core\Support\AssetHelper::js('modules/SearchClient.js') ?>"></script>
<?php else : ?>
    <script src="/assets/js/modules/SearchClient.js"></script>
<?php endif; ?>

<script>
    (function() {
        'use strict';

        const endpoint = '<?= url('api/v1/search/variantes') ?>';

        function escHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function resultRender(item) {
            return `<div class="d-flex flex-column p-2">
                    <span class="fw-bold text-primary">${escHtml(item.codigo_variante || '')}</span>
                    <small class="text-muted">${escHtml(item.detalle || '')}</small>
                </div>`;
        }

        function renderBadge(badgeId, item, colorClass) {
            const el = document.getElementById(badgeId);
            if (!el) return;
            el.innerHTML = item ?
                `<span class="badge ${colorClass} text-white py-2 px-3 rounded-pill">
                   <i class="fa-solid fa-check me-1"></i>${escHtml(item.codigo_variante)} — ${escHtml(item.detalle || '')}
               </span>` :
                '';
        }

        if (typeof SearchClient !== 'undefined') {
            new SearchClient({
                endpoint,
                inputElement: document.getElementById('search-origen-input'),
                resultsContainer: document.getElementById('search-origen-results'),
                minChars: 2,
                debounceDelay: 300,
                customItemRender: resultRender,
                onSelect(item) {
                    document.getElementById('id-variante-origen').value = item.id;
                    document.getElementById('search-origen-input').value =
                        (item.codigo_variante || '') + ' — ' + (item.detalle || '');
                    renderBadge('origen-badge', item, 'bg-danger');
                },
            });

            new SearchClient({
                endpoint,
                inputElement: document.getElementById('search-nueva-input'),
                resultsContainer: document.getElementById('search-nueva-results'),
                minChars: 2,
                debounceDelay: 300,
                customItemRender: resultRender,
                onSelect(item) {
                    document.getElementById('id-variante-nueva').value = item.id;
                    document.getElementById('search-nueva-input').value =
                        (item.codigo_variante || '') + ' — ' + (item.detalle || '');
                    renderBadge('nueva-badge', item, 'bg-success');
                },
            });
        }
    }());
</script>
