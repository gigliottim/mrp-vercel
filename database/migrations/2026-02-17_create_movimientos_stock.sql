-- Migración: Crear tabla movimientos_stock
-- Fecha: 2026-02-17
-- Descripción: Tabla para registrar el historial de movimientos de inventario por depósito

CREATE TABLE IF NOT EXISTS movimientos_stock (
    id SERIAL PRIMARY KEY,
    id_variante INTEGER NOT NULL,
    cantidad DECIMAL(15, 6) NOT NULL,
    id_tipo_deposito_origen INTEGER NOT NULL,
    id_tipo_deposito_destino INTEGER NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    referencia_tipo VARCHAR(50) NOT NULL, -- 'compra', 'venta', 'produccion', 'ajuste'
    referencia_id INTEGER NOT NULL,
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mov_variante FOREIGN KEY (id_variante)
        REFERENCES variantes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mov_origen FOREIGN KEY (id_tipo_deposito_origen)
        REFERENCES tipos_depositos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mov_destino FOREIGN KEY (id_tipo_deposito_destino)
        REFERENCES tipos_depositos(id) ON DELETE RESTRICT
);

CREATE INDEX idx_mov_variante ON movimientos_stock(id_variante);
CREATE INDEX idx_mov_origen ON movimientos_stock(id_tipo_deposito_origen);
CREATE INDEX idx_mov_destino ON movimientos_stock(id_tipo_deposito_destino);
CREATE INDEX idx_mov_fecha ON movimientos_stock(fecha);
CREATE INDEX idx_mov_referencia ON movimientos_stock(referencia_tipo, referencia_id);

COMMENT ON TABLE movimientos_stock IS 'Historial de movimientos de stock entre depósitos (tipos)';
COMMENT ON COLUMN movimientos_stock.cantidad IS 'Cantidad movida en Unidad de Uso de la variante';
