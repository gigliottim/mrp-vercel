<?php

use App\Core\View\View;

// Variables esperadas:
// $partItems, $totalParts, $dimensionFields, $unitSymbols, $search, $clearUrlPartes

$currentPage = max(1, (int) ($parts['page'] ?? 1));
$perPage = (int) ($parts['per_page'] ?? 15);
$showAll = $perPage <= 0;
$totalPages = $showAll ? 1 : max(1, (int) ceil($totalParts / max(1, $perPage)));
$currentPage = min($currentPage, $totalPages);

$buildPartesUrl = static function (array $params) use ($search): string {
    $query = array_merge(['tab' => 'partes'], $params);
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
            <h2 class="h5 mb-0">Listado de partes</h2>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted small\"><?= $totalParts ?> registros</span>
                <form method="get" class="d-flex align-items-center gap-2 mb-0">
                    <input type="hidden" name="tab" value="partes">
                    <?php if ($search !== '') : ?>
                        <input type="hidden" name="q" value="<?= View::escape($search) ?>">
                    <?php endif; ?>
                    <label for="partes-per-page" class="small text-muted mb-0">Mostrar</label>
                    <select id="partes-per-page" name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="15" <?= $perPage === 15 ? 'selected' : '' ?>>15</option>
                        <option value="30" <?= $perPage === 30 ? 'selected' : '' ?>>30</option>
                        <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
                        <option value="all" <?= $showAll ? 'selected' : '' ?>>Todos</option>
                    </select>
                </form>
            </div>
        </div>
        <form method="get" class="mb-3" id="search-form-partes" data-clear-url="<?= $clearUrlPartes ?>">
            <input type="hidden" name="tab" value="partes">
            <input type="hidden" name="per_page" value="<?= $showAll ? 'all' : $perPage ?>">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fa-solid fa-search text-muted"></i>
                </span>
                <input class="form-control border-start-0 ps-0" type="search" name="q" id="search-input-partes" placeholder="Buscar por código o detalle..." value="<?= View::escape($search) ?>" autocomplete="off" data-has-search="<?= $search !== '' ? 'true' : 'false' ?>">
                <?php if ($search !== '') : ?>
                    <button class="btn btn-outline-secondary" type="button" id="clear-search-partes" title="Limpiar búsqueda">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                <?php endif; ?>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Detalle</th>
                        <th>Tipo</th>
                        <th>Grupo</th>
                        <th>Medidas</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($partItems as $part) : ?>
                        <tr>
                            <td class="fw-semibold"><?= View::escape($part['codigo']) ?></td>
                            <td><?= View::escape($part['detalle']) ?></td>
                            <td><?= View::escape($part['tipo_nombre'] ?? '') ?></td>
                            <td><?= View::escape($part['grupo_nombre'] ?? '') ?></td>
                            <td class="text-muted small">
                                <?php
                                $snippets = [];
                                foreach ($dimensionFields as $field => $meta) {
                                    if ($part[$field] !== null) {
                                        $unitKey = $meta['unit'];
                                        $value = rtrim(rtrim((string) $part[$field], '0'), '.');
                                        $unitSymbol = $unitSymbols[(int) ($part[$unitKey] ?? 0)] ?? '';
                                        $snippets[] = View::escape($value) . ' ' . View::escape($unitSymbol);
                                    }
                                }
                                echo $snippets === [] ? '--' : implode(' - ', $snippets);
                                ?>
                            </td>
                            <td>
                                <span class="badge <?= (int) $part['activo'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= (int) $part['activo'] === 1 ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a class="btn btn-outline-warning" href="<?= url('productos/partes/manager/' . (int) $part['id']) ?>" title="Gestionar Parte">
                                        <i class="fa-solid fa-gear"></i>
                                    </a>
                                    <form method="post" action="<?= url('productos/partes/' . (int) $part['id']) ?>" onsubmit="return confirm('¿Eliminar parte?');">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button class="btn btn-outline-danger" type="submit" title="Eliminar">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($partItems === []) : ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Sin partes registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$showAll && $totalPages > 1) : ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <div class="btn-group btn-group-sm" role="group" aria-label="Paginacion de partes">
                    <a class="btn btn-outline-secondary <?= $currentPage <= 1 ? 'disabled' : '' ?>" href="<?= $currentPage <= 1 ? '#' : $buildPartesUrl(['page' => $prevPage, 'per_page' => $perPage]) ?>" <?= $currentPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Anterior</a>
                    <a class="btn btn-outline-secondary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" href="<?= $currentPage >= $totalPages ? '#' : $buildPartesUrl(['page' => $nextPage, 'per_page' => $perPage]) ?>" <?= $currentPage >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Siguiente</a>
                </div>
                <div class="small text-muted">Pagina <?= $currentPage ?> de <?= $totalPages ?></div>
                <form method="get" class="d-flex align-items-center gap-2 mb-0">
                    <input type="hidden" name="tab" value="partes">
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">
                    <?php if ($search !== '') : ?>
                        <input type="hidden" name="q" value="<?= View::escape($search) ?>">
                    <?php endif; ?>
                    <label for="partes-go-page" class="small text-muted mb-0">Ir a</label>
                    <input id="partes-go-page" type="number" name="page" class="form-control form-control-sm" min="1" max="<?= $totalPages ?>" value="<?= $currentPage ?>" style="max-width: 90px;">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Ir</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
