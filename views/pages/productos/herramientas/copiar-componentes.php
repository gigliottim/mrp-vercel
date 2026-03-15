<?php

use App\Core\View\View;

$resultado           = $resultado           ?? null;
$errors              = $errors              ?? [];
$id_variante_origen  = $id_variante_origen  ?? null;
$id_variante_destino = $id_variante_destino ?? null;
$origenInfo          = $origenInfo          ?? null;
$destinoInfo         = $destinoInfo         ?? null;
$origenDetalles      = $origenDetalles      ?? [];
$destinoDetalles     = $destinoDetalles     ?? [];

// Labels para mostrar en los inputs
$origenLabel = $origenInfo !== null
    ? 'Parte: ' . ($origenInfo['parte_codigo'] ?? '') . ' | Variante: ' . ($origenInfo['codigo_variante'] ?? '') . ' - ' . ($origenInfo['detalle'] ?? '')
    : '';
$destinoLabel = $destinoInfo !== null
    ? 'Parte: ' . ($destinoInfo['parte_codigo'] ?? '') . ' | Variante: ' . ($destinoInfo['codigo_variante'] ?? '') . ' - ' . ($destinoInfo['detalle'] ?? '')
    : '';
?>

<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Productos y BOM</p>
            <h1 class="h3 mb-1">
                <i class="fa-solid fa-copy me-2 text-success"></i>Copiar Componentes de BOM
            </h1>
            <p class="text-muted small mb-0">
                Copia los componentes de nivel 1 de una pieza de origen hacia una pieza de destino,
                reemplazando previamente los componentes existentes en destino.
            </p>
        </div>
        <a href="<?= url('productos/maestro') ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Maestro de Productos
        </a>
    </div>
</section>

