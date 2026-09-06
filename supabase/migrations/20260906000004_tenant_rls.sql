-- Políticas RLS multi-tenant para tablas de negocio
-- Aislamiento por company_id desde JWT claims (custom_access_token_hook)

alter table public.agent_ai_logs enable row level security;

create policy "agent_ai_logs_tenant_select" on public.agent_ai_logs
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "agent_ai_logs_tenant_insert" on public.agent_ai_logs
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "agent_ai_logs_tenant_update" on public.agent_ai_logs
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "agent_ai_logs_tenant_delete" on public.agent_ai_logs
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.agent_conversations enable row level security;

create policy "agent_conversations_tenant_select" on public.agent_conversations
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "agent_conversations_tenant_insert" on public.agent_conversations
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "agent_conversations_tenant_update" on public.agent_conversations
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "agent_conversations_tenant_delete" on public.agent_conversations
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.agent_messages enable row level security;

create policy "agent_messages_tenant_select" on public.agent_messages
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "agent_messages_tenant_insert" on public.agent_messages
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "agent_messages_tenant_update" on public.agent_messages
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "agent_messages_tenant_delete" on public.agent_messages
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.almacenes enable row level security;

create policy "almacenes_tenant_select" on public.almacenes
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "almacenes_tenant_insert" on public.almacenes
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "almacenes_tenant_update" on public.almacenes
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "almacenes_tenant_delete" on public.almacenes
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.bom_cabecera enable row level security;

create policy "bom_cabecera_tenant_select" on public.bom_cabecera
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "bom_cabecera_tenant_insert" on public.bom_cabecera
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "bom_cabecera_tenant_update" on public.bom_cabecera
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "bom_cabecera_tenant_delete" on public.bom_cabecera
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.bom_detalle enable row level security;

create policy "bom_detalle_tenant_select" on public.bom_detalle
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "bom_detalle_tenant_insert" on public.bom_detalle
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "bom_detalle_tenant_update" on public.bom_detalle
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "bom_detalle_tenant_delete" on public.bom_detalle
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.centros_trabajo enable row level security;

create policy "centros_trabajo_tenant_select" on public.centros_trabajo
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "centros_trabajo_tenant_insert" on public.centros_trabajo
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "centros_trabajo_tenant_update" on public.centros_trabajo
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "centros_trabajo_tenant_delete" on public.centros_trabajo
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.composicion_variantes enable row level security;

create policy "composicion_variantes_tenant_select" on public.composicion_variantes
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "composicion_variantes_tenant_insert" on public.composicion_variantes
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "composicion_variantes_tenant_update" on public.composicion_variantes
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "composicion_variantes_tenant_delete" on public.composicion_variantes
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.compras enable row level security;

create policy "compras_tenant_select" on public.compras
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "compras_tenant_insert" on public.compras
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "compras_tenant_update" on public.compras
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "compras_tenant_delete" on public.compras
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.configuracion enable row level security;

create policy "configuracion_tenant_select" on public.configuracion
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "configuracion_tenant_insert" on public.configuracion
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "configuracion_tenant_update" on public.configuracion
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "configuracion_tenant_delete" on public.configuracion
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.configuracion_general enable row level security;

create policy "configuracion_general_tenant_select" on public.configuracion_general
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "configuracion_general_tenant_insert" on public.configuracion_general
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "configuracion_general_tenant_update" on public.configuracion_general
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "configuracion_general_tenant_delete" on public.configuracion_general
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.entidades enable row level security;

create policy "entidades_tenant_select" on public.entidades
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "entidades_tenant_insert" on public.entidades
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "entidades_tenant_update" on public.entidades
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "entidades_tenant_delete" on public.entidades
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.grupos_partes enable row level security;

create policy "grupos_partes_tenant_select" on public.grupos_partes
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "grupos_partes_tenant_insert" on public.grupos_partes
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "grupos_partes_tenant_update" on public.grupos_partes
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "grupos_partes_tenant_delete" on public.grupos_partes
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.movimientos_inventario enable row level security;

