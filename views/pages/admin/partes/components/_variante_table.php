<?php

use App\Core\View\View;

// Variables esperadas:
// $variants, $totalVariants, $filteredParteId, $partLookup,
// $variantStates, $masaLookup, $search, $clearUrlVariantes

?>
<div class="card h-100">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Variantes registradas</h2>
            <div class="d-flex align-items-center gap-2">
                <?php if ($filteredParteId !== null && $filteredParteId > 0) : ?>
                    <?php $filteredPart = $partLookup[$filteredParteId] ?? null; ?>
                    <?php if ($filteredPart !== null) : ?>
                        <span class="badge bg-info text-dark">Filtrando: <?= View::escape($filteredPart['codigo']) ?></span>
                        <a href="<?= url('productos/partes?tab=variantes') ?>" class="btn btn-sm btn-outline-secondary" title="Limpiar filtro">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <span class="text-muted small"><?= $totalVariants ?> registros</span>
            </div>
        </div>
        <form method="get" class="mb-3" id="search-form-variantes" data-clear-url="<?= $clearUrlVariantes ?>">
            <input type="hidden" name="tab" value="variantes">
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
                                        <form method="post" action="<?= url('productos/partes/' . (int) $parteId . '/variantes/' . (int) $variant['id']) ?>" onsubmit="return confirm('¿Eliminar variante?');">
                                            <input type="hidden" name="_method" value="DELETE">
                                            <button class="btn btn-outline-danger" type="submit">
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
    </div>
</div>
