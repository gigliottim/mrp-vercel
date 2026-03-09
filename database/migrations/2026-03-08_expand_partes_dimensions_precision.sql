-- Permite hasta 10 decimales en dimensiones/medidas de partes
-- para alinearse con configuracion_general.decimal_places (max 10).

ALTER TABLE public.partes
    ALTER COLUMN largo_alto TYPE numeric(18,10),
    ALTER COLUMN ancho TYPE numeric(18,10),
    ALTER COLUMN espesor_profundidad TYPE numeric(18,10),
    ALTER COLUMN superficie TYPE numeric(18,10),
    ALTER COLUMN volumen TYPE numeric(18,10);
