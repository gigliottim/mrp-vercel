-- Agregar campos de timestamp a la tabla partes
-- Fecha: 2026-02-15

-- Agregar created_at si no existe
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'partes'
        AND column_name = 'created_at'
    ) THEN
        ALTER TABLE partes
        ADD COLUMN created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP;
    END IF;
END $$;

-- Agregar updated_at si no existe
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'partes'
        AND column_name = 'updated_at'
    ) THEN
        ALTER TABLE partes
        ADD COLUMN updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP;
    END IF;
END $$;

-- Crear trigger para actualizar updated_at automáticamente si no existe
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_trigger
        WHERE tgname = 'update_partes_updated_at'
    ) THEN
        CREATE TRIGGER update_partes_updated_at
            BEFORE UPDATE ON partes
            FOR EACH ROW
            EXECUTE FUNCTION update_updated_at_column();
    END IF;
END $$;

-- Inicializar valores para registros existentes
UPDATE partes
SET created_at = COALESCE(created_at, CURRENT_TIMESTAMP),
    updated_at = COALESCE(updated_at, CURRENT_TIMESTAMP)
WHERE created_at IS NULL OR updated_at IS NULL;