create policy "movimientos_inventario_tenant_select" on public.movimientos_inventario
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "movimientos_inventario_tenant_insert" on public.movimientos_inventario
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "movimientos_inventario_tenant_update" on public.movimientos_inventario
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "movimientos_inventario_tenant_delete" on public.movimientos_inventario
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.movimientos_stock enable row level security;

create policy "movimientos_stock_tenant_select" on public.movimientos_stock
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "movimientos_stock_tenant_insert" on public.movimientos_stock
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "movimientos_stock_tenant_update" on public.movimientos_stock
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "movimientos_stock_tenant_delete" on public.movimientos_stock
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.mrp_calculos_cabecera enable row level security;

create policy "mrp_calculos_cabecera_tenant_select" on public.mrp_calculos_cabecera
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "mrp_calculos_cabecera_tenant_insert" on public.mrp_calculos_cabecera
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "mrp_calculos_cabecera_tenant_update" on public.mrp_calculos_cabecera
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "mrp_calculos_cabecera_tenant_delete" on public.mrp_calculos_cabecera
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.mrp_sugerencias enable row level security;

create policy "mrp_sugerencias_tenant_select" on public.mrp_sugerencias
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "mrp_sugerencias_tenant_insert" on public.mrp_sugerencias
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "mrp_sugerencias_tenant_update" on public.mrp_sugerencias
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "mrp_sugerencias_tenant_delete" on public.mrp_sugerencias
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.ordenes_produccion enable row level security;

create policy "ordenes_produccion_tenant_select" on public.ordenes_produccion
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "ordenes_produccion_tenant_insert" on public.ordenes_produccion
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "ordenes_produccion_tenant_update" on public.ordenes_produccion
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "ordenes_produccion_tenant_delete" on public.ordenes_produccion
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.partes enable row level security;

create policy "partes_tenant_select" on public.partes
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "partes_tenant_insert" on public.partes
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "partes_tenant_update" on public.partes
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "partes_tenant_delete" on public.partes
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.planificacion_recursos enable row level security;

create policy "planificacion_recursos_tenant_select" on public.planificacion_recursos
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "planificacion_recursos_tenant_insert" on public.planificacion_recursos
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "planificacion_recursos_tenant_update" on public.planificacion_recursos
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "planificacion_recursos_tenant_delete" on public.planificacion_recursos
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.rutas_produccion enable row level security;

create policy "rutas_produccion_tenant_select" on public.rutas_produccion
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "rutas_produccion_tenant_insert" on public.rutas_produccion
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "rutas_produccion_tenant_update" on public.rutas_produccion
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "rutas_produccion_tenant_delete" on public.rutas_produccion
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.tipos_depositos enable row level security;

create policy "tipos_depositos_tenant_select" on public.tipos_depositos
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "tipos_depositos_tenant_insert" on public.tipos_depositos
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "tipos_depositos_tenant_update" on public.tipos_depositos
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "tipos_depositos_tenant_delete" on public.tipos_depositos
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.tipos_depositos_movimientos enable row level security;

create policy "tipos_depositos_movimientos_tenant_select" on public.tipos_depositos_movimientos
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "tipos_depositos_movimientos_tenant_insert" on public.tipos_depositos_movimientos
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "tipos_depositos_movimientos_tenant_update" on public.tipos_depositos_movimientos
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "tipos_depositos_movimientos_tenant_delete" on public.tipos_depositos_movimientos
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.tipos_partes enable row level security;

create policy "tipos_partes_tenant_select" on public.tipos_partes
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "tipos_partes_tenant_insert" on public.tipos_partes
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "tipos_partes_tenant_update" on public.tipos_partes
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "tipos_partes_tenant_delete" on public.tipos_partes
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.unidades_medida enable row level security;

create policy "unidades_medida_tenant_select" on public.unidades_medida
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "unidades_medida_tenant_insert" on public.unidades_medida
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "unidades_medida_tenant_update" on public.unidades_medida
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "unidades_medida_tenant_delete" on public.unidades_medida
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

alter table public.variantes enable row level security;

create policy "variantes_tenant_select" on public.variantes
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "variantes_tenant_insert" on public.variantes
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "variantes_tenant_update" on public.variantes
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "variantes_tenant_delete" on public.variantes
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);
