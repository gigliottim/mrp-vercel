-- Wizard SQL 02 - Validaciones de movimientos entre tipos de depositos

WITH reglas AS (
    SELECT
        o.id AS origen_id,
        d.id AS destino_id,
        r.observaciones
    FROM (
        VALUES
            ('ALMACEN', 'PRODUCCION', 'Movimiento estandar de materiales a produccion'),
            ('ALMACEN', 'PRE-PRODUCCION', 'Movimiento de preparacion para produccion'),
            ('PRODUCCION', 'ALMACEN', 'Retorno de material o producto terminado'),
            ('ALMACEN', 'CLIENTE', 'Salida de mercaderia a cliente'),
            ('PROVEEDOR', 'ALMACEN', 'Ingreso de mercaderia desde proveedor'),
            ('AJUSTE', 'ALMACEN', 'Ajuste de inventario - entrada'),
            ('AJUSTE', 'PRODUCCION', 'Ajuste de inventario - entrada'),
            ('AJUSTE', 'PRE-PRODUCCION', 'Ajuste de inventario - entrada'),
            ('AJUSTE', 'PROVEEDOR', 'Ajuste de inventario - entrada'),
            ('AJUSTE', 'CLIENTE', 'Ajuste de inventario - entrada'),
            ('ALMACEN', 'AJUSTE', 'Ajuste de inventario - salida'),
            ('PRODUCCION', 'AJUSTE', 'Ajuste de inventario - salida'),
            ('PRE-PRODUCCION', 'AJUSTE', 'Ajuste de inventario - salida'),
            ('PROVEEDOR', 'AJUSTE', 'Ajuste de inventario - salida'),
            ('CLIENTE', 'AJUSTE', 'Ajuste de inventario - salida')
    ) AS r(codigo_origen, codigo_destino, observaciones)
    JOIN tipos_depositos o ON o.codigo = r.codigo_origen
    JOIN tipos_depositos d ON d.codigo = r.codigo_destino
)
INSERT INTO tipos_depositos_movimientos
    (tipo_deposito_origen_id, tipo_deposito_destino_id, activo, observaciones, created_at, updated_at)
SELECT
    origen_id,
    destino_id,
    TRUE,
    observaciones,
    NOW(),
    NOW()
FROM reglas
ON CONFLICT (tipo_deposito_origen_id, tipo_deposito_destino_id)
DO UPDATE SET
    activo = EXCLUDED.activo,
    observaciones = EXCLUDED.observaciones,
    updated_at = NOW();
