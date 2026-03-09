-- Add sidebar menu item for entidades CRUD
-- Run with: php migrate_database.php --connection=mrp_auth

INSERT INTO menu_items (code, label, route, icon, section_key, section_label, sort_order, is_active)
VALUES (
    'catalogos.entidades',
    'Clientes y proveedores',
    '/configuracion/entidades',
    'fa-solid fa-address-book',
    'parametros_catalogos',
    'Parametros y catalogos',
    70,
    TRUE
)
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
