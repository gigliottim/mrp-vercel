<?php

use App\Core\View\View;
use App\Core\Support\AssetHelper;

$formError = $_SESSION['form_error'] ?? null;
$formSuccess = $_SESSION['form_success'] ?? null;
$needsParteDeletion = $_SESSION['needs_parte_deletion'] ?? false;
$deleteVarianteId = $_SESSION['delete_variante_id'] ?? null;
$deleteParteId = $_SESSION['delete_parte_id'] ?? null;
unset($_SESSION['form_error'], $_SESSION['form_success'], $_SESSION['needs_parte_deletion'], $_SESSION['delete_variante_id'], $_SESSION['delete_parte_id']);

$parts = $parts ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 15];
$search = $search ?? '';
$currentPerPage = (int) ($parts['per_page'] ?? 15);
$perPage = $currentPerPage;

$partItems = $parts['items'] ?? [];
$totalParts = $parts['total'] ?? count($partItems);

$currentPage = max(1, (int) ($parts['page'] ?? 1));
$showAll = $currentPerPage <= 0;
$totalPages = $showAll ? 1 : max(1, (int) ceil($totalParts / max(1, $currentPerPage)));
$currentPage = min($currentPage, $totalPages);

$prevPage = max(1, $currentPage - 1);
$nextPage = min($totalPages, $currentPage + 1);

$clearUrl = url('productos/partes?' . ($currentPerPage > 0 ? 'per_page=' . $currentPerPage : 'per_page=all'));

$buildUrl = static function (array $params) use ($search): string {
    $query = $params;
    if ($search !== '') {
        $query['q'] = $search;
    }
    return url('productos/partes?' . http_build_query($query));
};

$totalActivas = 0;
$totalVariantes = 0;
foreach ($partItems as $parte) {
    $variantes = $parte['variantes'] ?? [];
    $totalVariantes += count($variantes);
    foreach ($variantes as $v) {
        if (($v['estado'] ?? '') === 'activa') {
            $totalActivas++;
        }
    }
}
$porcentajeActivas = $totalVariantes > 0 ? round(($totalActivas / $totalVariantes) * 100) : 0;

$estadoMap = [
    'activa' => 'activa',
    'desarrollo' => 'desarrollo',
    'obsoleta' => 'obsoleta',
    'descontinuada' => 'descontinuada',
];
$estadoLabels = [
    'activa' => 'Activa',
    'desarrollo' => 'En desarrollo',
    'obsoleta' => 'Obsoleta',
    'descontinuada' => 'Descontinuada',
];
?>
<link rel="stylesheet" href="<?= AssetHelper::css('modules/partes/index-v2.css') ?>">

<?php if ($formError): ?>
    <div class="pv-alert pv-alert-danger" role="alert">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span><?= View::escape($formError) ?></span>
        <button type="button" class="btn-close ms-auto" style="font-size:.7rem;" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($formSuccess): ?>
    <div class="pv-alert pv-alert-success" role="alert">
        <i class="fa-solid fa-circle-check"></i>
        <span><?= View::escape($formSuccess) ?></span>
        <button type="button" class="btn-close ms-auto" style="font-size:.7rem;" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="pv-hero">
    <div class="pv-hero-inner d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <p class="pv-hero-kicker">Productos</p>
            <h1>Partes y Variantes</h1>
            <p>Gestiona las partes del catálogo y sus variantes de producción</p>
        </div>
        <div class="pv-hero-actions d-flex gap-2 flex-wrap">
            <a href="<?= url('productos/partes/importar') ?>" class="btn btn-light"><i class="fa-solid fa-file-import me-1"></i> Importar</a>
            <a href="<?= url('productos/partes/manager') ?>" class="btn btn-white"><i class="fa-solid fa-plus me-1"></i> Nueva Parte</a>
        </div>
    </div>
</div>

