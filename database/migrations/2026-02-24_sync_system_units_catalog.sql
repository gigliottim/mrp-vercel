-- Sincroniza catálogo canónico de unidades de sistema protegidas

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

-- Asegurar unidades de tipo "unidad" requeridas para compras (idempotente)
UPDATE unidades_medida
SET simbolo = 'u', equivalencia_base = 1, activo = TRUE, is_system = TRUE, locked = TRUE
WHERE lower(tipo) = 'unidad' AND lower(unidad) = 'unidad';

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo, is_system, locked)
SELECT 'unidad', 'Unidad', 'u', 1.0, TRUE, TRUE, TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE lower(tipo) = 'unidad' AND lower(unidad) = 'unidad');

UPDATE unidades_medida
SET simbolo = 'caja', equivalencia_base = 1, activo = TRUE, is_system = TRUE, locked = TRUE
WHERE lower(tipo) = 'unidad' AND lower(unidad) = 'caja';

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo, is_system, locked)
SELECT 'unidad', 'Caja', 'caja', 1.0, FALSE, TRUE, TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE lower(tipo) = 'unidad' AND lower(unidad) = 'caja');

UPDATE unidades_medida
SET simbolo = 'rollo', equivalencia_base = 1, activo = TRUE, is_system = TRUE, locked = TRUE
WHERE lower(tipo) = 'unidad' AND lower(unidad) = 'rollo';

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo, is_system, locked)
SELECT 'unidad', 'Rollo', 'rollo', 1.0, FALSE, TRUE, TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE lower(tipo) = 'unidad' AND lower(unidad) = 'rollo');

UPDATE unidades_medida
SET simbolo = 'bobina', equivalencia_base = 1, activo = TRUE, is_system = TRUE, locked = TRUE
WHERE lower(tipo) = 'unidad' AND lower(unidad) = 'bobina';

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo, is_system, locked)
SELECT 'unidad', 'Bobina', 'bobina', 1.0, FALSE, TRUE, TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE lower(tipo) = 'unidad' AND lower(unidad) = 'bobina');

-- Asegurar metro lineal (idempotente)
UPDATE unidades_medida
SET unidad = 'Metro lineal', simbolo = 'mL', equivalencia_base = 1, activo = TRUE, is_system = TRUE, locked = TRUE
WHERE lower(tipo) = 'longitud' AND (lower(unidad) = 'metro lineal' OR lower(simbolo) = 'ml');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo, is_system, locked)
SELECT 'longitud', 'Metro lineal', 'mL', 1.0, FALSE, TRUE, TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE lower(tipo) = 'longitud' AND lower(unidad) = 'metro lineal');

-- Marcar canónicas como bloqueadas/sistema
UPDATE unidades_medida
SET is_system = TRUE,
    locked = TRUE
WHERE
    (lower(tipo) = 'longitud' AND (simbolo = 'mL' OR lower(simbolo) = 'm'))
    OR (lower(tipo) = 'superficie' AND simbolo = 'm²')
    OR (lower(tipo) = 'masa' AND lower(simbolo) = 'kg')
    OR (lower(tipo) = 'unidad' AND lower(simbolo) IN ('u', 'caja', 'rollo', 'bobina'));
