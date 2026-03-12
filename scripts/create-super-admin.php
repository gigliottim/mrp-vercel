<?php

declare(strict_types=1);

/**
 * Crea (o actualiza) el super administrador en mrp_auth.
 *
 * - Inserta el usuario si no existe y actualiza su password si ya existe.
 * - Garantiza que el rol 'super_admin' exista en la tabla roles.
 * - Asigna user_has_roles(super_admin) al usuario.
 * - Inserta user_company para TODAS las empresas activas existentes.
 * - Instala un trigger de PostgreSQL que vincula automáticamente al
 *   super admin con cualquier empresa nueva que se registre en el futuro.
 *
 * Uso:  php scripts/create-super-admin.php
 */

require_once __DIR__ . '/../bootstrap/autoload.php';

use App\Core\Config\Config;
use App\Core\Support\Env;
use App\Core\Database\DatabaseManager;
use PDO;

Env::load(base_path('.env'));
Config::load(base_path('config'));

const SUPER_ADMIN_EMAIL    = 'martin@unik.ar';
const SUPER_ADMIN_NAME     = 'Martin Gigliotti';
const SUPER_ADMIN_PASSWORD = 'M#vua%f5A$Ja7K%N#6tH';
const SUPER_ADMIN_ROLE     = 'super_admin';

function out(string $msg, string $level = 'INFO'): void
{
    $prefix = match ($level) {
        'OK'   => '[OK]  ',
        'WARN' => '[WARN]',
        'FAIL' => '[FAIL]',
        default => '[INFO]',
    };
    echo $prefix . ' ' . $msg . PHP_EOL;
}

// ---------------------------------------------------------------------------
// Conexión
// ---------------------------------------------------------------------------
$db = DatabaseManager::connection('mrp_auth');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

out('Conectado a mrp_auth');

// ---------------------------------------------------------------------------
// 1. Garantizar rol super_admin
// ---------------------------------------------------------------------------
$roleStmt = $db->prepare("SELECT id FROM roles WHERE name = :name LIMIT 1");
$roleStmt->execute(['name' => SUPER_ADMIN_ROLE]);
$roleId = $roleStmt->fetchColumn();

if ($roleId === false) {
    $insert = $db->prepare(
        "INSERT INTO roles (name, guard_name, created_at, updated_at)
         VALUES (:name, 'web', NOW(), NOW())
         RETURNING id"
    );
    $insert->execute(['name' => SUPER_ADMIN_ROLE]);
    $roleId = (int) $insert->fetchColumn();
    out("Rol '" . SUPER_ADMIN_ROLE . "' creado  (id={$roleId})", 'OK');
} else {
    $roleId = (int) $roleId;
    out("Rol '" . SUPER_ADMIN_ROLE . "' ya existe (id={$roleId})");
}

// ---------------------------------------------------------------------------
// 2. Crear / actualizar usuario
// ---------------------------------------------------------------------------
$userStmt = $db->prepare("SELECT id FROM users WHERE lower(email) = :email LIMIT 1");
$userStmt->execute(['email' => mb_strtolower(SUPER_ADMIN_EMAIL)]);
$userId = $userStmt->fetchColumn();

$hash = password_hash(SUPER_ADMIN_PASSWORD, PASSWORD_DEFAULT);

if ($userId === false) {
    $ins = $db->prepare(
        "INSERT INTO users (name, email, password, two_factor_enabled, created_at, updated_at)
         VALUES (:name, :email, :password, FALSE, NOW(), NOW())
         RETURNING id"
    );
    $ins->execute([
        'name'     => SUPER_ADMIN_NAME,
        'email'    => SUPER_ADMIN_EMAIL,
        'password' => $hash,
    ]);
    $userId = (int) $ins->fetchColumn();
    out("Usuario creado (id={$userId})", 'OK');
} else {
    $userId = (int) $userId;
    $upd = $db->prepare(
        "UPDATE users SET password = :password, name = :name, updated_at = NOW() WHERE id = :id"
    );
    $upd->execute(['password' => $hash, 'name' => SUPER_ADMIN_NAME, 'id' => $userId]);
    out("Usuario actualizado (id={$userId}) — password regenerado", 'OK');
}

// ---------------------------------------------------------------------------
// 3. Asignar rol global user_has_roles
// ---------------------------------------------------------------------------
$hasRole = $db->prepare(
    "SELECT 1 FROM user_has_roles WHERE user_id = :uid AND role_id = :rid LIMIT 1"
);
$hasRole->execute(['uid' => $userId, 'rid' => $roleId]);

