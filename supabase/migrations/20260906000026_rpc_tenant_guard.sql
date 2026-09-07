-- rpc_tenant_guard: cierra el bypass cross-tenant de RPCs con p_company_id
--
-- Problema: los RPCs SECURITY DEFINER toman p_company_id arbitrario de
-- CUALQUIER caller autenticado vía PostgREST directo (POST /rest/v1/rpc/...),
-- operando sobre datos de otras empresas (SECURITY DEFINER salta RLS). Con el
-- registro público (E1) cualquiera obtiene un JWT authenticated y puede
-- atacar/leer otros tenants. Postgres además otorga EXECUTE a PUBLIC por
-- default: incluso anon podía invocarlas.
--
-- Fix:
--   1. seed_company: REVOKE EXECUTE a PUBLIC/anon/authenticated. Solo la API
--      la llama vía service_role (server-side), que conserva su GRANT
--      explícito de default privileges (verificado en proacl).
--   2. registrar_movimiento_partes, movimiento_inventario, recibir_compra,
--      verificar_solapamiento, crear_bom y next_numero_orden: guard de claim
--      al inicio del body — p_company_id debe coincidir con el company_id del
--      JWT (auth.jwt()). Cuerpos verbatim de las migraciones 12/16/17/19-22/24
--      (coincidencia byte a byte con pg_proc verificada antes de escribir esta
--      migración).
--      NOTA fail-closed: se usa IS DISTINCT FROM en lugar de <>. Con claim
--      ausente (NULL), `NULL <> x` evalúa NULL → IF falso → dejaría pasar;
--      IS DISTINCT FROM con NULL es TRUE → siempre lanza la excepción.
--      bom_tree / bom_where_used / bom_validate_add NO requieren guard: no
--      toman p_company_id y son SECURITY INVOKER — el RLS del caller
--      (*_tenant_select, company_id = auth claim) ya filtra cross-tenant.
--   3. register_rate_limit + check_register_rate(p_ip): contador persistente
--      del endpoint público de registro (en Vercel serverless la memoria por
--      instancia no es fiable). Ventana de 1 minuto, límite 3 (4º intento →
--      FALSE). Upsert atómico en SQL (1 roundtrip, sin race check-then-insert).
--      Sin RLS: inaccesible vía API porque solo service_role tiene GRANTs.

-- ── 1. seed_company: solo service_role ──────────────────────────────────────
REVOKE EXECUTE ON FUNCTION public.seed_company(bigint) FROM PUBLIC, anon, authenticated;

-- ── 2. Guard de tenant en RPCs SECURITY DEFINER ─────────────────────────────

-- registrar_movimiento_partes (verbatim de 20260906000024 + guard)
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
  -- Tenant guard: el company_id del JWT debe coincidir con el parámetro
  -- (fail-closed: claim ausente → NULL → IS DISTINCT FROM → excepción)
  IF p_company_id IS DISTINCT FROM (auth.jwt() ->> 'company_id')::bigint THEN
    RAISE EXCEPTION 'company_id no coincide con la sesión';
  END IF;

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

-- movimiento_inventario (verbatim de 20260906000020 + guard)
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
  -- Tenant guard: el company_id del JWT debe coincidir con el parámetro
  -- (fail-closed: claim ausente → NULL → IS DISTINCT FROM → excepción)
  IF p_company_id IS DISTINCT FROM (auth.jwt() ->> 'company_id')::bigint THEN
    RAISE EXCEPTION 'company_id no coincide con la sesión';
  END IF;

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

-- recibir_compra (verbatim de 20260906000021 + guard)
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
  -- Tenant guard: el company_id del JWT debe coincidir con el parámetro
  -- (fail-closed: claim ausente → NULL → IS DISTINCT FROM → excepción)
  IF p_company_id IS DISTINCT FROM (auth.jwt() ->> 'company_id')::bigint THEN
    RAISE EXCEPTION 'company_id no coincide con la sesión';
  END IF;

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

-- verificar_solapamiento (verbatim de 20260906000022 + guard)
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
  -- Tenant guard: el company_id del JWT debe coincidir con el parámetro
  -- (fail-closed: claim ausente → NULL → IS DISTINCT FROM → excepción)
  IF p_company_id IS DISTINCT FROM (auth.jwt() ->> 'company_id')::bigint THEN
    RAISE EXCEPTION 'company_id no coincide con la sesión';
  END IF;

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

-- crear_bom (verbatim de 20260906000016 + guard)
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
  -- Tenant guard: el company_id del JWT debe coincidir con el parámetro
  -- (fail-closed: claim ausente → NULL → IS DISTINCT FROM → excepción)
  IF p_company_id IS DISTINCT FROM (auth.jwt() ->> 'company_id')::bigint THEN
    RAISE EXCEPTION 'company_id no coincide con la sesión';
  END IF;

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

