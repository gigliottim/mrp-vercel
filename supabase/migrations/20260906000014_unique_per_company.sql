-- Fix multi-tenant: constraints UNIQUE globales -> por company_id
-- El dump original tenía UNIQUE global (una sola empresa). Con multi-tenant,
-- cada empresa necesita su propio set de códigos/símbolos. Se reemplazan
-- las constraints globales por compuestas (company_id, campo).

-- unidades_medida
ALTER TABLE public.unidades_medida DROP CONSTRAINT IF EXISTS unidades_medida_tipo_simbolo_key;
ALTER TABLE public.unidades_medida DROP CONSTRAINT IF EXISTS unidades_medida_tipo_unidad_key;
ALTER TABLE public.unidades_medida ADD CONSTRAINT unidades_medida_company_tipo_simbolo_key UNIQUE (company_id, tipo, simbolo);
ALTER TABLE public.unidades_medida ADD CONSTRAINT unidades_medida_company_tipo_unidad_key UNIQUE (company_id, tipo, unidad);

-- tipos_partes
ALTER TABLE public.tipos_partes DROP CONSTRAINT IF EXISTS tipos_partes_codigo_key;
ALTER TABLE public.tipos_partes ADD CONSTRAINT tipos_partes_company_codigo_key UNIQUE (company_id, codigo);

-- grupos_partes
ALTER TABLE public.grupos_partes DROP CONSTRAINT IF EXISTS grupos_partes_codigo_key;
ALTER TABLE public.grupos_partes ADD CONSTRAINT grupos_partes_company_codigo_key UNIQUE (company_id, codigo);

-- tipos_depositos
ALTER TABLE public.tipos_depositos DROP CONSTRAINT IF EXISTS tipos_depositos_codigo_key;
ALTER TABLE public.tipos_depositos ADD CONSTRAINT tipos_depositos_company_codigo_key UNIQUE (company_id, codigo);

-- almacenes
ALTER TABLE public.almacenes DROP CONSTRAINT IF EXISTS almacenes_codigo_key;
ALTER TABLE public.almacenes ADD CONSTRAINT almacenes_company_codigo_key UNIQUE (company_id, codigo);

-- entidades (no tiene UNIQUE global relevante, solo pkey)

-- centros_trabajo
ALTER TABLE public.centros_trabajo DROP CONSTRAINT IF EXISTS centros_trabajo_codigo_key;
ALTER TABLE public.centros_trabajo ADD CONSTRAINT centros_trabajo_company_codigo_key UNIQUE (company_id, codigo);

-- partes
ALTER TABLE public.partes DROP CONSTRAINT IF EXISTS partes_codigo_key;
ALTER TABLE public.partes ADD CONSTRAINT partes_company_codigo_key UNIQUE (company_id, codigo);

-- ordenes_produccion
ALTER TABLE public.ordenes_produccion DROP CONSTRAINT IF EXISTS ordenes_produccion_numero_orden_key;
ALTER TABLE public.ordenes_produccion ADD CONSTRAINT ordenes_produccion_company_numero_orden_key UNIQUE (company_id, numero_orden);

-- bom_cabecera (variante_padre_id, version) -> (company_id, variante_padre_id, version)
ALTER TABLE public.bom_cabecera DROP CONSTRAINT IF EXISTS bom_cabecera_variante_padre_id_version_key;
ALTER TABLE public.bom_cabecera ADD CONSTRAINT bom_cabecera_company_variante_version_key UNIQUE (company_id, variante_padre_id, version);

-- composicion_variantes (id_padre, id_hijo) -> (company_id, id_padre, id_hijo)
ALTER TABLE public.composicion_variantes DROP CONSTRAINT IF EXISTS composicion_variantes_id_padre_id_hijo_key;
ALTER TABLE public.composicion_variantes ADD CONSTRAINT composicion_variantes_company_padre_hijo_key UNIQUE (company_id, id_padre, id_hijo);

-- rutas_produccion (bom_id, secuencia) -> (company_id, bom_id, secuencia)
ALTER TABLE public.rutas_produccion DROP CONSTRAINT IF EXISTS rutas_produccion_bom_id_secuencia_key;
ALTER TABLE public.rutas_produccion ADD CONSTRAINT rutas_produccion_company_bom_secuencia_key UNIQUE (company_id, bom_id, secuencia);
