-- Migración del esquema tenant mrp_tunna a Supabase
-- Fuente: database/backups/2026-09-06_09-40-12/mrp_tunna_schema_2026-09-06_09-40-12.sql
-- Cambios: company_id bigint NOT NULL agregado a todas las tablas; schema_migrations excluida.

-- ── Extensiones ──
CREATE EXTENSION IF NOT EXISTS btree_gist WITH SCHEMA public;
CREATE EXTENSION IF NOT EXISTS ltree WITH SCHEMA public;

-- ── Funciones ──
CREATE FUNCTION public.actualizar_stock_trigger() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    DECLARE
        v_variante_id INTEGER;
        v_delta NUMERIC;
    BEGIN
        IF (TG_OP = 'INSERT') THEN
            v_variante_id := NEW.variante_id;
            v_delta := NEW.cantidad * NEW.signo;
        ELSIF (TG_OP = 'DELETE') THEN
            v_variante_id := OLD.variante_id;
            v_delta := (OLD.cantidad * OLD.signo) * -1;
        END IF;

        UPDATE variantes
        SET stock_actual = stock_actual + v_delta,
            fecha_modificacion = CURRENT_TIMESTAMP
        WHERE id = v_variante_id;

        RETURN NEW;
    END;
END;
$$;

CREATE FUNCTION public.update_fecha_modificacion_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.fecha_modificacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

-- ── Tablas ──
CREATE TABLE public.agent_ai_logs (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    conversation_id character varying(100),
    model_used character varying(100),
    provider character varying(50),
    prompt_hash character varying(64),
    response_time_ms integer,
    validation_result character varying(50) DEFAULT 'unknown'::character varying,
    tokens_used integer,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.agent_conversations (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    tenant_id integer DEFAULT 0 NOT NULL,
    user_id integer DEFAULT 0 NOT NULL,
    intent character varying(50) DEFAULT 'general_query'::character varying NOT NULL,
    status character varying(20) DEFAULT 'active'::character varying,
    metadata jsonb DEFAULT '{}'::jsonb,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.agent_messages (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    conversation_id character varying(100) NOT NULL,
    role character varying(20) DEFAULT 'user'::character varying NOT NULL,
    content text DEFAULT ''::text NOT NULL,
    metadata jsonb DEFAULT '{}'::jsonb,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.almacenes (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    codigo character varying(20) NOT NULL,
    nombre character varying(100) NOT NULL,
    es_deposito_venta boolean DEFAULT true,
    es_deposito_produccion boolean DEFAULT false,
    activo boolean DEFAULT true
);

CREATE TABLE public.bom_cabecera (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    variante_padre_id integer NOT NULL,
    version character varying(10) DEFAULT '1.0'::character varying,
    activa boolean DEFAULT true,
    fecha_efectiva date NOT NULL,
    fecha_vencimiento date,
    observaciones text,
    aprobada_por bigint,
    fecha_aprobacion timestamp with time zone,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.bom_detalle (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    bom_id integer NOT NULL,
    variante_componente_id integer NOT NULL,
    cantidad_necesaria numeric(10,5) NOT NULL,
    unidad_medida_id integer NOT NULL,
    desperdicio_porcentaje numeric(5,2) DEFAULT 0.00,
    es_opcional boolean DEFAULT false,
    secuencia integer DEFAULT 1,
    costo_unitario_estimado numeric(15,4) DEFAULT 0.0000,
    tiempo_setup_mins integer DEFAULT 0,
    tiempo_proceso_mins integer DEFAULT 0,
    condicion_aplicacion jsonb,
    observaciones character varying(500),
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.centros_trabajo (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    codigo character varying(20) NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion text,
    tipo character varying(20) DEFAULT 'manual'::character varying,
    capacidad_horas_dia numeric(4,2) DEFAULT 8.00,
    eficiencia_porcentaje numeric(5,2) DEFAULT 100.00,
    costo_hora numeric(12,2) DEFAULT 0.00,
    capacidad_finita boolean DEFAULT false,
    calendario_id integer,
    activo boolean DEFAULT true,
    ubicacion character varying(100),
    responsable character varying(100),
    observaciones text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT centros_trabajo_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('manual'::character varying)::text, ('semi_automatico'::character varying)::text, ('automatico'::character varying)::text])))
);

CREATE TABLE public.composicion_variantes (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    id_padre integer NOT NULL,
    id_hijo integer NOT NULL,
    cantidad numeric(10,4) DEFAULT 1.0000 NOT NULL,
    fecha_creacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion bigint,
    activo boolean DEFAULT true
);

CREATE TABLE public.compras (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    fecha date NOT NULL,
    precio_unitario numeric(15,2) NOT NULL,
    observaciones text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    id_movimiento_stock integer,
    id_entidad integer,
    nro_comprobante character varying(50)
);

CREATE TABLE public.configuracion (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    clave character varying(100) NOT NULL,
    valor text,
    descripcion text,
    tipo character varying(20) DEFAULT 'string'::character varying,
    fecha_actualizacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT configuracion_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('string'::character varying)::text, ('number'::character varying)::text, ('boolean'::character varying)::text, ('json'::character varying)::text])))
);

