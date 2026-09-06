-- Grant SELECT a authenticated sobre user_company
-- La migración 05 revocó ALL; la política user_company_own_select (migración 13)
-- permite leer filas propias, pero sin GRANT el rol no puede acceder a la tabla.

GRANT SELECT ON public.user_company TO authenticated;
