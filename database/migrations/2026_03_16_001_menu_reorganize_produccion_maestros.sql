-- ================================================================
-- Migración: Reorganización del menú - Sección Producción y Maestros
-- Fecha: 2026-03-16
--
-- Cambios:
--   1. catalogos.entidades  → pasa a PLANIFICACIÓN Y COMPRAS
--   2. produccion.centros_trabajo y produccion.rutas → nueva sección PRODUCCIÓN
--   3. Sección catalogo_productos renombrada a "Desarrollo y Maestros"
--
-- SEGURO: Solo modifica section_key, section_label y sort_order.
--         No toca routes, codes ni permisos ACL.
-- ================================================================

BEGIN;

-- ────────────────────────────────────────────────────────────────
-- 1. catalogos.entidades → PLANIFICACIÓN Y COMPRAS
-- ────────────────────────────────────────────────────────────────
UPDATE menu_items
SET section_key   = 'planificacion_compras',
    section_label = 'Planificación y Compras',
    sort_order    = 50
WHERE code = 'catalogos.entidades';

-- ────────────────────────────────────────────────────────────────
-- 2. produccion.centros_trabajo y produccion.rutas → PRODUCCIÓN
--    (nueva sección independiente)
-- ────────────────────────────────────────────────────────────────
UPDATE menu_items
SET section_key   = 'produccion',
    section_label = 'Producción',
    sort_order    = 10
WHERE code = 'produccion.centros_trabajo';

UPDATE menu_items
SET section_key   = 'produccion',
    section_label = 'Producción',
    sort_order    = 20
WHERE code = 'produccion.rutas';

-- ────────────────────────────────────────────────────────────────
-- 3. Renombrar sección "Catálogo de Productos" → "Desarrollo y Maestros"
--    Aplica a todos los ítems con section_key = 'catalogo_productos'
-- ────────────────────────────────────────────────────────────────
UPDATE menu_items
SET section_label = 'Desarrollo y Maestros'
WHERE section_key = 'catalogo_productos';

-- ────────────────────────────────────────────────────────────────
-- Registrar en schema_migrations
-- ────────────────────────────────────────────────────────────────
INSERT INTO schema_migrations (filename, executed_at)
VALUES ('2026_03_16_001_menu_reorganize_produccion_maestros.sql', NOW())
ON CONFLICT (filename) DO NOTHING;

COMMIT;