CREATE TABLE public.configuracion_general (

    company_id bigint NOT NULL,
    id smallint DEFAULT 1 NOT NULL,
    decimal_places smallint DEFAULT 4 NOT NULL,
    rounding_mode character varying(20) DEFAULT 'half_up'::character varying NOT NULL,
    thousand_separator character varying(1) DEFAULT '.'::character varying NOT NULL,
    decimal_separator character varying(1) DEFAULT ','::character varying NOT NULL,
    date_format character varying(20) DEFAULT 'd/m/Y'::character varying NOT NULL,
    time_format character varying(20) DEFAULT 'H:i'::character varying NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT chk_configuracion_general_rounding_mode CHECK (((rounding_mode)::text = ANY (ARRAY[('half_up'::character varying)::text, ('half_down'::character varying)::text, ('half_even'::character varying)::text, ('truncate'::character varying)::text]))),
    CONSTRAINT chk_configuracion_general_separators CHECK (((thousand_separator)::text <> (decimal_separator)::text)),
    CONSTRAINT configuracion_general_decimal_places_check CHECK (((decimal_places >= 1) AND (decimal_places <= 10))),
    CONSTRAINT configuracion_general_id_check CHECK ((id = 1))
);

CREATE TABLE public.entidades (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    razon_social character varying(255) NOT NULL,
    tipo character varying(20) DEFAULT 'PROVEEDOR'::character varying,
    identificacion_tributaria character varying(50),
    contacto_email character varying(255),
    contacto_telefono character varying(50),
    direccion text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT entidades_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('PROVEEDOR'::character varying)::text, ('CLIENTE'::character varying)::text, ('AMBOS'::character varying)::text])))
);

