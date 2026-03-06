-- Migración: Crear tabla tipos_depositos
-- Fecha: 2026-02-15
-- Descripción: Tabla de catálogo para tipos de depósito

CREATE TABLE IF NOT EXISTS tipos_depositos (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    orden INTEGER DEFAULT 0,
    es_sistema BOOLEAN DEFAULT FALSE,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Crear índices
CREATE INDEX idx_tipos_depositos_codigo ON tipos_depositos(codigo);
CREATE INDEX idx_tipos_depositos_activo ON tipos_depositos(activo);

-- Insertar tipos de depósito por defecto (inalterables)
INSERT INTO tipos_depositos (codigo, nombre, descripcion, orden, es_sistema, activo) VALUES
    ('ALMACEN', 'Almacén', 'Depósito principal de almacenamiento', 1, TRUE, TRUE),
    ('PRODUCCION', 'Producción', 'Depósito de materiales en producción', 2, TRUE, TRUE),
    ('PRE-PRODUCCION', 'Pre-Producción', 'Depósito de materiales previo a producción', 3, TRUE, TRUE),
    ('PROVEEDOR', 'Proveedor', 'Depósito en ubicación del proveedor', 4, TRUE, TRUE),
    ('CLIENTE', 'Cliente', 'Depósito en ubicación del cliente', 5, TRUE, TRUE),
    ('AJUSTE', 'Ajuste', 'Depósito para ajustes de inventario', 6, TRUE, TRUE);

COMMENT ON TABLE tipos_depositos IS 'Catálogo de tipos de depósito del sistema';
COMMENT ON COLUMN tipos_depositos.es_sistema IS 'Indica si el tipo es del sistema y no puede ser eliminado';