<?php if ($errors !== []) : ?>
    <div class="alert alert-danger">
        <strong><i class="fa-solid fa-triangle-exclamation me-1"></i>No se pudo completar la operación:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $e) : ?>
                <li><?= View::escape($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($resultado !== null) : ?>
    <div class="alert alert-<?= $resultado['copiados'] > 0 ? 'success' : 'warning' ?> mb-4">
        <h5 class="alert-heading mb-2">
            <i class="fa-solid fa-check-circle me-1"></i>Operación completada
        </h5>
        <ul class="mb-0">
            <li>Componentes copiados: <strong><?= (int) $resultado['copiados'] ?></strong></li>
            <li>Componentes previos eliminados en destino: <strong><?= (int) $resultado['eliminados'] ?></strong></li>
            <?php if ($resultado['saltados'] !== []) : ?>
                <li class="text-warning">
                    Saltados por validación (<?= count($resultado['saltados']) ?>):
                    <ul class="mb-0 mt-1">
                        <?php foreach ($resultado['saltados'] as $s) : ?>
                            <li><?= View::escape($s) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fa-solid fa-sliders me-2 text-secondary"></i>Parámetros de copia</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info py-2 small mb-4">
            <i class="fa-solid fa-circle-info me-1"></i>
            <strong>¿Cómo funciona?</strong>
            Se copian todos los componentes directos (nivel 1) de la BOM activa de la Pieza Origen
            hacia la Pieza Destino. Antes de copiar, se eliminan los componentes existentes en
            destino para evitar duplicados. Los componentes que generarían ciclos son omitidos.
        </div>

        <form method="post" action="<?= url('productos/copiar-componentes') ?>">
            <input type="hidden" name="id_variante_origen"  value="<?= (int) ($id_variante_origen  ?? 0) ?>">
            <input type="hidden" name="id_variante_destino" value="<?= (int) ($id_variante_destino ?? 0) ?>">

            <div class="row g-4">
                <!-- Pieza Origen -->
                <div class="col-md-6">
                    <div class="card border-primary">
                        <div class="card-header bg-primary bg-opacity-10 border-primary">
                            <h6 class="mb-0 text-primary">
                                <i class="fa-solid fa-arrow-right-from-bracket me-1"></i>Pieza Origen (fuente)
                            </h6>
                        </div>
                        <div class="card-body">
                            <label class="form-label">Buscar pieza origen</label>
                            <div style="position:relative;">
                                <input type="text"
                                    id="search-origen-input"
                                    class="form-control"
                                    placeholder="Código o descripción…"
                                    autocomplete="off"
                                    value="<?= View::escape($origenLabel) ?>">
                                <div id="search-origen-results"></div>
                            </div>
                        </div>
                        <?php if ($origenInfo !== null) : ?>
                        <div class="border-top">
                            <div class="card-body py-2 px-3">
                                <p class="text-uppercase text-muted small mb-2 fw-semibold">
                                    <i class="fa-solid fa-sitemap me-1"></i>Componentes nivel 1
                                </p>
                                <?php if ($origenDetalles === []) : ?>
                                    <p class="text-muted small fst-italic mb-0">Sin componentes en esta BOM.</p>
                                <?php else : ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0 small">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Código</th><th>Detalle</th>
                                                    <th class="text-center">Tipo</th>
                                                    <th class="text-end">Cant.</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($origenDetalles as $it) : ?>
                                                    <tr>
                                                        <td class="fw-semibold text-primary small">
                                                            <?= View::escape(($it['parte_codigo'] ?? '') . '-' . ($it['componente_codigo'] ?? '')) ?>
                                                        </td>
                                                        <td class="small text-muted">
                                                            <?= View::escape(trim(($it['parte_detalle'] ?? '') . ' - ' . ($it['componente_detalle'] ?? ''), ' -')) ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-light text-dark border small"><?= View::escape($it['tipo_codigo'] ?? '') ?></span>
                                                        </td>
                                                        <td class="text-end small">
                                                            <?= View::escape(app_format_number((float) ($it['cantidad_necesaria'] ?? 0))) ?>
                                                            <?= View::escape($it['unidad_simbolo'] ?? '') ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pieza Destino -->
                <div class="col-md-6">
                    <div class="card border-success">
                        <div class="card-header bg-success bg-opacity-10 border-success">
                            <h6 class="mb-0 text-success">
                                <i class="fa-solid fa-arrow-right-to-bracket me-1"></i>Pieza Destino (receptora)
                            </h6>
                        </div>
                        <div class="card-body">
                            <label class="form-label">Buscar pieza destino</label>
                            <div style="position:relative;">
                                <input type="text"
                                    id="search-destino-input"
                                    class="form-control"
                                    placeholder="Código o descripción…"
                                    autocomplete="off"
                                    value="<?= View::escape($destinoLabel) ?>">
                                <div id="search-destino-results"></div>
                            </div>
                        </div>
                        <?php if ($destinoInfo !== null) : ?>
                        <div class="border-top">
                            <div class="card-body py-2 px-3">
                                <p class="text-uppercase text-muted small mb-2 fw-semibold">
                                    <i class="fa-solid fa-sitemap me-1"></i>Componentes actuales nivel 1
                                    <span class="badge bg-warning text-dark ms-1 fw-normal">serán reemplazados</span>
                                </p>
                                <?php if ($destinoDetalles === []) : ?>
                                    <p class="text-muted small fst-italic mb-0">Sin componentes en esta BOM.</p>
                                <?php else : ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0 small">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Código</th><th>Detalle</th>
                                                    <th class="text-center">Tipo</th>
                                                    <th class="text-end">Cant.</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($destinoDetalles as $it) : ?>
                                                    <tr>
                                                        <td class="fw-semibold text-success small">
                                                            <?= View::escape(($it['parte_codigo'] ?? '') . '-' . ($it['componente_codigo'] ?? '')) ?>
                                                        </td>
                                                        <td class="small text-muted">
                                                            <?= View::escape(trim(($it['parte_detalle'] ?? '') . ' - ' . ($it['componente_detalle'] ?? ''), ' -')) ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-light text-dark border small"><?= View::escape($it['tipo_codigo'] ?? '') ?></span>
                                                        </td>
                                                        <td class="text-end small">
                                                            <?= View::escape(app_format_number((float) ($it['cantidad_necesaria'] ?? 0))) ?>
                                                            <?= View::escape($it['unidad_simbolo'] ?? '') ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= url('productos/copiar-componentes') ?>" class="btn btn-outline-secondary">Limpiar</a>
                <button type="submit" class="btn btn-primary"
                    <?= ($id_variante_origen > 0 && $id_variante_destino > 0) ? '' : 'disabled' ?>>
                    <i class="fa-solid fa-copy me-1"></i>Copiar Componentes
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (class_exists('App\Core\Support\AssetHelper')) : ?>
    <script src="<?= \App\Core\Support\AssetHelper::js('modules/SearchClient.js') ?>"></script>
<?php else : ?>
    <script src="/assets/js/modules/SearchClient.js"></script>
<?php endif; ?>

<script>
(function () {
    'use strict';

    const searchEndpoint = '<?= url('api/v1/search/variantes') ?>';
    const baseUrl        = '<?= url('productos/copiar-componentes') ?>';
    const currentOrigen  = '<?= (int) ($id_variante_origen  ?? 0) ?>';
    const currentDestino = '<?= (int) ($id_variante_destino ?? 0) ?>';

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function goTo(origenId, destinoId) {
        const params = new URLSearchParams();
        if (origenId  > 0) params.set('id_variante_origen',  origenId);
        if (destinoId > 0) params.set('id_variante_destino', destinoId);
        window.location.href = baseUrl + (params.toString() ? '?' + params.toString() : '');
    }

    function resultRender(item) {
        return `<div class="d-flex flex-column p-2">
            <span class="fw-bold text-primary">${escHtml(item.parte_codigo || '')} - ${escHtml(item.codigo_variante || '')}</span>
            <small class="text-muted">${escHtml(item.parte_detalle || '')} - ${escHtml(item.detalle || '')}</small>
        </div>`;
    }

    if (typeof SearchClient !== 'undefined') {
        new SearchClient({
            endpoint:         searchEndpoint,
            inputElement:     document.getElementById('search-origen-input'),
            resultsContainer: document.getElementById('search-origen-results'),
            minChars: 2, debounceDelay: 300,
            customItemRender: resultRender,
            onSelect(item) {
                goTo(item.id, currentDestino);
            },
        });

        new SearchClient({
            endpoint:         searchEndpoint,
            inputElement:     document.getElementById('search-destino-input'),
            resultsContainer: document.getElementById('search-destino-results'),
            minChars: 2, debounceDelay: 300,
            customItemRender: resultRender,
            onSelect(item) {
                goTo(currentOrigen, item.id);
            },
        });
    }
}());
</script>

        function clearBom(role) {
            const wrap = document.getElementById(role + '-bom-wrap');
            if (wrap) wrap.style.display = 'none';
            const content = document.getElementById(role + '-bom-content');
            if (content) content.innerHTML = '';
        }

        // ── SearchClient ──────────────────────────────────────────────────────────
        if (typeof SearchClient !== 'undefined') {
            new SearchClient({
                endpoint: searchEndpoint,
                inputElement: document.getElementById('search-origen-input'),
                resultsContainer: document.getElementById('search-origen-results'),
                minChars: 2,
                debounceDelay: 300,
                customItemRender: resultRender,
                onSelect(item) {
                    document.getElementById('id-variante-origen').value = item.id;
                    document.getElementById('search-origen-input').value = buildLabel(item);
                    state.origen = true;
                    fetchBom(item.id, 'origen');
                    updateBtn();
                },
            });

            new SearchClient({
                endpoint: searchEndpoint,
                inputElement: document.getElementById('search-destino-input'),
                resultsContainer: document.getElementById('search-destino-results'),
                minChars: 2,
                debounceDelay: 300,
                customItemRender: resultRender,
                onSelect(item) {
                    document.getElementById('id-variante-destino').value = item.id;
                    document.getElementById('search-destino-input').value = buildLabel(item);
                    state.destino = true;
                    fetchBom(item.id, 'destino');
                    updateBtn();
                },
            });

            ['origen', 'destino'].forEach(role => {
                document.getElementById('search-' + role + '-input').addEventListener('input', function() {
                    if (this.value === '') {
                        document.getElementById('id-variante-' + role).value = '';
                        state[role] = false;
                        clearBom(role);
                        updateBtn();
                    }
                });
            });
        }
    }());
</script>
