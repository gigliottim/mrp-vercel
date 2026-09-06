-- crear_bom: crea cabecera + detalles de BOM en una transacción atómica
-- (security definer: puede insertar en ambas tablas aunque RLS bloquee inserts
-- directos en bom_detalle sin company_id explícito)

CREATE OR REPLACE FUNCTION public.crear_bom(
  p_company_id bigint,
  p_variante_padre_id integer,
  p_version text,
  p_fecha_efectiva date,
  p_detalles jsonb
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_bom_id integer;
  v_detalle jsonb;
  v_result jsonb;
BEGIN
  -- Verificar que la variante padre existe en la empresa
  IF NOT EXISTS (SELECT 1 FROM variantes WHERE id = p_variante_padre_id AND company_id = p_company_id) THEN
    RAISE EXCEPTION 'variante padre inexistente en esta empresa';
  END IF;

  -- Crear cabecera
  INSERT INTO bom_cabecera (company_id, variante_padre_id, version, activa, fecha_efectiva)
  VALUES (p_company_id, p_variante_padre_id, p_version, TRUE, p_fecha_efectiva)
  RETURNING id INTO v_bom_id;

  -- Insertar detalles
  FOR v_detalle IN SELECT * FROM jsonb_array_elements(p_detalles)
  LOOP
    INSERT INTO bom_detalle (
      company_id, bom_id, variante_componente_id, cantidad_necesaria,
      unidad_medida_id, desperdicio_porcentaje, es_opcional, secuencia,
      costo_unitario_estimado, tiempo_setup_mins, tiempo_proceso_mins, observaciones
    )
    VALUES (
      p_company_id, v_bom_id,
      (v_detalle->>'variante_componente_id')::integer,
      (v_detalle->>'cantidad_necesaria')::numeric,
      (v_detalle->>'unidad_medida_id')::integer,
      COALESCE((v_detalle->>'desperdicio_porcentaje')::numeric, 0),
      COALESCE((v_detalle->>'es_opcional')::boolean, FALSE),
      COALESCE((v_detalle->>'secuencia')::integer, 1),
      COALESCE((v_detalle->>'costo_unitario_estimado')::numeric, 0),
      COALESCE((v_detalle->>'tiempo_setup_mins')::integer, 0),
      COALESCE((v_detalle->>'tiempo_proceso_mins')::integer, 0),
      (v_detalle->>'observaciones')
    );
  END LOOP;

  SELECT jsonb_build_object('id', id, 'variante_padre_id', variante_padre_id, 'version', version, 'activa', activa, 'fecha_efectiva', fecha_efectiva)
  INTO v_result FROM bom_cabecera WHERE id = v_bom_id;
  RETURN v_result;
END;
$$;

GRANT EXECUTE ON FUNCTION public.crear_bom(bigint, integer, text, date, jsonb) TO authenticated;
