-- crear_parte_con_variante: replica createParteWithDefaultVariant() del PHP
-- (PartesVariantesController). Inserta partes + variante default atomica-
-- mente: si cualquiera falla, rollback total. Invariante: toda parte tiene
-- al menos una variante.

CREATE OR REPLACE FUNCTION public.crear_parte_con_variante(
  p_company_id bigint,
  p_datos jsonb
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_parte_id integer;
  v_codigo text := upper(trim(coalesce(p_datos->>'codigo', '')));
  v_detalle text := trim(coalesce(p_datos->>'detalle', ''));
  v_codigo_variante text;
  v_parte jsonb;
  v_variante jsonb;
BEGIN
  -- Tenant guard fail-closed (patron rpc_tenant_guard)
  IF p_company_id IS DISTINCT FROM (auth.jwt() ->> 'company_id')::bigint THEN
    RAISE EXCEPTION 'company_id no coincide con la sesion';
  END IF;

  IF v_codigo = '' THEN
    RAISE EXCEPTION 'codigo requerido';
  END IF;

  INSERT INTO partes (
    company_id, codigo, id_tipo, id_grupo, detalle,
    largo_alto, id_um_largo_alto, ancho, id_um_ancho,
    espesor_profundidad, id_um_espesor, superficie, id_um_superficie,
    volumen, id_um_volumen, activo,
    id_um_compra, id_um_uso, factor_conversion
  )
  VALUES (
    p_company_id, v_codigo,
    (p_datos->>'id_tipo')::integer,
    (p_datos->>'id_grupo')::integer,
    v_detalle,
    COALESCE((p_datos->>'largo_alto')::numeric, NULL),
    COALESCE((p_datos->>'id_um_largo_alto')::integer, NULL),
    COALESCE((p_datos->>'ancho')::numeric, NULL),
    COALESCE((p_datos->>'id_um_ancho')::integer, NULL),
    COALESCE((p_datos->>'espesor_profundidad')::numeric, NULL),
    COALESCE((p_datos->>'id_um_espesor')::integer, NULL),
    COALESCE((p_datos->>'superficie')::numeric, NULL),
    COALESCE((p_datos->>'id_um_superficie')::integer, NULL),
    COALESCE((p_datos->>'volumen')::numeric, NULL),
    COALESCE((p_datos->>'id_um_volumen')::integer, NULL),
    COALESCE((p_datos->>'activo')::boolean, TRUE),
    COALESCE((p_datos->>'id_um_compra')::integer, NULL),
    COALESCE((p_datos->>'id_um_uso')::integer, NULL),
    COALESCE((p_datos->>'factor_conversion')::numeric, NULL)
  )
  RETURNING id INTO v_parte_id;

  -- Variante default (buildDefaultVariantData del PHP)
  v_codigo_variante := COALESCE(NULLIF(v_codigo, ''), 'BASE-' || v_parte_id::text);
  v_codigo_variante := left(v_codigo_variante, 50);

  INSERT INTO variantes (
    company_id, id_parte, codigo_variante, detalle, estado,
    lote_minimo, punto_pedido
  )
  VALUES (
    p_company_id, v_parte_id, v_codigo_variante,
    COALESCE(NULLIF(v_detalle, ''), 'Variante base'),
    'activa', 1, 0
  );

  SELECT to_jsonb(p) INTO v_parte FROM partes p WHERE p.id = v_parte_id;
  SELECT to_jsonb(v) INTO v_variante FROM variantes v WHERE v.id_parte = v_parte_id ORDER BY v.id LIMIT 1;

  RETURN jsonb_build_object('parte', v_parte, 'variante', v_variante);
END;
$$;

REVOKE EXECUTE ON FUNCTION public.crear_parte_con_variante(bigint, jsonb) FROM PUBLIC, anon;
GRANT EXECUTE ON FUNCTION public.crear_parte_con_variante(bigint, jsonb) TO authenticated, service_role;