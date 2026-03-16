-- ================================================================
-- Migración: Reorganización de secciones del menú para mini-pymes
-- Objetivo: Reducir de 9 secciones a 5, agrupando por flujo de trabajo
--
-- Nueva estructura:
--   taller               → uso diario del operario
--   catalogo_productos   → gestión de partes/BOM (ocasional)
--   planificacion_compras→ planificación semanal + compras
--   reportes             → sin cambios
--   administracion       → configuración + empresa/usuarios
--
-- SEGURO: Solo modifica section_key, section_label y sort_order.
--         No toca routes, codes ni permisos ACL.
-- ================================================================

BEGIN;

-- ────────────────────────────────────────────────────────────────
-- 1. TALLER  (uso diario del operario)
-- ────────────────────────────────────────────────────────────────
UPDATE menu_items SET section_key = 'taller', section_label = 'Taller', sort_order = 5
WHERE code = 'panel.inicio';

UPDATE menu_items SET section_key = 'taller', section_label = 'Taller', sort_order = 10
WHERE code = 'produccion.dashboard';

UPDATE menu_items SET section_key = 'taller', section_label = 'Taller', sort_order = 20
WHERE code = 'produccion.ordenes';

UPDATE menu_items SET section_key = 'taller', section_label = 'Taller', sort_order = 30
WHERE code = 'transacciones.movimientos';

UPDATE menu_items SET section_key = 'taller', section_label = 'Taller', sort_order = 40
WHERE code = 'inventario.critico';

UPDATE menu_items SET section_key = 'taller', section_label = 'Taller', sort_order = 50
WHERE code = 'produccion.gantt';

-- ────────────────────────────────────────────────────────────────
-- 2. CATÁLOGO DE PRODUCTOS  (estructura del producto)
-- ────────────────────────────────────────────────────────────────
UPDATE menu_items SET section_key = 'catalogo_productos', section_label = 'Catálogo de Productos', sort_order = 10
WHERE code = 'productos.partes';

UPDATE menu_items SET section_key = 'catalogo_productos', section_label = 'Catálogo de Productos', sort_order = 20
WHERE code = 'productos.manager';

UPDATE menu_items SET section_key = 'catalogo_productos', section_label = 'Catálogo de Productos', sort_order = 30
WHERE code = 'productos.bom';

UPDATE menu_items SET section_key = 'catalogo_productos', section_label = 'Catálogo de Productos', sort_order = 40
WHERE code = 'productos.maestro';

UPDATE menu_items SET section_key = 'catalogo_productos', section_label = 'Catálogo de Productos', sort_order = 50
WHERE code = 'productos.copiar_componentes';

UPDATE menu_items SET section_key = 'catalogo_productos', section_label = 'Catálogo de Productos', sort_order = 60
WHERE code = 'productos.reemplazar_partes';

-- ────────────────────────────────────────────────────────────────
-- 3. PLANIFICACIÓN Y COMPRAS  (decisiones de la semana)
-- ────────────────────────────────────────────────────────────────
UPDATE menu_items SET section_key = 'planificacion_compras', section_label = 'Planificación y Compras', sort_order = 10
WHERE code = 'planeamiento.sugerencias';

UPDATE menu_items SET section_key = 'planificacion_compras', section_label = 'Planificación y Compras', sort_order = 20
WHERE code = 'planeamiento.ordenes';

UPDATE menu_items SET section_key = 'planificacion_compras', section_label = 'Planificación y Compras', sort_order = 30
WHERE code = 'produccion.planificacion';

UPDATE menu_items SET section_key = 'planificacion_compras', section_label = 'Planificación y Compras', sort_order = 40
WHERE code = 'transacciones.compras';

-- ────────────────────────────────────────────────────────────────
-- 4. REPORTES  (sin cambios en section_key, solo sort_order)
-- ────────────────────────────────────────────────────────────────
UPDATE menu_items SET sort_order = 10 WHERE code = 'reportes.destino_partes';
UPDATE menu_items SET sort_order = 20 WHERE code = 'reportes.listado_ingenieria';
UPDATE menu_items SET sort_order = 30 WHERE code = 'reportes.planificacion_produccion';
UPDATE menu_items SET sort_order = 40 WHERE code = 'reportes.resumen_grupos';

-- ────────────────────────────────────────────────────────────────
-- 5. ADMINISTRACIÓN  (configurar una vez + empresa/usuarios)
-- ────────────────────────────────────────────────────────────────
UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 10
WHERE code = 'catalogos.configuracion';

UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 20
WHERE code = 'catalogos.entidades';

UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 30
WHERE code = 'catalogos.unidades';

UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 40
WHERE code = 'catalogos.tipos_partes';

UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 50
WHERE code = 'catalogos.tipos_depositos';

UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 60
WHERE code = 'catalogos.validaciones_depositos';

UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 70
WHERE code = 'catalogos.grupos_partes';

UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 80
WHERE code = 'produccion.centros_trabajo';

UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 90
WHERE code = 'produccion.rutas';

UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 100
WHERE code = 'admin.empresa';

UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 110
WHERE code = 'admin.usuarios';

UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 120
WHERE code = 'admin.roles';

UPDATE menu_items SET section_key = 'administracion', section_label = 'Administración', sort_order = 130
WHERE code = 'admin.permisos';

-- ────────────────────────────────────────────────────────────────
-- Registrar en schema_migrations
-- ────────────────────────────────────────────────────────────────
INSERT INTO schema_migrations (filename, executed_at)
VALUES ('2026_03_15_002_reorganize_menu_sections_minipyme.sql', NOW())
ON CONFLICT (filename) DO NOTHING;

COMMIT;
