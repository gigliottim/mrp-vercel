-- Sincroniza secuencias tras inserts con id explícito (dump)
-- Los INSERTs con id no avanzan las secuencias; sin esto, el próximo
-- INSERT de companies/roles/etc. choca con PK duplicada.

SELECT setval('public.companies_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.companies));
SELECT setval('public.roles_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.roles));
SELECT setval('public.permissions_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.permissions));
SELECT setval('public.menu_items_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.menu_items));
SELECT setval('public.menu_acl_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.menu_acl));
SELECT setval('public.audit_logs_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.audit_logs));

-- Secuencias tenant (datos se migran en Task 6; setval con 1 si vacías)
SELECT setval('public.agent_ai_logs_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.agent_ai_logs));
SELECT setval('public.agent_conversations_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.agent_conversations));
SELECT setval('public.agent_messages_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.agent_messages));
SELECT setval('public.almacenes_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.almacenes));
SELECT setval('public.bom_cabecera_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.bom_cabecera));
SELECT setval('public.bom_detalle_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.bom_detalle));
SELECT setval('public.centros_trabajo_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.centros_trabajo));
SELECT setval('public.composicion_variantes_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.composicion_variantes));
SELECT setval('public.compras_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.compras));
SELECT setval('public.configuracion_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.configuracion));
SELECT setval('public.entidades_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.entidades));
SELECT setval('public.grupos_partes_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.grupos_partes));
SELECT setval('public.movimientos_inventario_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.movimientos_inventario));
SELECT setval('public.movimientos_stock_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.movimientos_stock));
SELECT setval('public.mrp_calculos_cabecera_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.mrp_calculos_cabecera));
SELECT setval('public.mrp_sugerencias_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.mrp_sugerencias));
SELECT setval('public.ordenes_produccion_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.ordenes_produccion));
SELECT setval('public.partes_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.partes));
SELECT setval('public.planificacion_recursos_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.planificacion_recursos));
SELECT setval('public.rutas_produccion_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.rutas_produccion));
SELECT setval('public.tipos_depositos_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.tipos_depositos));
SELECT setval('public.tipos_depositos_movimientos_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.tipos_depositos_movimientos));
SELECT setval('public.tipos_partes_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.tipos_partes));
SELECT setval('public.unidades_medida_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.unidades_medida));
SELECT setval('public.variantes_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.variantes));
