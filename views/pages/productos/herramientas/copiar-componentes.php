<?php

use App\Core\View\View;

$resultado           = $resultado           ?? null;
$errors              = $errors              ?? [];
$id_variante_origen  = $id_variante_origen  ?? null;
$id_variante_destino = $id_variante_destino ?? null;
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
                            <div id="search-origen-container" style="position:relative;">
                                <input type="text"
                                    id="search-origen-input"
                                    class="form-control"
                                    placeholder="Código o descripción…"
                                    autocomplete="off">
                                <div id="search-origen-results"></div>
                            </div>
                            <input type="hidden" name="id_variante_origen" id="id-variante-origen" value="<?= (int) ($id_variante_origen ?? 0) ?: '' ?>">
                        </div>
                        <!-- Rama 1 origen -->
                        <div id="origen-bom-wrap" class="border-top" style="display:none;">
                            <div class="card-body py-2 px-3">
                                <p class="text-uppercase text-muted small mb-2 fw-semibold">
                                    <i class="fa-solid fa-sitemap me-1"></i>Componentes nivel 1
                                </p>
                                <div id="origen-bom-content"></div>
                            </div>
                        </div>
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
                            <div id="search-destino-container" style="position:relative;">
                                <input type="text"
                                    id="search-destino-input"
                                    class="form-control"
                                    placeholder="Código o descripción…"
                                    autocomplete="off">
                                <div id="search-destino-results"></div>
                            </div>
                            <input type="hidden" name="id_variante_destino" id="id-variante-destino" value="<?= (int) ($id_variante_destino ?? 0) ?: '' ?>">
                        </div>
                        <!-- Rama 1 destino -->
                        <div id="destino-bom-wrap" class="border-top" style="display:none;">
                            <div class="card-body py-2 px-3">
                                <p class="text-uppercase text-muted small mb-2 fw-semibold">
                                    <i class="fa-solid fa-sitemap me-1"></i>Componentes actuales nivel 1
                                    <span class="badge bg-warning text-dark ms-1 fw-normal" title="Serán reemplazados al copiar">serán reemplazados</span>
                                </p>
                                <div id="destino-bom-content"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= url('productos/maestro') ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary" id="btn-copiar" disabled>
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
    const bomBase        = '<?= url('api/v1/bom/variantes') ?>';
    const btnCopiar      = document.getElementById('btn-copiar');
    const state          = { origen: false, destino: false };

    // ── utilidades ────────────────────────────────────────────────────────────
    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function fmtNum(n) {
        return (window.appFormatNumber ? window.appFormatNumber(n) : n);
    }

    function resultRender(item) {
        return `<div class="d-flex flex-column p-2">
                    <span class="fw-bold text-primary">${escHtml(item.codigo_variante || '')}</span>
                    <small class="text-muted">${escHtml(item.detalle || '')}</small>
                </div>`;
    }

    function updateBtn() {
        btnCopiar.disabled = !(state.origen && state.destino);
    }

    // ── tabla BOM nivel 1 ─────────────────────────────────────────────────────
    function renderBomTable(items) {
        if (!items || items.length === 0) {
            return '<p class="text-muted small mb-0 fst-italic">Sin componentes en esta BOM.</p>';
        }
        const rows = items.map(it => {
            const cod = escHtml(it.parte_codigo + (it.componente_codigo ? '-' + it.componente_codigo : ''));
            const det = escHtml(it.componente_detalle || '');
            const tip = escHtml(it.tipo_codigo || '');
            const qty = escHtml(fmtNum(it.cantidad));
            const um  = escHtml(it.unidad || '');
            return `<tr>
                <td class="fw-semibold text-primary small">${cod}</td>
                <td class="small text-muted">${det}</td>
                <td class="text-center"><span class="badge bg-light text-dark border small">${tip}</span></td>
                <td class="text-end small">${qty} ${um}</td>
            </tr>`;
        }).join('');
        return `<table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Código</th><th>Detalle</th>
                    <th class="text-center">Tipo</th>
                    <th class="text-end">Cant.</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>`;
    }

    function fetchBom(varianteId, role) {
        const wrap    = document.getElementById(role + '-bom-wrap');
        const content = document.getElementById(role + '-bom-content');
        if (!wrap || !content) return;

        content.innerHTML = '<span class="text-muted small"><i class="fa-solid fa-spinner fa-spin me-1"></i>Cargando…</span>';
        wrap.style.display = '';

        fetch(bomBase + '/' + varianteId + '/nivel1', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                content.innerHTML = renderBomTable(data.items || []);
            })
            .catch(() => {
                content.innerHTML = '<p class="text-danger small mb-0">No se pudo cargar la BOM.</p>';
            });
    }

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
            inputElement:     document.getElementById('search-origen-input'),
            resultsContainer: document.getElementById('search-origen-results'),
            minChars: 2, debounceDelay: 300,
            customItemRender: resultRender,
            onSelect(item) {
                document.getElementById('id-variante-origen').value = item.id;
                document.getElementById('search-origen-input').value =
                    (item.codigo_variante || '') + ' — ' + (item.detalle || '');
                state.origen = true;
                fetchBom(item.id, 'origen');
                updateBtn();
            },
        });

        new SearchClient({
            endpoint: searchEndpoint,
            inputElement:     document.getElementById('search-destino-input'),
            resultsContainer: document.getElementById('search-destino-results'),
            minChars: 2, debounceDelay: 300,
            customItemRender: resultRender,
            onSelect(item) {
                document.getElementById('id-variante-destino').value = item.id;
                document.getElementById('search-destino-input').value =
                    (item.codigo_variante || '') + ' — ' + (item.detalle || '');
                state.destino = true;
                fetchBom(item.id, 'destino');
                updateBtn();
            },
        });

        ['origen', 'destino'].forEach(role => {
            document.getElementById('search-' + role + '-input').addEventListener('input', function () {
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
