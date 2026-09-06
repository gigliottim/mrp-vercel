-- verificar_solapamiento: retorna TRUE si el periodo se solapa con otro
-- recurso programado en el mismo centro de trabajo (misma empresa)

CREATE OR REPLACE FUNCTION public.verificar_solapamiento(
  p_company_id bigint,
  p_centro_trabajo_id integer,
  p_inicio timestamp,
  p_fin timestamp,
  p_excluir_id integer DEFAULT NULL
)
RETURNS boolean
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_solapa boolean;
BEGIN
  SELECT EXISTS (
    SELECT 1 FROM planificacion_recursos
    WHERE company_id = p_company_id
      AND centro_trabajo_id = p_centro_trabajo_id
      AND (p_excluir_id IS NULL OR id <> p_excluir_id)
      AND periodo && tsrange(p_inicio, p_fin, '[)')
  ) INTO v_solapa;
  RETURN v_solapa;
END;
$$;

GRANT EXECUTE ON FUNCTION public.verificar_solapamiento(bigint, integer, timestamp, timestamp, integer) TO authenticated;
