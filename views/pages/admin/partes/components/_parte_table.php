<?php

use App\Core\View\View;

// Variables esperadas:
// $partItems, $totalParts, $dimensionFields, $unitSymbols, $search, $clearUrlPartes

?>
<div class="card h-100">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Listado de partes</h2>
            <span class="text-muted small"><?= $totalParts ?> registros</span>
        </div>
        <form method="get" class="mb-3" id="search-form-partes" data-clear-url="<?= $clearUrlPartes ?>">
            <input type="hidden" name="tab" value="partes">
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
    </div>
</div>
