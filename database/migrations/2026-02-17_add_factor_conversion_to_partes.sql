-- Add factor_conversion to partes table
ALTER TABLE partes
ADD COLUMN factor_conversion DECIMAL(15, 6) DEFAULT 1.0;

COMMENT ON COLUMN partes.factor_conversion IS 'Factor de conversión: 1 UM Compra = X UM Uso';
