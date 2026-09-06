-- Numeración de órdenes de producción por empresa

CREATE SEQUENCE IF NOT EXISTS public.orden_num_seq;

CREATE OR REPLACE FUNCTION public.next_numero_orden(p_company_id bigint)
RETURNS text
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_seq bigint;
BEGIN
  SELECT nextval('public.orden_num_seq') INTO v_seq;
  RETURN 'OP-' || to_char(CURRENT_DATE, 'YYYYMMDD') || '-' || p_company_id || '-' || lpad(v_seq::text, 4, '0');
END;
$$;

GRANT EXECUTE ON FUNCTION public.next_numero_orden(bigint) TO authenticated;
