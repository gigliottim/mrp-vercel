-- =============================================================================
-- Migración: Reorganización de Roles del Sistema
-- Fecha: 2026-03-23
-- Estrategia: Solo DML (INSERT/UPDATE/DELETE) — no requiere ser owner de tabla.
--
-- Objetivo:
--   Establecer 4 roles globales fijos con IDs 1-4:
--     1 = Super Administrador  (acceso total, todas las empresas)
--     2 = Administrador        (acceso total a su empresa)
--     3 = Supervisor
--     4 = Usuario
--
-- Mapeo de roles actuales (DB vps pre-migración):
--   id=4  super_admin (guard=web)           → ID 1 (Super Administrador)
--   id=1  Administrador (guard=company:2:…) → ID 2 (Administrador)
--   id=2  supervisor (guard=sup)            → ID 3 (Supervisor)
--   id=3  operator / id=5,6,7              → ID 4 (Usuario)
-- =============================================================================

BEGIN;

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 1: Insertar roles staging con IDs altos (101-104) que no colisionen
--         Nombres con prefijo _tmp_ para evitar choque con constraint UNIQUE name
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO roles (id, name, guard_name, created_at, updated_at) VALUES
    (101, '_tmp_super_administrador', 'web', NOW(), NOW()),
    (102, '_tmp_administrador',       'web', NOW(), NOW()),
    (103, '_tmp_supervisor',          'web', NOW(), NOW()),
    (104, '_tmp_usuario',             'web', NOW(), NOW());

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 2: Reasignar user_company → staging IDs
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE user_company SET role_id = 101 WHERE role_id = 4;
UPDATE user_company SET role_id = 102 WHERE role_id = 1;
UPDATE user_company SET role_id = 103 WHERE role_id = 2;
UPDATE user_company SET role_id = 104 WHERE role_id IN (3, 5, 6, 7);

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 3: Reasignar user_has_roles → staging IDs
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE user_has_roles SET role_id = 101 WHERE role_id = 4;
UPDATE user_has_roles SET role_id = 102 WHERE role_id = 1;
UPDATE user_has_roles SET role_id = 103 WHERE role_id = 2;
-- roles 3,5,6,7 no tienen permisos útiles → eliminar
DELETE FROM user_has_roles WHERE role_id IN (3, 5, 6, 7);

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 4: Eliminar permisos y los roles originales (1-7)
--         Ya no hay referencias FK hacia ellos.
-- ─────────────────────────────────────────────────────────────────────────────
DELETE FROM role_has_permissions WHERE role_id IN (1, 2, 3, 4, 5, 6, 7);
DELETE FROM roles WHERE id IN (1, 2, 3, 4, 5, 6, 7);

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 5: Insertar roles definitivos con IDs 1-4
--         IDs 1-7 ahora libres; las FKs se respetan porque 101-104 existen.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO roles (id, name, guard_name, created_at, updated_at) VALUES
    (1, 'Super Administrador', 'web', NOW(), NOW()),
    (2, 'Administrador',       'web', NOW(), NOW()),
    (3, 'Supervisor',          'web', NOW(), NOW()),
    (4, 'Usuario',             'web', NOW(), NOW());

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 6: Reasignar user_company → IDs definitivos
--         roles.id 1-4 ya existen → la FK se satisface en el UPDATE.
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE user_company SET role_id = 1 WHERE role_id = 101;
UPDATE user_company SET role_id = 2 WHERE role_id = 102;
UPDATE user_company SET role_id = 3 WHERE role_id = 103;
UPDATE user_company SET role_id = 4 WHERE role_id = 104;

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 7: Reasignar user_has_roles → IDs definitivos
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE user_has_roles SET role_id = 1 WHERE role_id = 101;
UPDATE user_has_roles SET role_id = 2 WHERE role_id = 102;
UPDATE user_has_roles SET role_id = 3 WHERE role_id = 103;
UPDATE user_has_roles SET role_id = 4 WHERE role_id = 104;

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 8: Eliminar staging roles 101-104
--         Ya no hay referencias hacia ellos.
-- ─────────────────────────────────────────────────────────────────────────────
DELETE FROM roles WHERE id IN (101, 102, 103, 104);

-- ─────────────────────────────────────────────────────────────────────────────
-- FASE 9: Resetear la secuencia para que el próximo auto-ID sea >= 10
-- ─────────────────────────────────────────────────────────────────────────────
SELECT setval(pg_get_serial_sequence('roles', 'id'), 10);

COMMIT;
