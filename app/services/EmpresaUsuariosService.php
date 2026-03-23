<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth\AuthManager;
use App\Core\Database\DatabaseManager;
use PDO;
use RuntimeException;

final class EmpresaUsuariosService
{
    private const FIXED_ROLE_NAMES = ['admin_empresa', 'administrator', 'super administrador', 'administrador', 'usuario', 'user', 'super_admin'];
    private const ADMIN_ROLE_NAMES = ['admin_empresa', 'administrator', 'super_admin', 'super administrador', 'administrador'];
    private const COMPANY_ROLE_GUARD_PREFIX = 'company:';

    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? DatabaseManager::connection('mrp_auth');
    }

    public function currentCompanyId(): int
    {
        $tenant = AuthManager::tenant();
        $companyId = (int) ($tenant['id'] ?? 0);
        if ($companyId <= 0) {
            throw new RuntimeException('No hay empresa activa en sesion.');
        }

        return $companyId;
    }

    public function currentUserId(): int
    {
        $user = AuthManager::user();
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            throw new RuntimeException('No hay usuario autenticado en sesion.');
        }

        return $userId;
    }

    public function isCurrentUserCompanyAdmin(): bool
    {
        $tenant = AuthManager::tenant();
        $roleName = mb_strtolower((string) ($tenant['role_name'] ?? ''));
        if ($roleName !== '' && in_array($roleName, self::ADMIN_ROLE_NAMES, true)) {
            return true;
        }

        $companyId = $this->currentCompanyId();
        $userId = $this->currentUserId();
        $currentRoleName = $this->roleNameByUserCompany($companyId, $userId);

        return $currentRoleName !== null && $this->isAdminRoleName($currentRoleName);
    }

    public function isCurrentUserSuperAdmin(): bool
    {
        $tenant = AuthManager::tenant();
        $roleName = mb_strtolower((string) ($tenant['role_name'] ?? ''));
        return in_array($roleName, ['super_admin', 'super administrador'], true);
    }

    public function listCompanies(): array
    {
        $isSuperAdmin = $this->isCurrentUserSuperAdmin();

        $sql = 'SELECT id, name AS nombre, slug, tax_id AS cuit, contact_email AS email,
                       CASE WHEN status = :active THEN 1 ELSE 0 END AS activo
                FROM companies';

        if (!$isSuperAdmin) {
            $sql .= ' WHERE id = :id LIMIT 1';
        } else {
            $sql .= ' ORDER BY name ASC';
        }

        $stmt = $this->connection->prepare($sql);

        $params = ['active' => 'active'];
        if (!$isSuperAdmin) {
            $params['id'] = $this->currentCompanyId();
        }

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findCompany(int $id): ?array
    {
        $currentCompanyId = $this->currentCompanyId();
        if ($id !== $currentCompanyId && !$this->isCurrentUserSuperAdmin()) {
            return null;
        }

        $stmt = $this->connection->prepare(
            'SELECT id, name AS nombre, slug, tax_id AS cuit, contact_email AS email,
                    CASE WHEN status = :active THEN 1 ELSE 0 END AS activo
             FROM companies
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $id,
            'active' => 'active',
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function updateCompany(int $id, array $data): void
    {
        $currentCompanyId = $this->currentCompanyId();
        $isSuperAdmin = $this->isCurrentUserSuperAdmin();

        if ($id !== $currentCompanyId && !$isSuperAdmin) {
            throw new RuntimeException('Solo puedes editar la empresa activa en sesion.');
        }

        if ($id === $currentCompanyId && ((int) ($data['activo'] ?? 1)) === 0) {
            throw new RuntimeException('No puedes desactivar tu propia empresa.');
        }

        $stmt = $this->connection->prepare(
            'UPDATE companies
             SET name = :name,
                 slug = :slug,
                 tax_id = :tax_id,
                 contact_email = :contact_email,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
            'name' => $data['nombre'],
            'slug' => $data['slug'],
            'tax_id' => $data['cuit'] !== '' ? $data['cuit'] : null,
            'contact_email' => $data['email'] !== '' ? $data['email'] : null,
            'status' => ((int) $data['activo'] === 1) ? 'active' : 'suspended',
        ]);
    }

    public function listRoles(int $companyId): array
    {
        $superAdminFilter = $this->isCurrentUserSuperAdmin() ? '' : "AND lower(r.name) <> 'super_admin'";

        $stmt = $this->connection->prepare(
            "SELECT DISTINCT r.id, r.name AS nombre, r.guard_name AS codigo,
                    CAST(NULL AS VARCHAR) AS descripcion,
                    1 AS activo
             FROM roles r
             WHERE (r.guard_name LIKE :company_guard
                OR EXISTS (
                    SELECT 1
                    FROM user_company uc
                    WHERE uc.role_id = r.id
                      AND uc.company_id = :company_id
                ))
             {$superAdminFilter}
             ORDER BY r.name ASC"
        );
        $stmt->execute([
            'company_id' => $companyId,
            'company_guard' => $this->companyRoleGuardLike($companyId),
        ]);

        return array_map(fn(array $row): array => [
            ...$row,
            'codigo' => $this->extractRoleCode((string) ($row['codigo'] ?? 'web')),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findRole(int $companyId, int $id): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT id, name AS nombre, guard_name AS codigo,
                    CAST(NULL AS VARCHAR) AS descripcion,
                    1 AS activo
             FROM roles
             WHERE id = :id
               AND (
                    guard_name LIKE :company_guard
                    OR EXISTS (
                        SELECT 1
                        FROM user_company uc
                        WHERE uc.role_id = roles.id
                          AND uc.company_id = :company_id
                    )
               )
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'company_guard' => $this->companyRoleGuardLike($companyId),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $row['codigo'] = $this->extractRoleCode((string) ($row['codigo'] ?? 'web'));
        }
        return $row === false ? null : $row;
    }

    public function createRole(int $companyId, array $data): int
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO roles (name, guard_name, created_at, updated_at)
             VALUES (:name, :guard_name, NOW(), NOW())
             RETURNING id'
        );
        $stmt->execute([
            'name' => $data['nombre'],
            'guard_name' => $this->composeCompanyRoleGuard($companyId, (string) ($data['codigo'] ?? '')),
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function updateRole(int $companyId, int $id, array $data): void
    {
        if ($this->findRole($companyId, $id) === null) {
            throw new RuntimeException('El rol no pertenece a tu empresa.');
        }

        $stmt = $this->connection->prepare(
            'UPDATE roles
             SET name = :name,
                 guard_name = :guard_name,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['nombre'],
            'guard_name' => $this->composeCompanyRoleGuard($companyId, (string) ($data['codigo'] ?? '')),
        ]);
    }

    public function deleteRole(int $companyId, int $id): void
    {
        if ($this->findRole($companyId, $id) === null) {
            throw new RuntimeException('El rol no pertenece a tu empresa.');
        }

        $name = $this->roleNameById($id);
        if ($name !== null && in_array(mb_strtolower($name), self::FIXED_ROLE_NAMES, true)) {
            throw new RuntimeException('No se puede eliminar un rol del sistema base (Administrador, Usuario o Super Administrador).');
        }

        $userCompany = $this->connection->prepare('SELECT 1 FROM user_company WHERE role_id = :id LIMIT 1');
        $userCompany->execute(['id' => $id]);
        if ($userCompany->fetchColumn() !== false) {
            throw new RuntimeException('El rol esta asignado a usuarios de empresa.');
        }

        $userHasRoles = $this->connection->prepare('SELECT 1 FROM user_has_roles WHERE role_id = :id LIMIT 1');
        $userHasRoles->execute(['id' => $id]);
        if ($userHasRoles->fetchColumn() !== false) {
            throw new RuntimeException('El rol esta asignado en user_has_roles.');
        }

        $stmt = $this->connection->prepare('DELETE FROM roles WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function listUsersByCompany(int $companyId): array
    {
        $superAdminFilter = $this->isCurrentUserSuperAdmin()
            ? ''
            : "AND (r.name IS NULL OR lower(r.name) <> 'super_admin')";

        $sql = "SELECT u.id,
                       u.name AS nombre,
                       u.email,
                       COALESCE(r.name, :no_role) AS rol_nombre,
                       COALESCE(TO_CHAR(u.last_login_at, :fmt), :dash) AS ultimo_acceso,
                       CASE WHEN uc.role_id IS NULL THEN 0 ELSE 1 END AS activo,
                       uc.role_id
                FROM user_company uc
                INNER JOIN users u ON u.id = uc.user_id
                LEFT JOIN roles r ON r.id = uc.role_id
                WHERE uc.company_id = :company_id
                {$superAdminFilter}
                ORDER BY u.name ASC";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'company_id' => $companyId,
            'no_role' => 'Sin rol',
            'fmt' => 'YYYY-MM-DD HH24:MI',
            'dash' => '-',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findUserForCompany(int $companyId, int $userId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT u.id,
                    u.name AS nombre,
                    u.email,
                    COALESCE(uc.role_id, 0) AS role_id,
                    CASE WHEN uc.role_id IS NULL THEN 0 ELSE 1 END AS activo
             FROM user_company uc
             INNER JOIN users u ON u.id = uc.user_id
             WHERE uc.company_id = :company_id
               AND uc.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([
            'company_id' => $companyId,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function createUserForCompany(int $companyId, array $data): int
    {
        $this->connection->beginTransaction();

        try {
            $insertUser = $this->connection->prepare(
                'INSERT INTO users (name, email, password, two_factor_enabled, created_at, updated_at)
                 VALUES (:name, :email, :password, FALSE, NOW(), NOW())
                 RETURNING id'
            );
            $insertUser->execute([
                'name' => $data['nombre'],
                'email' => mb_strtolower($data['email']),
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ]);
            $userId = (int) $insertUser->fetchColumn();

            $bindCompany = $this->connection->prepare(
                'INSERT INTO user_company (user_id, company_id, role_id)
                 VALUES (:user_id, :company_id, :role_id)'
            );
            $bindCompany->execute([
                'user_id' => $userId,
                'company_id' => $companyId,
                'role_id' => (int) $data['role_id'],
            ]);

            $this->syncUserRoleBridge($userId, (int) $data['role_id']);

            $this->connection->commit();
            return $userId;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function updateUserForCompany(int $companyId, int $userId, array $data): void
    {
        if (!$this->roleBelongsToCompany($companyId, (int) $data['role_id'])) {
            throw new RuntimeException('El rol seleccionado no pertenece a tu empresa.');
        }

        $this->connection->beginTransaction();

        try {
            $existingRoleName = $this->roleNameByUserCompany($companyId, $userId);
            $newRoleName = $this->roleNameById((int) $data['role_id']);

            if ($existingRoleName === 'super_admin' && $newRoleName !== 'super_admin') {
                throw new RuntimeException('Ningún Super Administrador puede ser modificado a otro rol.');
            }

            if ($existingRoleName !== null && $this->isAdminRoleName($existingRoleName) && !$this->isAdminRoleName((string) $newRoleName)) {
                if ($this->adminCountForCompany($companyId) <= 1) {
                    throw new RuntimeException('Debe existir al menos un admin activo por empresa.');
                }

                if ($userId === $this->currentUserId()) {
                    throw new RuntimeException('No puedes quitarte a ti mismo el rol administrador.');
                }
            }

            $updateUser = $this->connection->prepare(
                'UPDATE users
                 SET name = :name,
                     email = :email,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $updateUser->execute([
                'id' => $userId,
                'name' => $data['nombre'],
                'email' => mb_strtolower($data['email']),
            ]);

            if (($data['password'] ?? '') !== '') {
                $updatePassword = $this->connection->prepare(
                    'UPDATE users
                     SET password = :password,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $updatePassword->execute([
                    'id' => $userId,
                    'password' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
                ]);
            }

            $updateCompanyLink = $this->connection->prepare(
                'UPDATE user_company
                 SET role_id = :role_id
                 WHERE user_id = :user_id
                   AND company_id = :company_id'
            );
            $updateCompanyLink->execute([
                'role_id' => (int) $data['role_id'],
                'user_id' => $userId,
                'company_id' => $companyId,
            ]);

            $this->syncUserRoleBridge($userId, (int) $data['role_id']);

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function deleteUserForCompany(int $companyId, int $userId): void
    {
        $roleName = $this->roleNameByUserCompany($companyId, $userId);
        if ($roleName === 'super_admin') {
            throw new RuntimeException('Ningún Super Administrador puede ser eliminado.');
        }
        if ($roleName !== null && $this->isAdminRoleName($roleName)) {
            if ($this->adminCountForCompany($companyId) <= 1) {
                throw new RuntimeException('Debe existir al menos un admin activo por empresa.');
            }

            if ($userId === $this->currentUserId()) {
                throw new RuntimeException('No puedes eliminarte a ti mismo como admin.');
            }
        }

        $stmt = $this->connection->prepare(
            'DELETE FROM user_company
             WHERE user_id = :user_id
               AND company_id = :company_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);
    }

    public function updateOwnPasswordForCompany(int $companyId, int $userId, string $password): void
    {
        if ($password === '') {
            throw new RuntimeException('La password es obligatoria.');
        }

        $currentUserId = $this->currentUserId();
        if ($userId !== $currentUserId) {
            throw new RuntimeException('Solo puedes cambiar tu propia password.');
        }

        if ($this->findUserForCompany($companyId, $userId) === null) {
            throw new RuntimeException('No estas asociado a la empresa activa.');
        }

        $stmt = $this->connection->prepare(
            'UPDATE users
             SET password = :password,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public function roleBelongsToCompany(int $companyId, int $roleId): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT 1
             FROM roles r
             WHERE r.id = :role_id
               AND (
                    r.guard_name LIKE :company_guard
                    OR EXISTS (
                        SELECT 1
                        FROM user_company uc
                        WHERE uc.role_id = r.id
                          AND uc.company_id = :company_id
                    )
               )
             LIMIT 1'
        );
        $stmt->execute(['role_id' => $roleId, 'company_id' => $companyId, 'company_guard' => $this->companyRoleGuardLike($companyId)]);
        return $stmt->fetchColumn() !== false;
    }

    private function syncUserRoleBridge(int $userId, int $roleId): void
    {
        $delete = $this->connection->prepare(
            'DELETE FROM user_has_roles
             WHERE user_id = :user_id
               AND role_id = :role_id'
        );
        $delete->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);

        $insert = $this->connection->prepare(
            'INSERT INTO user_has_roles (user_id, role_id)
             VALUES (:user_id, :role_id)'
        );
        $insert->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    private function roleNameByUserCompany(int $companyId, int $userId): ?string
    {
        $stmt = $this->connection->prepare(
            'SELECT r.name
             FROM user_company uc
             INNER JOIN roles r ON r.id = uc.role_id
             WHERE uc.company_id = :company_id
               AND uc.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([
            'company_id' => $companyId,
            'user_id' => $userId,
        ]);

        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    private function roleNameById(int $roleId): ?string
    {
        $stmt = $this->connection->prepare('SELECT name FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $roleId]);

        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    private function adminCountForCompany(int $companyId): int
    {
        $placeholders = implode(', ', array_fill(0, count(self::ADMIN_ROLE_NAMES), '?'));

        $stmt = $this->connection->prepare(
            "SELECT COUNT(*)
             FROM user_company uc
             INNER JOIN roles r ON r.id = uc.role_id
             WHERE uc.company_id = ?
               AND lower(r.name) IN ($placeholders)"
        );

        $params = array_merge([$companyId], self::ADMIN_ROLE_NAMES);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function isAdminRoleName(string $name): bool
    {
        return in_array(mb_strtolower($name), self::ADMIN_ROLE_NAMES, true);
    }

    private function composeCompanyRoleGuard(int $companyId, string $codigo): string
    {
        $cleanCode = trim($codigo) !== '' ? trim($codigo) : 'web';
        return self::COMPANY_ROLE_GUARD_PREFIX . $companyId . ':' . $cleanCode;
    }

    private function companyRoleGuardLike(int $companyId): string
    {
        return self::COMPANY_ROLE_GUARD_PREFIX . $companyId . ':%';
    }

    private function extractRoleCode(string $guardName): string
    {
        if (!str_starts_with($guardName, self::COMPANY_ROLE_GUARD_PREFIX)) {
            return $guardName;
        }

        $parts = explode(':', $guardName, 3);
        if (count($parts) !== 3 || trim($parts[2]) === '') {
            return 'web';
        }

        return $parts[2];
    }

    /**
     * Elimina completamente una empresa: registros en mrp_auth y su base de datos.
     * Solo SuperAdmin puede ejecutar este método y no puede borrar su propia empresa activa.
     *
     * @throws \RuntimeException Si no tiene permisos o intenta borrar la empresa activa.
     * @throws \Throwable        Cualquier error de BD relanza tras rollback.
     */
    public function deleteCompany(int $id): void
    {
        if (!$this->isCurrentUserSuperAdmin()) {
            throw new \RuntimeException('Solo Super Administradores pueden eliminar empresas.');
        }
        if ($id === $this->currentCompanyId()) {
            throw new \RuntimeException('No puedes eliminar la empresa en la que estás logueado.');
        }

        $stmt = $this->connection->prepare(
            'SELECT cd.database_name
               FROM company_databases cd
              WHERE cd.company_id = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row    = $stmt->fetch(\PDO::FETCH_ASSOC);
        $dbName = ($row !== false && isset($row['database_name']))
            ? (string) $row['database_name']
            : '';

        // Usuarios que pertenecen EXCLUSIVAMENTE a esta empresa (no tienen otras).
        // Solo esos se borrarán de la tabla `users`; los que comparten empresa quedan intactos.
        $stmtExclusive = $this->connection->prepare(
            'SELECT uc.user_id
               FROM user_company uc
              WHERE uc.company_id = :id
                AND NOT EXISTS (
                    SELECT 1 FROM user_company uc2
                     WHERE uc2.user_id    = uc.user_id
                       AND uc2.company_id <> :id
                )'
        );
        $stmtExclusive->execute(['id' => $id]);
        $exclusiveUserIds = $stmtExclusive->fetchAll(\PDO::FETCH_COLUMN);

        $this->connection->beginTransaction();
        try {
            // 1. Roles de los usuarios de esta empresa
            $this->connection->prepare(
                'DELETE FROM user_has_roles
                  WHERE user_id IN (
                      SELECT user_id FROM user_company WHERE company_id = :id
                  )'
            )->execute(['id' => $id]);

            // 2. Relación usuario ↔ empresa
            $this->connection->prepare(
                'DELETE FROM user_company WHERE company_id = :id'
            )->execute(['id' => $id]);

            // 3. Usuarios que no pertenecen a ninguna otra empresa
            if ($exclusiveUserIds !== []) {
                $placeholders = implode(',', array_fill(0, count($exclusiveUserIds), '?'));
                $this->connection->prepare(
                    'DELETE FROM users WHERE id IN (' . $placeholders . ')'
                )->execute($exclusiveUserIds);
            }

            // 4. ACL de menú de la empresa (FK sin CASCADE)
            $this->connection->prepare(
                'DELETE FROM menu_acl WHERE company_id = :id'
            )->execute(['id' => $id]);

            // 5. Registro de la base de datos de la empresa
            $this->connection->prepare(
                'DELETE FROM company_databases WHERE company_id = :id'
            )->execute(['id' => $id]);

            // 6. Empresa
            $this->connection->prepare(
                'DELETE FROM companies WHERE id = :id'
            )->execute(['id' => $id]);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        if ($dbName !== '' && preg_match('/^[a-z][a-z0-9_]*$/', $dbName) === 1) {
            $this->connection->prepare(
                'SELECT pg_terminate_backend(pid)
                   FROM pg_stat_activity
                  WHERE datname = :db
                    AND pid <> pg_backend_pid()'
            )->execute(['db' => $dbName]);

            $quoted = '"' . $dbName . '"';
            $this->connection->exec('DROP DATABASE IF EXISTS ' . $quoted);
        }
    }
}
