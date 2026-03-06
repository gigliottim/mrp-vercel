-- Wizard SQL 01 - Depositos iniciales

INSERT INTO tipos_depositos (codigo, nombre, descripcion, orden, es_sistema, activo, created_at, updated_at)
VALUES
    ('ALMACEN', 'Almacen', 'Deposito principal de almacenamiento', 1, TRUE, TRUE, NOW(), NOW()),
    ('PRODUCCION', 'Produccion', 'Deposito de materiales en produccion', 2, TRUE, TRUE, NOW(), NOW()),
    ('PRE-PRODUCCION', 'Pre-Produccion', 'Deposito de preparacion para produccion', 3, TRUE, TRUE, NOW(), NOW()),
    ('PROVEEDOR', 'Proveedor', 'Deposito de proveedor', 4, TRUE, TRUE, NOW(), NOW()),
    ('CLIENTE', 'Cliente', 'Deposito en ubicacion de cliente', 5, TRUE, TRUE, NOW(), NOW()),
    ('AJUSTE', 'Ajuste', 'Deposito para ajustes de inventario', 6, TRUE, TRUE, NOW(), NOW())
ON CONFLICT (codigo)
DO UPDATE SET
    nombre = EXCLUDED.nombre,
    descripcion = EXCLUDED.descripcion,
    orden = EXCLUDED.orden,
    es_sistema = EXCLUDED.es_sistema,
    activo = EXCLUDED.activo,
    updated_at = NOW();

INSERT INTO almacenes (codigo, nombre, es_deposito_venta, es_deposito_produccion, activo)
SELECT 'ALM-GRAL', 'Almacen General', TRUE, FALSE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM almacenes WHERE codigo = 'ALM-GRAL');

INSERT INTO almacenes (codigo, nombre, es_deposito_venta, es_deposito_produccion, activo)
SELECT 'PROD-LINEA', 'Produccion en Linea', FALSE, TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM almacenes WHERE codigo = 'PROD-LINEA');
