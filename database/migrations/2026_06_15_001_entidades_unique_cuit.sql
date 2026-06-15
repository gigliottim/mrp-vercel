-- Add partial unique index on entidades.identificacion_tributaria
-- Only enforces uniqueness for non-null, non-empty values
-- This prevents duplicate CUIT/CUIL across all entity types

CREATE UNIQUE INDEX IF NOT EXISTS entidades_identificacion_tributaria_unique_idx
ON entidades (identificacion_tributaria)
WHERE identificacion_tributaria IS NOT NULL
  AND identificacion_tributaria <> '';