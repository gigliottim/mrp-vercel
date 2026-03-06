-- Add costo to variantes if not exists
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'variantes' AND column_name = 'costo') THEN
        ALTER TABLE variantes ADD COLUMN costo DECIMAL(15, 2) DEFAULT 0;
    END IF;
END $$;

-- Create compras table
CREATE TABLE IF NOT EXISTS compras (
    id SERIAL PRIMARY KEY,
    id_variante INT NOT NULL,
    fecha DATE NOT NULL,
    precio_unitario DECIMAL(15, 2) NOT NULL,
    cantidad DECIMAL(15, 2) NOT NULL DEFAULT 1,
    proveedor VARCHAR(255) NULL,
    observaciones TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_compras_variante FOREIGN KEY (id_variante) REFERENCES variantes(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_compras_variante_fecha ON compras(id_variante, fecha DESC);
