<?php

declare(strict_types=1);

/**
 * Parcial de datos: prepara las estructuras para el árbol de menú ACL.
 *
 * Variables de entrada esperadas:
 *   $old, $editing, $menuTree, $subjects
 *
 * Variables producidas (disponibles para las vistas parciales):
 *   $oldValue, $subjectTypeOptions, $scopeOptions, $permissionOptions,
 *   $selectedMenuId, $selectedSubjectType, $selectedSubjectId,
 *   $nodesById, $childrenByParent, $sectionOrder, $sectionRootsByKey,
 *   $rolesList, $usersList, $selectedMenuInfo, $renderTreeNode
 */

$old      = $old ?? [];
$editing  = $editing ?? null;
$errors   = $errors ?? [];
$aclRows  = $aclRows ?? [];
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
$scopeOptions       = ['item' => 'Item', 'branch' => 'Branch'];
$permissionOptions  = ['read' => 'Read', 'write' => 'Write', 'deny' => 'Deny'];

$selectedMenuId      = (int) $oldValue('menu_item_id', 0);
$selectedSubjectType = (string) $oldValue('subject_type', 'role');
$selectedSubjectId   = (int) $oldValue('subject_id', 0);

// Indexar nodos y detectar secciones
$nodesById    = [];
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
        'id'            => $id,
        'label'         => (string) ($node['label'] ?? ''),
        'code'          => (string) ($node['code'] ?? ''),
        'icon'          => (string) ($node['icon'] ?? 'fa-solid fa-circle'),
        'parent_id'     => isset($node['parent_id']) && $node['parent_id'] !== null ? (int) $node['parent_id'] : 0,
        'sort_order'    => (int) ($node['sort_order'] ?? 0),
        'section_key'   => $sectionKey,
        'section_label' => (string) ($node['section_label'] ?? 'General'),
    ];
}

// Agrupar hijos por padre
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

// Raíces de sección
$sectionRootsByKey = [];
foreach ($sectionOrder as $sectionKey => $sectionLabel) {
    $sectionRootsByKey[$sectionKey] = array_values(array_filter(
        $childrenByParent[0] ?? [],
        static fn(array $node): bool => (string) ($node['section_key'] ?? '') === $sectionKey
    ));
}

$rolesList         = array_filter($subjects, fn($s) => $s['subject_type'] === 'role');
$usersList         = array_filter($subjects, fn($s) => $s['subject_type'] === 'user');
$selectedMenuInfo  = ($selectedMenuId > 0 && isset($nodesById[$selectedMenuId])) ? $nodesById[$selectedMenuId] : null;

// Árbol recursivo
$renderTreeNode = function (array $treeNode) use (&$renderTreeNode, $childrenByParent, $selectedMenuId): string {
    $nodeId    = (int) ($treeNode['id'] ?? 0);
    $nodeLabel = (string) ($treeNode['label'] ?? '');
    $nodeCode  = (string) ($treeNode['code'] ?? '');
    $isSelected = $nodeId === $selectedMenuId;
    $children  = $childrenByParent[$nodeId] ?? [];
    $isItem    = ($children !== []) ? 'false' : 'true';
    $iconClass = ($children !== []) ? 'fa-solid fa-folder text-warning' : 'fa-solid fa-cube text-info';

    $html  = '<li class="mb-1 acl-tree-li" data-tree-li="1">';
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

// Fallback visual si no hay árbol (no afecta el rendering del árbol ya procesado)
if ($menuTree === []) {
    $menuTree = [
        ['id' => 0, 'label' => 'Empresa', 'code' => 'empresa'],
        ['id' => 0, 'label' => 'Usuarios', 'code' => 'usuarios'],
        ['id' => 0, 'label' => 'Roles', 'code' => 'roles'],
        ['id' => 0, 'label' => 'Permisos', 'code' => 'permisos'],
    ];
}
