-- 1. Crear tabla entidades
CREATE TABLE IF NOT EXISTS entidades (
    id SERIAL PRIMARY KEY,
    razon_social VARCHAR(255) NOT NULL,
    tipo VARCHAR(20) CHECK (tipo IN ('PROVEEDOR', 'CLIENTE', 'AMBOS')) DEFAULT 'PROVEEDOR',
    identificacion_tributaria VARCHAR(50),
    contacto_email VARCHAR(255),
    contacto_telefono VARCHAR(50),
    direccion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Migrar proveedores existentes de la tabla compras
INSERT INTO entidades (razon_social, tipo)
SELECT DISTINCT proveedor, 'PROVEEDOR'
FROM compras
WHERE proveedor IS NOT NULL AND proveedor != '';

-- 3. Asegurar estructura de movimientos_stock
CREATE TABLE IF NOT EXISTS movimientos_stock (
    id SERIAL PRIMARY KEY,
    id_variante INTEGER,
    cantidad DECIMAL(15, 6) NOT NULL,
    id_tipo_deposito_origen INTEGER NOT NULL,
    id_tipo_deposito_destino INTEGER NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    referencia_tipo VARCHAR(50) NOT NULL,
    referencia_id INTEGER NOT NULL,
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    -- Foreign keys added later or assumed implicit if circular deps are tricky during migration
);

-- Si la tabla ya existía, asegurarse de tener las FKs
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'fk_mov_variante') THEN
        ALTER TABLE movimientos_stock ADD CONSTRAINT fk_mov_variante FOREIGN KEY (id_variante) REFERENCES variantes(id) ON DELETE RESTRICT;
    END IF;
    -- Verificar otras FK si necesario
END $$;


-- 4. Alterar tabla compras para convertirla en satélite
-- Primero, agregamos las columnas nuevas
ALTER TABLE compras ADD COLUMN IF NOT EXISTS id_movimiento_stock INT;
ALTER TABLE compras ADD COLUMN IF NOT EXISTS id_entidad INT;
ALTER TABLE compras ADD COLUMN IF NOT EXISTS nro_comprobante VARCHAR(50);
-- precio_unitario ya existe
-- observaciones ya existe

-- 5. Data Migration: Vincular compras existentes con entidades
UPDATE compras c
SET id_entidad = e.id
FROM entidades e
WHERE c.proveedor = e.razon_social;

-- 6. Data Migration: Generar movimientos_stock para compras históricas que no tengan movimiento
-- Insertamos en movimientos_stock basado en compras que aún no tienen id_movimiento_stock vinculado (o referencia externa)
-- Asumimos que compras viejas no tenían movimiento asociado en movimientos_stock bajo la nueva lógica
-- NOTA: El sistema actual YA creaba movimientos en el controlador actualizado recientemente, pero para compras viejas quizas no.
-- Vamos a insertar movimientos para TODAS las compras, y luego linkearlas.
-- Pero espera, si el controlador ya insertaba movimientos, duplicaríamos.
-- Estrategia: Insertar movimiento solo si no existe uno con referencia_tipo='compra' y referencia_id=compra.id

DO $$
DECLARE
    r RECORD;
    new_move_id INT;
    id_almacen INT;
    id_proveedor INT;
BEGIN
    -- Obtener IDs de tipos de deposito
    SELECT id INTO id_almacen FROM tipos_depositos WHERE codigo = 'ALMACEN' LIMIT 1;
    SELECT id INTO id_proveedor FROM tipos_depositos WHERE codigo = 'PROVEEDOR' LIMIT 1;

    FOR r IN SELECT * FROM compras WHERE id_movimiento_stock IS NULL LOOP
        -- Verificar si ya existe movimiento para esta compra (por ref_tipo/ref_id del sistema anterior)
        -- Usamos perform dynamic query or standard select
        -- Como la tabla existe arriba, esto deberia funcionar

        -- Verificar si el movimiento existe por referencia
        new_move_id := NULL;
        SELECT id INTO new_move_id FROM movimientos_stock
        WHERE referencia_tipo IN ('compra', 'compra_legacy') AND referencia_id = r.id LIMIT 1;

        IF new_move_id IS NULL THEN
            -- Crear movimiento
            INSERT INTO movimientos_stock (
                fecha,
                id_variante,
                cantidad,
                id_tipo_deposito_origen,
                id_tipo_deposito_destino,
                referencia_tipo,
                referencia_id,
                observaciones,
                created_at
            ) VALUES (
                r.fecha,
                r.id_variante,
                r.cantidad,
                id_proveedor,
                id_almacen,
                'compra_legacy',
                r.id,
                'Migracion desde tabla compras',
                r.created_at
            ) RETURNING id INTO new_move_id;
        END IF;

        -- Actualizar compra con el link al movimiento
        UPDATE compras SET id_movimiento_stock = new_move_id WHERE id = r.id;
    END LOOP;
END $$;

-- 7. Limpiar tabla compras (Eliminar columnas redundantes)
-- OJO: Esto es destructivo. Asegurarse del backup.
ALTER TABLE compras DROP COLUMN IF EXISTS proveedor;
ALTER TABLE compras DROP COLUMN IF EXISTS id_variante; -- Ya está en movimiento
ALTER TABLE compras DROP COLUMN IF EXISTS cantidad;    -- Ya está en movimiento
ALTER TABLE compras DROP COLUMN IF EXISTS precio_total; -- Se puede calcular precio_unitario * cantidad del movimiento, o dejarlo por performance. Propuesta decia "solo almacenar precio unitario y total". Dejemos precio_total.

-- FK Constraints
ALTER TABLE compras ADD CONSTRAINT fk_compras_movimiento FOREIGN KEY (id_movimiento_stock) REFERENCES movimientos_stock(id) ON DELETE CASCADE;
ALTER TABLE compras ADD CONSTRAINT fk_compras_entidad FOREIGN KEY (id_entidad) REFERENCES entidades(id);
