<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database\DatabaseManager;
use PDO;

final class MenuService
{
    private const BYPASS_ROLE_NAMES = ['super_admin', 'admin_empresa', 'administrator'];
    private const SECTION_VISUAL_ORDER = [
        'panel',
        'productos_bom',
        'planeamiento_mrp',
        'produccion',
        'transacciones',
        'inventario_stock',
        'reportes',
        'parametros_catalogos',
        'empresa_usuarios',
    ];

    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? DatabaseManager::connection('mrp_auth');
    }

    /**
     * @return array{sidebar_tree: array<int, array<string, mixed>>, permissions: array<string, string>}
     */
    public function resolveForUser(int $companyId, int $userId): array
    {
        $menuItems = $this->fetchActiveMenuItems();
        if ($menuItems === []) {
            return ['sidebar_tree' => [], 'permissions' => []];
        }

        $roleContext = $this->fetchRoleContext($companyId, $userId);
        if ($this->hasBypassRole($roleContext['role_names'])) {
            $fullMap = [];
            foreach ($menuItems as $item) {
                $fullMap[(int) $item['id']] = 'write';
            }

            return [
                'sidebar_tree' => $this->buildSidebarTree($menuItems, $fullMap),
                'permissions' => $this->codePermissions($menuItems, $fullMap),
            ];
        }

        $aclRows = $this->fetchAclRows($companyId, $userId, $roleContext['role_ids']);
        $permissionMap = $this->resolvePermissionMap($menuItems, $aclRows);

        return [
            'sidebar_tree' => $this->buildSidebarTree($menuItems, $permissionMap),
            'permissions' => $this->codePermissions($menuItems, $permissionMap),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchActiveMenuItems(): array
    {
        $stmt = $this->connection->query(
            "SELECT id, code, label, route, icon, section_key, section_label, parent_id, sort_order
             FROM menu_items
             WHERE is_active = TRUE
             ORDER BY
                CASE section_key
                    WHEN 'panel' THEN 10
                    WHEN 'productos_bom' THEN 20
                    WHEN 'planeamiento_mrp' THEN 30
                    WHEN 'produccion' THEN 40
                    WHEN 'transacciones' THEN 50
                    WHEN 'inventario_stock' THEN 60
                    WHEN 'reportes' THEN 70
                    WHEN 'parametros_catalogos' THEN 80
                    WHEN 'empresa_usuarios' THEN 90
                    ELSE 999
                END ASC,
                sort_order ASC,
                id ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{role_ids: array<int, int>, role_names: array<int, string>}
     */
    private function fetchRoleContext(int $companyId, int $userId): array
    {
        $sql = 'SELECT r.id, r.name
            FROM user_company uc
            INNER JOIN roles r ON r.id = uc.role_id
            WHERE uc.company_id = :company_id
              AND uc.user_id = :user_id';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'company_id' => $companyId,
            'user_id' => $userId,
        ]);

        $roleIds = [];
        $roleNames = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $roleIds[] = (int) ($row['id'] ?? 0);
            $roleNames[] = mb_strtolower((string) ($row['name'] ?? ''));
        }

        return [
            'role_ids' => array_values(array_unique(array_filter($roleIds))),
            'role_names' => array_values(array_unique(array_filter($roleNames))),
        ];
    }

    private function hasBypassRole(array $roleNames): bool
    {
        foreach ($roleNames as $name) {
            if (in_array($name, self::BYPASS_ROLE_NAMES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, int> $roleIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchAclRows(int $companyId, int $userId, array $roleIds): array
    {
        $params = [
            'company_id' => $companyId,
            'user_id' => $userId,
        ];

        $rolePlaceholders = [];
        foreach ($roleIds as $index => $roleId) {
            $key = 'role_' . $index;
            $params[$key] = $roleId;
            $rolePlaceholders[] = ':' . $key;
        }

        $roleFilter = $rolePlaceholders === []
            ? '1 = 0'
            : 'subject_type = \'role\' AND subject_id IN (' . implode(',', $rolePlaceholders) . ')';

        $sql = 'SELECT menu_item_id, subject_type, scope, permission_level, effect
            FROM menu_acl
            WHERE company_id = :company_id
              AND ((subject_type = \'user\' AND subject_id = :user_id) OR (' . $roleFilter . '))';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int, array<string, mixed>> $menuItems
     * @param array<int, array<string, mixed>> $aclRows
     * @return array<int, string>
     */
    private function resolvePermissionMap(array $menuItems, array $aclRows): array
    {
        $childrenByParent = [];
        foreach ($menuItems as $item) {
            $parentId = isset($item['parent_id']) ? (int) $item['parent_id'] : 0;
            $childrenByParent[$parentId][] = (int) $item['id'];
        }

        $roleAllows = [];
        $roleDenies = [];
        $userAllows = [];
        $userDenies = [];

        foreach ($aclRows as $row) {
            $targetIds = $this->expandScopeIds(
                (int) ($row['menu_item_id'] ?? 0),
                (string) ($row['scope'] ?? 'item'),
                $childrenByParent
            );

            $subject = (string) ($row['subject_type'] ?? 'role');
            $effect = (string) ($row['effect'] ?? 'allow');
            $level = (string) ($row['permission_level'] ?? 'read');

            if ($effect === 'deny') {
                if ($subject === 'user') {
                    $userDenies = array_merge($userDenies, $targetIds);
                } else {
                    $roleDenies = array_merge($roleDenies, $targetIds);
                }
                continue;
            }

            if ($subject === 'user') {
                foreach ($targetIds as $id) {
                    $userAllows[$id] = $this->maxLevel($userAllows[$id] ?? null, $level);
                }
                continue;
            }

            foreach ($targetIds as $id) {
                $roleAllows[$id] = $this->maxLevel($roleAllows[$id] ?? null, $level);
            }
        }

        $resolved = $roleAllows;
        foreach (array_unique($roleDenies) as $id) {
            unset($resolved[$id]);
        }

        foreach ($userAllows as $id => $level) {
            $resolved[$id] = $this->maxLevel($resolved[$id] ?? null, $level);
        }

        foreach (array_unique($userDenies) as $id) {
            unset($resolved[$id]);
        }

        return $resolved;
    }

    /**
     * @param array<int, array<int, int>> $childrenByParent
     * @return array<int, int>
     */
    private function expandScopeIds(int $menuItemId, string $scope, array $childrenByParent): array
    {
        if ($menuItemId <= 0) {
            return [];
        }

        if ($scope !== 'branch') {
            return [$menuItemId];
        }

        $expanded = [];
        $stack = [$menuItemId];

        while ($stack !== []) {
            $current = array_pop($stack);
            if (isset($expanded[$current])) {
                continue;
            }

            $expanded[$current] = $current;
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $stack[] = (int) $childId;
            }
        }

        return array_values($expanded);
    }

    private function maxLevel(?string $existing, string $incoming): string
    {
        if ($existing === 'write' || $incoming === 'write') {
            return 'write';
        }

        return 'read';
    }

    /**
     * @param array<int, array<string, mixed>> $menuItems
     * @param array<int, string> $permissionMap
     * @return array<int, array<string, mixed>>
     */
    private function buildSidebarTree(array $menuItems, array $permissionMap): array
    {
        $sections = [];

        foreach ($menuItems as $item) {
            $itemId = (int) $item['id'];
            if (!isset($permissionMap[$itemId])) {
                continue;
            }

            $sectionKey = (string) $item['section_key'];
            if (!isset($sections[$sectionKey])) {
                $sections[$sectionKey] = [
                    'section_key' => $sectionKey,
                    'section_label' => (string) $item['section_label'],
                    'items' => [],
                ];
            }

            $sections[$sectionKey]['items'][] = [
                'id' => $itemId,
                'code' => (string) $item['code'],
                'label' => (string) $item['label'],
                'route' => $item['route'] !== null ? (string) $item['route'] : null,
                'icon' => $item['icon'] !== null ? (string) $item['icon'] : 'fa-solid fa-circle',
                'children' => [],
                'permission_level' => $permissionMap[$itemId],
                'sort_order' => (int) $item['sort_order'],
            ];
        }

        foreach ($sections as &$section) {
            usort($section['items'], static fn(array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
            foreach ($section['items'] as &$menuItem) {
                unset($menuItem['sort_order']);
            }
            unset($menuItem);
        }
        unset($section);

        $orderMap = array_flip(self::SECTION_VISUAL_ORDER);
        uksort(
            $sections,
            static function (string $left, string $right) use ($orderMap): int {
                $leftOrder = $orderMap[$left] ?? PHP_INT_MAX;
                $rightOrder = $orderMap[$right] ?? PHP_INT_MAX;

                if ($leftOrder === $rightOrder) {
                    return $left <=> $right;
                }

                return $leftOrder <=> $rightOrder;
            }
        );

        return array_values($sections);
    }

    /**
     * @param array<int, array<string, mixed>> $menuItems
     * @param array<int, string> $permissionMap
     * @return array<string, string>
     */
    private function codePermissions(array $menuItems, array $permissionMap): array
    {
        $permissions = [];

        foreach ($menuItems as $item) {
            $id = (int) $item['id'];
            if (!isset($permissionMap[$id])) {
                continue;
            }

            $permissions[(string) $item['code']] = $permissionMap[$id];
        }

        return $permissions;
    }
}