<div class="pv-stats-row pv-fade-in">
    <div class="pv-stat-card">
        <div class="pv-stat-icon" style="background:rgba(79,70,229,.1);color:var(--pv-primary);">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
        <div class="pv-stat-value"><?= number_format($totalParts) ?></div>
        <div class="pv-stat-label">Total Partes</div>
    </div>
    <div class="pv-stat-card">
        <div class="pv-stat-icon" style="background:rgba(6,182,212,.1);color:#0891b2;">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="pv-stat-value"><?= number_format($totalVariantes) ?></div>
        <div class="pv-stat-label">Total Variantes</div>
    </div>
    <div class="pv-stat-card">
        <div class="pv-stat-icon" style="background:rgba(34,197,94,.1);color:#16a34a;">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="pv-stat-value"><?= $porcentajeActivas ?>%</div>
        <div class="pv-stat-label">Activas</div>
    </div>
    <div class="pv-stat-card">
        <div class="pv-stat-icon" style="background:rgba(245,158,11,.1);color:#d97706;">
            <i class="fa-solid fa-file-lines"></i>
        </div>
        <div class="pv-stat-value"><?= $showAll ? $totalParts : min($currentPerPage, max(0, $totalParts - (($currentPage - 1) * $currentPerPage))) ?></div>
        <div class="pv-stat-label">En esta página</div>
    </div>
</div>

<div class="pv-search-card pv-fade-in">
    <form method="get" action="<?= url('productos/partes') ?>" id="search-form-partes" data-clear-url="<?= $clearUrl ?>">
        <div class="row g-3 align-items-end">
            <div class="col-lg-8">
                <p class="pv-section-label">Buscar parte o variante</p>
                <div class="pv-search-input-wrapper">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text"
                        class="pv-search-input"
                        id="search-input-partes"
                        name="q"
                        value="<?= View::escape($search) ?>"
                        data-has-search="<?= $search !== '' ? 'true' : 'false' ?>"
                        placeholder="Buscar por código o detalle de parte / variante..."
                        autocomplete="off" />
                </div>
            </div>
            <div class="col-lg-4">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" style="border-radius:var(--pv-radius-sm);">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar
                    </button>
                    <?php if ($search !== ''): ?>
                        <button type="button" id="clear-search-partes" class="btn btn-outline-secondary" title="Limpiar búsqueda" style="border-radius:var(--pv-radius-sm);">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($currentPerPage > 0): ?>
            <input type="hidden" name="per_page" value="<?= $currentPerPage ?>">
        <?php endif; ?>
    </form>
</div>

