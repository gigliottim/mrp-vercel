-- ================================================================
-- Migración: Nueva sección EMPRESA Y USUARIOS en el menú
-- Fecha: 2026-03-16
--
-- Cambios:
--   1. admin.empresa, admin.usuarios, admin.roles, admin.permisos
--      pasan de 'administracion' a nueva sección 'empresa_usuarios'
--
-- SEGURO: Solo modifica section_key, section_label y sort_order.
--         No toca routes, codes ni permisos ACL.
-- ================================================================

BEGIN;

UPDATE menu_items
SET section_key   = 'empresa_usuarios',
    section_label = 'Empresa y Usuarios',
    sort_order    = 10
WHERE code = 'admin.empresa';

UPDATE menu_items
SET section_key   = 'empresa_usuarios',
    section_label = 'Empresa y Usuarios',
    sort_order    = 20
WHERE code = 'admin.usuarios';

UPDATE menu_items
SET section_key   = 'empresa_usuarios',
    section_label = 'Empresa y Usuarios',
    sort_order    = 30
WHERE code = 'admin.roles';

UPDATE menu_items
SET section_key   = 'empresa_usuarios',
    section_label = 'Empresa y Usuarios',
    sort_order    = 40
WHERE code = 'admin.permisos';

-- ────────────────────────────────────────────────────────────────
-- Registrar en schema_migrations
-- ────────────────────────────────────────────────────────────────
INSERT INTO schema_migrations (filename, executed_at)
VALUES ('2026_03_16_002_menu_empresa_usuarios_section.sql', NOW())
ON CONFLICT (filename) DO NOTHING;

COMMIT;
