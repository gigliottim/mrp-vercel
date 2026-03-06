-- Catálogo mixto de unidades: sistema protegidas + custom de negocio

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

-- Tipo unidad: base y unidades de compra frecuentes
INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo, is_system, locked)
SELECT 'unidad', 'Unidad', 'u', 1.0, TRUE, TRUE, TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'unidad' AND simbolo = 'u');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo, is_system, locked)
SELECT 'unidad', 'Caja', 'caja', 1.0, FALSE, TRUE, TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'unidad' AND simbolo = 'caja');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo, is_system, locked)
SELECT 'unidad', 'Rollo', 'rollo', 1.0, FALSE, TRUE, TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'unidad' AND simbolo = 'rollo');

-- Metro lineal para compra lineal de materiales
INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo, is_system, locked)
SELECT 'longitud', 'Metro lineal', 'mL', 1.0, FALSE, TRUE, TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'longitud' AND simbolo = 'mL');

-- Marcar unidades canónicas como sistema/protegidas
UPDATE unidades_medida
SET is_system = TRUE,
    locked = TRUE
WHERE
    (tipo = 'longitud' AND simbolo IN ('m', 'mL'))
    OR (tipo = 'superficie' AND simbolo = 'm²')
    OR (tipo = 'masa' AND simbolo = 'kg')
    OR (tipo = 'unidad' AND simbolo IN ('u', 'caja', 'rollo'));