<div id="partesList" class="pv-fade-in">
    <?php if (empty($partItems)): ?>
        <div class="pv-empty-state">
            <i class="fa-solid fa-inbox"></i>
            <h3>Sin resultados</h3>
            <p>No se encontraron partes<?= $search !== '' ? ' para su búsqueda' : '' ?>.</p>
        </div>
    <?php else: ?>
        <?php foreach ($partItems as $index => $parte): ?>
            <?php
            $variantes = $parte['variantes'] ?? [];
            $cardId = 'parte_card_' . $parte['id'];
            ?>
            <div class="pv-parte-card" id="<?= $cardId ?>">
                <div class="pv-parte-header" onclick="pvToggleCard('<?= $cardId ?>')">
                    <div class="pv-parte-info">
                        <span class="pv-parte-code"><?= View::escape($parte['codigo']) ?></span>
                        <span class="pv-parte-detail"><?= View::escape($parte['detalle']) ?></span>
                    </div>
                    <div class="pv-parte-meta">
                        <?php if ($parte['grupo_nombre']): ?>
                            <span class="pv-badge pv-badge-group"><i class="fa-solid fa-layer-group" style="font-size:.65rem;"></i> <?= View::escape($parte['grupo_nombre']) ?></span>
                        <?php endif; ?>
                        <?php if ($parte['tipo_nombre']): ?>
                            <span class="pv-badge pv-badge-type"><?= View::escape($parte['tipo_codigo'] ?? substr($parte['tipo_nombre'], 0, 2)) ?></span>
                        <?php endif; ?>
                        <span class="pv-badge pv-badge-variants"><i class="fa-solid fa-layer-group" style="font-size:.6rem;"></i> <?= count($variantes) ?> variante<?= count($variantes) !== 1 ? 's' : '' ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="<?= url('productos/partes/manager/' . $parte['id']) ?>"
                            class="pv-action-btn"
                            title="Editar / Gestionar Parte"
                            onclick="event.stopPropagation();">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <div class="pv-chevron"><i class="fa-solid fa-chevron-down"></i></div>
                    </div>
                </div>
                <div class="pv-parte-body">
                    <div class="pv-parte-body-inner">
                        <?php if (empty($variantes)): ?>
                            <div class="pv-no-variants">
                                <div style="display:flex;align-items:center;gap:.75rem;justify-content:center;">
                                    <i class="fa-solid fa-circle-plus" style="font-size:1.25rem;color:var(--pv-primary);"></i>
                                    <span>Esta parte aún no tiene variantes registradas.</span>
                                </div>
                                <a href="<?= url('productos/partes/manager/' . $parte['id']) ?>" class="btn btn-sm btn-primary mt-2" style="border-radius:var(--pv-radius-sm);">
                                    <i class="fa-solid fa-plus me-1"></i> Agregar variante
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="pv-variant-table-responsive">
                                <table class="pv-variant-table">
                                    <thead>
                                        <tr>
                                            <th>Código Variante</th>
                                            <th>Detalle</th>
                                            <th>Estado</th>
                                            <th>Stock</th>
                                            <th>Peso</th>
                                            <th style="text-align:right;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($variantes as $variante): ?>
                                            <?php $estadoClass = $estadoMap[$variante['estado']] ?? 'activa'; ?>
                                            <?php
                                            $stockValue = $variante['stock_actual'] ?? null;
                                            $pesoValue = $variante['peso'] ?? null;
                                            $pesoUm = $variante['id_um_peso'] ?? null;
                                            ?>
                                            <tr>
                                                <td><span class="pv-variant-code"><?= View::escape($variante['codigo_variante']) ?></span></td>
                                                <td><?= View::escape($variante['detalle']) ?></td>
                                                <td>
                                                    <span class="pv-estado-<?= $estadoClass ?>">
                                                        <span class="pv-estado-dot"></span>
                                                        <span class="pv-estado-label"><?= $estadoLabels[$variante['estado']] ?? ucfirst($variante['estado']) ?></span>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($stockValue !== null && $stockValue !== ''): ?>
                                                        <span class="pv-stock-value"><?= number_format((float) $stockValue, 0, ',', '.') ?></span>
                                                    <?php else: ?>
                                                        <span style="color:var(--pv-text-light);">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($pesoValue !== null && $pesoValue !== '' && (float) $pesoValue > 0): ?>
                                                        <?= number_format((float) $pesoValue, 3, ',', '.') ?>
                                                        <?php if ($pesoUm): ?>
                                                            <small style="color:var(--pv-text-light);">kg</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span style="color:var(--pv-text-light);">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="pv-variant-actions">
                                                        <a href="<?= url("productos/partes/manager/{$parte['id']}/variantes/{$variante['id']}") ?>"
                                                            class="pv-action-btn" title="Editar variante">
                                                            <i class="fa-solid fa-pen"></i>
                                                        </a>
                                                        <a href="<?= url('productos/maestro?id_variante=' . $variante['id']) ?>"
                                                            class="pv-action-btn" title="Cargar como Maestro" target="_blank">
                                                            <i class="fa-solid fa-network-wired"></i>
                                                        </a>
                                                        <a href="<?= url('reportes/destino-partes?id_variante=' . $variante['id']) ?>"
                                                            class="pv-action-btn" title="Destino de partes" target="_blank">
                                                            <i class="fa-solid fa-layer-group"></i>
                                                        </a>
                                                        <button type="button" class="pv-action-btn danger" title="Eliminar variante"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalDeleteVariante"
                                                            data-parte-id="<?= $parte['id'] ?>"
                                                            data-variante-id="<?= $variante['id'] ?>"
                                                            data-codigo="<?= View::escape($variante['codigo_variante']) ?>">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </div>
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
    <?php endif; ?>
