-- seed_company: siembra datos base para una empresa nueva
-- Datos extraídos de database/wizard/01_mrp_depositos.sql, 02_mrp_validaciones.sql, 03_mrp_um.sql
-- (verificados 2026-09-06): 6 tipos de depósito, 2 almacenes, 15 validaciones, 29 unidades.

CREATE OR REPLACE FUNCTION public.seed_company(p_company_id bigint)
RETURNS void
LANGUAGE plpgsql
AS $$
BEGIN
  -- ── Tipos de depósito (wizard 01) ──
  INSERT INTO public.tipos_depositos (company_id, codigo, nombre, descripcion, orden, es_sistema, activo)
  SELECT p_company_id, codigo, nombre, descripcion, orden, TRUE, TRUE
  FROM (VALUES
    ('ALMACEN', 'Almacen', 'Deposito principal de almacenamiento', 1),
    ('PRODUCCION', 'Produccion', 'Deposito de materiales en produccion', 2),
    ('PRE-PRODUCCION', 'Pre-Produccion', 'Deposito de preparacion para produccion', 3),
    ('PROVEEDOR', 'Proveedor', 'Deposito de proveedor', 4),
    ('CLIENTE', 'Cliente', 'Deposito en ubicacion de cliente', 5),
    ('AJUSTE', 'Ajuste', 'Deposito para ajustes de inventario', 6)
  ) AS t(codigo, nombre, descripcion, orden)
  ON CONFLICT DO NOTHING;

  -- ── Almacenes (wizard 01) ──
  INSERT INTO public.almacenes (company_id, codigo, nombre, es_deposito_venta, es_deposito_produccion, activo)
  SELECT p_company_id, codigo, nombre, es_deposito_venta, es_deposito_produccion, TRUE
  FROM (VALUES
    ('ALM-GRAL', 'Almacen General', TRUE, FALSE),
    ('PROD-LINEA', 'Produccion en Linea', FALSE, TRUE)
  ) AS a(codigo, nombre, es_deposito_venta, es_deposito_produccion)
  ON CONFLICT DO NOTHING;

  -- ── Validaciones origen→destino (wizard 02, 15 reglas) ──
  INSERT INTO public.tipos_depositos_movimientos (company_id, tipo_deposito_origen_id, tipo_deposito_destino_id, activo, observaciones)
  SELECT p_company_id, o.id, d.id, r.activo, r.observaciones
  FROM (VALUES
    ('AJUSTE', 'ALMACEN', TRUE, 'Ajuste de inventario - entrada'),
    ('AJUSTE', 'CLIENTE', TRUE, 'Ajuste de inventario - entrada'),
    ('AJUSTE', 'PRE-PRODUCCION', TRUE, 'Ajuste de inventario - entrada'),
    ('AJUSTE', 'PRODUCCION', TRUE, 'Ajuste de inventario - entrada'),
    ('AJUSTE', 'PROVEEDOR', TRUE, 'Ajuste de inventario - entrada'),
    ('ALMACEN', 'AJUSTE', TRUE, ''),
    ('ALMACEN', 'CLIENTE', TRUE, ''),
    ('ALMACEN', 'PRE-PRODUCCION', TRUE, ''),
    ('CLIENTE', 'ALMACEN', TRUE, ''),
    ('PRE-PRODUCCION', 'ALMACEN', TRUE, ''),
    ('PRE-PRODUCCION', 'PRODUCCION', TRUE, ''),
    ('PRODUCCION', 'AJUSTE', TRUE, ''),
    ('PRODUCCION', 'ALMACEN', TRUE, ''),
    ('PRODUCCION', 'PRE-PRODUCCION', TRUE, ''),
    ('PROVEEDOR', 'ALMACEN', TRUE, '')
  ) AS r(codigo_origen, codigo_destino, activo, observaciones)
  JOIN public.tipos_depositos o ON o.company_id = p_company_id AND o.codigo = r.codigo_origen
  JOIN public.tipos_depositos d ON d.company_id = p_company_id AND d.codigo = r.codigo_destino
  ON CONFLICT DO NOTHING;

  -- ── Unidades de medida (wizard 03, 29 unidades) ──
  INSERT INTO public.unidades_medida (company_id, tipo, unidad, simbolo, equivalencia_base, es_base, activo, is_system, locked)
  SELECT p_company_id, tipo, unidad, simbolo, equivalencia_base, es_base, TRUE, is_system, locked
  FROM (VALUES
    ('longitud', 'Centimetro', 'cm', 0.01000000, FALSE, FALSE, FALSE),
    ('longitud', 'Metro', 'm', 1.00000000, TRUE, TRUE, TRUE),
    ('longitud', 'Metro lineal', 'mL', 1.00000000, FALSE, TRUE, TRUE),
    ('longitud', 'Milimetro', 'mm', 0.00100000, FALSE, FALSE, FALSE),
    ('longitud', 'Pulgada', 'in', 0.02540000, FALSE, FALSE, FALSE),
    ('masa', 'Gramo', 'g', 0.00100000, FALSE, FALSE, FALSE),
    ('masa', 'Kilogramo', 'kg', 1.00000000, TRUE, TRUE, TRUE),
    ('masa', 'Libra', 'lb', 0.45359200, FALSE, FALSE, FALSE),
    ('masa', 'Onza', 'oz', 0.02834950, FALSE, FALSE, FALSE),
    ('masa', 'Tonelada metrica', 't', 1000.00000000, FALSE, FALSE, FALSE),
    ('superficie', 'Centimetro cuadrado', 'cm²', 0.00010000, FALSE, FALSE, FALSE),
    ('superficie', 'Metro cuadrado', 'm²', 1.00000000, TRUE, TRUE, TRUE),
    ('temperatura', 'Celsius', '°C', 1.00000000, TRUE, FALSE, FALSE),
    ('temperatura', 'Fahrenheit', '°F', 1.00000000, FALSE, FALSE, FALSE),
    ('temperatura', 'Kelvin', 'K', 1.00000000, FALSE, FALSE, FALSE),
    ('tiempo', 'Dia', 'd', 86400.00000000, FALSE, FALSE, FALSE),
    ('tiempo', 'Hora', 'h', 3600.00000000, FALSE, FALSE, FALSE),
    ('tiempo', 'Minuto', 'min', 60.00000000, FALSE, FALSE, FALSE),
    ('tiempo', 'Segundo', 's', 1.00000000, TRUE, FALSE, FALSE),
    ('tiempo', 'Semana', 'sem', 604800.00000000, FALSE, FALSE, FALSE),
    ('unidad', 'Bobina', 'bobina', 1.00000000, FALSE, TRUE, TRUE),
    ('unidad', 'Caja', 'caja', 1.00000000, FALSE, TRUE, TRUE),
    ('unidad', 'Rollo', 'rollo', 1.00000000, FALSE, TRUE, TRUE),
    ('unidad', 'Unidad', 'u', 1.00000000, TRUE, TRUE, TRUE),
    ('volumen', 'Centilitro', 'cl', 0.01000000, FALSE, FALSE, FALSE),
    ('volumen', 'Decilitro', 'dl', 0.10000000, FALSE, FALSE, FALSE),
    ('volumen', 'Litro', 'l', 1.00000000, TRUE, FALSE, FALSE),
    ('volumen', 'Metro cubico', 'm³', 1000.00000000, FALSE, FALSE, FALSE),
    ('volumen', 'Mililitro/Centimetro cubico', 'ml/cm³', 0.00100000, FALSE, FALSE, FALSE)
  ) AS u(tipo, unidad, simbolo, equivalencia_base, es_base, is_system, locked)
  ON CONFLICT DO NOTHING;
END;
$$;
