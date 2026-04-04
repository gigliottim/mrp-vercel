-- Migración: Agregar ítem del Agente AI al menú lateral
-- Sección: taller
-- Ítem: Agente AI (chatbot asistente)

INSERT INTO menu_items (code, label, route, icon, section_key, section_label, parent_id, sort_order, is_active)
VALUES
    (
        'agent_ai.chat',
        'Agente AI',
        '/agent',
        'fa-solid fa-robot',
        'taller',
        'Taller',
        NULL,
        60,
        TRUE
    )
ON CONFLICT (code) DO NOTHING;

-- Registrar en schema_migrations
INSERT INTO schema_migrations (filename, executed_at)
VALUES ('2026_04_03_004_add_agent_ai_menu_item.sql', NOW())
ON CONFLICT (filename) DO NOTHING;