</div>

<?php if (!$showAll && $totalPages > 1): ?>
    <nav class="pv-pagination" aria-label="Navegación de partes">
        <a href="<?= $buildUrl(['page' => $prevPage, 'per_page' => $perPage]) ?>"
           class="pv-pagination-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>">
            <i class="fa-solid fa-chevron-left"></i>
        </a>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <?php if ($p === 1 || $p === $totalPages || abs($currentPage - $p) <= 2): ?>
                <a href="<?= $buildUrl(['page' => $p, 'per_page' => $perPage]) ?>"
                   class="pv-pagination-btn <?= $p === $currentPage ? 'active' : '' ?>"><?= $p ?></a>
            <?php elseif (abs($currentPage - $p) === 3): ?>
                <span class="pv-pagination-btn" style="cursor:default;border-color:transparent;">...</span>
            <?php endif; ?>
        <?php endfor; ?>
        <a href="<?= $buildUrl(['page' => $nextPage, 'per_page' => $perPage]) ?>"
           class="pv-pagination-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    </nav>
<?php endif; ?>

<div class="modal fade" id="modalDeleteVariante" tabindex="-1" aria-labelledby="modalDeleteVarianteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius:var(--pv-radius);border:1px solid var(--pv-border);">
            <div class="modal-body text-center py-4 px-3">
                <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,.1);display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;">
                    <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;font-size:1.35rem;"></i>
                </div>
                <h5 class="fw-bold mb-2" style="font-size:1rem;" id="modalDeleteVarianteLabel">Eliminar variante</h5>
                <p id="deleteVarianteMessage" class="mb-0" style="font-size:.875rem;color:var(--pv-text-muted);"></p>
            </div>
            <div class="d-flex gap-2 px-3 pb-3 justify-content-center">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:var(--pv-radius-sm);">Cancelar</button>
                <button type="button" class="btn btn-sm btn-danger" id="btnConfirmDeleteVariante" style="border-radius:var(--pv-radius-sm);">
                    <i class="fa-solid fa-trash me-1"></i> Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= AssetHelper::js('modules/SearchClient.js') ?>" defer></script>
<script src="<?= AssetHelper::js('partes-search.js') ?>" defer></script>
<script>
function pvToggleCard(cardId) {
    const card = document.getElementById(cardId);
    if (card) {
        card.classList.toggle('open');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('modalDeleteVariante');
    const msgEl = document.getElementById('deleteVarianteMessage');
    const btnConfirm = document.getElementById('btnConfirmDeleteVariante');
    let currentParteId = null;
    let currentVarianteId = null;

    modalEl.addEventListener('show.bs.modal', (e) => {
        const trigger = e.relatedTarget;
        currentParteId = trigger.dataset.parteId;
        currentVarianteId = trigger.dataset.varianteId;
        const codigo = trigger.dataset.codigo;
        msgEl.innerHTML = '¿Está seguro de que desea eliminar la variante <strong>' + codigo + '</strong>?<br><br><small style="color:var(--pv-text-light);">Si es la única variante de la parte, se eliminará también la parte completa.</small>';
    });

    btnConfirm.addEventListener('click', () => {
        if (!currentParteId || !currentVarianteId) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= url('productos/partes/') ?>' + currentParteId + '/variantes/' + currentVarianteId;
        const methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        methodField.value = 'DELETE';
        form.appendChild(methodField);
        const forceField = document.createElement('input');
        forceField.type = 'hidden';
        forceField.name = 'force_delete_parte';
        forceField.value = '1';
        form.appendChild(forceField);
        document.body.appendChild(form);
        bootstrap.Modal.getInstance(modalEl).hide();
        form.submit();
    });
});
</script>