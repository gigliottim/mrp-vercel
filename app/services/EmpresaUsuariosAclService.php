<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database\DatabaseManager;
use PDO;

final class EmpresaUsuariosAclService
{
    private const COMPANY_ROLE_GUARD_PREFIX = 'company:';

    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? DatabaseManager::connection('mrp_auth');
    }

    public function listMenuTree(): array
    {
        $stmt = $this->connection->query(
            "SELECT id, code, label, route, icon, section_key, section_label, parent_id, sort_order
             FROM menu_items
             WHERE is_active = TRUE
             ORDER BY
                CASE section_key
                    WHEN 'taller' THEN 10
                    WHEN 'catalogo_productos' THEN 20
                    WHEN 'planificacion_compras' THEN 30
                    WHEN 'reportes' THEN 40
                    WHEN 'administracion' THEN 50
                    WHEN 'empresa_usuarios' THEN 60
                    ELSE 999
                END ASC,
                sort_order ASC,
                id ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listAclRowsByCompany(int $companyId): array
    {
        $sql = 'SELECT a.id,
                       a.menu_item_id,
                       mi.label AS menu_label,
                       a.subject_type,
                       a.subject_id,
                       a.scope,
                       CASE WHEN a.effect = :deny THEN :deny ELSE a.permission_level END AS permission_level,
                       a.effect,
                       a.permission_level AS permission_level_raw
                FROM menu_acl a
                INNER JOIN menu_items mi ON mi.id = a.menu_item_id
                WHERE a.company_id = :company_id
                ORDER BY mi.section_key ASC, mi.sort_order ASC, a.subject_type ASC, a.subject_id ASC';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'company_id' => $companyId,
            'deny' => 'deny',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAclByIdForCompany(int $companyId, int $id): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT id, menu_item_id, subject_type, subject_id, scope, permission_level, effect
             FROM menu_acl
             WHERE id = :id
               AND company_id = :company_id
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $row['permission_level'] = ($row['effect'] ?? 'allow') === 'deny'
            ? 'deny'
            : (string) ($row['permission_level'] ?? 'read');

        return $row;
    }

    public function upsertAclForCompany(int $companyId, array $data): void
    {
        [$permissionLevel, $effect] = $this->normalizeAclPermission((string) $data['permission_level']);

        $sql = 'INSERT INTO menu_acl (company_id, menu_item_id, subject_type, subject_id, scope, permission_level, effect, created_at, updated_at)
                VALUES (:company_id, :menu_item_id, :subject_type, :subject_id, :scope, :permission_level, :effect, NOW(), NOW())
                ON CONFLICT (company_id, menu_item_id, subject_type, subject_id, scope)
                DO UPDATE SET
                    permission_level = EXCLUDED.permission_level,
                    effect = EXCLUDED.effect,
                    updated_at = NOW()';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'company_id' => $companyId,
            'menu_item_id' => (int) $data['menu_item_id'],
            'subject_type' => $data['subject_type'],
            'subject_id' => (int) $data['subject_id'],
            'scope' => $data['scope'],
            'permission_level' => $permissionLevel,
            'effect' => $effect,
        ]);
    }

    public function updateAclForCompany(int $companyId, int $id, array $data): void
    {
        [$permissionLevel, $effect] = $this->normalizeAclPermission((string) $data['permission_level']);

        $stmt = $this->connection->prepare(
            'UPDATE menu_acl
             SET menu_item_id = :menu_item_id,
                 subject_type = :subject_type,
                 subject_id = :subject_id,
                 scope = :scope,
                 permission_level = :permission_level,
                 effect = :effect,
                 updated_at = NOW()
             WHERE id = :id
               AND company_id = :company_id'
        );
        $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'menu_item_id' => (int) $data['menu_item_id'],
            'subject_type' => $data['subject_type'],
            'subject_id' => (int) $data['subject_id'],
            'scope' => $data['scope'],
            'permission_level' => $permissionLevel,
            'effect' => $effect,
        ]);
    }

    public function deleteAclForCompany(int $companyId, int $id): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM menu_acl
             WHERE id = :id
               AND company_id = :company_id'
        );
        $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
        ]);
    }

    public function listSubjectsForCompany(int $companyId, bool $hideSuperAdmin = false): array
    {
        $superAdminRoleFilter = $hideSuperAdmin ? "AND lower(r.name) <> 'super_admin'" : '';
        $superAdminUserFilter = $hideSuperAdmin
            ? 'AND NOT EXISTS (
                   SELECT 1
                   FROM user_company uc_sa
                   INNER JOIN roles r_sa ON r_sa.id = uc_sa.role_id
                   WHERE uc_sa.user_id = u.id
                     AND lower(r_sa.name) = \'super_admin\'
               )'
            : '';

        $sql = "SELECT :role_type AS subject_type, r.id, ( :role_prefix || r.name ) AS label
                FROM roles r
                WHERE (r.guard_name LIKE :company_guard
                     OR EXISTS (
                                SELECT 1
                                FROM user_company uc_roles
                                WHERE uc_roles.role_id = r.id
                                    AND uc_roles.company_id = :company_id
                     ))
                {$superAdminRoleFilter}
                UNION ALL
                SELECT :user_type AS subject_type, u.id, ( :user_prefix || u.name ) AS label
                FROM user_company uc
                INNER JOIN users u ON u.id = uc.user_id
                WHERE uc.company_id = :company_id
                {$superAdminUserFilter}
                ORDER BY subject_type, label";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'role_type' => 'role',
            'user_type' => 'user',
            'role_prefix' => 'Rol: ',
            'user_prefix' => 'Usuario: ',
            'company_id' => $companyId,
            'company_guard' => self::COMPANY_ROLE_GUARD_PREFIX . $companyId . ':%',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function normalizeAclPermission(string $input): array
    {
        if ($input === 'deny') {
            return ['read', 'deny'];
        }

        if ($input === 'write') {
            return ['write', 'allow'];
        }

        return ['read', 'allow'];
    }
}
