-- Sidebar menu canonical source in mrp_auth
-- Run with: php migrate_database.php --connection=mrp_auth

CREATE TABLE IF NOT EXISTS menu_items (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(120) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    route VARCHAR(255) NULL,
    icon VARCHAR(120) NULL,
    section_key VARCHAR(80) NOT NULL,
    section_label VARCHAR(120) NOT NULL,
    parent_id BIGINT NULL REFERENCES menu_items(id),
    sort_order INT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_menu_items_section_sort
    ON menu_items(section_key, sort_order);

CREATE INDEX IF NOT EXISTS idx_menu_items_parent
    ON menu_items(parent_id);

CREATE TABLE IF NOT EXISTS menu_acl (
    id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES companies(id),
    menu_item_id BIGINT NOT NULL REFERENCES menu_items(id),
    subject_type VARCHAR(10) NOT NULL CHECK (subject_type IN ('role', 'user')),
    subject_id BIGINT NOT NULL,
    scope VARCHAR(10) NOT NULL CHECK (scope IN ('item', 'branch')),
    permission_level VARCHAR(10) NOT NULL CHECK (permission_level IN ('read', 'write')),
    effect VARCHAR(10) NOT NULL DEFAULT 'allow' CHECK (effect IN ('allow', 'deny')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(company_id, menu_item_id, subject_type, subject_id, scope)
);

CREATE INDEX IF NOT EXISTS idx_menu_acl_company_subject
    ON menu_acl(company_id, subject_type, subject_id);

WITH seed_items (code, label, route, icon, section_key, section_label, sort_order) AS (
    VALUES
        ('panel.inicio', 'Panel inicial', '/dashboard', 'fa-solid fa-gauge', 'panel', 'Panel', 10),
        ('planeamiento.sugerencias', 'Sugerencias MRP', '/planeamiento/sugerencias', 'fa-solid fa-list-check', 'planeamiento_mrp', 'Planeamiento MRP', 10),
        ('planeamiento.ordenes', 'Ordenes planificadas', '/planeamiento/ordenes', 'fa-solid fa-calendar-check', 'planeamiento_mrp', 'Planeamiento MRP', 20),
        ('produccion.dashboard', 'Dashboard de Operaciones', '/produccion', 'fa-solid fa-gauge-high', 'produccion', 'Produccion', 10),
        ('produccion.centros_trabajo', 'Centros de Trabajo', '/produccion/centros-trabajo', 'fa-solid fa-industry', 'produccion', 'Produccion', 20),
        ('produccion.rutas', 'Rutas de Produccion', '/produccion/rutas', 'fa-solid fa-route', 'produccion', 'Produccion', 30),
        ('produccion.ordenes', 'Ordenes de Produccion', '/produccion/ordenes', 'fa-solid fa-clipboard-list', 'produccion', 'Produccion', 40),
        ('produccion.planificacion', 'Planificacion de Recursos', '/produccion/planificacion', 'fa-solid fa-calendar-alt', 'produccion', 'Produccion', 50),
        ('produccion.gantt', 'Vista Gantt', '/produccion/planificacion/gantt', 'fa-solid fa-chart-gantt', 'produccion', 'Produccion', 60),
        ('productos.partes', 'Listado de Partes', '/productos/partes', 'fa-solid fa-puzzle-piece', 'productos_bom', 'Productos y BOM', 10),
        ('productos.manager', 'Gestor de partes', '/productos/partes/manager', 'fa-solid fa-wrench', 'productos_bom', 'Productos y BOM', 20),
        ('productos.bom', 'BOM activas', '/productos/bom', 'fa-solid fa-diagram-project', 'productos_bom', 'Productos y BOM', 30),
        ('productos.maestro', 'Composicion de variantes', '/productos/maestro', 'fa-solid fa-layer-group', 'productos_bom', 'Productos y BOM', 40),
        ('inventario.critico', 'Stock critico', '/inventario/critico', 'fa-solid fa-triangle-exclamation', 'inventario_stock', 'Inventario y stock', 10),
        ('transacciones.movimientos', 'Movimientos de Partes', '/transacciones/movimientos-partes', 'fa-solid fa-arrow-right-arrow-left', 'transacciones', 'Transacciones', 10),
        ('transacciones.compras', 'Gestion de Compras', '/compras', 'fa-solid fa-shopping-cart', 'transacciones', 'Transacciones', 20),
        ('reportes.destino_partes', 'Destino de Partes', '/reportes/destino-partes', 'fa-solid fa-sitemap', 'reportes', 'Reportes', 10),
        ('reportes.listado_ingenieria', 'Listado de Ingenieria', '/reportes/listado-ingenieria', 'fa-solid fa-list-check', 'reportes', 'Reportes', 20),
        ('reportes.planificacion_produccion', 'Planificacion de Produccion', '/reportes/planificacion-produccion', 'fa-solid fa-calendar-days', 'reportes', 'Reportes', 30),
        ('reportes.resumen_grupos', 'Resumen por grupos', '/reportes/resumen-grupos', 'fa-solid fa-layer-group', 'reportes', 'Reportes', 40),
        ('catalogos.configuracion', 'Configuracion', '/configuracion/general', 'fa-solid fa-sliders', 'parametros_catalogos', 'Parametros y catalogos', 10),
        ('catalogos.unidades', 'Unidades de medida', '/configuracion/unidades', 'fa-solid fa-ruler-combined', 'parametros_catalogos', 'Parametros y catalogos', 20),
        ('catalogos.tipos_partes', 'Tipos de partes', '/configuracion/tipos-partes', 'fa-solid fa-tags', 'parametros_catalogos', 'Parametros y catalogos', 30),
        ('catalogos.tipos_depositos', 'Tipos de deposito', '/configuracion/tipos-depositos', 'fa-solid fa-warehouse', 'parametros_catalogos', 'Parametros y catalogos', 40),
        ('catalogos.validaciones_depositos', 'Validaciones de movimientos', '/configuracion/depositos-validaciones', 'fa-solid fa-arrow-right-arrow-left', 'parametros_catalogos', 'Parametros y catalogos', 50),
        ('catalogos.grupos_partes', 'Grupos de partes', '/configuracion/grupos-partes', 'fa-solid fa-layer-group', 'parametros_catalogos', 'Parametros y catalogos', 60),
        ('admin.empresa', 'Empresa', '/configuracion/general', 'fa-solid fa-building', 'empresa_usuarios', 'Empresa y Usuarios', 10),
        ('admin.usuarios', 'Usuarios', NULL, 'fa-solid fa-users', 'empresa_usuarios', 'Empresa y Usuarios', 20),
        ('admin.roles', 'Roles', NULL, 'fa-solid fa-user-shield', 'empresa_usuarios', 'Empresa y Usuarios', 30),
        ('admin.permisos', 'Permisos', NULL, 'fa-solid fa-key', 'empresa_usuarios', 'Empresa y Usuarios', 40)
)
INSERT INTO menu_items (code, label, route, icon, section_key, section_label, sort_order, is_active)
SELECT code, label, route, icon, section_key, section_label, sort_order, TRUE
FROM seed_items
ON CONFLICT (code)
DO UPDATE SET
    label = EXCLUDED.label,
    route = EXCLUDED.route,
    icon = EXCLUDED.icon,
    section_key = EXCLUDED.section_key,
    section_label = EXCLUDED.section_label,
    sort_order = EXCLUDED.sort_order,
    is_active = TRUE,
    updated_at = CURRENT_TIMESTAMP;
