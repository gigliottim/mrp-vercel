<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Database\DatabaseManager;

function out(string $text): void
{
    echo $text . PHP_EOL;
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = DatabaseManager::connection('mrp_auth');
    $pdo->beginTransaction();

    out('[SMOKE] Conexion a mrp_auth: OK');

    $companyId = (int) $pdo->query('SELECT id FROM companies ORDER BY id ASC LIMIT 1')->fetchColumn();
    $menuItemId = (int) $pdo->query('SELECT id FROM menu_items WHERE is_active = TRUE ORDER BY id ASC LIMIT 1')->fetchColumn();
    $baseRoleId = (int) $pdo->query('SELECT id FROM roles ORDER BY id ASC LIMIT 1')->fetchColumn();

    assertTrue($companyId > 0, 'No se encontro company base para test.');
    assertTrue($menuItemId > 0, 'No se encontro menu_item base para test.');
    assertTrue($baseRoleId > 0, 'No se encontro role base para test.');

    $suffix = (string) time();

    // 1) Roles CRUD
    $roleName = 'smoke_role_' . $suffix;
    $insertRole = $pdo->prepare('INSERT INTO roles (name, guard_name, created_at, updated_at) VALUES (:name, :guard, NOW(), NOW()) RETURNING id');
    $insertRole->execute(['name' => $roleName, 'guard' => 'web']);
    $roleId = (int) $insertRole->fetchColumn();
    assertTrue($roleId > 0, 'No se pudo crear role en smoke test.');

    $updateRole = $pdo->prepare('UPDATE roles SET name = :name, updated_at = NOW() WHERE id = :id');
    $updateRole->execute(['name' => $roleName . '_upd', 'id' => $roleId]);
    assertTrue($updateRole->rowCount() === 1, 'No se pudo actualizar role en smoke test.');
    out('[SMOKE] Roles CRUD: OK');

    // 2) Companies CRUD
    $companySlug = 'smoke-company-' . $suffix;
    $insertCompany = $pdo->prepare(
        'INSERT INTO companies (name, slug, tax_id, contact_email, status, created_at, updated_at)
         VALUES (:name, :slug, :tax, :email, :status, NOW(), NOW()) RETURNING id'
    );
    $insertCompany->execute([
        'name' => 'Smoke Company ' . $suffix,
        'slug' => $companySlug,
        'tax' => 'SMK-' . $suffix,
        'email' => 'smoke+' . $suffix . '@example.test',
        'status' => 'active',
    ]);
    $newCompanyId = (int) $insertCompany->fetchColumn();
    assertTrue($newCompanyId > 0, 'No se pudo crear company en smoke test.');

    $updateCompany = $pdo->prepare('UPDATE companies SET name = :name, status = :status, updated_at = NOW() WHERE id = :id');
    $updateCompany->execute([
        'name' => 'Smoke Company Updated ' . $suffix,
        'status' => 'suspended',
        'id' => $newCompanyId,
    ]);
    assertTrue($updateCompany->rowCount() === 1, 'No se pudo actualizar company en smoke test.');
    out('[SMOKE] Companies CRUD: OK');

    // 3) Users + user_company
    $email = 'smoke.user.' . $suffix . '@example.test';
    $insertUser = $pdo->prepare(
        'INSERT INTO users (name, email, password, two_factor_enabled, created_at, updated_at)
         VALUES (:name, :email, :password, FALSE, NOW(), NOW()) RETURNING id'
    );
    $insertUser->execute([
        'name' => 'Smoke User ' . $suffix,
        'email' => $email,
        'password' => password_hash('smoke-password', PASSWORD_DEFAULT),
    ]);
    $userId = (int) $insertUser->fetchColumn();
    assertTrue($userId > 0, 'No se pudo crear user en smoke test.');

    $bindUserCompany = $pdo->prepare('INSERT INTO user_company (user_id, company_id, role_id) VALUES (:user_id, :company_id, :role_id)');
    $bindUserCompany->execute([
        'user_id' => $userId,
        'company_id' => $companyId,
        'role_id' => $roleId,
    ]);
    assertTrue($bindUserCompany->rowCount() === 1, 'No se pudo asociar user_company en smoke test.');

    $linkRole = $pdo->prepare('INSERT INTO user_has_roles (user_id, role_id) VALUES (:user_id, :role_id)');
    $linkRole->execute([
        'user_id' => $userId,
        'role_id' => $roleId,
    ]);
    assertTrue($linkRole->rowCount() === 1, 'No se pudo crear user_has_roles en smoke test.');

    $updateUser = $pdo->prepare('UPDATE users SET name = :name, updated_at = NOW() WHERE id = :id');
    $updateUser->execute([
        'name' => 'Smoke User Updated ' . $suffix,
        'id' => $userId,
    ]);
    assertTrue($updateUser->rowCount() === 1, 'No se pudo actualizar user en smoke test.');
    out('[SMOKE] Users + user_company: OK');

    // 4) Menu ACL CRUD
    $insertAcl = $pdo->prepare(
        'INSERT INTO menu_acl (company_id, menu_item_id, subject_type, subject_id, scope, permission_level, effect, created_at, updated_at)
         VALUES (:company_id, :menu_item_id, :subject_type, :subject_id, :scope, :permission_level, :effect, NOW(), NOW())
         RETURNING id'
    );
    $insertAcl->execute([
        'company_id' => $companyId,
        'menu_item_id' => $menuItemId,
        'subject_type' => 'role',
        'subject_id' => $roleId,
        'scope' => 'item',
        'permission_level' => 'read',
        'effect' => 'allow',
    ]);
    $aclId = (int) $insertAcl->fetchColumn();
    assertTrue($aclId > 0, 'No se pudo crear ACL en smoke test.');

    $updateAcl = $pdo->prepare('UPDATE menu_acl SET permission_level = :lvl, effect = :effect, updated_at = NOW() WHERE id = :id');
    $updateAcl->execute([
        'lvl' => 'write',
        'effect' => 'allow',
        'id' => $aclId,
    ]);
    assertTrue($updateAcl->rowCount() === 1, 'No se pudo actualizar ACL en smoke test.');

    $deleteAcl = $pdo->prepare('DELETE FROM menu_acl WHERE id = :id');
    $deleteAcl->execute(['id' => $aclId]);
    assertTrue($deleteAcl->rowCount() === 1, 'No se pudo eliminar ACL en smoke test.');
    out('[SMOKE] Menu ACL CRUD: OK');

    $pdo->rollBack();
    out('[SMOKE] Resultado final: PASS (todo validado en transaccion con ROLLBACK)');
    exit(0);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    out('[SMOKE] Resultado final: FAIL');
    out('[SMOKE] Motivo: ' . $exception->getMessage());
    exit(1);
}
