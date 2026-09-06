-- registrar_movimiento_partes: port de MovimientosPartesController::store (PHP)
-- 1. Valida matriz tipos_depositos_movimientos (origen→destino activo)
-- 2. Rechaza stock negativo en origen (salvo AJUSTE y PROVEEDOR)
-- 3. Compra (origen PROVEEDOR): exige entidad + importe, aplica factor_conversion,
--    crea registro satélite en compras
-- 4. Escribe movimientos_inventario (trigger actualiza stock_actual) + movimientos_stock

CREATE OR REPLACE FUNCTION public.registrar_movimiento_partes(
  p_company_id bigint,
  p_variante_id integer,
  p_cantidad numeric,
  p_tipo_deposito_origen_id integer,
  p_tipo_deposito_destino_id integer,
  p_tipo_movimiento text,
  p_entidad_id integer DEFAULT NULL,
  p_importe_total numeric DEFAULT NULL,
  p_fecha timestamptz DEFAULT NULL,
  p_nro_comprobante text DEFAULT NULL,
  p_observaciones text DEFAULT NULL
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_codigo_origen text;
  v_codigo_destino text;
  v_stock_origen numeric;
  v_factor numeric;
  v_cantidad_uso numeric;
  v_es_compra boolean;
  v_compra_id integer;
  v_mov_inv_id integer;
  v_mov_stock_id integer;
  v_costo_unitario numeric;
BEGIN
  IF p_cantidad IS NULL OR p_cantidad <= 0 THEN
    RAISE EXCEPTION 'La cantidad debe ser positiva';
  END IF;

  IF p_fecha IS NOT NULL AND p_fecha > now() THEN
    RAISE EXCEPTION 'La fecha no puede ser futura';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM variantes WHERE id = p_variante_id AND company_id = p_company_id) THEN
    RAISE EXCEPTION 'variante inexistente en esta empresa';
  END IF;

  SELECT codigo INTO v_codigo_origen FROM tipos_depositos
    WHERE id = p_tipo_deposito_origen_id AND company_id = p_company_id;
  SELECT codigo INTO v_codigo_destino FROM tipos_depositos
    WHERE id = p_tipo_deposito_destino_id AND company_id = p_company_id;
  IF v_codigo_origen IS NULL OR v_codigo_destino IS NULL THEN
    RAISE EXCEPTION 'depósito origen/destino inexistente';
  END IF;

  -- 1. Matriz de movimientos permitidos
  IF NOT EXISTS (
    SELECT 1 FROM tipos_depositos_movimientos
    WHERE company_id = p_company_id
      AND tipo_deposito_origen_id = p_tipo_deposito_origen_id
      AND tipo_deposito_destino_id = p_tipo_deposito_destino_id
      AND activo
  ) THEN
    RAISE EXCEPTION 'movimiento no permitido entre depósitos % → %', v_codigo_origen, v_codigo_destino;
  END IF;

  -- 2. Stock negativo: solo AJUSTE y PROVEEDOR permiten
  IF v_codigo_origen NOT IN ('AJUSTE', 'PROVEEDOR') THEN
    SELECT COALESCE((
      SELECT SUM(cantidad) FROM movimientos_stock
      WHERE company_id = p_company_id AND id_variante = p_variante_id
        AND id_tipo_deposito_destino = p_tipo_deposito_origen_id
    ), 0) - COALESCE((
      SELECT SUM(cantidad) FROM movimientos_stock
      WHERE company_id = p_company_id AND id_variante = p_variante_id
        AND id_tipo_deposito_origen = p_tipo_deposito_origen_id
    ), 0)
    INTO v_stock_origen;
    IF (v_stock_origen - p_cantidad) < 0 THEN
      RAISE EXCEPTION 'stock insuficiente en origen (% disponible)', v_stock_origen;
    END IF;
  END IF;

  -- 3. Compra desde PROVEEDOR
  v_es_compra := v_codigo_origen = 'PROVEEDOR';
  v_factor := 1;
  v_cantidad_uso := p_cantidad;
  v_compra_id := NULL;

  IF v_es_compra THEN
    IF p_entidad_id IS NULL THEN
      RAISE EXCEPTION 'Debe seleccionar una entidad para movimientos desde PROVEEDOR';
    END IF;
    IF p_importe_total IS NULL OR p_importe_total <= 0 THEN
      RAISE EXCEPTION 'Debe ingresar el Importe Total en compras';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM entidades WHERE id = p_entidad_id AND company_id = p_company_id) THEN
      RAISE EXCEPTION 'entidad inexistente en esta empresa';
    END IF;
    SELECT COALESCE(NULLIF(factor_conversion, 0), 1) INTO v_factor
      FROM partes WHERE id = (SELECT id_parte FROM variantes WHERE id = p_variante_id);
    v_cantidad_uso := round(p_cantidad * v_factor, 6);
  END IF;

  v_costo_unitario := CASE
    WHEN v_es_compra THEN round((p_importe_total / p_cantidad) / v_factor, 4)
    ELSE 0 END;

  -- 4a. movimientos_inventario (trigger actualiza stock_actual)
  INSERT INTO movimientos_inventario (
    company_id, variante_id, tipo_movimiento, cantidad, signo,
    costo_unitario_snapshot, fecha_movimiento, observaciones, referencia_documento
  ) VALUES (
    p_company_id, p_variante_id, p_tipo_movimiento, v_cantidad_uso,
    CASE WHEN p_tipo_movimiento IN ('produccion_consumo','venta_despacho','transferencia_salida') THEN -1 ELSE 1 END,
    v_costo_unitario, COALESCE(p_fecha, now()), p_observaciones, p_nro_comprobante
  ) RETURNING id INTO v_mov_inv_id;

  -- 4b. Compra satélite
  IF v_es_compra THEN
    INSERT INTO compras (company_id, fecha, precio_unitario, id_entidad, nro_comprobante, observaciones, id_movimiento_stock)
    VALUES (p_company_id, COALESCE(p_fecha::date, CURRENT_DATE), v_costo_unitario, p_entidad_id, p_nro_comprobante, p_observaciones, NULL)
    RETURNING id INTO v_compra_id;
  END IF;

  -- 4c. movimientos_stock (stock por depósito, como en PHP)
  INSERT INTO movimientos_stock (
    company_id, id_variante, cantidad, id_tipo_deposito_origen, id_tipo_deposito_destino,
    referencia_tipo, referencia_id, fecha, observaciones
  ) VALUES (
    p_company_id, p_variante_id, v_cantidad_uso, p_tipo_deposito_origen_id, p_tipo_deposito_destino_id,
    CASE WHEN v_es_compra THEN 'compra_satelite' ELSE 'interno' END,
    COALESCE(v_compra_id, 0), COALESCE(p_fecha, now()), p_observaciones
  ) RETURNING id INTO v_mov_stock_id;

  IF v_compra_id IS NOT NULL THEN
    UPDATE compras SET id_movimiento_stock = v_mov_stock_id WHERE id = v_compra_id;
  END IF;

  RETURN jsonb_build_object(
    'movimiento_inventario_id', v_mov_inv_id,
    'movimiento_stock_id', v_mov_stock_id,
    'compra_id', v_compra_id,
    'cantidad_uso', v_cantidad_uso,
    'factor_conversion', v_factor
  );
END;
$$;

GRANT EXECUTE ON FUNCTION public.registrar_movimiento_partes(bigint, integer, numeric, integer, integer, text, integer, numeric, timestamptz, text, text) TO authenticated;
