-- Migración: Crear tabla tipos_depositos_movimientos
-- Fecha: 2026-02-15
-- Descripción: Tabla para configurar movimientos permitidos entre tipos de depósitos

CREATE TABLE IF NOT EXISTS tipos_depositos_movimientos (
    id SERIAL PRIMARY KEY,
    tipo_deposito_origen_id INTEGER NOT NULL,
    tipo_deposito_destino_id INTEGER NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign keys
    CONSTRAINT fk_tipo_origen FOREIGN KEY (tipo_deposito_origen_id)
        REFERENCES tipos_depositos(id) ON DELETE CASCADE,
    CONSTRAINT fk_tipo_destino FOREIGN KEY (tipo_deposito_destino_id)
        REFERENCES tipos_depositos(id) ON DELETE CASCADE,

    -- Constraint para evitar duplicados
    CONSTRAINT uq_origen_destino UNIQUE (tipo_deposito_origen_id, tipo_deposito_destino_id)
);

-- Crear índices para mejorar rendimiento
CREATE INDEX idx_tdm_origen ON tipos_depositos_movimientos(tipo_deposito_origen_id);
CREATE INDEX idx_tdm_destino ON tipos_depositos_movimientos(tipo_deposito_destino_id);
CREATE INDEX idx_tdm_activo ON tipos_depositos_movimientos(activo);

-- Insertar configuración por defecto (ejemplos comunes)
-- ALMACEN puede mover a PRODUCCION y PRE-PRODUCCION
INSERT INTO tipos_depositos_movimientos (tipo_deposito_origen_id, tipo_deposito_destino_id, observaciones, activo)
SELECT
    (SELECT id FROM tipos_depositos WHERE codigo = 'ALMACEN'),
    (SELECT id FROM tipos_depositos WHERE codigo = 'PRODUCCION'),
    'Movimiento estándar de materiales a producción',
    TRUE
WHERE EXISTS (SELECT 1 FROM tipos_depositos WHERE codigo = 'ALMACEN')
  AND EXISTS (SELECT 1 FROM tipos_depositos WHERE codigo = 'PRODUCCION');

INSERT INTO tipos_depositos_movimientos (tipo_deposito_origen_id, tipo_deposito_destino_id, observaciones, activo)
SELECT
    (SELECT id FROM tipos_depositos WHERE codigo = 'ALMACEN'),
    (SELECT id FROM tipos_depositos WHERE codigo = 'PRE-PRODUCCION'),
    'Movimiento de preparación para producción',
    TRUE
WHERE EXISTS (SELECT 1 FROM tipos_depositos WHERE codigo = 'ALMACEN')
  AND EXISTS (SELECT 1 FROM tipos_depositos WHERE codigo = 'PRE-PRODUCCION');

-- PRODUCCION puede mover a ALMACEN (productos terminados)
INSERT INTO tipos_depositos_movimientos (tipo_deposito_origen_id, tipo_deposito_destino_id, observaciones, activo)
SELECT
    (SELECT id FROM tipos_depositos WHERE codigo = 'PRODUCCION'),
    (SELECT id FROM tipos_depositos WHERE codigo = 'ALMACEN'),
    'Devolución de materiales o productos terminados',
    TRUE
WHERE EXISTS (SELECT 1 FROM tipos_depositos WHERE codigo = 'PRODUCCION')
  AND EXISTS (SELECT 1 FROM tipos_depositos WHERE codigo = 'ALMACEN');

-- ALMACEN puede mover a CLIENTE
INSERT INTO tipos_depositos_movimientos (tipo_deposito_origen_id, tipo_deposito_destino_id, observaciones, activo)
SELECT
    (SELECT id FROM tipos_depositos WHERE codigo = 'ALMACEN'),
    (SELECT id FROM tipos_depositos WHERE codigo = 'CLIENTE'),
    'Envío de productos a cliente',
    TRUE
WHERE EXISTS (SELECT 1 FROM tipos_depositos WHERE codigo = 'ALMACEN')
  AND EXISTS (SELECT 1 FROM tipos_depositos WHERE codigo = 'CLIENTE');

-- PROVEEDOR puede mover a ALMACEN
INSERT INTO tipos_depositos_movimientos (tipo_deposito_origen_id, tipo_deposito_destino_id, observaciones, activo)
SELECT
    (SELECT id FROM tipos_depositos WHERE codigo = 'PROVEEDOR'),
    (SELECT id FROM tipos_depositos WHERE codigo = 'ALMACEN'),
    'Recepción de materiales de proveedor',
    TRUE
WHERE EXISTS (SELECT 1 FROM tipos_depositos WHERE codigo = 'PROVEEDOR')
  AND EXISTS (SELECT 1 FROM tipos_depositos WHERE codigo = 'ALMACEN');

-- AJUSTE puede mover desde/hacia cualquier tipo (usado para correcciones)
INSERT INTO tipos_depositos_movimientos (tipo_deposito_origen_id, tipo_deposito_destino_id, observaciones, activo)
SELECT
    (SELECT id FROM tipos_depositos WHERE codigo = 'AJUSTE'),
    t.id,
    'Ajuste de inventario - entrada',
    TRUE
FROM tipos_depositos t
WHERE t.codigo != 'AJUSTE' AND t.activo = TRUE;

INSERT INTO tipos_depositos_movimientos (tipo_deposito_origen_id, tipo_deposito_destino_id, observaciones, activo)
SELECT
    t.id,
    (SELECT id FROM tipos_depositos WHERE codigo = 'AJUSTE'),
    'Ajuste de inventario - salida',
    TRUE
FROM tipos_depositos t
WHERE t.codigo != 'AJUSTE' AND t.activo = TRUE;

COMMENT ON TABLE tipos_depositos_movimientos IS 'Configuración de movimientos permitidos entre tipos de depósitos';
COMMENT ON COLUMN tipos_depositos_movimientos.tipo_deposito_origen_id IS 'Tipo de depósito de origen del movimiento';
COMMENT ON COLUMN tipos_depositos_movimientos.tipo_deposito_destino_id IS 'Tipo de depósito de destino del movimiento';
COMMENT ON COLUMN tipos_depositos_movimientos.activo IS 'Indica si el movimiento está habilitado';
