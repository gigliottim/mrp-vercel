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

$rolesList = array_filter($subjects, fn($s) => $s['subject_type'] === 'role');
$usersList = array_filter($subjects, fn($s) => $s['subject_type'] === 'user');

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
<style>
    .acl-tree-card {
        /* eliminamos max-height fijo para que se adapte al contenedor padre */
    }

    .acl-tree-node {
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .acl-tree-node:hover {
        background-color: #e9ecef;
    }

    .acl-tree-node.is-selected {
        background-color: #cfe2ff;
        border-left: 3px solid #0d6efd;
    }

    .permission-row {
        transition: background-color 0.2s;
    }

    .permission-row:hover {
        background-color: #f8f9fa;
    }

    .role-group-header,
    .user-group-header {
        background-color: #e9ecef;
        padding: 8px 12px;
        font-weight: 600;
        font-size: 0.9em;
        text-transform: uppercase;
        border-radius: 4px;
        margin-top: 15px;
        margin-bottom: 10px;
    }
</style>
<link rel="stylesheet" href="<?= AssetHelper::css('modules/empresa-usuarios/permisos-tree.css') ?>">

<section class="mb-4 flex-shrink-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Empresa y Usuarios</p>
            <h1 class="h3 mb-0">Permisos</h1>
        </div>
    </div>
</section>

<nav class="nav nav-pills mb-4 flex-wrap gap-2 flex-shrink-0">
    <a class="nav-link" href="<?= url('/empresa-usuarios/empresa') ?>">Empresa</a>
    <a class="nav-link" href="<?= url('/empresa-usuarios/usuarios') ?>">Usuarios</a>
    <a class="nav-link" href="<?= url('/empresa-usuarios/roles') ?>">Roles</a>
    <a class="nav-link active" href="<?= url('/empresa-usuarios/permisos') ?>">Permisos</a>
</nav>

<div class="row g-4 flex-grow-1" style="min-height: 50vh;">
    <div class="col-12 h-100 d-flex flex-column">
        <div class="card flex-grow-1 d-flex flex-column">
            <div class="card-body d-flex flex-column">
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
                    action="<?= url('/roles-permisos/acl') ?>"
                    class="vstack gap-3 flex-grow-1">
                    <div class="row g-4 flex-grow-1 mb-4">
                        <div class="col-12 col-md-4 d-flex flex-column">
                            <div class="card d-flex flex-column flex-grow-1 acl-tree-card">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center sticky-top">
                                    <h3 class="h6 mb-0">Estructura</h3>
                                    <small class="text-muted">Seleccione un módulo</small>
                                </div>
                                <div class="card-body d-flex flex-column" style="overflow-y: hidden;">
                                    <div class="input-group input-group-sm mb-2 flex-shrink-0">
                                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                                        <input id="menu-tree-search" type="text" class="form-control" placeholder="Filtrar menú...">
                                    </div>

                                    <?php
                                    $renderTreeNode = function (array $treeNode) use (&$renderTreeNode, $childrenByParent, $selectedMenuId): string {
                                        $nodeId = (int) ($treeNode['id'] ?? 0);
                                        $nodeLabel = (string) ($treeNode['label'] ?? '');
                                        $nodeCode = (string) ($treeNode['code'] ?? '');
                                        $isSelected = $nodeId === $selectedMenuId;
                                        $children = $childrenByParent[$nodeId] ?? [];

                                        $isItem = ($children !== []) ? 'false' : 'true';
                                        $iconClass = ($children !== []) ? 'fa-solid fa-folder text-warning' : 'fa-solid fa-cube text-info';

                                        $html = '<li class="mb-1 acl-tree-li" data-tree-li="1">';
                                        $html .= '<div class="p-2 rounded d-flex align-items-center gap-2 acl-tree-node' . ($isSelected ? ' is-selected' : '') . '"';
                                        $html .= ' data-tree-node';
                                        $html .= ' data-node-id="' . $nodeId . '"';
                                        $html .= ' data-node-label="' . esc($nodeLabel) . '"';
                                        $html .= ' data-node-code="' . esc($nodeCode) . '"';
                                        $html .= ' onclick="selectNode(event, ' . $nodeId . ', \'' . esc($nodeLabel) . '\', \'' . esc($nodeCode) . '\', ' . $isItem . ')"';
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

                                    <div class="acl-tree-panel border-0 px-0 flex-grow-1" id="acl-tree-panel" style="overflow-y: auto;">
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
                                                            <div class="p-2 mb-1 d-flex align-items-center gap-2 rounded acl-tree-node" onclick="selectNode(event, '<?= esc((string) $sectionKey) ?>', '<?= esc((string) $sectionLabel) ?>', '<?= esc((string) $sectionKey) ?>', false)">
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

                        <div class="col-12 col-md-8 d-flex flex-column">
                            <div class="card h-100 d-flex flex-column flex-grow-1">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center sticky-top">
                                    <div>
                                        <h3 class="h6 mb-0">Asignación de Permisos</h3>
                                        <small class="text-muted">Para: <strong id="selected-node-title" class="text-primary">-</strong>
                                            <span id="selected-node-type" class="badge bg-secondary ms-1">Seleccione un elemento</span></small>
                                    </div>
                                    <div class="input-group input-group-sm" style="width: 200px;">
                                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                                        <input type="text" class="form-control" placeholder="Buscar sujeto...">
                                    </div>
                                </div>

                                <div class="card-body p-0 flex-grow-1" style="overflow-y: auto;">
                                    <div class="table-responsive h-100">
                                        <table class="table table-hover align-middle mb-0 border-top-0">
                                            <thead class="table-light sticky-top" style="top: 0px; z-index: 10;">
                                                <tr>
                                                    <th class="ps-4">Sujeto (Rol / Usuario)</th>
                                                    <th style="width: 35%">Nivel de Permiso</th>
                                                    <th style="width: 15%" class="text-center">Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- ROLES -->
                                                <tr>
                                                    <td colspan="3" class="p-0">
                                                        <div class="role-group-header ms-3 me-3"><i class="fa-solid fa-shield-halved text-secondary me-2"></i>Roles</div>
                                                    </td>
                                                </tr>
                                                <?php foreach ($rolesList as $role) : ?>
                                                    <tr class="permission-row">
                                                        <td class="ps-4 fw-medium"><?= View::escape((string) ($role['label'] ?? '')) ?></td>
                                                        <td>
                                                            <select name="perms[role][<?= (int)($role['id'] ?? 0) ?>]" class="form-select form-select-sm border-0 bg-transparent shadow-none" onchange="updateRowState(this)">
                                                                <option value="none" selected>No definido (Heredada)</option>
                                                                <option value="allow">Acceder</option>
                                                                <option value="deny">Denegar</option>
                                                            </select>
                                                        </td>
                                                        <td class="text-center status-indicator">
                                                            <span class="text-muted"><i class="fa-solid fa-minus"></i></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>

                                                <?php if ($rolesList === []) : ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted small py-2">No hay roles definidos</td>
                                                    </tr>
                                                <?php endif; ?>

                                                <!-- USUARIOS -->
                                                <tr>
                                                    <td colspan="3" class="p-0">
                                                        <div class="user-group-header ms-3 me-3"><i class="fa-solid fa-user text-secondary me-2"></i>Usuarios (Excepciones específicas)</div>
                                                    </td>
                                                </tr>
                                                <?php foreach ($usersList as $user) : ?>
                                                    <?php
                                                    $userName = (string) ($user['label'] ?? '');
                                                    $initials = mb_substr($userName, 0, 2);
                                                    ?>
                                                    <tr class="permission-row">
                                                        <td class="ps-4 fw-medium d-flex align-items-center gap-2">
                                                            <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center" style="width: 24px; height: 24px; font-size: 10px;">
                                                                <?= View::escape(strtoupper($initials)) ?>
                                                            </div>
                                                            <?= View::escape($userName) ?>
                                                        </td>
                                                        <td>
                                                            <select name="perms[user][<?= (int)($user['id'] ?? 0) ?>]" class="form-select form-select-sm border-0 bg-transparent shadow-none text-muted" onchange="updateRowState(this)">
                                                                <option value="none" selected>No definido (Heredada)</option>
                                                                <option value="allow">Acceder</option>
                                                                <option value="deny">Denegar</option>
                                                            </select>
                                                        </td>
                                                        <td class="text-center status-indicator">
                                                            <span class="text-muted"><i class="fa-solid fa-minus"></i></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>

                                                <?php if ($usersList === []) : ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted small py-2">No hay usuarios definidos</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="card-footer bg-light d-flex justify-content-end gap-2 py-3">
                                    <button class="btn btn-outline-secondary" type="button">Descartar Cambios</button>
                                    <button class="btn btn-primary d-flex align-items-center gap-2" type="submit">
                                        <i class="fa-solid fa-save"></i> Guardar Permisos
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <script>
                    const aclData = <?= json_encode($aclRows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;

                    function selectNode(e, nodeId, label, code, isItem) {
                        document.querySelectorAll('.acl-tree-node').forEach(el => el.classList.remove('is-selected'));
                        if (e) {
                            e.currentTarget.classList.add('is-selected');
                        }

                        document.getElementById('selected-node-title').textContent = label.toUpperCase();

                        let badge = document.getElementById('selected-node-type');
                        if (isItem) {
                            badge.textContent = 'Menú Ítem';
                            badge.className = 'badge bg-secondary ms-1';
                        } else {
                            badge.textContent = 'Rama / Grupo';
                            badge.className = 'badge bg-warning text-dark ms-1';
                        }

                        // Update hidden input if it's a numeric node
                        const numId = parseInt(nodeId, 10);
                        if (!isNaN(numId)) {
                            document.getElementById('menu_item_id').value = numId;
                        } else {
                            document.getElementById('menu_item_id').value = '';
                        }

                        // Reset all selects depending on node type
                        const defaultVal = isItem ? 'none' : 'allow';
                        document.querySelectorAll('select[name^="perms["]').forEach(select => {
                            select.value = defaultVal;
                            updateRowState(select);
                        });

                        // Apply saved ACLs for this node if any
                        if (!isNaN(numId)) {
                            const nodeAcls = aclData.filter(row => parseInt(row.menu_item_id, 10) === numId);
                            nodeAcls.forEach(acl => {
                                const type = acl.subject_type; // 'role' or 'user'
                                const id = acl.subject_id;
                                const level = acl.permission_level;

                                const selectNode = document.querySelector(`select[name="perms[${type}][${id}]"]`);
                                if (selectNode) {
                                    selectNode.value = level;
                                    updateRowState(selectNode);
                                }
                            });
                        }

                        // Efecto visual de carga
                        const tbody = document.querySelector('tbody');
                        tbody.style.opacity = '0.3';
                        setTimeout(() => {
                            tbody.style.opacity = '1';
                        }, 300);
                    }

                    function updateRowState(selectElement) {
                        const val = selectElement.value;
                        const rootRow = selectElement.closest('tr');
                        const indicator = rootRow.querySelector('.status-indicator');

                        // Reset classes
                        selectElement.classList.remove('text-primary', 'text-danger', 'text-muted', 'fw-bold');

                        if (val === 'allow') {
                            selectElement.classList.add('text-primary', 'fw-bold');
                            indicator.innerHTML = '<span class="badge bg-success rounded-pill"><i class="fa-solid fa-check"></i></span>';
                        } else if (val === 'deny') {
                            selectElement.classList.add('text-danger', 'fw-bold');
                            indicator.innerHTML = '<span class="badge bg-danger rounded-pill"><i class="fa-solid fa-xmark"></i></span>';
                        } else {
                            selectElement.classList.add('text-muted');
                            indicator.innerHTML = '<span class="text-muted"><i class="fa-solid fa-minus"></i></span>';
                        }
                    }
                </script>

                <script src="<?= AssetHelper::js('modules/empresa-usuarios/permisos-tree.js') ?>" defer></script>