CREATE TABLE public.grupos_partes (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    codigo character varying(20) NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion text,
    color character varying(7) DEFAULT '#007bff'::character varying,
    activo boolean DEFAULT true,
    fecha_creacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.movimientos_inventario (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    variante_id integer NOT NULL,
    almacen_id integer,
    orden_produccion_id integer,
    tipo_movimiento character varying(30) NOT NULL,
    cantidad numeric(12,4) NOT NULL,
    signo integer DEFAULT 1 NOT NULL,
    costo_unitario_snapshot numeric(15,4) DEFAULT 0,
    fecha_movimiento timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_id bigint,
    observaciones text,
    referencia_documento character varying(100),
    CONSTRAINT movimientos_inventario_signo_check CHECK ((signo = ANY (ARRAY[1, '-1'::integer]))),
    CONSTRAINT movimientos_inventario_tipo_movimiento_check CHECK (((tipo_movimiento)::text = ANY (ARRAY[('compra_recepcion'::character varying)::text, ('produccion_ingreso'::character varying)::text, ('produccion_consumo'::character varying)::text, ('produccion_descarte'::character varying)::text, ('ajuste_inventario'::character varying)::text, ('venta_despacho'::character varying)::text, ('transferencia_salida'::character varying)::text, ('transferencia_entrada'::character varying)::text])))
);

CREATE TABLE public.movimientos_stock (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    id_variante integer,
    cantidad numeric(15,6) NOT NULL,
    id_tipo_deposito_origen integer NOT NULL,
    id_tipo_deposito_destino integer NOT NULL,
    fecha timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    referencia_tipo character varying(50) NOT NULL,
    referencia_id integer NOT NULL,
    observaciones text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.mrp_calculos_cabecera (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    fecha_calculo timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    escenario character varying(50) DEFAULT 'OFICIAL'::character varying,
    usuario_id bigint,
    parametros_usados jsonb
);

CREATE TABLE public.mrp_sugerencias (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    calculo_id integer,
    variante_id integer NOT NULL,
    tipo_accion character varying(20) NOT NULL,
    cantidad_actual numeric(12,4),
    cantidad_sugerida numeric(12,4) NOT NULL,
    cantidad_reservada numeric(12,4) DEFAULT 0,
    fecha_necesidad date NOT NULL,
    fecha_inicio_sugerida date NOT NULL,
    origen_demanda character varying(100),
    orden_origen_id integer,
    prioridad character varying(20) DEFAULT 'normal'::character varying,
    estado character varying(20) DEFAULT 'pendiente'::character varying,
    observaciones text,
    CONSTRAINT mrp_sugerencias_estado_check CHECK (((estado)::text = ANY (ARRAY[('pendiente'::character varying)::text, ('aprobada'::character varying)::text, ('rechazada'::character varying)::text, ('convertida'::character varying)::text]))),
    CONSTRAINT mrp_sugerencias_tipo_accion_check CHECK (((tipo_accion)::text = ANY (ARRAY[('producir'::character varying)::text, ('comprar'::character varying)::text, ('transferir'::character varying)::text, ('cancelar_orden'::character varying)::text])))
);

CREATE TABLE public.ordenes_produccion (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    numero_orden character varying(50) NOT NULL,
    variante_id integer NOT NULL,
    bom_id_utilizada integer,
    cantidad_planificada numeric(12,4) NOT NULL,
    cantidad_producida numeric(12,4) DEFAULT 0,
    cantidad_desechada numeric(12,4) DEFAULT 0,
    fecha_inicio_programada date NOT NULL,
    fecha_fin_programada date NOT NULL,
    fecha_inicio_real timestamp with time zone,
    fecha_fin_real timestamp with time zone,
    estado character varying(20) DEFAULT 'pendiente'::character varying,
    prioridad character varying(20) DEFAULT 'normal'::character varying,
    configuracion_orden jsonb DEFAULT '{}'::jsonb,
    observaciones text,
    usuario_creador bigint,
    fecha_creacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ordenes_produccion_estado_check CHECK (((estado)::text = ANY (ARRAY[('borrador'::character varying)::text, ('planificada'::character varying)::text, ('liberada'::character varying)::text, ('en_proceso'::character varying)::text, ('pausada'::character varying)::text, ('completada'::character varying)::text, ('cancelada'::character varying)::text, ('cerrada'::character varying)::text]))),
    CONSTRAINT ordenes_produccion_prioridad_check CHECK (((prioridad)::text = ANY (ARRAY[('baja'::character varying)::text, ('normal'::character varying)::text, ('alta'::character varying)::text, ('urgente'::character varying)::text])))
);

CREATE TABLE public.partes (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    codigo character varying(50) NOT NULL,
    id_tipo integer NOT NULL,
    id_grupo integer NOT NULL,
    detalle character varying(255) NOT NULL,
    largo_alto numeric(18,10),
    id_um_largo_alto integer,
    ancho numeric(18,10),
    id_um_ancho integer,
    espesor_profundidad numeric(18,10),
    id_um_espesor integer,
    superficie numeric(18,10),
    id_um_superficie integer,
    volumen numeric(18,10),
    id_um_volumen integer,
    atributos_base jsonb DEFAULT '{}'::jsonb,
    reglas_configuracion jsonb DEFAULT '{}'::jsonb,
    activo boolean DEFAULT true,
    creado_por bigint,
    fecha_creacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    id_um_compra integer,
    id_um_uso integer,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    factor_conversion numeric(15,6) DEFAULT 1.0
);

CREATE TABLE public.planificacion_recursos (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    orden_produccion_id integer NOT NULL,
    operacion_id integer,
    centro_trabajo_id integer NOT NULL,
    periodo tsrange NOT NULL,
    estado character varying(20) DEFAULT 'programado'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT periodo_valido CHECK ((lower(periodo) < upper(periodo))),
    CONSTRAINT planificacion_recursos_estado_check CHECK (((estado)::text = ANY (ARRAY[('programado'::character varying)::text, ('en_ejecucion'::character varying)::text, ('completado'::character varying)::text])))
);

CREATE TABLE public.rutas_produccion (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    bom_id integer NOT NULL,
    secuencia integer NOT NULL,
    centro_trabajo_id integer NOT NULL,
    descripcion character varying(255) NOT NULL,
    tiempo_setup_mins integer DEFAULT 0,
    tiempo_proceso_unitario_mins numeric(10,2) DEFAULT 0,
    tiempo_cola_mins integer DEFAULT 0,
    tiempo_movimiento_mins integer DEFAULT 0,
    capacidad_requerida numeric(10,2) DEFAULT 1.0,
    costo_operacion_fijo numeric(12,2) DEFAULT 0.00,
    costo_operacion_variable numeric(12,2) DEFAULT 0.00,
    instrucciones text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.tipos_depositos (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    codigo character varying(50) NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion text,
    orden integer DEFAULT 0,
    es_sistema boolean DEFAULT false,
    activo boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.tipos_depositos_movimientos (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    tipo_deposito_origen_id integer NOT NULL,
    tipo_deposito_destino_id integer NOT NULL,
    activo boolean DEFAULT true,
    observaciones text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.tipos_partes (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    codigo character varying(50) NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion text,
    orden integer DEFAULT 0,
    activo boolean DEFAULT true,
    fecha_creacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    requiere_stock boolean DEFAULT true
);

CREATE TABLE public.unidades_medida (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    tipo character varying(20) NOT NULL,
    unidad character varying(50) NOT NULL,
    simbolo character varying(10) NOT NULL,
    equivalencia_base numeric(15,8) NOT NULL,
    es_base boolean DEFAULT false,
    activo boolean DEFAULT true,
    fecha_creacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    is_system boolean DEFAULT false NOT NULL,
    locked boolean DEFAULT false NOT NULL,
    CONSTRAINT unidades_medida_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('longitud'::character varying)::text, ('superficie'::character varying)::text, ('volumen'::character varying)::text, ('masa'::character varying)::text, ('tiempo'::character varying)::text, ('temperatura'::character varying)::text, ('unidad'::character varying)::text])))
);

CREATE TABLE public.variantes (

    company_id bigint NOT NULL,
    id integer NOT NULL,
    id_parte integer NOT NULL,
    codigo_variante character varying(50) NOT NULL,
    detalle character varying(255) NOT NULL,
    estado character varying(20) DEFAULT 'activa'::character varying,
    lote_minimo numeric(10,2) DEFAULT 1.00,
    punto_pedido numeric(10,2) DEFAULT 0.00,
    stock_seguridad numeric(10,2) DEFAULT 0.00,
    anticipo_compra integer DEFAULT 0,
    lead_time_produccion integer DEFAULT 0,
    stock_actual numeric(10,2) DEFAULT 0.00,
    peso numeric(10,4),
    id_um_peso integer,
    ubicacion_defecto jsonb,
    atributos jsonb DEFAULT '{}'::jsonb,
    sku character varying(100) GENERATED ALWAYS AS (codigo_variante) STORED,
    creado_por bigint,
    fecha_creacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    costo numeric(15,2) DEFAULT 0,
    ubicacion_cuerpo character varying(100),
    ubicacion_pasillo character varying(100),
    ubicacion_estante character varying(100),
    CONSTRAINT variantes_estado_check CHECK (((estado)::text = ANY (ARRAY[('activa'::character varying)::text, ('obsoleta'::character varying)::text, ('descontinuada'::character varying)::text, ('desarrollo'::character varying)::text])))
);

-- ── Secuencias ──
CREATE SEQUENCE public.agent_ai_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_ai_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.agent_ai_logs_id_seq OWNED BY public.agent_ai_logs.id;

CREATE SEQUENCE public.agent_conversations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_conversations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.agent_conversations_id_seq OWNED BY public.agent_conversations.id;

CREATE SEQUENCE public.agent_messages_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.agent_messages_id_seq OWNED BY public.agent_messages.id;

CREATE SEQUENCE public.almacenes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: almacenes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.almacenes_id_seq OWNED BY public.almacenes.id;

CREATE SEQUENCE public.bom_cabecera_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bom_cabecera_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bom_cabecera_id_seq OWNED BY public.bom_cabecera.id;

CREATE SEQUENCE public.bom_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bom_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bom_detalle_id_seq OWNED BY public.bom_detalle.id;

CREATE SEQUENCE public.centros_trabajo_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: centros_trabajo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.centros_trabajo_id_seq OWNED BY public.centros_trabajo.id;

CREATE SEQUENCE public.composicion_variantes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: composicion_variantes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.composicion_variantes_id_seq OWNED BY public.composicion_variantes.id;

CREATE SEQUENCE public.compras_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: compras_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.compras_id_seq OWNED BY public.compras.id;

CREATE SEQUENCE public.configuracion_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: configuracion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.configuracion_id_seq OWNED BY public.configuracion.id;

CREATE SEQUENCE public.entidades_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: entidades_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.entidades_id_seq OWNED BY public.entidades.id;

CREATE SEQUENCE public.grupos_partes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: grupos_partes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.grupos_partes_id_seq OWNED BY public.grupos_partes.id;

CREATE SEQUENCE public.movimientos_inventario_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: movimientos_inventario_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.movimientos_inventario_id_seq OWNED BY public.movimientos_inventario.id;

CREATE SEQUENCE public.movimientos_stock_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: movimientos_stock_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.movimientos_stock_id_seq OWNED BY public.movimientos_stock.id;

CREATE SEQUENCE public.mrp_calculos_cabecera_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mrp_calculos_cabecera_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mrp_calculos_cabecera_id_seq OWNED BY public.mrp_calculos_cabecera.id;

CREATE SEQUENCE public.mrp_sugerencias_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mrp_sugerencias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mrp_sugerencias_id_seq OWNED BY public.mrp_sugerencias.id;

CREATE SEQUENCE public.ordenes_produccion_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ordenes_produccion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ordenes_produccion_id_seq OWNED BY public.ordenes_produccion.id;

CREATE SEQUENCE public.partes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: partes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.partes_id_seq OWNED BY public.partes.id;

CREATE SEQUENCE public.planificacion_recursos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: planificacion_recursos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.planificacion_recursos_id_seq OWNED BY public.planificacion_recursos.id;

CREATE SEQUENCE public.rutas_produccion_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rutas_produccion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rutas_produccion_id_seq OWNED BY public.rutas_produccion.id;


CREATE SEQUENCE public.tipos_depositos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tipos_depositos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tipos_depositos_id_seq OWNED BY public.tipos_depositos.id;

CREATE SEQUENCE public.tipos_depositos_movimientos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tipos_depositos_movimientos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tipos_depositos_movimientos_id_seq OWNED BY public.tipos_depositos_movimientos.id;

CREATE SEQUENCE public.tipos_partes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tipos_partes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tipos_partes_id_seq OWNED BY public.tipos_partes.id;

CREATE SEQUENCE public.unidades_medida_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: unidades_medida_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.unidades_medida_id_seq OWNED BY public.unidades_medida.id;

CREATE SEQUENCE public.variantes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: variantes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.variantes_id_seq OWNED BY public.variantes.id;

-- ── Defaults ──
ALTER TABLE ONLY public.agent_ai_logs ALTER COLUMN id SET DEFAULT nextval('public.agent_ai_logs_id_seq'::regclass);
ALTER TABLE ONLY public.agent_conversations ALTER COLUMN id SET DEFAULT nextval('public.agent_conversations_id_seq'::regclass);
ALTER TABLE ONLY public.agent_messages ALTER COLUMN id SET DEFAULT nextval('public.agent_messages_id_seq'::regclass);
ALTER TABLE ONLY public.almacenes ALTER COLUMN id SET DEFAULT nextval('public.almacenes_id_seq'::regclass);
ALTER TABLE ONLY public.bom_cabecera ALTER COLUMN id SET DEFAULT nextval('public.bom_cabecera_id_seq'::regclass);
ALTER TABLE ONLY public.bom_detalle ALTER COLUMN id SET DEFAULT nextval('public.bom_detalle_id_seq'::regclass);
ALTER TABLE ONLY public.centros_trabajo ALTER COLUMN id SET DEFAULT nextval('public.centros_trabajo_id_seq'::regclass);
ALTER TABLE ONLY public.composicion_variantes ALTER COLUMN id SET DEFAULT nextval('public.composicion_variantes_id_seq'::regclass);
ALTER TABLE ONLY public.compras ALTER COLUMN id SET DEFAULT nextval('public.compras_id_seq'::regclass);
ALTER TABLE ONLY public.configuracion ALTER COLUMN id SET DEFAULT nextval('public.configuracion_id_seq'::regclass);
ALTER TABLE ONLY public.entidades ALTER COLUMN id SET DEFAULT nextval('public.entidades_id_seq'::regclass);
ALTER TABLE ONLY public.grupos_partes ALTER COLUMN id SET DEFAULT nextval('public.grupos_partes_id_seq'::regclass);
ALTER TABLE ONLY public.movimientos_inventario ALTER COLUMN id SET DEFAULT nextval('public.movimientos_inventario_id_seq'::regclass);
ALTER TABLE ONLY public.movimientos_stock ALTER COLUMN id SET DEFAULT nextval('public.movimientos_stock_id_seq'::regclass);
ALTER TABLE ONLY public.mrp_calculos_cabecera ALTER COLUMN id SET DEFAULT nextval('public.mrp_calculos_cabecera_id_seq'::regclass);
ALTER TABLE ONLY public.mrp_sugerencias ALTER COLUMN id SET DEFAULT nextval('public.mrp_sugerencias_id_seq'::regclass);
ALTER TABLE ONLY public.ordenes_produccion ALTER COLUMN id SET DEFAULT nextval('public.ordenes_produccion_id_seq'::regclass);
ALTER TABLE ONLY public.partes ALTER COLUMN id SET DEFAULT nextval('public.partes_id_seq'::regclass);
ALTER TABLE ONLY public.planificacion_recursos ALTER COLUMN id SET DEFAULT nextval('public.planificacion_recursos_id_seq'::regclass);
ALTER TABLE ONLY public.rutas_produccion ALTER COLUMN id SET DEFAULT nextval('public.rutas_produccion_id_seq'::regclass);
ALTER TABLE ONLY public.tipos_depositos ALTER COLUMN id SET DEFAULT nextval('public.tipos_depositos_id_seq'::regclass);
ALTER TABLE ONLY public.tipos_depositos_movimientos ALTER COLUMN id SET DEFAULT nextval('public.tipos_depositos_movimientos_id_seq'::regclass);
ALTER TABLE ONLY public.tipos_partes ALTER COLUMN id SET DEFAULT nextval('public.tipos_partes_id_seq'::regclass);
ALTER TABLE ONLY public.unidades_medida ALTER COLUMN id SET DEFAULT nextval('public.unidades_medida_id_seq'::regclass);
ALTER TABLE ONLY public.variantes ALTER COLUMN id SET DEFAULT nextval('public.variantes_id_seq'::regclass);

-- ── Constraints ──
ALTER TABLE ONLY public.agent_ai_logs
    ADD CONSTRAINT agent_ai_logs_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.agent_conversations
    ADD CONSTRAINT agent_conversations_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.agent_messages
    ADD CONSTRAINT agent_messages_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.almacenes
    ADD CONSTRAINT almacenes_codigo_key UNIQUE (codigo);
ALTER TABLE ONLY public.almacenes
    ADD CONSTRAINT almacenes_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.bom_cabecera
    ADD CONSTRAINT bom_cabecera_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.bom_cabecera
    ADD CONSTRAINT bom_cabecera_variante_padre_id_version_key UNIQUE (variante_padre_id, version);
ALTER TABLE ONLY public.bom_detalle
    ADD CONSTRAINT bom_detalle_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.centros_trabajo
    ADD CONSTRAINT centros_trabajo_codigo_key UNIQUE (codigo);
ALTER TABLE ONLY public.centros_trabajo
    ADD CONSTRAINT centros_trabajo_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.composicion_variantes
    ADD CONSTRAINT composicion_variantes_id_padre_id_hijo_key UNIQUE (id_padre, id_hijo);
ALTER TABLE ONLY public.composicion_variantes
    ADD CONSTRAINT composicion_variantes_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.configuracion
    ADD CONSTRAINT configuracion_clave_key UNIQUE (clave);
ALTER TABLE ONLY public.configuracion_general
    ADD CONSTRAINT configuracion_general_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.configuracion
    ADD CONSTRAINT configuracion_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.entidades
    ADD CONSTRAINT entidades_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.grupos_partes
    ADD CONSTRAINT grupos_partes_codigo_key UNIQUE (codigo);
ALTER TABLE ONLY public.grupos_partes
    ADD CONSTRAINT grupos_partes_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.movimientos_stock
    ADD CONSTRAINT movimientos_stock_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.mrp_calculos_cabecera
    ADD CONSTRAINT mrp_calculos_cabecera_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.mrp_sugerencias
    ADD CONSTRAINT mrp_sugerencias_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.ordenes_produccion
    ADD CONSTRAINT ordenes_produccion_numero_orden_key UNIQUE (numero_orden);
ALTER TABLE ONLY public.ordenes_produccion
    ADD CONSTRAINT ordenes_produccion_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_codigo_key UNIQUE (codigo);
ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.planificacion_recursos
    ADD CONSTRAINT planificacion_recursos_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.rutas_produccion
    ADD CONSTRAINT rutas_produccion_bom_id_secuencia_key UNIQUE (bom_id, secuencia);
ALTER TABLE ONLY public.rutas_produccion
    ADD CONSTRAINT rutas_produccion_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.tipos_depositos
    ADD CONSTRAINT tipos_depositos_codigo_key UNIQUE (codigo);
ALTER TABLE ONLY public.tipos_depositos_movimientos
    ADD CONSTRAINT tipos_depositos_movimientos_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.tipos_depositos
    ADD CONSTRAINT tipos_depositos_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.tipos_partes
    ADD CONSTRAINT tipos_partes_codigo_key UNIQUE (codigo);
ALTER TABLE ONLY public.tipos_partes
    ADD CONSTRAINT tipos_partes_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_tipo_simbolo_key UNIQUE (tipo, simbolo);
ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_tipo_unidad_key UNIQUE (tipo, unidad);
ALTER TABLE ONLY public.tipos_depositos_movimientos
    ADD CONSTRAINT uq_origen_destino UNIQUE (tipo_deposito_origen_id, tipo_deposito_destino_id);
ALTER TABLE ONLY public.variantes
    ADD CONSTRAINT variantes_id_parte_codigo_variante_key UNIQUE (id_parte, codigo_variante);
ALTER TABLE ONLY public.variantes
    ADD CONSTRAINT variantes_pkey PRIMARY KEY (id);

-- ── Índices ──
CREATE INDEX idx_agent_ai_logs_conversation ON public.agent_ai_logs USING btree (conversation_id);
CREATE INDEX idx_agent_ai_logs_created_at ON public.agent_ai_logs USING btree (created_at);
CREATE INDEX idx_agent_conversations_intent ON public.agent_conversations USING btree (intent);
CREATE INDEX idx_agent_conversations_status ON public.agent_conversations USING btree (status);
CREATE INDEX idx_agent_conversations_tenant ON public.agent_conversations USING btree (tenant_id);
CREATE INDEX idx_agent_conversations_user ON public.agent_conversations USING btree (user_id);
CREATE INDEX idx_agent_messages_conversation ON public.agent_messages USING btree (conversation_id);
CREATE INDEX idx_agent_messages_created_at ON public.agent_messages USING btree (created_at);
CREATE INDEX idx_agent_messages_role ON public.agent_messages USING btree (role);
CREATE INDEX idx_mov_destino ON public.movimientos_stock USING btree (id_tipo_deposito_destino);
CREATE INDEX idx_mov_fecha ON public.movimientos_stock USING btree (fecha);
CREATE INDEX idx_mov_origen ON public.movimientos_stock USING btree (id_tipo_deposito_origen);
CREATE INDEX idx_mov_referencia ON public.movimientos_stock USING btree (referencia_tipo, referencia_id);
CREATE INDEX idx_mov_variante ON public.movimientos_stock USING btree (id_variante);
CREATE INDEX idx_movimientos_fecha ON public.movimientos_inventario USING btree (fecha_movimiento);
CREATE INDEX idx_movimientos_variante ON public.movimientos_inventario USING btree (variante_id);
CREATE INDEX idx_partes_id_um_compra ON public.partes USING btree (id_um_compra);
CREATE INDEX idx_partes_id_um_uso ON public.partes USING btree (id_um_uso);
CREATE INDEX idx_tdm_activo ON public.tipos_depositos_movimientos USING btree (activo);
CREATE INDEX idx_tdm_destino ON public.tipos_depositos_movimientos USING btree (tipo_deposito_destino_id);
CREATE INDEX idx_tdm_origen ON public.tipos_depositos_movimientos USING btree (tipo_deposito_origen_id);
CREATE INDEX idx_tipos_depositos_activo ON public.tipos_depositos USING btree (activo);
CREATE INDEX idx_tipos_depositos_codigo ON public.tipos_depositos USING btree (codigo);

-- ── Índices company_id ──
CREATE INDEX idx_agent_ai_logs_company ON public.agent_ai_logs USING btree (company_id);
CREATE INDEX idx_agent_conversations_company ON public.agent_conversations USING btree (company_id);
CREATE INDEX idx_agent_messages_company ON public.agent_messages USING btree (company_id);
CREATE INDEX idx_almacenes_company ON public.almacenes USING btree (company_id);
CREATE INDEX idx_bom_cabecera_company ON public.bom_cabecera USING btree (company_id);
CREATE INDEX idx_bom_detalle_company ON public.bom_detalle USING btree (company_id);
CREATE INDEX idx_centros_trabajo_company ON public.centros_trabajo USING btree (company_id);
CREATE INDEX idx_composicion_variantes_company ON public.composicion_variantes USING btree (company_id);
CREATE INDEX idx_compras_company ON public.compras USING btree (company_id);
CREATE INDEX idx_configuracion_company ON public.configuracion USING btree (company_id);
CREATE INDEX idx_configuracion_general_company ON public.configuracion_general USING btree (company_id);
CREATE INDEX idx_entidades_company ON public.entidades USING btree (company_id);
CREATE INDEX idx_grupos_partes_company ON public.grupos_partes USING btree (company_id);
CREATE INDEX idx_movimientos_inventario_company ON public.movimientos_inventario USING btree (company_id);
CREATE INDEX idx_movimientos_stock_company ON public.movimientos_stock USING btree (company_id);
CREATE INDEX idx_mrp_calculos_cabecera_company ON public.mrp_calculos_cabecera USING btree (company_id);
CREATE INDEX idx_mrp_sugerencias_company ON public.mrp_sugerencias USING btree (company_id);
CREATE INDEX idx_ordenes_produccion_company ON public.ordenes_produccion USING btree (company_id);
CREATE INDEX idx_partes_company ON public.partes USING btree (company_id);
CREATE INDEX idx_planificacion_recursos_company ON public.planificacion_recursos USING btree (company_id);
CREATE INDEX idx_rutas_produccion_company ON public.rutas_produccion USING btree (company_id);
CREATE INDEX idx_tipos_depositos_company ON public.tipos_depositos USING btree (company_id);
CREATE INDEX idx_tipos_depositos_movimientos_company ON public.tipos_depositos_movimientos USING btree (company_id);
CREATE INDEX idx_tipos_partes_company ON public.tipos_partes USING btree (company_id);
CREATE INDEX idx_unidades_medida_company ON public.unidades_medida USING btree (company_id);
CREATE INDEX idx_variantes_company ON public.variantes USING btree (company_id);

-- ── Foreign keys ──
ALTER TABLE ONLY public.bom_cabecera
    ADD CONSTRAINT bom_cabecera_variante_padre_id_fkey FOREIGN KEY (variante_padre_id) REFERENCES public.variantes(id);
ALTER TABLE ONLY public.bom_detalle
    ADD CONSTRAINT bom_detalle_bom_id_fkey FOREIGN KEY (bom_id) REFERENCES public.bom_cabecera(id) ON DELETE CASCADE;


--
-- Name: bom_detalle bom_detalle_unidad_medida_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bom_detalle
    ADD CONSTRAINT bom_detalle_unidad_medida_id_fkey FOREIGN KEY (unidad_medida_id) REFERENCES public.unidades_medida(id);
ALTER TABLE ONLY public.bom_detalle
    ADD CONSTRAINT bom_detalle_variante_componente_id_fkey FOREIGN KEY (variante_componente_id) REFERENCES public.variantes(id);
ALTER TABLE ONLY public.composicion_variantes
    ADD CONSTRAINT composicion_variantes_id_hijo_fkey FOREIGN KEY (id_hijo) REFERENCES public.variantes(id) ON DELETE CASCADE;


--
-- Name: composicion_variantes composicion_variantes_id_padre_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.composicion_variantes
    ADD CONSTRAINT composicion_variantes_id_padre_fkey FOREIGN KEY (id_padre) REFERENCES public.variantes(id) ON DELETE CASCADE;


--
-- Name: compras fk_compras_entidad; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT fk_compras_entidad FOREIGN KEY (id_entidad) REFERENCES public.entidades(id);
ALTER TABLE ONLY public.compras
    ADD CONSTRAINT fk_compras_movimiento FOREIGN KEY (id_movimiento_stock) REFERENCES public.movimientos_stock(id) ON DELETE CASCADE;


--
-- Name: movimientos_stock fk_mov_variante; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_stock
    ADD CONSTRAINT fk_mov_variante FOREIGN KEY (id_variante) REFERENCES public.variantes(id) ON DELETE RESTRICT;


--
-- Name: tipos_depositos_movimientos fk_tipo_destino; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_depositos_movimientos
    ADD CONSTRAINT fk_tipo_destino FOREIGN KEY (tipo_deposito_destino_id) REFERENCES public.tipos_depositos(id) ON DELETE CASCADE;


--
-- Name: tipos_depositos_movimientos fk_tipo_origen; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_depositos_movimientos
    ADD CONSTRAINT fk_tipo_origen FOREIGN KEY (tipo_deposito_origen_id) REFERENCES public.tipos_depositos(id) ON DELETE CASCADE;


--
-- Name: movimientos_inventario movimientos_inventario_almacen_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_almacen_id_fkey FOREIGN KEY (almacen_id) REFERENCES public.almacenes(id);
ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_orden_produccion_id_fkey FOREIGN KEY (orden_produccion_id) REFERENCES public.ordenes_produccion(id);
ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_variante_id_fkey FOREIGN KEY (variante_id) REFERENCES public.variantes(id);
ALTER TABLE ONLY public.mrp_sugerencias
    ADD CONSTRAINT mrp_sugerencias_calculo_id_fkey FOREIGN KEY (calculo_id) REFERENCES public.mrp_calculos_cabecera(id) ON DELETE CASCADE;


--
-- Name: mrp_sugerencias mrp_sugerencias_variante_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mrp_sugerencias
    ADD CONSTRAINT mrp_sugerencias_variante_id_fkey FOREIGN KEY (variante_id) REFERENCES public.variantes(id);
ALTER TABLE ONLY public.ordenes_produccion
    ADD CONSTRAINT ordenes_produccion_bom_id_utilizada_fkey FOREIGN KEY (bom_id_utilizada) REFERENCES public.bom_cabecera(id);
ALTER TABLE ONLY public.ordenes_produccion
    ADD CONSTRAINT ordenes_produccion_variante_id_fkey FOREIGN KEY (variante_id) REFERENCES public.variantes(id);
ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_grupo_fkey FOREIGN KEY (id_grupo) REFERENCES public.grupos_partes(id);
ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_tipo_fkey FOREIGN KEY (id_tipo) REFERENCES public.tipos_partes(id);
ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_ancho_fkey FOREIGN KEY (id_um_ancho) REFERENCES public.unidades_medida(id);
ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_compra_fkey FOREIGN KEY (id_um_compra) REFERENCES public.unidades_medida(id);
ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_espesor_fkey FOREIGN KEY (id_um_espesor) REFERENCES public.unidades_medida(id);
ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_largo_alto_fkey FOREIGN KEY (id_um_largo_alto) REFERENCES public.unidades_medida(id);
ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_superficie_fkey FOREIGN KEY (id_um_superficie) REFERENCES public.unidades_medida(id);
ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_uso_fkey FOREIGN KEY (id_um_uso) REFERENCES public.unidades_medida(id);
ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_volumen_fkey FOREIGN KEY (id_um_volumen) REFERENCES public.unidades_medida(id);
ALTER TABLE ONLY public.planificacion_recursos
    ADD CONSTRAINT planificacion_recursos_centro_trabajo_id_fkey FOREIGN KEY (centro_trabajo_id) REFERENCES public.centros_trabajo(id);
ALTER TABLE ONLY public.planificacion_recursos
    ADD CONSTRAINT planificacion_recursos_operacion_id_fkey FOREIGN KEY (operacion_id) REFERENCES public.rutas_produccion(id);
ALTER TABLE ONLY public.planificacion_recursos
    ADD CONSTRAINT planificacion_recursos_orden_produccion_id_fkey FOREIGN KEY (orden_produccion_id) REFERENCES public.ordenes_produccion(id) ON DELETE CASCADE;


--
-- Name: rutas_produccion rutas_produccion_bom_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rutas_produccion
    ADD CONSTRAINT rutas_produccion_bom_id_fkey FOREIGN KEY (bom_id) REFERENCES public.bom_cabecera(id) ON DELETE CASCADE;


--
-- Name: rutas_produccion rutas_produccion_centro_trabajo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rutas_produccion
    ADD CONSTRAINT rutas_produccion_centro_trabajo_id_fkey FOREIGN KEY (centro_trabajo_id) REFERENCES public.centros_trabajo(id);
ALTER TABLE ONLY public.variantes
    ADD CONSTRAINT variantes_id_parte_fkey FOREIGN KEY (id_parte) REFERENCES public.partes(id) ON DELETE CASCADE;


--
-- Name: variantes variantes_id_um_peso_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.variantes
    ADD CONSTRAINT variantes_id_um_peso_fkey FOREIGN KEY (id_um_peso) REFERENCES public.unidades_medida(id);

-- ── Triggers ──
CREATE TRIGGER set_timestamp_ordenes BEFORE UPDATE ON public.ordenes_produccion FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
CREATE TRIGGER set_timestamp_partes BEFORE UPDATE ON public.partes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
CREATE TRIGGER set_timestamp_variantes BEFORE UPDATE ON public.variantes FOR EACH ROW EXECUTE FUNCTION public.update_fecha_modificacion_column();
CREATE TRIGGER trg_actualizar_stock AFTER INSERT ON public.movimientos_inventario FOR EACH ROW EXECUTE FUNCTION public.actualizar_stock_trigger();
CREATE TRIGGER update_partes_updated_at BEFORE UPDATE ON public.partes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
