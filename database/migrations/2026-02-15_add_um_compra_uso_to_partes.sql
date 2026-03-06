-- Migración: Agregar campos de unidad de medida de compra y uso a tabla partes
-- Fecha: 2026-02-15
-- Descripción: Agrega dos campos nuevos para diferenciar la unidad de medida de compra
--              de la unidad de medida de uso (ej: se compra en kg, se usa en metros)

-- Agregar campo para unidad de medida de compra
ALTER TABLE public.partes
ADD COLUMN id_um_compra INTEGER;

-- Agregar campo para unidad de medida de uso
ALTER TABLE public.partes
ADD COLUMN id_um_uso INTEGER;

-- Agregar comentarios a las columnas
COMMENT ON COLUMN public.partes.id_um_compra IS 'Unidad de medida en la que se compra el ítem';
COMMENT ON COLUMN public.partes.id_um_uso IS 'Unidad de medida en la que se usa el ítem en producción';

-- Agregar foreign keys
ALTER TABLE public.partes
ADD CONSTRAINT partes_id_um_compra_fkey
FOREIGN KEY (id_um_compra) REFERENCES public.unidades_medida(id);

ALTER TABLE public.partes
ADD CONSTRAINT partes_id_um_uso_fkey
FOREIGN KEY (id_um_uso) REFERENCES public.unidades_medida(id);

-- Crear índices para mejorar performance en consultas
CREATE INDEX idx_partes_id_um_compra ON public.partes(id_um_compra);
CREATE INDEX idx_partes_id_um_uso ON public.partes(id_um_uso);

-- Script de rollback (comentado)
-- ALTER TABLE public.partes DROP CONSTRAINT IF EXISTS partes_id_um_uso_fkey;
-- ALTER TABLE public.partes DROP CONSTRAINT IF EXISTS partes_id_um_compra_fkey;
-- DROP INDEX IF EXISTS idx_partes_id_um_uso;
-- DROP INDEX IF EXISTS idx_partes_id_um_compra;
-- ALTER TABLE public.partes DROP COLUMN IF EXISTS id_um_uso;
-- ALTER TABLE public.partes DROP COLUMN IF EXISTS id_um_compra;
