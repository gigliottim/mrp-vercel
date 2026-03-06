-- Añadir campos de ubicación a la tabla variantes
ALTER TABLE variantes
    ADD COLUMN IF NOT EXISTS ubicacion_cuerpo VARCHAR(100),
    ADD COLUMN IF NOT EXISTS ubicacion_pasillo VARCHAR(100),
    ADD COLUMN IF NOT EXISTS ubicacion_estante VARCHAR(100);
