<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth\AuthManager;
use App\Core\Database\DatabaseManager;
use PDO;
use RuntimeException;

final class EmpresaUsuariosService
{
    private const ADMIN_ROLE_NAMES = ['admin_empresa', 'administrator', 'super_admin'];
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

    public function listCompanies(): array
    {
        $sql = 'SELECT id, name AS nombre, slug, tax_id AS cuit, contact_email AS email,
                CASE WHEN status = :active THEN 1 ELSE 0 END AS activo
                FROM companies
                WHERE id = :id
                LIMIT 1';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'active' => 'active',
            'id' => $this->currentCompanyId(),
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findCompany(int $id): ?array
    {
        $currentCompanyId = $this->currentCompanyId();
        if ($id !== $currentCompanyId) {
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
        if ($id !== $currentCompanyId) {
            throw new RuntimeException('Solo puedes editar la empresa activa en sesion.');
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
        $stmt = $this->connection->prepare(
            'SELECT DISTINCT r.id, r.name AS nombre, r.guard_name AS codigo,
                    CAST(NULL AS VARCHAR) AS descripcion,
                    1 AS activo
             FROM roles r
             WHERE r.guard_name LIKE :company_guard
                OR EXISTS (
                    SELECT 1
                    FROM user_company uc
                    WHERE uc.role_id = r.id
                      AND uc.company_id = :company_id
                )
             ORDER BY r.name ASC'
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
        if ($name !== null && $this->isAdminRoleName($name)) {
            throw new RuntimeException('No se puede eliminar un rol administrador base.');
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
        $sql = 'SELECT u.id,
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
                ORDER BY u.name ASC';

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
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*)
             FROM user_company uc
             INNER JOIN roles r ON r.id = uc.role_id
             WHERE uc.company_id = :company_id
               AND lower(r.name) IN (:role_1, :role_2, :role_3)'
        );
        $stmt->execute([
            'company_id' => $companyId,
            'role_1' => self::ADMIN_ROLE_NAMES[0],
            'role_2' => self::ADMIN_ROLE_NAMES[1],
            'role_3' => self::ADMIN_ROLE_NAMES[2],
        ]);
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
}
