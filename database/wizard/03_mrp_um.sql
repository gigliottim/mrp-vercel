-- Wizard SQL 03 - Unidades de medida iniciales
-- Generado desde: mrp_tunna
-- Fecha: 2026-03-23 15:45:20

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'unidades_medida' AND column_name = 'is_system'
    ) THEN
        ALTER TABLE unidades_medida ADD COLUMN is_system BOOLEAN NOT NULL DEFAULT FALSE;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'unidades_medida' AND column_name = 'locked'
    ) THEN
        ALTER TABLE unidades_medida ADD COLUMN locked BOOLEAN NOT NULL DEFAULT FALSE;
    END IF;
END $$;

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo, is_system, locked)
VALUES    ('longitud', 'Centimetro', 'cm', 0.01000000, FALSE, TRUE, FALSE, FALSE),
    ('longitud', 'Metro', 'm', 1.00000000, TRUE, TRUE, TRUE, TRUE),
    ('longitud', 'Metro lineal', 'mL', 1.00000000, FALSE, TRUE, TRUE, TRUE),
    ('longitud', 'Milimetro', 'mm', 0.00100000, FALSE, TRUE, FALSE, FALSE),
    ('longitud', 'Pulgada', 'in', 0.02540000, FALSE, TRUE, FALSE, FALSE),
    ('masa', 'Gramo', 'g', 0.00100000, FALSE, TRUE, FALSE, FALSE),
    ('masa', 'Kilogramo', 'kg', 1.00000000, TRUE, TRUE, TRUE, TRUE),
    ('masa', 'Libra', 'lb', 0.45359200, FALSE, TRUE, FALSE, FALSE),
    ('masa', 'Onza', 'oz', 0.02834950, FALSE, TRUE, FALSE, FALSE),
    ('masa', 'Tonelada metrica', 't', 1000.00000000, FALSE, TRUE, FALSE, FALSE),
    ('superficie', 'Centimetro cuadrado', 'cm²', 0.00010000, FALSE, TRUE, FALSE, FALSE),
    ('superficie', 'Metro cuadrado', 'm²', 1.00000000, TRUE, TRUE, TRUE, TRUE),
    ('temperatura', 'Celsius', '°C', 1.00000000, TRUE, TRUE, FALSE, FALSE),
    ('temperatura', 'Fahrenheit', '°F', 1.00000000, FALSE, TRUE, FALSE, FALSE),
    ('temperatura', 'Kelvin', 'K', 1.00000000, FALSE, TRUE, FALSE, FALSE),
    ('tiempo', 'Dia', 'd', 86400.00000000, FALSE, TRUE, FALSE, FALSE),
    ('tiempo', 'Hora', 'h', 3600.00000000, FALSE, TRUE, FALSE, FALSE),
    ('tiempo', 'Minuto', 'min', 60.00000000, FALSE, TRUE, FALSE, FALSE),
    ('tiempo', 'Segundo', 's', 1.00000000, TRUE, TRUE, FALSE, FALSE),
    ('tiempo', 'Semana', 'sem', 604800.00000000, FALSE, TRUE, FALSE, FALSE),
    ('unidad', 'Bobina', 'bobina', 1.00000000, FALSE, TRUE, TRUE, TRUE),
    ('unidad', 'Caja', 'caja', 1.00000000, FALSE, TRUE, TRUE, TRUE),
    ('unidad', 'Rollo', 'rollo', 1.00000000, FALSE, TRUE, TRUE, TRUE),
    ('unidad', 'Unidad', 'u', 1.00000000, TRUE, TRUE, TRUE, TRUE),
    ('volumen', 'Centilitro', 'cl', 0.01000000, FALSE, TRUE, FALSE, FALSE),
    ('volumen', 'Decilitro', 'dl', 0.10000000, FALSE, TRUE, FALSE, FALSE),
    ('volumen', 'Litro', 'l', 1.00000000, TRUE, TRUE, FALSE, FALSE),
    ('volumen', 'Metro cubico', 'm³', 1000.00000000, FALSE, TRUE, FALSE, FALSE),
    ('volumen', 'Mililitro/Centimetro cubico', 'ml/cm³', 0.00100000, FALSE, TRUE, FALSE, FALSE)
ON CONFLICT (simbolo)
DO UPDATE SET
    tipo = EXCLUDED.tipo,
    unidad = EXCLUDED.unidad,
    equivalencia_base = EXCLUDED.equivalencia_base,
    es_base = EXCLUDED.es_base,
    activo = EXCLUDED.activo,
    is_system = EXCLUDED.is_system,
    locked = EXCLUDED.locked;