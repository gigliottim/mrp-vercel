<?php

use App\Core\View\View;

// Variables esperadas:
// $variants, $totalVariants, $filteredParteId, $partLookup,
// $variantStates, $masaLookup, $search, $clearUrlVariantes

$variantsMeta = $variantsMeta ?? ['page' => 1, 'per_page' => 15, 'total' => $totalVariants];
$currentPage = max(1, (int) ($variantsMeta['page'] ?? 1));
$perPage = (int) ($variantsMeta['per_page'] ?? 15);
$showAll = $perPage <= 0;
$totalPages = $showAll ? 1 : max(1, (int) ceil($totalVariants / max(1, $perPage)));
$currentPage = min($currentPage, $totalPages);

$buildVariantesUrl = static function (array $params) use ($search, $filteredParteId): string {
    $query = array_merge(['tab' => 'variantes'], $params);
    if ($filteredParteId !== null && $filteredParteId > 0) {
        $query['id_parte'] = (int) $filteredParteId;
    }
    if ($search !== '') {
        $query['q'] = $search;
    }
    return url('productos/partes?' . http_build_query($query));
};

$prevPage = max(1, $currentPage - 1);
$nextPage = min($totalPages, $currentPage + 1);

?>
<div class="card h-100">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h5 mb-0">Variantes registradas</h2>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php if ($filteredParteId !== null && $filteredParteId > 0) : ?>
                    <?php $filteredPart = $partLookup[$filteredParteId] ?? null; ?>
                    <?php if ($filteredPart !== null) : ?>
                        <span class="badge bg-info text-dark">Filtrando: <?= View::escape($filteredPart['codigo']) ?></span>
                        <a href="<?= url('productos/partes?tab=variantes' . ($showAll ? '&per_page=all' : '&per_page=' . $perPage)) ?>" class="btn btn-sm btn-outline-secondary" title="Limpiar filtro">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <span class="text-muted small"><?= $totalVariants ?> registros</span>
                <form method="get" class="d-flex align-items-center gap-2 mb-0">
                    <input type="hidden" name="tab" value="variantes">
                    <?php if ($filteredParteId !== null && $filteredParteId > 0) : ?>
                        <input type="hidden" name="id_parte" value="<?= (int) $filteredParteId ?>">
                    <?php endif; ?>
                    <?php if ($search !== '') : ?>
                        <input type="hidden" name="q" value="<?= View::escape($search) ?>">
                    <?php endif; ?>
                    <label for="variantes-per-page" class="small text-muted mb-0">Mostrar</label>
                    <select id="variantes-per-page" name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="15" <?= $perPage === 15 ? 'selected' : '' ?>>15</option>
                        <option value="30" <?= $perPage === 30 ? 'selected' : '' ?>>30</option>
                        <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
                        <option value="all" <?= $showAll ? 'selected' : '' ?>>Todos</option>
                    </select>
                </form>
            </div>
        </div>
        <form method="get" class="mb-3" id="search-form-variantes" data-clear-url="<?= $clearUrlVariantes ?>">
            <input type="hidden" name="tab" value="variantes">
            <input type="hidden" name="per_page" value="<?= $showAll ? 'all' : $perPage ?>">
            <?php if ($filteredParteId !== null && $filteredParteId > 0) : ?>
                <input type="hidden" name="id_parte" value="<?= (int) $filteredParteId ?>">
            <?php endif; ?>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fa-solid fa-search text-muted"></i>
                </span>
                <input class="form-control border-start-0 ps-0" type="search" name="q" id="search-input-variantes" placeholder="Buscar por parte, código o detalle..." value="<?= View::escape($search) ?>" autocomplete="off" data-has-search="<?= $search !== '' ? 'true' : 'false' ?>">
                <?php if ($search !== '') : ?>
                    <button class="btn btn-outline-secondary" type="button" id="clear-search-variantes" title="Limpiar búsqueda">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                <?php endif; ?>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Parte</th>
                        <th>Código</th>
                        <th>Detalle</th>
                        <th>Estado</th>
                        <th>Stock</th>
                        <th>Peso</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($variants as $parteId => $items) : ?>
                        <?php $canDeletePartVariants = count($items) > 1; ?>
                        <?php foreach ($items as $variant) : ?>
                            <tr>
                                <td>
                                    <?php $parte = $partLookup[(int) $parteId] ?? null; ?>
                                    <span class="fw-semibold">
                                        <?= View::escape($parte['codigo'] ?? ('ID ' . $parteId)) ?>
                                    </span>
                                    <br>
                                    <small class="text-muted"><?= View::escape($parte['detalle'] ?? '') ?></small>
                                </td>
                                <td><?= View::escape($variant['codigo_variante']) ?></td>
                                <td><?= View::escape($variant['detalle']) ?></td>
                                <td>
                                    <span class="badge <?= $variant['estado'] === 'activa' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= View::escape($variantStates[$variant['estado']] ?? $variant['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= View::escape((string) $variant['stock_actual']) ?>
                                    <small class="text-muted">min <?= View::escape((string) $variant['lote_minimo']) ?> / pedido <?= View::escape((string) $variant['punto_pedido']) ?></small>
                                </td>
                                <td>
                                    <?php if ($variant['peso'] !== null) : ?>
                                        <?= View::escape((string) $variant['peso']) ?>
                                        <small class="text-muted">
                                            <?= View::escape($masaLookup[(int) ($variant['id_um_peso'] ?? 0)]['simbolo'] ?? '') ?>
                                        </small>
                                    <?php else : ?>
                                        --
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a class="btn btn-outline-warning" href="<?= url('productos/partes/manager/' . (int) $parteId . '/variantes/' . (int) $variant['id'] . '/editar') ?>" title="Gestionar Variante">
                                            <i class="fa-solid fa-gear"></i>
                                        </a>
                                        <form method="post" action="<?= url('productos/partes/' . (int) $parteId . '/variantes/' . (int) $variant['id']) ?>" onsubmit="return <?= $canDeletePartVariants ? "confirm('¿Eliminar variante?');" : 'false;' ?>">
                                            <input type="hidden" name="_method" value="DELETE">
                                            <button class="btn btn-outline-danger" type="submit" <?= $canDeletePartVariants ? '' : 'disabled title="No se puede eliminar la ultima variante de una parte"' ?>>
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php if ($totalVariants === 0) : ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Sin variantes registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$showAll && $totalPages > 1) : ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <div class="btn-group btn-group-sm" role="group" aria-label="Paginacion de variantes">
                    <a class="btn btn-outline-secondary <?= $currentPage <= 1 ? 'disabled' : '' ?>" href="<?= $currentPage <= 1 ? '#' : $buildVariantesUrl(['page' => $prevPage, 'per_page' => $perPage]) ?>" <?= $currentPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Anterior</a>
                    <a class="btn btn-outline-secondary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" href="<?= $currentPage >= $totalPages ? '#' : $buildVariantesUrl(['page' => $nextPage, 'per_page' => $perPage]) ?>" <?= $currentPage >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Siguiente</a>
                </div>
                <div class="small text-muted">Pagina <?= $currentPage ?> de <?= $totalPages ?></div>
                <form method="get" class="d-flex align-items-center gap-2 mb-0">
                    <input type="hidden" name="tab" value="variantes">
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <?php if ($filteredParteId !== null && $filteredParteId > 0) : ?>
                        <input type="hidden" name="id_parte" value="<?= (int) $filteredParteId ?>">
                    <?php endif; ?>
                    <?php if ($search !== '') : ?>
                        <input type="hidden" name="q" value="<?= View::escape($search) ?>">
                    <?php endif; ?>
                    <label for="variantes-go-page" class="small text-muted mb-0">Ir a</label>
                    <input id="variantes-go-page" type="number" name="page" class="form-control form-control-sm" min="1" max="<?= $totalPages ?>" value="<?= $currentPage ?>" style="max-width: 90px;">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Ir</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
