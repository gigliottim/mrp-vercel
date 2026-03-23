-- =============================================================================
-- Migración: Reorganización de Roles del Sistema
-- Fecha: 2026-03-23
-- Estrategia: Solo DML idempotente (INSERT ON CONFLICT / UPDATE WHERE EXISTS).
--
-- Objetivo:
--   Establecer 4 roles globales fijos con IDs 1-4:
--     1 = Super Administrador  (acceso total, todas las empresas)
--     2 = Administrador        (acceso total a su empresa)
--     3 = Supervisor
--     4 = Usuario
--
-- Es seguro ejecutar múltiples veces: usa ON CONFLICT en INSERTs.
-- =============================================================================

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 1: Insertar roles staging con IDs altos (101-104)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO roles (id, name, guard_name, created_at, updated_at) VALUES
    (101, '_tmp_super_administrador', 'web', NOW(), NOW()),
    (102, '_tmp_administrador',       'web', NOW(), NOW()),
    (103, '_tmp_supervisor',          'web', NOW(), NOW()),
    (104, '_tmp_usuario',             'web', NOW(), NOW())
ON CONFLICT (id) DO NOTHING;

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 2: Reasignar user_company → staging IDs (solo si el origen aún existe)
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE user_company SET role_id = 101 WHERE role_id = 4 AND EXISTS (SELECT 1 FROM roles WHERE id = 4);
UPDATE user_company SET role_id = 102 WHERE role_id = 1 AND EXISTS (SELECT 1 FROM roles WHERE id = 1);
UPDATE user_company SET role_id = 103 WHERE role_id = 2 AND EXISTS (SELECT 1 FROM roles WHERE id = 2);
UPDATE user_company SET role_id = 104 WHERE role_id IN (3, 5, 6, 7);

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 3: Reasignar user_has_roles → staging IDs
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE user_has_roles SET role_id = 101 WHERE role_id = 4 AND EXISTS (SELECT 1 FROM roles WHERE id = 4);
UPDATE user_has_roles SET role_id = 102 WHERE role_id = 1 AND EXISTS (SELECT 1 FROM roles WHERE id = 1);
UPDATE user_has_roles SET role_id = 103 WHERE role_id = 2 AND EXISTS (SELECT 1 FROM roles WHERE id = 2);
DELETE FROM user_has_roles WHERE role_id IN (3, 5, 6, 7);

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 4: Eliminar permisos y roles originales (solo si aún existen)
-- ─────────────────────────────────────────────────────────────────────────────
DELETE FROM role_has_permissions WHERE role_id IN (
    SELECT id FROM roles WHERE id IN (1, 2, 3, 4, 5, 6, 7)
      AND name NOT IN ('Super Administrador', 'Administrador', 'Supervisor', 'Usuario')
);
DELETE FROM roles WHERE id IN (1, 2, 3, 4, 5, 6, 7)
  AND name NOT IN ('Super Administrador', 'Administrador', 'Supervisor', 'Usuario');

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 5: Insertar roles definitivos con IDs 1-4
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO roles (id, name, guard_name, created_at, updated_at) VALUES
    (1, 'Super Administrador', 'web', NOW(), NOW()),
    (2, 'Administrador',       'web', NOW(), NOW()),
    (3, 'Supervisor',          'web', NOW(), NOW()),
    (4, 'Usuario',             'web', NOW(), NOW())
ON CONFLICT (id) DO UPDATE SET
    name       = EXCLUDED.name,
    guard_name = EXCLUDED.guard_name,
    updated_at = EXCLUDED.updated_at;

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 6: Reasignar user_company → IDs definitivos (desde staging)
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE user_company SET role_id = 1 WHERE role_id = 101;
UPDATE user_company SET role_id = 2 WHERE role_id = 102;
UPDATE user_company SET role_id = 3 WHERE role_id = 103;
UPDATE user_company SET role_id = 4 WHERE role_id = 104;

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 7: Reasignar user_has_roles → IDs definitivos (desde staging)
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE user_has_roles SET role_id = 1 WHERE role_id = 101;
UPDATE user_has_roles SET role_id = 2 WHERE role_id = 102;
UPDATE user_has_roles SET role_id = 3 WHERE role_id = 103;
UPDATE user_has_roles SET role_id = 4 WHERE role_id = 104;

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 8: Limpiar staging roles 101-104
-- ─────────────────────────────────────────────────────────────────────────────
DELETE FROM roles WHERE id IN (101, 102, 103, 104);

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 9: Resetear la secuencia para que el próximo auto-ID sea >= 10
-- ─────────────────────────────────────────────────────────────────────────────
SELECT setval(pg_get_serial_sequence('roles', 'id'), GREATEST(10, (SELECT MAX(id) FROM roles) + 1));
