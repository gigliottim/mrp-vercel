-- Wizard SQL 02 - Validaciones de movimientos entre tipos de depositos
-- Generado desde: mrp_tunna
-- Fecha: 2026-03-23 15:45:05

WITH reglas AS (
    SELECT
        o.id AS origen_id,
        d.id AS destino_id,
        r.activo,
        r.observaciones
    FROM (
        VALUES            ('AJUSTE', 'ALMACEN', TRUE, 'Ajuste de inventario - entrada'),
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
    JOIN tipos_depositos o ON o.codigo = r.codigo_origen
    JOIN tipos_depositos d ON d.codigo = r.codigo_destino
)
INSERT INTO tipos_depositos_movimientos
    (tipo_deposito_origen_id, tipo_deposito_destino_id, activo, observaciones, created_at, updated_at)
SELECT
    origen_id,
    destino_id,
    activo,
    observaciones,
    NOW(),
    NOW()
FROM reglas
ON CONFLICT (tipo_deposito_origen_id, tipo_deposito_destino_id)
DO UPDATE SET
    activo = EXCLUDED.activo,
    observaciones = EXCLUDED.observaciones,
    updated_at = NOW();