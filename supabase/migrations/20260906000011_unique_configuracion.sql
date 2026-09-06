-- Fix: configuracion UNIQUE(clave) -> UNIQUE(company_id, clave)
-- El dump original tenía UNIQUE(clave) global; con multi-tenant cada empresa
-- necesita su propio set de claves.

ALTER TABLE public.configuracion DROP CONSTRAINT IF EXISTS configuracion_clave_key;
ALTER TABLE public.configuracion ADD CONSTRAINT configuracion_company_clave_key UNIQUE (company_id, clave);
