-- RLS para menu_acl y menu_items: aislamiento por empresa
-- Hallazgo: estas tablas tenían RLS deshabilitado (grants ALL a authenticated),
-- permitiendo leer ACLs/menús de TODAS las empresas vía PostgREST.

ALTER TABLE public.menu_acl ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.menu_items ENABLE ROW LEVEL SECURITY;

-- Solo lectura para authenticated (los writes van por service_role/admin API)
REVOKE ALL ON public.menu_acl FROM authenticated;
REVOKE ALL ON public.menu_items FROM authenticated;
GRANT SELECT ON public.menu_acl TO authenticated;
GRANT SELECT ON public.menu_items TO authenticated;

CREATE POLICY "menu_acl_tenant_select" ON public.menu_acl
  FOR SELECT TO authenticated
  USING (company_id = (auth.jwt() ->> 'company_id')::bigint);

CREATE POLICY "menu_items_select_all" ON public.menu_items
  FOR SELECT TO authenticated
  USING (true);
