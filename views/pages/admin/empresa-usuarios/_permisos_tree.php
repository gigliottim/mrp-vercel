<?php

declare(strict_types=1);

use App\Core\Auth\TenantContext;
use App\Core\View\View;

/**
 * Parcial: panel izquierdo del árbol de menú para seleccionar el nodo ACL.
 *
 * Variables de entrada esperadas:
 *   $sectionOrder, $sectionRootsByKey, $selectedMenuId, $renderTreeNode, $errors
 */

$tenantData = TenantContext::get();
$tenantName = $tenantData['name'] ?? 'Empresa';
?>
<div id="acl-col-left" class="col-12 col-md-4 d-flex flex-column">
    <div class="card d-flex flex-column flex-grow-1 acl-tree-card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center sticky-top">
            <h3 class="h6 mb-0">Estructura</h3>
            <small class="text-muted">Seleccione un módulo</small>
        </div>
        <div class="card-body d-flex flex-column flex-grow-1">
            <div class="input-group input-group-sm mb-2 flex-shrink-0">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input id="menu-tree-search" type="text" class="form-control" placeholder="Filtrar menú...">
            </div>

            <div class="acl-tree-panel border-0 px-0 flex-grow-1" id="acl-tree-panel">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <div class="p-2 mb-2 bg-light rounded d-flex align-items-center gap-2">
                            <i class="fa-solid fa-folder text-warning fs-5" style="width:24px; text-align:center;"></i>
                            <div class="d-flex flex-column lh-sm">
                                <span class="fw-bold text-dark text-uppercase"><?= View::escape($tenantName) ?></span>
                                <small class="text-secondary text-uppercase" style="font-size: 0.75rem;">Administración de Permisos</small>
                            </div>
                        </div>

                        <ul class="list-unstyled ms-3 ps-2 border-start border-2 border-light mb-0">
                            <?php foreach ($sectionOrder as $sectionKey => $sectionLabel) : ?>
                                <?php $sectionRoots = $sectionRootsByKey[$sectionKey] ?? []; ?>
                                <?php if ($sectionRoots === []) {
                                    continue;
                                } ?>
                                <li class="mb-2 mt-3" data-tree-section="1">
                                    <div class="p-2 mb-1 d-flex align-items-center gap-2 rounded text-muted" style="cursor:default;">
                                        <i class="fa-solid fa-folder text-warning fs-5" style="width:24px; text-align:center;"></i>
                                        <div class="d-flex flex-column lh-sm">
                                            <span class="fw-bold text-dark text-uppercase"><?= View::escape((string) $sectionLabel) ?></span>
                                            <small class="text-secondary text-uppercase" style="font-size: 0.75rem;">Rama / Sección</small>
                                        </div>
                                    </div>

                                    <ul class="list-unstyled ms-3 ps-2 border-start border-2 border-light mb-0 acl-tree-list mt-1">
                                        <?php foreach ($sectionRoots as $rootNode) : ?>
                                            <?= $renderTreeNode($rootNode) ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <input
        id="menu_item_id"
        class="form-control d-none<?= isset($errors['menu_item_id']) ? ' is-invalid' : '' ?>"
        type="number"
        name="menu_item_id"
        value="<?= View::escape((string) $selectedMenuId) ?>"
        min="1">
</div>
