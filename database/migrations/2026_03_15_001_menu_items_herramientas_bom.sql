-- Migración: Agregar ítems de herramientas BOM al menú lateral
-- Sección: productos_bom
-- Nuevos ítems: Copiar Componentes y Reemplazar Partes en el Maestro

INSERT INTO menu_items (code, label, route, icon, section_key, section_label, parent_id, sort_order, is_active)
VALUES
    (
        'productos.copiar_componentes',
        'Copiar Componentes',
        '/productos/copiar-componentes',
        'fa-solid fa-copy',
        'productos_bom',
        'Productos y BOM',
        NULL,
        50,
        TRUE
    ),
    (
        'productos.reemplazar_partes',
        'Reemplazar Partes en Maestro',
        '/productos/reemplazar-partes',
        'fa-solid fa-shuffle',
        'productos_bom',
        'Productos y BOM',
        NULL,
        60,
        TRUE
    )
ON CONFLICT (code) DO NOTHING;
