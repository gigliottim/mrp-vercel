-- RLS para user_company: el usuario autenticado puede leer sus propias filas
-- (necesario para GET /api/v1/companies). La migración 05 revocó ALL a
-- authenticated; esta política restaura solo lectura de filas propias.

ALTER TABLE public.user_company ENABLE ROW LEVEL SECURITY;

CREATE POLICY "user_company_own_select" ON public.user_company
  FOR SELECT TO authenticated
  USING (user_id = (SELECT auth.uid()));
