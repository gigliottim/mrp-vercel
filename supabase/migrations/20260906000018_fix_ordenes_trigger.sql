-- Fix: trigger set_timestamp_ordenes roto
-- El dump original usaba update_updated_at_column() que setea NEW.updated_at,
-- pero ordenes_produccion NO tiene updated_at (usa fecha_actualizacion).
-- En el sistema PHP este trigger fallaba en cada UPDATE de orden.

CREATE OR REPLACE FUNCTION public.update_fecha_actualizacion_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.fecha_actualizacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS set_timestamp_ordenes ON public.ordenes_produccion;
CREATE TRIGGER set_timestamp_ordenes BEFORE UPDATE ON public.ordenes_produccion
    FOR EACH ROW EXECUTE FUNCTION public.update_fecha_actualizacion_column();
