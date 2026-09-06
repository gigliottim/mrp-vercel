-- Fix: movimiento_inventario — usuario_id es bigint (legacy), auth.uid() es uuid.
-- Se omite el campo (queda NULL); el auditor se resuelve en P4 con una
-- columna user_uuid uuid adicional.

CREATE OR REPLACE FUNCTION public.movimiento_inventario(
  p_company_id bigint,
  p_variante_id integer,
  p_almacen_id integer,
  p_orden_produccion_id integer,
  p_tipo_movimiento text,
  p_cantidad numeric,
  p_costo_unitario_snapshot numeric DEFAULT 0,
  p_observaciones text DEFAULT NULL,
  p_referencia_documento text DEFAULT NULL
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_id integer;
  v_result jsonb;
BEGIN
  -- Verificar que la variante existe en la empresa
  IF NOT EXISTS (SELECT 1 FROM variantes WHERE id = p_variante_id AND company_id = p_company_id) THEN
    RAISE EXCEPTION 'variante inexistente en esta empresa';
  END IF;

  INSERT INTO movimientos_inventario (
    company_id, variante_id, almacen_id, orden_produccion_id, tipo_movimiento,
    cantidad, signo, costo_unitario_snapshot, observaciones, referencia_documento
  )
  VALUES (
    p_company_id, p_variante_id, p_almacen_id, p_orden_produccion_id, p_tipo_movimiento,
    p_cantidad,
    CASE WHEN p_tipo_movimiento IN ('produccion_consumo', 'venta_despacho', 'transferencia_salida') THEN -1 ELSE 1 END,
    p_costo_unitario_snapshot, p_observaciones, p_referencia_documento
  )
  RETURNING id INTO v_id;

  -- El trigger actualizar_stock_trigger actualiza stock_actual automáticamente.

  SELECT jsonb_build_object('id', id, 'variante_id', variante_id, 'tipo_movimiento', tipo_movimiento, 'cantidad', cantidad, 'signo', signo)
  INTO v_result FROM movimientos_inventario WHERE id = v_id;
  RETURN v_result;
END;
$$;

GRANT EXECUTE ON FUNCTION public.movimiento_inventario(bigint, integer, integer, integer, text, numeric, numeric, text, text) TO authenticated;
