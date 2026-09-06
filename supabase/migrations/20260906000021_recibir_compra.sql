-- recibir_compra: crea compra + recepción (movimiento inventario + stock) atómicamente

CREATE OR REPLACE FUNCTION public.recibir_compra(
  p_company_id bigint,
  p_fecha date,
  p_precio_unitario numeric,
  p_id_entidad integer,
  p_nro_comprobante text,
  p_variante_id integer,
  p_cantidad numeric,
  p_observaciones text DEFAULT NULL
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_compra_id integer;
  v_dep_origen integer;
  v_dep_destino integer;
  v_result jsonb;
BEGIN
  -- Verificar entidad y variante en la empresa
  IF NOT EXISTS (SELECT 1 FROM entidades WHERE id = p_id_entidad AND company_id = p_company_id) THEN
    RAISE EXCEPTION 'entidad inexistente en esta empresa';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM variantes WHERE id = p_variante_id AND company_id = p_company_id) THEN
    RAISE EXCEPTION 'variante inexistente en esta empresa';
  END IF;

  INSERT INTO compras (company_id, fecha, precio_unitario, id_entidad, nro_comprobante, observaciones)
  VALUES (p_company_id, p_fecha, p_precio_unitario, p_id_entidad, p_nro_comprobante, p_observaciones)
  RETURNING id INTO v_compra_id;

  -- Recepción: movimiento de inventario (el trigger actualiza stock_actual)
  PERFORM public.movimiento_inventario(
    p_company_id, p_variante_id, NULL, NULL,
    'compra_recepcion', p_cantidad, p_precio_unitario, p_observaciones, p_nro_comprobante
  );

  -- Registro en movimientos_stock (PROVEEDOR -> ALMACEN)
  SELECT id INTO v_dep_origen FROM tipos_depositos WHERE company_id = p_company_id AND codigo = 'PROVEEDOR' LIMIT 1;
  SELECT id INTO v_dep_destino FROM tipos_depositos WHERE company_id = p_company_id AND codigo = 'ALMACEN' LIMIT 1;

  INSERT INTO movimientos_stock (company_id, id_variante, cantidad, id_tipo_deposito_origen, id_tipo_deposito_destino, referencia_tipo, referencia_id, observaciones)
  VALUES (p_company_id, p_variante_id, p_cantidad, v_dep_origen, v_dep_destino, 'compra', v_compra_id, p_observaciones);

  SELECT jsonb_build_object('id', id, 'fecha', fecha, 'precio_unitario', precio_unitario, 'nro_comprobante', nro_comprobante)
  INTO v_result FROM compras WHERE id = v_compra_id;
  RETURN v_result;
END;
$$;

GRANT EXECUTE ON FUNCTION public.recibir_compra(bigint, date, numeric, integer, text, integer, numeric, text) TO authenticated;