if ($hasRole->fetchColumn() === false) {
    $db->prepare(
        "INSERT INTO user_has_roles (user_id, role_id) VALUES (:uid, :rid)"
    )->execute(['uid' => $userId, 'rid' => $roleId]);
    out("user_has_roles asignado (user={$userId}, role={$roleId})", 'OK');
} else {
    out("user_has_roles ya estaba asignado");
}

// ---------------------------------------------------------------------------
// 4. Vincular con todas las empresas activas existentes
// ---------------------------------------------------------------------------
$companies = $db->query("SELECT id, name FROM companies WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
$linked = 0;
$skipped = 0;

foreach ($companies as $company) {
    $exists = $db->prepare(
        "SELECT 1 FROM user_company WHERE user_id = :uid AND company_id = :cid LIMIT 1"
    );
    $exists->execute(['uid' => $userId, 'cid' => $company['id']]);

    if ($exists->fetchColumn() === false) {
        $db->prepare(
            "INSERT INTO user_company (user_id, company_id, role_id)
             VALUES (:uid, :cid, :rid)"
        )->execute(['uid' => $userId, 'cid' => $company['id'], 'rid' => $roleId]);
        out("Empresa '{$company['name']}' (id={$company['id']}) vinculada", 'OK');
        $linked++;
    } else {
        // Actualizar role_id si cambió
        $db->prepare(
            "UPDATE user_company SET role_id = :rid WHERE user_id = :uid AND company_id = :cid"
        )->execute(['rid' => $roleId, 'uid' => $userId, 'cid' => $company['id']]);
        $skipped++;
    }
}

out("Empresas vinculadas nuevas: {$linked} | ya existentes: {$skipped}");

// ---------------------------------------------------------------------------
// 5. Trigger PostgreSQL: auto-vincular al super admin con empresas futuras
// ---------------------------------------------------------------------------

// Función que llevará el trigger
$db->exec(<<<SQL
CREATE OR REPLACE FUNCTION fn_super_admin_auto_link()
RETURNS TRIGGER
LANGUAGE plpgsql
AS \$\$
DECLARE
    v_super_email  TEXT    := 'martin@unik.ar';
    v_role_name    TEXT    := 'super_admin';
    v_user_id      INTEGER;
    v_role_id      INTEGER;
BEGIN
    -- Localizar usuario y rol; si no existen, no fallar el INSERT de companies
    SELECT id INTO v_user_id FROM users WHERE lower(email) = v_super_email LIMIT 1;
    SELECT id INTO v_role_id FROM roles  WHERE name = v_role_name            LIMIT 1;

    IF v_user_id IS NOT NULL AND v_role_id IS NOT NULL THEN
        INSERT INTO user_company (user_id, company_id, role_id)
        VALUES (v_user_id, NEW.id, v_role_id)
        ON CONFLICT DO NOTHING;
    END IF;

    RETURN NEW;
END;
\$\$;
SQL);

// Eliminar trigger previo si existía con otro nombre / definición
$db->exec("DROP TRIGGER IF EXISTS trg_super_admin_auto_link ON companies;");

$db->exec(<<<SQL
CREATE TRIGGER trg_super_admin_auto_link
AFTER INSERT ON companies
FOR EACH ROW
EXECUTE FUNCTION fn_super_admin_auto_link();
SQL);

out('Trigger trg_super_admin_auto_link instalado en tabla companies', 'OK');

// ---------------------------------------------------------------------------
// 6. Verificación final
// ---------------------------------------------------------------------------
$check = $db->prepare(
    "SELECT c.name, c.slug, r.name AS role
     FROM user_company uc
     JOIN companies c ON c.id = uc.company_id
     JOIN roles     r ON r.id  = uc.role_id
     WHERE uc.user_id = :uid
     ORDER BY c.name"
);
$check->execute(['uid' => $userId]);
$rows = $check->fetchAll(PDO::FETCH_ASSOC);

out('');
out('=== Accesos del super admin ===');
foreach ($rows as $row) {
    out("  empresa='{$row['name']}' slug={$row['slug']} rol={$row['role']}");
}
out('Total empresas con acceso: ' . count($rows), count($rows) > 0 ? 'OK' : 'WARN');
out('');
out('Listo. Usuario: ' . SUPER_ADMIN_EMAIL, 'OK');
