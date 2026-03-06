-- Migración: Corregir trigger de variantes
-- La tabla usa fecha_modificacion, no updated_at
-- Fecha: 2026-02-15

-- Eliminar el trigger incorrecto
DROP TRIGGER IF EXISTS set_timestamp_variantes ON variantes;

-- Crear función específica para fecha_modificacion si no existe
CREATE OR REPLACE FUNCTION update_fecha_modificacion_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.fecha_modificacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Crear nuevo trigger correcto
CREATE TRIGGER set_timestamp_variantes
    BEFORE UPDATE ON variantes
    FOR EACH ROW
    EXECUTE FUNCTION update_fecha_modificacion_column();

COMMENT ON TRIGGER set_timestamp_variantes ON variantes IS 'Actualiza automáticamente fecha_modificacion en cada UPDATE';
