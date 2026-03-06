#!/bin/bash
# Script para ejecutar dentro del contenedor Docker de PostgreSQL
# Uso: Copiar y pegar en la terminal del contenedor

psql -U mrp -d tenant_demo << 'EOF'

-- Eliminar el trigger incorrecto
DROP TRIGGER IF EXISTS set_timestamp_variantes ON variantes;

-- Crear función específica para fecha_modificacion
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

SELECT 'Trigger corregido exitosamente' AS resultado;

EOF
