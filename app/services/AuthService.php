<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database\DatabaseManager;
use PDO;
use RuntimeException;
use Throwable;

final class AuthService
{
    private PDO $connection;
    private MenuService $menuService;

    public function __construct(?PDO $connection = null, ?MenuService $menuService = null)
    {
        $this->connection = $connection ?? DatabaseManager::connection('mrp_auth');
        $this->menuService = $menuService ?? new MenuService($this->connection);
    }

    /**
     * @return array{user: array, companies: array}
     */
    public function validateUser(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));

        if ($email === '' || $password === '') {
            throw new RuntimeException('Credenciales incompletas.');
        }

        $user = $this->findUserByEmail($email);
        if ($user === null || password_verify($password, $user['password']) === false) {
            throw new RuntimeException('Credenciales inválidas.');
        }

        $companies = $this->companiesForUser((int) $user['id']);
        if ($companies === []) {
            throw new RuntimeException('No tienes empresas asociadas.');
        }

        return [
            'user' => $user,
            'companies' => $companies
        ];
    }

    /**
     * @return array{user: array<string, mixed>, tenant: array<string, mixed>, permissions: array<int, string>}
     */
    public function attempt(string $tenantAlias, string $email, string $password): array
    {
        // ... (existing logic wrapper if needed, or we deprecate this in favor of split steps)
        // For backward compatibility or direct calls:
        $result = $this->validateUser($email, $password);
        $user = $result['user'];
        $companies = $result['companies'];

        $tenantAlias = trim($tenantAlias);
        $tenant = $this->matchTenant($companies, $tenantAlias);

        if ($tenant === null) {
            throw new RuntimeException('No se encontró el tenant solicitado o no tienes acceso.');
        }

        $menuResolution = $this->resolveSidebarPayload((int) $tenant['id'], (int) $user['id']);
        $permissions = $menuResolution['permissions'];

        return [
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
            ],
            'tenant' => $tenant,
            'permissions' => $permissions,
            'sidebar_tree' => $menuResolution['sidebar_tree'],
            'sidebar_version' => 1,
        ];
    }

    /**
     * Finalize login with a selected tenant for a pre-validated user
     */
    public function loginWithTenant(array $user, string $tenantSlug): array
    {
        $companies = $this->companiesForUser((int) $user['id']);
        $tenant = $this->matchTenant($companies, $tenantSlug);

        if ($tenant === null) {
            throw new RuntimeException('No se encontró el tenant solicitado o no tienes acceso.');
        }

        $menuResolution = $this->resolveSidebarPayload((int) $tenant['id'], (int) $user['id']);
        $permissions = $menuResolution['permissions'];

        return [
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
            ],
            'tenant' => $tenant,
            'permissions' => $permissions,
            'sidebar_tree' => $menuResolution['sidebar_tree'],
            'sidebar_version' => 1,
        ];
    }

    private function findUserByEmail(string $email): ?array
    {
        $stmt = $this->connection->prepare('SELECT id, name, email, password FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user === false ? null : $user;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function companiesForUser(int $userId): array
    {
        $sql = 'SELECT c.id, c.name, c.slug, c.status, cd.database_name, cd.host, cd.port, cd.username, cd.password_encrypted, uc.role_id, r.name AS role_name
            FROM user_company uc
            INNER JOIN companies c ON c.id = uc.company_id
            LEFT JOIN roles r ON r.id = uc.role_id
            LEFT JOIN company_databases cd ON cd.company_id = c.id
            WHERE uc.user_id = :user_id';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function matchTenant(array $companies, string $alias): ?array
    {
        $normalized = mb_strtolower(trim($alias));
        $withoutPrefix = str_starts_with($normalized, 'mrp_') ? substr($normalized, 4) : $normalized;
        $withPrefix = $withoutPrefix === '' ? '' : 'mrp_' . $withoutPrefix;

        $candidates = array_values(array_unique(array_filter([
            $normalized !== '' ? $normalized : null,
            $withoutPrefix !== '' ? $withoutPrefix : null,
            $withPrefix !== '' ? $withPrefix : null,
        ])));

        foreach ($companies as $company) {
            $slug = mb_strtolower((string) $company['slug']);
            $dbName = mb_strtolower((string) ($company['database_name'] ?? ''));

            if (in_array($slug, $candidates, true) || ($dbName !== '' && in_array($dbName, $candidates, true))) {
                if (($company['status'] ?? 'active') !== 'active') {
                    throw new RuntimeException('La empresa seleccionada está suspendida.');
                }

                $encrypted = $company['password_encrypted'] ?? null;
                $password = $encrypted === 'ENC(local-dev-only)'
                    ? 'a77MUbg_7QxdvdP7C9MrR'
                    : $encrypted;

                return [
                    'id' => (int) $company['id'],
                    'name' => $company['name'],
                    'slug' => $company['slug'],
                    'role_id' => isset($company['role_id']) ? (int) $company['role_id'] : null,
                    'role_name' => $company['role_name'] ?? null,
                    'database' => [
                        'name' => $company['database_name'] ?? null,
                        'host' => $company['host'] ?? null,
                        'port' => $company['port'] ?? null,
                        'username' => $company['username'] ?? null,
                        'password_encrypted' => $encrypted,
                        'password' => $password,
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function permissionsForUser(int $userId): array
    {
        $sql = 'SELECT DISTINCT p.name
            FROM permissions p
            INNER JOIN role_has_permissions rp ON rp.permission_id = p.id
            INNER JOIN user_has_roles ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = :user_id';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return array_values(array_map(static fn($row) => $row['name'], $stmt->fetchAll(PDO::FETCH_ASSOC)));
    }

    /**
     * @return array{sidebar_tree: array<int, array<string, mixed>>, permissions: array<string, string>}
     */
    private function resolveSidebarPayload(int $companyId, int $userId): array
    {
        try {
            return $this->menuService->resolveForUser($companyId, $userId);
        } catch (Throwable $exception) {
            // Keep login available while mrp_auth menu tables are being rolled out.
            return [
                'sidebar_tree' => [
                    [
                        'section_key' => 'panel',
                        'section_label' => 'Panel',
                        'items' => [
                            [
                                'id' => 0,
                                'code' => 'panel.inicio',
                                'label' => 'Panel inicial',
                                'route' => '/dashboard',
                                'icon' => 'fa-solid fa-gauge',
                                'children' => [],
                                'permission_level' => 'write',
                            ],
                        ],
                    ],
                ],
                'permissions' => ['panel.inicio' => 'write'],
            ];
        }
    }
}
