<?php

declare(strict_types=1);

use App\Core\View\View;
use App\Core\Support\AssetHelper;

$old = $old ?? [];
$editing = $editing ?? null;
$errors = $errors ?? [];
$aclRows = $aclRows ?? [];
$menuTree = $menuTree ?? [];
$subjects = $subjects ?? [];

$oldValue = static function (string $field, $default = '') use ($old, $editing) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }

    if ($editing !== null && array_key_exists($field, $editing)) {
        return $editing[$field];
    }

    return $default;
};

$subjectTypeOptions = ['role' => 'Rol', 'user' => 'Usuario'];
$scopeOptions = ['item' => 'Item', 'branch' => 'Branch'];
$permissionOptions = ['read' => 'Read', 'write' => 'Write', 'deny' => 'Deny'];

$selectedMenuId = (int) $oldValue('menu_item_id', 0);
$selectedSubjectType = (string) $oldValue('subject_type', 'role');
$selectedSubjectId = (int) $oldValue('subject_id', 0);

$nodesById = [];
$sectionOrder = [];
foreach ($menuTree as $node) {
    $id = (int) ($node['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }

    $sectionKey = (string) ($node['section_key'] ?? 'general');
    if (!isset($sectionOrder[$sectionKey])) {
        $sectionOrder[$sectionKey] = (string) ($node['section_label'] ?? 'General');
    }

    $nodesById[$id] = [
        'id' => $id,
        'label' => (string) ($node['label'] ?? ''),
        'code' => (string) ($node['code'] ?? ''),
        'icon' => (string) ($node['icon'] ?? 'fa-solid fa-circle'),
        'parent_id' => isset($node['parent_id']) && $node['parent_id'] !== null ? (int) $node['parent_id'] : 0,
        'sort_order' => (int) ($node['sort_order'] ?? 0),
        'section_key' => $sectionKey,
        'section_label' => (string) ($node['section_label'] ?? 'General'),
    ];
}

$childrenByParent = [];
foreach ($nodesById as $node) {
    $parentId = $node['parent_id'];
    if (!isset($nodesById[$parentId])) {
        $parentId = 0;
    }
    $childrenByParent[$parentId][] = $node;
}

foreach ($childrenByParent as &$children) {
    usort($children, static function (array $a, array $b): int {
        $orderDiff = ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
        if ($orderDiff !== 0) {
            return $orderDiff;
        }
        return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
    });
}
unset($children);

$sectionRootsByKey = [];
foreach ($sectionOrder as $sectionKey => $sectionLabel) {
    $sectionRootsByKey[$sectionKey] = array_values(array_filter(
        $childrenByParent[0] ?? [],
        static fn(array $node): bool => (string) ($node['section_key'] ?? '') === $sectionKey
    ));
}

$selectedMenuInfo = null;
if ($selectedMenuId > 0 && isset($nodesById[$selectedMenuId])) {
    $selectedMenuInfo = $nodesById[$selectedMenuId];
}

if ($menuTree === []) {
    $menuTree = [
        ['id' => 0, 'label' => 'Empresa', 'code' => 'empresa'],
        ['id' => 0, 'label' => 'Usuarios', 'code' => 'usuarios'],
        ['id' => 0, 'label' => 'Roles', 'code' => 'roles'],
        ['id' => 0, 'label' => 'Permisos', 'code' => 'permisos'],
    ];
}
?>
<link rel="stylesheet" href="<?= AssetHelper::css('modules/empresa-usuarios/permisos-tree.css') ?>">

<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Empresa y Usuarios</p>
            <h1 class="h3 mb-0">Permisos</h1>
        </div>
    </div>
</section>

<nav class="nav nav-pills mb-4 flex-wrap gap-2">
    <a class="nav-link" href="<?= url('/empresa-usuarios/empresa') ?>">Empresa</a>
    <a class="nav-link" href="<?= url('/empresa-usuarios/usuarios') ?>">Usuarios</a>
    <a class="nav-link" href="<?= url('/empresa-usuarios/roles') ?>">Roles</a>
    <a class="nav-link active" href="<?= url('/empresa-usuarios/permisos') ?>">Permisos</a>
</nav>

<div class="row g-4">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="text-muted small text-uppercase mb-1">Formulario ACL</p>
                        <h2 class="h5 mb-0"><?= $editing ? 'Editar permiso' : 'Nuevo permiso' ?></h2>
                    </div>
                    <?php if ($editing) : ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/empresa-usuarios/permisos') ?>">Cancelar</a>
                    <?php endif; ?>
                </div>

                <?php if (isset($errors['general'])) : ?>
                    <div class="alert alert-danger"><?= View::escape($errors['general']) ?></div>
                <?php endif; ?>

                <form
                    method="post"
                    action="<?= $editing ? url('/roles-permisos/acl/' . (int) $editing['id']) : url('/roles-permisos/acl') ?>"
                    class="vstack gap-3">
                    <?php if ($editing) : ?>
                        <input type="hidden" name="_method" value="PUT">
                    <?php endif; ?>
                    <div class="row g-4">
                        <div class="col-12 col-md-4">
                            <div class="card h-100 acl-tree-card">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h3 class="h6 mb-0">Estructura</h3>
                                    <small class="text-muted">Seleccion visual por nodo</small>
                                </div>
                                <div class="card-body">
                                    <label class="form-label mb-2" for="menu-tree-search">Arbol de menu</label>

                                    <div class="input-group input-group-sm mb-2">
                                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                                        <input id="menu-tree-search" type="text" class="form-control" placeholder="Filtrar por etiqueta o codigo">
                                    </div>

                                    <?php
                                    $renderTreeNode = function (array $treeNode) use (&$renderTreeNode, $childrenByParent, $selectedMenuId): string {
                                        $nodeId = (int) ($treeNode['id'] ?? 0);
                                        $nodeLabel = (string) ($treeNode['label'] ?? '');
                                        $nodeCode = (string) ($treeNode['code'] ?? '');
                                        $isSelected = $nodeId === $selectedMenuId;
                                        $children = $childrenByParent[$nodeId] ?? [];

                                        $iconClass = ($children !== []) ? 'fa-solid fa-folder text-warning' : 'fa-solid fa-cube text-info';

                                        $html = '<li class="mb-1 acl-tree-li" data-tree-li="1">';
                                        $html .= '<div class="p-2 rounded d-flex align-items-center gap-2 acl-tree-node' . ($isSelected ? ' is-selected' : '') . '"';
                                        $html .= ' data-tree-node';
                                        $html .= ' data-node-id="' . $nodeId . '"';
                                        $html .= ' data-node-label="' . esc($nodeLabel) . '"';
                                        $html .= ' data-node-code="' . esc($nodeCode) . '"';
                                        $html .= ' data-search-text="' . esc(mb_strtolower($nodeLabel . ' ' . $nodeCode)) . '">';

                                        $html .= '<i class="' . $iconClass . ' fs-5" style="width:24px; text-align:center;"></i>';

                                        $html .= '<div class="d-flex flex-column lh-sm">';
                                        $html .= '<span class="fw-bold text-dark text-uppercase">' . esc($nodeCode) . '</span>';
                                        $html .= '<small class="text-secondary text-uppercase" style="font-size: 0.75rem;">' . esc($nodeLabel) . '</small>';
                                        $html .= '</div></div>';

                                        if ($children !== []) {
                                            $html .= '<ul class="list-unstyled ms-3 ps-2 border-start border-2 border-light mb-0 acl-tree-list mt-1">';
                                            foreach ($children as $childNode) {
                                                $html .= $renderTreeNode($childNode);
                                            }
                                            $html .= '</ul>';
                                        }

                                        $html .= '</li>';
                                        return $html;
                                    };

                                    $tenantData = \App\Core\Auth\TenantContext::get();
                                    $tenantName = $tenantData['name'] ?? 'Empresa';
                                    ?>

                                    <div class="acl-tree-panel border-0 px-0" id="acl-tree-panel">
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
                                                        <li class="mb-2" data-tree-section="1">
                                                            <div class="p-2 mb-1 d-flex align-items-center gap-2 rounded">
                                                                <i class="fa-solid fa-folder text-warning fs-5" style="width:24px; text-align:center;"></i>
                                                                <div class="d-flex flex-column lh-sm">
                                                                    <span class="fw-bold text-dark text-uppercase"><?= View::escape((string) $sectionLabel) ?></span>
                                                                    <small class="text-secondary text-uppercase" style="font-size: 0.75rem;">Sección</small>
                                                                </div>
                                                            </div>

                                                            <ul class="list-unstyled ms-3 ps-2 border-start border-2 border-light mb-0 acl-tree-list">
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
                                min="1"
                                required>

                            <?php if (isset($errors['menu_item_id'])) : ?>
                                <div class="text-danger small mt-1"><?= View::escape($errors['menu_item_id']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-8">
                            <div class="acl-selected-box mb-3">
                                <div class="small text-muted">Nodo seleccionado</div>
                                <div id="selected-menu-node" class="fw-semibold">
                                    <?php if ($selectedMenuInfo !== null) : ?>
                                        <?= View::escape((string) $selectedMenuInfo['label']) ?>
                                        <span class="text-muted">(<?= View::escape((string) $selectedMenuInfo['code']) ?>)</span>
                                    <?php else : ?>
                                        Ninguno
                                    <?php endif; ?>
                                </div>
                            </div>

                            <label class="form-label" for="subject_type">Sujeto</label>
                            <select
                                id="subject_type"
                                class="form-select<?= isset($errors['subject_type']) ? ' is-invalid' : '' ?>"
                                name="subject_type"
                                required>
                                <?php foreach ($subjectTypeOptions as $key => $label) : ?>
                                    <option value="<?= $key ?>" <?= $selectedSubjectType === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['subject_type'])) : ?>
                                <div class="invalid-feedback d-block"><?= View::escape($errors['subject_type']) ?></div>
                            <?php endif; ?>

                            <div class="mt-3">
                                <label class="form-label" for="subject_ref">Seleccion rapida (rol/usuario)</label>
                                <select id="subject_ref" class="form-select">
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($subjects as $subject) : ?>
                                        <?php
                                        $sType = (string) ($subject['subject_type'] ?? '');
                                        $sId = (int) ($subject['id'] ?? 0);
                                        $selected = $sType === $selectedSubjectType && $sId === $selectedSubjectId;
                                        ?>
                                        <option
                                            value="<?= View::escape($sType . ':' . $sId) ?>"
                                            data-subject-type="<?= View::escape($sType) ?>"
                                            data-subject-id="<?= $sId ?>"
                                            <?= $selected ? 'selected' : '' ?>>
                                            <?= View::escape((string) ($subject['label'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <input
                                id="subject_id"
                                type="hidden"
                                name="subject_id"
                                value="<?= View::escape((string) $selectedSubjectId) ?>"
                                required>

                            <?php if (isset($errors['subject_id'])) : ?>
                                <div class="text-danger small mt-2"><?= View::escape($errors['subject_id']) ?></div>
                            <?php endif; ?>

                            <div class="row g-3 mt-1">
                                <div class="col-6">
                                    <label class="form-label" for="scope">Scope</label>
                                    <select
                                        id="scope"
                                        class="form-select<?= isset($errors['scope']) ? ' is-invalid' : '' ?>"
                                        name="scope"
                                        required>
                                        <?php $selectedScope = (string) $oldValue('scope', 'item'); ?>
                                        <?php foreach ($scopeOptions as $key => $label) : ?>
                                            <option value="<?= $key ?>" <?= $selectedScope === $key ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['scope'])) : ?>
                                        <div class="invalid-feedback d-block"><?= View::escape($errors['scope']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="permission_level">Permiso</label>
                                    <select
                                        id="permission_level"
                                        class="form-select<?= isset($errors['permission_level']) ? ' is-invalid' : '' ?>"
                                        name="permission_level"
                                        required>
                                        <?php $selectedPerm = (string) $oldValue('permission_level', 'read'); ?>
                                        <?php foreach ($permissionOptions as $key => $label) : ?>
                                            <option value="<?= $key ?>" <?= $selectedPerm === $key ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['permission_level'])) : ?>
                                        <div class="invalid-feedback d-block"><?= View::escape($errors['permission_level']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-grid mt-3">
                                <button class="btn btn-primary" type="submit">
                                    <?= $editing ? 'Actualizar permiso' : 'Crear permiso' ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Tabla ACL (Arbol / Sujeto / Permiso)</h2>
                    <span class="text-muted small"><?= count($aclRows) ?> resultados</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Arbol</th>
                                <th>Sujeto</th>
                                <th>Permiso</th>
                                <th>Scope</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aclRows as $row) : ?>
                                <?php
                                $subjectText = (string) (($row['subject_type'] ?? '') . ':' . ($row['subject_id'] ?? ''));
                                $permission = (string) ($row['permission_level'] ?? 'read');
                                $badgeClass = 'text-bg-secondary';
                                if ($permission === 'write') {
                                    $badgeClass = 'text-bg-primary';
                                }
                                if ($permission === 'deny') {
                                    $badgeClass = 'text-bg-danger';
                                }
                                ?>
                                <tr>
                                    <td><?= View::escape((string) ($row['menu_label'] ?? '')) ?></td>
                                    <td><?= View::escape($subjectText) ?></td>
                                    <td><span class="badge <?= $badgeClass ?>"><?= View::escape($permission) ?></span></td>
                                    <td><?= View::escape((string) ($row['scope'] ?? 'item')) ?></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a class="btn btn-outline-secondary" href="<?= url('/empresa-usuarios/permisos/' . (int) ($row['id'] ?? 0) . '/editar') ?>">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <form
                                                method="post"
                                                action="<?= url('/empresa-usuarios/permisos/' . (int) ($row['id'] ?? 0)) ?>"
                                                onsubmit="return confirm('Eliminar permiso ACL?');">
                                                <input type="hidden" name="_method" value="DELETE">
                                                <button class="btn btn-outline-danger" type="submit">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ($aclRows === []) : ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Sin reglas ACL para mostrar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= AssetHelper::js('modules/empresa-usuarios/permisos-tree.js') ?>" defer></script>