-- next_numero_orden (verbatim de 20260906000017 + guard): SECURITY DEFINER con
-- p_company_id arbitrario — mismo vector de ataque (quema la secuencia ajena y
-- filtra el formato de numeración de otro tenant)
CREATE OR REPLACE FUNCTION public.next_numero_orden(p_company_id bigint)
RETURNS text
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_seq bigint;
BEGIN
  -- Tenant guard: el company_id del JWT debe coincidir con el parámetro
  -- (fail-closed: claim ausente → NULL → IS DISTINCT FROM → excepción)
  IF p_company_id IS DISTINCT FROM (auth.jwt() ->> 'company_id')::bigint THEN
    RAISE EXCEPTION 'company_id no coincide con la sesión';
  END IF;

  SELECT nextval('public.orden_num_seq') INTO v_seq;
  RETURN 'OP-' || to_char(CURRENT_DATE, 'YYYYMMDD') || '-' || p_company_id || '-' || lpad(v_seq::text, 4, '0');
END;
$$;

-- ACLs: CREATE OR REPLACE conserva los GRANTs previos (incluido PUBLIC execute
-- de los default privileges). Dejar solo lo necesario: authenticated (JWT con
-- claim, llamado siempre con c.get('companyId')) y service_role (backend).
REVOKE EXECUTE ON FUNCTION public.registrar_movimiento_partes(bigint, integer, numeric, integer, integer, text, integer, numeric, timestamptz, text, text) FROM PUBLIC, anon;
REVOKE EXECUTE ON FUNCTION public.movimiento_inventario(bigint, integer, integer, integer, text, numeric, numeric, text, text) FROM PUBLIC, anon;
REVOKE EXECUTE ON FUNCTION public.recibir_compra(bigint, date, numeric, integer, text, integer, numeric, text) FROM PUBLIC, anon;
REVOKE EXECUTE ON FUNCTION public.verificar_solapamiento(bigint, integer, timestamp, timestamp, integer) FROM PUBLIC, anon;
REVOKE EXECUTE ON FUNCTION public.crear_bom(bigint, integer, text, date, jsonb) FROM PUBLIC, anon;
REVOKE EXECUTE ON FUNCTION public.next_numero_orden(bigint) FROM PUBLIC, anon;

GRANT EXECUTE ON FUNCTION public.registrar_movimiento_partes(bigint, integer, numeric, integer, integer, text, integer, numeric, timestamptz, text, text) TO authenticated, service_role;
GRANT EXECUTE ON FUNCTION public.movimiento_inventario(bigint, integer, integer, integer, text, numeric, numeric, text, text) TO authenticated, service_role;
GRANT EXECUTE ON FUNCTION public.recibir_compra(bigint, date, numeric, integer, text, integer, numeric, text) TO authenticated, service_role;
GRANT EXECUTE ON FUNCTION public.verificar_solapamiento(bigint, integer, timestamp, timestamp, integer) TO authenticated, service_role;
GRANT EXECUTE ON FUNCTION public.crear_bom(bigint, integer, text, date, jsonb) TO authenticated, service_role;
GRANT EXECUTE ON FUNCTION public.next_numero_orden(bigint) TO authenticated, service_role;

-- ── 3. Rate limit persistente del registro público ──────────────────────────
CREATE TABLE IF NOT EXISTS public.register_rate_limit (
  ip text PRIMARY KEY,
  window_start timestamptz NOT NULL DEFAULT now(),
  count integer NOT NULL DEFAULT 1
);

-- Sin RLS por diseño: solo service_role (backend server-side) la accede.
-- Los default privileges de postgres en public darían permisos a
-- anon/authenticated: se revocan explícitamente.
REVOKE ALL ON TABLE public.register_rate_limit FROM PUBLIC, anon, authenticated;
GRANT ALL ON TABLE public.register_rate_limit TO service_role;

-- check_register_rate(p_ip) → TRUE = permitido (count <= 3 en la ventana de 1
-- minuto). Upsert atómico: si window_start < now() - 1min resetea a 1, sino
-- incrementa. SECURITY DEFINER para escribir sin depender de grants del caller.
CREATE OR REPLACE FUNCTION public.check_register_rate(p_ip text)
RETURNS boolean
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_count integer;
BEGIN
  INSERT INTO register_rate_limit (ip, window_start, count)
  VALUES (p_ip, now(), 1)
  ON CONFLICT (ip) DO UPDATE
    SET window_start = CASE
          WHEN register_rate_limit.window_start < now() - interval '1 minute' THEN now()
          ELSE register_rate_limit.window_start
        END,
        count = CASE
          WHEN register_rate_limit.window_start < now() - interval '1 minute' THEN 1
          ELSE register_rate_limit.count + 1
        END
  RETURNING count INTO v_count;

  RETURN v_count <= 3;
END;
$$;

REVOKE EXECUTE ON FUNCTION public.check_register_rate(text) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.check_register_rate(text) TO service_role;
