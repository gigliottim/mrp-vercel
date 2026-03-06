--
-- PostgreSQL database dump
--

\restrict ZclBTJrQjmWfvrOP1SYzKriXbgxj4jlyXgeAisDwDhQykHIZCrAX7p2gkCLXiVH

-- Dumped from database version 18.1
-- Dumped by pg_dump version 18.1

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: mrp
--

-- *not* creating schema, since initdb creates it


ALTER SCHEMA public OWNER TO mrp;

--
-- Name: btree_gist; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS btree_gist WITH SCHEMA public;


--
-- Name: EXTENSION btree_gist; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION btree_gist IS 'support for indexing common datatypes in GiST';


--
-- Name: ltree; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS ltree WITH SCHEMA public;


--
-- Name: EXTENSION ltree; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION ltree IS 'data type for hierarchical tree-like structures';


--
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


--
-- Name: actualizar_stock_trigger(); Type: FUNCTION; Schema: public; Owner: mrp
--

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


ALTER FUNCTION public.actualizar_stock_trigger() OWNER TO mrp;

--
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: public; Owner: mrp
--

CREATE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_updated_at_column() OWNER TO mrp;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: almacenes; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.almacenes (
    id integer NOT NULL,
    codigo character varying(20) NOT NULL,
    nombre character varying(100) NOT NULL,
    es_deposito_venta boolean DEFAULT true,
    es_deposito_produccion boolean DEFAULT false,
    activo boolean DEFAULT true
);


ALTER TABLE public.almacenes OWNER TO mrp;

--
-- Name: almacenes_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.almacenes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.almacenes_id_seq OWNER TO mrp;

--
-- Name: almacenes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.almacenes_id_seq OWNED BY public.almacenes.id;


--
-- Name: bom_cabecera; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.bom_cabecera (
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


ALTER TABLE public.bom_cabecera OWNER TO mrp;

--
-- Name: bom_cabecera_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.bom_cabecera_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.bom_cabecera_id_seq OWNER TO mrp;

--
-- Name: bom_cabecera_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.bom_cabecera_id_seq OWNED BY public.bom_cabecera.id;


--
-- Name: bom_detalle; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.bom_detalle (
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


ALTER TABLE public.bom_detalle OWNER TO mrp;

--
-- Name: bom_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.bom_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.bom_detalle_id_seq OWNER TO mrp;

--
-- Name: bom_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.bom_detalle_id_seq OWNED BY public.bom_detalle.id;


--
-- Name: centros_trabajo; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.centros_trabajo (
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
    CONSTRAINT centros_trabajo_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['manual'::character varying, 'semi_automatico'::character varying, 'automatico'::character varying])::text[])))
);


ALTER TABLE public.centros_trabajo OWNER TO mrp;

--
-- Name: centros_trabajo_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.centros_trabajo_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.centros_trabajo_id_seq OWNER TO mrp;

--
-- Name: centros_trabajo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.centros_trabajo_id_seq OWNED BY public.centros_trabajo.id;


--
-- Name: composicion_variantes; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.composicion_variantes (
    id integer NOT NULL,
    id_padre integer NOT NULL,
    id_hijo integer NOT NULL,
    cantidad numeric(10,4) DEFAULT 1.0000 NOT NULL,
    fecha_creacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion bigint,
    activo boolean DEFAULT true
);


ALTER TABLE public.composicion_variantes OWNER TO mrp;

--
-- Name: composicion_variantes_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.composicion_variantes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.composicion_variantes_id_seq OWNER TO mrp;

--
-- Name: composicion_variantes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.composicion_variantes_id_seq OWNED BY public.composicion_variantes.id;


--
-- Name: configuracion; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.configuracion (
    id integer NOT NULL,
    clave character varying(100) NOT NULL,
    valor text,
    descripcion text,
    tipo character varying(20) DEFAULT 'string'::character varying,
    fecha_actualizacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT configuracion_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['string'::character varying, 'number'::character varying, 'boolean'::character varying, 'json'::character varying])::text[])))
);


ALTER TABLE public.configuracion OWNER TO mrp;

--
-- Name: configuracion_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.configuracion_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.configuracion_id_seq OWNER TO mrp;

--
-- Name: configuracion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.configuracion_id_seq OWNED BY public.configuracion.id;


--
-- Name: grupos_partes; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.grupos_partes (
    id integer NOT NULL,
    codigo character varying(20) NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion text,
    color character varying(7) DEFAULT '#007bff'::character varying,
    activo boolean DEFAULT true,
    fecha_creacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.grupos_partes OWNER TO mrp;

--
-- Name: grupos_partes_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.grupos_partes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.grupos_partes_id_seq OWNER TO mrp;

--
-- Name: grupos_partes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.grupos_partes_id_seq OWNED BY public.grupos_partes.id;


--
-- Name: movimientos_inventario; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.movimientos_inventario (
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
    CONSTRAINT movimientos_inventario_tipo_movimiento_check CHECK (((tipo_movimiento)::text = ANY ((ARRAY['compra_recepcion'::character varying, 'produccion_ingreso'::character varying, 'produccion_consumo'::character varying, 'produccion_descarte'::character varying, 'ajuste_inventario'::character varying, 'venta_despacho'::character varying, 'transferencia_salida'::character varying, 'transferencia_entrada'::character varying])::text[])))
);


ALTER TABLE public.movimientos_inventario OWNER TO mrp;

--
-- Name: movimientos_inventario_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.movimientos_inventario_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.movimientos_inventario_id_seq OWNER TO mrp;

--
-- Name: movimientos_inventario_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.movimientos_inventario_id_seq OWNED BY public.movimientos_inventario.id;


--
-- Name: mrp_calculos_cabecera; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.mrp_calculos_cabecera (
    id integer NOT NULL,
    fecha_calculo timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    escenario character varying(50) DEFAULT 'OFICIAL'::character varying,
    usuario_id bigint,
    parametros_usados jsonb
);


ALTER TABLE public.mrp_calculos_cabecera OWNER TO mrp;

--
-- Name: mrp_calculos_cabecera_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.mrp_calculos_cabecera_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mrp_calculos_cabecera_id_seq OWNER TO mrp;

--
-- Name: mrp_calculos_cabecera_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.mrp_calculos_cabecera_id_seq OWNED BY public.mrp_calculos_cabecera.id;


--
-- Name: mrp_sugerencias; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.mrp_sugerencias (
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
    CONSTRAINT mrp_sugerencias_estado_check CHECK (((estado)::text = ANY ((ARRAY['pendiente'::character varying, 'aprobada'::character varying, 'rechazada'::character varying, 'convertida'::character varying])::text[]))),
    CONSTRAINT mrp_sugerencias_tipo_accion_check CHECK (((tipo_accion)::text = ANY ((ARRAY['producir'::character varying, 'comprar'::character varying, 'transferir'::character varying, 'cancelar_orden'::character varying])::text[])))
);


ALTER TABLE public.mrp_sugerencias OWNER TO mrp;

--
-- Name: mrp_sugerencias_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.mrp_sugerencias_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mrp_sugerencias_id_seq OWNER TO mrp;

--
-- Name: mrp_sugerencias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.mrp_sugerencias_id_seq OWNED BY public.mrp_sugerencias.id;


--
-- Name: ordenes_produccion; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.ordenes_produccion (
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
    CONSTRAINT ordenes_produccion_estado_check CHECK (((estado)::text = ANY ((ARRAY['borrador'::character varying, 'planificada'::character varying, 'liberada'::character varying, 'en_proceso'::character varying, 'pausada'::character varying, 'completada'::character varying, 'cancelada'::character varying, 'cerrada'::character varying])::text[]))),
    CONSTRAINT ordenes_produccion_prioridad_check CHECK (((prioridad)::text = ANY ((ARRAY['baja'::character varying, 'normal'::character varying, 'alta'::character varying, 'urgente'::character varying])::text[])))
);


ALTER TABLE public.ordenes_produccion OWNER TO mrp;

--
-- Name: ordenes_produccion_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.ordenes_produccion_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ordenes_produccion_id_seq OWNER TO mrp;

--
-- Name: ordenes_produccion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.ordenes_produccion_id_seq OWNED BY public.ordenes_produccion.id;


--
-- Name: partes; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.partes (
    id integer NOT NULL,
    codigo character varying(50) NOT NULL,
    id_tipo integer NOT NULL,
    id_grupo integer NOT NULL,
    detalle character varying(255) NOT NULL,
    largo_alto numeric(10,4),
    id_um_largo_alto integer,
    ancho numeric(10,4),
    id_um_ancho integer,
    espesor_profundidad numeric(10,4),
    id_um_espesor integer,
    superficie numeric(10,4),
    id_um_superficie integer,
    volumen numeric(10,4),
    id_um_volumen integer,
    atributos_base jsonb DEFAULT '{}'::jsonb,
    reglas_configuracion jsonb DEFAULT '{}'::jsonb,
    activo boolean DEFAULT true,
    creado_por bigint,
    fecha_creacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.partes OWNER TO mrp;

--
-- Name: partes_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.partes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.partes_id_seq OWNER TO mrp;

--
-- Name: partes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.partes_id_seq OWNED BY public.partes.id;


--
-- Name: planificacion_recursos; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.planificacion_recursos (
    id integer NOT NULL,
    orden_produccion_id integer NOT NULL,
    operacion_id integer,
    centro_trabajo_id integer NOT NULL,
    periodo tsrange NOT NULL,
    estado character varying(20) DEFAULT 'programado'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT periodo_valido CHECK ((lower(periodo) < upper(periodo))),
    CONSTRAINT planificacion_recursos_estado_check CHECK (((estado)::text = ANY ((ARRAY['programado'::character varying, 'en_ejecucion'::character varying, 'completado'::character varying])::text[])))
);


ALTER TABLE public.planificacion_recursos OWNER TO mrp;

--
-- Name: planificacion_recursos_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.planificacion_recursos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.planificacion_recursos_id_seq OWNER TO mrp;

--
-- Name: planificacion_recursos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.planificacion_recursos_id_seq OWNED BY public.planificacion_recursos.id;


--
-- Name: rutas_produccion; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.rutas_produccion (
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


ALTER TABLE public.rutas_produccion OWNER TO mrp;

--
-- Name: rutas_produccion_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.rutas_produccion_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.rutas_produccion_id_seq OWNER TO mrp;

--
-- Name: rutas_produccion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.rutas_produccion_id_seq OWNED BY public.rutas_produccion.id;


--
-- Name: tipos_partes; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.tipos_partes (
    id integer NOT NULL,
    codigo character varying(50) NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion text,
    orden integer DEFAULT 0,
    activo boolean DEFAULT true,
    fecha_creacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.tipos_partes OWNER TO mrp;

--
-- Name: tipos_partes_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.tipos_partes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tipos_partes_id_seq OWNER TO mrp;

--
-- Name: tipos_partes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.tipos_partes_id_seq OWNED BY public.tipos_partes.id;


--
-- Name: unidades_medida; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.unidades_medida (
    id integer NOT NULL,
    tipo character varying(20) NOT NULL,
    unidad character varying(50) NOT NULL,
    simbolo character varying(10) NOT NULL,
    equivalencia_base numeric(15,8) NOT NULL,
    es_base boolean DEFAULT false,
    activo boolean DEFAULT true,
    fecha_creacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unidades_medida_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['longitud'::character varying, 'superficie'::character varying, 'volumen'::character varying, 'masa'::character varying, 'tiempo'::character varying, 'temperatura'::character varying, 'unidad'::character varying])::text[])))
);


ALTER TABLE public.unidades_medida OWNER TO mrp;

--
-- Name: unidades_medida_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.unidades_medida_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.unidades_medida_id_seq OWNER TO mrp;

--
-- Name: unidades_medida_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.unidades_medida_id_seq OWNED BY public.unidades_medida.id;


--
-- Name: variantes; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.variantes (
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
    CONSTRAINT variantes_estado_check CHECK (((estado)::text = ANY ((ARRAY['activa'::character varying, 'obsoleta'::character varying, 'descontinuada'::character varying, 'desarrollo'::character varying])::text[])))
);


ALTER TABLE public.variantes OWNER TO mrp;

--
-- Name: variantes_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.variantes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.variantes_id_seq OWNER TO mrp;

--
-- Name: variantes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.variantes_id_seq OWNED BY public.variantes.id;


--
-- Name: almacenes id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.almacenes ALTER COLUMN id SET DEFAULT nextval('public.almacenes_id_seq'::regclass);


--
-- Name: bom_cabecera id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.bom_cabecera ALTER COLUMN id SET DEFAULT nextval('public.bom_cabecera_id_seq'::regclass);


--
-- Name: bom_detalle id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.bom_detalle ALTER COLUMN id SET DEFAULT nextval('public.bom_detalle_id_seq'::regclass);


--
-- Name: centros_trabajo id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.centros_trabajo ALTER COLUMN id SET DEFAULT nextval('public.centros_trabajo_id_seq'::regclass);


--
-- Name: composicion_variantes id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.composicion_variantes ALTER COLUMN id SET DEFAULT nextval('public.composicion_variantes_id_seq'::regclass);


--
-- Name: configuracion id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.configuracion ALTER COLUMN id SET DEFAULT nextval('public.configuracion_id_seq'::regclass);


--
-- Name: grupos_partes id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.grupos_partes ALTER COLUMN id SET DEFAULT nextval('public.grupos_partes_id_seq'::regclass);


--
-- Name: movimientos_inventario id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.movimientos_inventario ALTER COLUMN id SET DEFAULT nextval('public.movimientos_inventario_id_seq'::regclass);


--
-- Name: mrp_calculos_cabecera id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.mrp_calculos_cabecera ALTER COLUMN id SET DEFAULT nextval('public.mrp_calculos_cabecera_id_seq'::regclass);


--
-- Name: mrp_sugerencias id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.mrp_sugerencias ALTER COLUMN id SET DEFAULT nextval('public.mrp_sugerencias_id_seq'::regclass);


--
-- Name: ordenes_produccion id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.ordenes_produccion ALTER COLUMN id SET DEFAULT nextval('public.ordenes_produccion_id_seq'::regclass);


--
-- Name: partes id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.partes ALTER COLUMN id SET DEFAULT nextval('public.partes_id_seq'::regclass);


--
-- Name: planificacion_recursos id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.planificacion_recursos ALTER COLUMN id SET DEFAULT nextval('public.planificacion_recursos_id_seq'::regclass);


--
-- Name: rutas_produccion id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.rutas_produccion ALTER COLUMN id SET DEFAULT nextval('public.rutas_produccion_id_seq'::regclass);


--
-- Name: tipos_partes id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.tipos_partes ALTER COLUMN id SET DEFAULT nextval('public.tipos_partes_id_seq'::regclass);


--
-- Name: unidades_medida id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.unidades_medida ALTER COLUMN id SET DEFAULT nextval('public.unidades_medida_id_seq'::regclass);


--
-- Name: variantes id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.variantes ALTER COLUMN id SET DEFAULT nextval('public.variantes_id_seq'::regclass);


--
-- Data for Name: almacenes; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.almacenes (id, codigo, nombre, es_deposito_venta, es_deposito_produccion, activo) FROM stdin;
1	GRAL	Almac??n General	t	t	t
\.


--
-- Data for Name: bom_cabecera; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.bom_cabecera (id, variante_padre_id, version, activa, fecha_efectiva, fecha_vencimiento, observaciones, aprobada_por, fecha_aprobacion, created_at, updated_at) FROM stdin;
1	5	1.0	t	2026-02-11	\N	\N	1	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
3	58	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:12.7885+00	2026-02-12 02:34:12.7885+00
4	59	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:13.088965+00	2026-02-12 02:34:13.088965+00
5	62	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:13.837556+00	2026-02-12 02:34:13.837556+00
6	65	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:14.589634+00	2026-02-12 02:34:14.589634+00
7	70	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:15.712178+00	2026-02-12 02:34:15.712178+00
8	71	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:16.012772+00	2026-02-12 02:34:16.012772+00
9	77	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:17.463747+00	2026-02-12 02:34:17.463747+00
10	83	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:18.888648+00	2026-02-12 02:34:18.888648+00
11	84	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:19.187478+00	2026-02-12 02:34:19.187478+00
12	89	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:20.396652+00	2026-02-12 02:34:20.396652+00
13	92	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:21.07147+00	2026-02-12 02:34:21.07147+00
14	93	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:21.370898+00	2026-02-12 02:34:21.370898+00
15	97	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:22.348141+00	2026-02-12 02:34:22.348141+00
16	102	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:23.547717+00	2026-02-12 02:34:23.547717+00
17	105	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:24.221101+00	2026-02-12 02:34:24.221101+00
18	107	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:24.746411+00	2026-02-12 02:34:24.746411+00
19	110	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:25.496458+00	2026-02-12 02:34:25.496458+00
20	115	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:26.620997+00	2026-02-12 02:34:26.620997+00
21	118	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:27.363728+00	2026-02-12 02:34:27.363728+00
22	124	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:28.887812+00	2026-02-12 02:34:28.887812+00
23	127	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:29.638814+00	2026-02-12 02:34:29.638814+00
24	130	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:30.388449+00	2026-02-12 02:34:30.388449+00
25	133	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:31.063056+00	2026-02-12 02:34:31.063056+00
26	135	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:31.597337+00	2026-02-12 02:34:31.597337+00
27	140	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:32.717711+00	2026-02-12 02:34:32.717711+00
28	141	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:33.010344+00	2026-02-12 02:34:33.010344+00
29	146	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:34.211673+00	2026-02-12 02:34:34.211673+00
30	149	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:34.952134+00	2026-02-12 02:34:34.952134+00
31	154	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:36.152323+00	2026-02-12 02:34:36.152323+00
32	158	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:37.0693+00	2026-02-12 02:34:37.0693+00
33	159	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:37.369939+00	2026-02-12 02:34:37.369939+00
34	162	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:38.101063+00	2026-02-12 02:34:38.101063+00
35	167	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:39.260424+00	2026-02-12 02:34:39.260424+00
36	171	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:40.237581+00	2026-02-12 02:34:40.237581+00
37	174	1.0	t	2026-02-12	\N	\N	\N	\N	2026-02-12 02:34:40.987286+00	2026-02-12 02:34:40.987286+00
40	57	1.0	t	2026-02-14	\N	\N	\N	\N	2026-02-14 20:28:04.58899+00	2026-02-14 20:28:04.58899+00
\.


--
-- Data for Name: bom_detalle; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.bom_detalle (id, bom_id, variante_componente_id, cantidad_necesaria, unidad_medida_id, desperdicio_porcentaje, es_opcional, secuencia, costo_unitario_estimado, tiempo_setup_mins, tiempo_proceso_mins, condicion_aplicacion, observaciones, created_at) FROM stdin;
55	3	59	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:12.865716+00
56	4	60	2.50000	8	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:13.163511+00
57	4	61	0.20000	8	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:13.393417+00
58	4	62	0.10000	8	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:13.615903+00
59	5	63	0.08000	8	0.00	f	3	0.0000	0	0	\N	\N	2026-02-12 02:34:13.912566+00
60	5	64	0.02000	8	0.00	f	3	0.0000	0	0	\N	\N	2026-02-12 02:34:14.141574+00
61	3	65	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:14.365304+00
62	6	66	2.00000	7	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:14.663438+00
63	6	67	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:14.892533+00
64	6	68	50.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:15.11593+00
65	3	69	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:15.340947+00
66	7	71	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:15.787873+00
67	8	72	0.80000	8	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:16.088702+00
68	8	73	12.00000	7	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:16.317027+00
69	8	74	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:16.541132+00
70	8	75	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:16.766738+00
71	8	76	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:16.996354+00
72	7	77	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:17.230528+00
73	9	78	40.00000	7	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:17.538119+00
74	9	79	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:17.767856+00
75	9	80	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:17.992808+00
76	9	81	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:18.216291+00
77	9	82	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:18.441068+00
78	7	83	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:18.668032+00
79	10	84	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:18.964057+00
80	11	85	0.10000	8	0.00	f	3	0.0000	0	0	\N	\N	2026-02-12 02:34:19.263597+00
81	11	86	12.00000	7	0.00	f	3	0.0000	0	0	\N	\N	2026-02-12 02:34:19.493794+00
82	11	87	1.00000	13	0.00	f	3	0.0000	0	0	\N	\N	2026-02-12 02:34:19.716129+00
83	10	88	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:19.941317+00
84	10	89	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:20.173588+00
85	12	90	0.30000	9	0.00	f	3	0.0000	0	0	\N	\N	2026-02-12 02:34:20.471395+00
86	12	91	6.00000	7	0.00	f	3	0.0000	0	0	\N	\N	2026-02-12 02:34:20.702531+00
87	13	93	2.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:21.147793+00
88	14	94	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:21.446463+00
89	14	95	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:21.675731+00
90	14	96	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:21.900824+00
91	13	97	2.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:22.123601+00
92	15	98	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:22.421059+00
93	15	99	8.00000	7	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:22.651512+00
94	15	100	4.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:22.875719+00
95	13	101	2.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:23.100356+00
96	13	102	2.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:23.326269+00
97	16	103	1.60000	9	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:23.622118+00
98	16	104	4.00000	7	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:23.849411+00
99	17	106	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:24.296326+00
100	17	107	2.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:24.525+00
101	18	108	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:24.821036+00
102	18	109	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:25.048793+00
103	17	110	2.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:25.275751+00
104	19	111	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:25.572876+00
105	19	112	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:25.799798+00
106	19	113	20.00000	7	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:26.024489+00
107	17	114	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:26.25004+00
108	20	116	2.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:26.696177+00
109	20	117	72.00000	7	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:26.91757+00
110	20	118	2.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:27.141734+00
111	21	119	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:27.438193+00
112	21	120	4.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:27.709443+00
113	21	121	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:27.933564+00
114	20	122	2.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:28.283569+00
115	20	123	2.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:28.51718+00
116	22	125	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:28.962437+00
117	22	126	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:29.192186+00
118	22	127	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:29.418056+00
119	23	128	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:29.713592+00
120	23	129	4.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:29.942002+00
121	22	130	2.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:30.168498+00
122	24	131	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:30.463818+00
123	24	132	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:30.691291+00
124	25	134	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:31.139426+00
125	25	135	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:31.367759+00
126	26	136	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:31.670761+00
127	26	137	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:31.900431+00
128	26	138	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:32.123181+00
129	25	139	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:32.346992+00
130	27	141	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:32.785211+00
131	28	142	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:33.085708+00
132	28	143	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:33.315037+00
133	28	144	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:33.541471+00
134	27	145	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:33.764279+00
135	27	146	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:33.988537+00
136	29	147	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:34.284453+00
137	29	148	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:34.505647+00
138	27	149	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:34.729678+00
139	30	150	3.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:35.026245+00
140	30	151	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:35.255559+00
141	30	152	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:35.48096+00
142	27	153	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:35.706911+00
143	27	154	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:35.929253+00
144	31	155	2.00000	9	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:36.226359+00
145	31	156	10.00000	9	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:36.471184+00
146	31	157	12.00000	7	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:36.696909+00
147	32	159	2.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:37.143461+00
148	33	160	2.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:37.442488+00
149	33	161	4.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:37.663106+00
150	32	162	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:37.8834+00
151	34	163	0.50000	8	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:38.167899+00
152	34	164	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:38.38148+00
153	32	165	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:38.597035+00
154	32	166	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:38.82215+00
155	32	167	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:39.039701+00
156	35	168	3.00000	8	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:39.33554+00
157	35	169	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:39.563965+00
158	32	170	4.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:39.789653+00
159	32	171	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:40.014826+00
160	36	172	0.20000	8	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:40.316627+00
161	36	173	4.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:40.538959+00
162	32	174	1.00000	13	0.00	f	1	0.0000	0	0	\N	\N	2026-02-12 02:34:40.76363+00
163	37	175	4.00000	7	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:41.062109+00
164	37	176	1.00000	13	0.00	f	2	0.0000	0	0	\N	\N	2026-02-12 02:34:41.397939+00
165	40	58	1.00000	7	0.00	f	0	0.0000	0	0	\N	\N	2026-02-14 20:28:04.646544+00
166	40	70	1.00000	7	0.00	f	0	0.0000	0	0	\N	\N	2026-02-14 20:29:11.448929+00
167	40	92	2.00000	7	0.00	f	0	0.0000	0	0	\N	\N	2026-02-14 20:31:08.63019+00
168	40	105	1.00000	7	0.00	f	0	0.0000	0	0	\N	\N	2026-02-14 20:32:14.231027+00
169	40	115	2.00000	7	0.00	f	0	0.0000	0	0	\N	\N	2026-02-14 20:32:50.913634+00
170	40	124	1.00000	7	0.00	f	0	0.0000	0	0	\N	\N	2026-02-14 20:33:28.571294+00
171	40	133	1.00000	7	0.00	f	0	0.0000	0	0	\N	\N	2026-02-14 20:34:45.186461+00
172	40	140	1.00000	7	0.00	f	0	0.0000	0	0	\N	\N	2026-02-14 20:35:33.962468+00
173	40	158	1.00000	7	0.00	f	0	0.0000	0	0	\N	\N	2026-02-14 20:35:58.756488+00
\.


--
-- Data for Name: centros_trabajo; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.centros_trabajo (id, codigo, nombre, descripcion, tipo, capacidad_horas_dia, eficiencia_porcentaje, costo_hora, capacidad_finita, calendario_id, activo, ubicacion, responsable, observaciones, created_at, updated_at) FROM stdin;
1	CT-CORTE	Corte y Preparaci??n	\N	manual	8.00	100.00	25.00	f	\N	t	\N	\N	\N	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
2	CT-SOLD	Soldadura TIG	\N	manual	8.00	100.00	45.00	f	\N	t	\N	\N	\N	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
3	CT-PINT	Cabina de Pintura	\N	semi_automatico	16.00	100.00	60.00	f	\N	t	\N	\N	\N	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
4	CT-ENS	Ensamble Final	\N	manual	8.00	100.00	30.00	f	\N	t	\N	\N	\N	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
\.


--
-- Data for Name: composicion_variantes; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.composicion_variantes (id, id_padre, id_hijo, cantidad, fecha_creacion, usuario_creacion, activo) FROM stdin;
\.


--
-- Data for Name: configuracion; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.configuracion (id, clave, valor, descripcion, tipo, fecha_actualizacion) FROM stdin;
\.


--
-- Data for Name: grupos_partes; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.grupos_partes (id, codigo, nombre, descripcion, color, activo, fecha_creacion, fecha_modificacion) FROM stdin;
5	METAL	Metales y Estructuras	\N	#607d8b	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
6	COMP	Componentes Comprados	\N	#ff9800	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
8	BICIS	Bicicletas Completas	\N	#4caf50	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
9	GRAL	General	Grupo General	#007bff	t	2026-02-12 02:15:46.748644+00	2026-02-12 02:15:46.748644+00
10	GEN	General	\N	#007bff	t	2026-02-12 02:34:12.179929+00	2026-02-12 02:34:12.179929+00
7	QUIM	Qu├¡micos y Pinturas		#e91e63	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
\.


--
-- Data for Name: movimientos_inventario; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.movimientos_inventario (id, variante_id, almacen_id, orden_produccion_id, tipo_movimiento, cantidad, signo, costo_unitario_snapshot, fecha_movimiento, usuario_id, observaciones, referencia_documento) FROM stdin;
\.


--
-- Data for Name: mrp_calculos_cabecera; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.mrp_calculos_cabecera (id, fecha_calculo, escenario, usuario_id, parametros_usados) FROM stdin;
\.


--
-- Data for Name: mrp_sugerencias; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.mrp_sugerencias (id, calculo_id, variante_id, tipo_accion, cantidad_actual, cantidad_sugerida, cantidad_reservada, fecha_necesidad, fecha_inicio_sugerida, origen_demanda, orden_origen_id, prioridad, estado, observaciones) FROM stdin;
\.


--
-- Data for Name: ordenes_produccion; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.ordenes_produccion (id, numero_orden, variante_id, bom_id_utilizada, cantidad_planificada, cantidad_producida, cantidad_desechada, fecha_inicio_programada, fecha_fin_programada, fecha_inicio_real, fecha_fin_real, estado, prioridad, configuracion_orden, observaciones, usuario_creador, fecha_creacion, fecha_actualizacion) FROM stdin;
\.


--
-- Data for Name: partes; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.partes (id, codigo, id_tipo, id_grupo, detalle, largo_alto, id_um_largo_alto, ancho, id_um_ancho, espesor_profundidad, id_um_espesor, superficie, id_um_superficie, volumen, id_um_volumen, atributos_base, reglas_configuracion, activo, creado_por, fecha_creacion, fecha_modificacion) FROM stdin;
6	P-ALUM-6061	4	5	Tubo Aluminio 6061 T6	\N	9	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
8	C-RUEDA-MTB	4	6	Rueda Completa MTB	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
9	S-CUADRO-X1	5	5	Cuadro Soldado Modelo X1	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
61	PT	6	10	E-CITY PRO (BICICLETA EL├ëCTRICA URBANA)	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:12.276974+00	2026-02-12 02:34:12.276974+00
62	SC1	10	10	CUADRO PRINCIPAL E-FRAME-AL01	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:12.423511+00	2026-02-12 02:34:12.423511+00
63	SC2	10	10	Cuadro aluminio 6061	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:12.630928+00	2026-02-12 02:34:12.630928+00
64	MP1	4	10	Tubo aluminio	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:12.938376+00	2026-02-12 02:34:12.938376+00
65	MP2	4	10	Soldadura TIG	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:13.238914+00	2026-02-12 02:34:13.238914+00
66	SC3	10	10	Pintura electrost├ítica	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:13.463544+00	2026-02-12 02:34:13.463544+00
67	MP3	4	10	Polvo ep├│xico negro	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:13.690473+00	2026-02-12 02:34:13.690473+00
68	MP4	4	10	Polvo ep├│xico mate	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:13.988806+00	2026-02-12 02:34:13.988806+00
69	SC4	10	10	Horquilla suspensi├│n	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:14.212641+00	2026-02-12 02:34:14.212641+00
70	MP5	4	10	Barras acero	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:14.437942+00	2026-02-12 02:34:14.437942+00
71	MP6	4	10	Resortes	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:14.737709+00	2026-02-12 02:34:14.737709+00
72	MP7	4	10	Aceite suspensi├│n	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:14.963188+00	2026-02-12 02:34:14.963188+00
73	MO1 (Operaci├│n)	7	10	N├║mero serie grabado	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:15.188015+00	2026-02-12 02:34:15.188015+00
74	SC5	10	10	SISTEMA DE TRACCI├ôN E-DRIVE-500	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:15.412476+00	2026-02-12 02:34:15.412476+00
75	SC6	10	10	Motor hub trasero 500W	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:15.563465+00	2026-02-12 02:34:15.563465+00
76	MP8	4	10	Estator cobre	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:15.862759+00	2026-02-12 02:34:15.862759+00
77	MP9	4	10	Imanes neodimio	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:16.163043+00	2026-02-12 02:34:16.163043+00
78	MP10	4	10	Carcasa motor aluminio	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:16.388278+00	2026-02-12 02:34:16.388278+00
79	MP11	4	10	Rodamientos	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:16.612493+00	2026-02-12 02:34:16.612493+00
80	MP12	4	10	Eje acero templado	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:16.838308+00	2026-02-12 02:34:16.838308+00
81	SC7	10	10	Bater├¡a Li-Ion 48V 12Ah	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:17.074944+00	2026-02-12 02:34:17.074944+00
82	MP13	4	10	Celdas 18650	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:17.304581+00	2026-02-12 02:34:17.304581+00
83	MP14	4	10	BMS	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:17.613234+00	2026-02-12 02:34:17.613234+00
84	MP15	4	10	Carcasa bater├¡a ABS	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:17.838035+00	2026-02-12 02:34:17.838035+00
85	MP16	4	10	Conector XT60	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:18.063772+00	2026-02-12 02:34:18.063772+00
86	MO2 (Operaci├│n)	7	10	Ensamble celdas	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:18.288222+00	2026-02-12 02:34:18.288222+00
87	SC8	10	10	Controlador 48V 25A	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:18.513177+00	2026-02-12 02:34:18.513177+00
88	SC9	10	10	PCB potencia	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:18.738243+00	2026-02-12 02:34:18.738243+00
89	MP17	4	10	Placa cobre	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:19.038323+00	2026-02-12 02:34:19.038323+00
90	MP18	4	10	MOSFETs	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:19.339158+00	2026-02-12 02:34:19.339158+00
91	MP19	4	10	Disipador aluminio	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:19.566853+00	2026-02-12 02:34:19.566853+00
92	MP20	4	10	Caja controlador aluminio	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:19.789639+00	2026-02-12 02:34:19.789639+00
93	SC10	10	10	Cableado interno	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:20.013434+00	2026-02-12 02:34:20.013434+00
94	MP21	4	10	Cables silicona	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:20.248315+00	2026-02-12 02:34:20.248315+00
95	MP22	4	10	Conectores JST	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:20.547341+00	2026-02-12 02:34:20.547341+00
96	SC11	10	10	SISTEMA DE FRENOS E-BRAKE-HYD	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:20.77119+00	2026-02-12 02:34:20.77119+00
97	SC12	10	10	Maneta freno izquierda/derecha	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:20.921105+00	2026-02-12 02:34:20.921105+00
98	MP23	4	10	Cuerpo aleaci├│n aluminio	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:21.221449+00	2026-02-12 02:34:21.221449+00
99	MP24	4	10	Bomba hidr├íulica	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:21.521498+00	2026-02-12 02:34:21.521498+00
100	MP25	4	10	Dep├│sito l├¡quido	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:21.747326+00	2026-02-12 02:34:21.747326+00
101	SC13	10	10	Pinza freno 4 pistones	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:21.972376+00	2026-02-12 02:34:21.972376+00
102	MP26	4	10	Cuerpo pinza CNC	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:22.196721+00	2026-02-12 02:34:22.196721+00
103	MP27	4	10	Pistones cer├ímicos	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:22.498095+00	2026-02-12 02:34:22.498095+00
104	MP28	4	10	Pastillas sinterizadas	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:22.721095+00	2026-02-12 02:34:22.721095+00
105	MP29	4	10	Discos 180mm	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:22.947322+00	2026-02-12 02:34:22.947322+00
106	SC14	10	10	L├¡nea hidr├íulica	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:23.172951+00	2026-02-12 02:34:23.172951+00
107	MP30	4	10	Manguera trenzada	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:23.396645+00	2026-02-12 02:34:23.396645+00
108	MP31	4	10	Conectores lat├│n	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:23.698112+00	2026-02-12 02:34:23.698112+00
109	SC15	10	10	TRANSMISI├ôN E-DRIVE-TRAIN	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:23.921149+00	2026-02-12 02:34:23.921149+00
110	MP32	4	10	Plato 44T	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:24.072167+00	2026-02-12 02:34:24.072167+00
111	SC16	10	10	Bielas aluminio forjado	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:24.373022+00	2026-02-12 02:34:24.373022+00
112	MP33	4	10	Biela izquierda	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:24.598094+00	2026-02-12 02:34:24.598094+00
113	MP34	4	10	Biela derecha	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:24.89922+00	2026-02-12 02:34:24.89922+00
114	SC17	10	10	Pedales antideslizantes	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:25.121406+00	2026-02-12 02:34:25.121406+00
115	MP35	4	10	Cuerpo pedal aleaci├│n	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:25.346006+00	2026-02-12 02:34:25.346006+00
116	MP36	4	10	Eje cromoly	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:25.646721+00	2026-02-12 02:34:25.646721+00
117	MP37	4	10	Pines acero	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:25.871923+00	2026-02-12 02:34:25.871923+00
118	MP38	4	10	Cadena reforzada	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:26.09791+00	2026-02-12 02:34:26.09791+00
119	SC18	10	10	SISTEMA DE RUEDAS E-WHEEL-26	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:26.320957+00	2026-02-12 02:34:26.320957+00
120	MP39	4	10	Llanta aluminio doble pared	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:26.473058+00	2026-02-12 02:34:26.473058+00
121	MP40	4	10	Radios acero inox	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:26.762983+00	2026-02-12 02:34:26.762983+00
122	SC19	10	10	Buje sellado	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:26.989387+00	2026-02-12 02:34:26.989387+00
123	MP41	4	10	Cuerpo buje	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:27.213851+00	2026-02-12 02:34:27.213851+00
124	MP42	4	10	Rodamientos sellados	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:27.513348+00	2026-02-12 02:34:27.513348+00
125	MP43	4	10	Eje 10mm	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:27.781051+00	2026-02-12 02:34:27.781051+00
126	MP44	4	10	Neum├ítico 26x2.0	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:28.005487+00	2026-02-12 02:34:28.005487+00
127	MP45	4	10	C├ímara aire	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:28.362817+00	2026-02-12 02:34:28.362817+00
128	SC20	10	10	SISTEMA DE DIRECCI├ôN E-STEER	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:28.588109+00	2026-02-12 02:34:28.588109+00
129	MP46	4	10	Manillar aluminio 720mm	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:28.740056+00	2026-02-12 02:34:28.740056+00
130	MP47	4	10	Potencia aluminio	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:29.038263+00	2026-02-12 02:34:29.038263+00
131	SC21	10	10	Juego direcci├│n sellado	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:29.262736+00	2026-02-12 02:34:29.262736+00
132	MP48	4	10	Rodamientos direcci├│n	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:29.489169+00	2026-02-12 02:34:29.489169+00
133	MP49	4	10	Espaciadores	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:29.788357+00	2026-02-12 02:34:29.788357+00
134	SC22	10	10	Pu├▒os ergon├│micos	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:30.012402+00	2026-02-12 02:34:30.012402+00
135	MP50	4	10	Base caucho	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:30.239554+00	2026-02-12 02:34:30.239554+00
136	MP51	4	10	Bloqueo aluminio	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:30.539735+00	2026-02-12 02:34:30.539735+00
137	SC23	10	10	SISTEMA DE ASIENTO E-SEAT	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:30.763323+00	2026-02-12 02:34:30.763323+00
138	MP52	4	10	Tija sill├¡n aluminio	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:30.913354+00	2026-02-12 02:34:30.913354+00
139	SC24	10	10	Sill├¡n gel confort	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:31.213792+00	2026-02-12 02:34:31.213792+00
140	MP53	4	10	Base pl├ística	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:31.438238+00	2026-02-12 02:34:31.438238+00
141	MP54	4	10	Espuma moldeada	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:31.747946+00	2026-02-12 02:34:31.747946+00
142	MP55	4	10	Tapiz impermeable	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:31.971604+00	2026-02-12 02:34:31.971604+00
143	MP56	4	10	Abrazadera tija	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:32.194391+00	2026-02-12 02:34:32.194391+00
144	SC25	10	10	SISTEMA EL├ëCTRICO AUXILIAR E-ELEC	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:32.420456+00	2026-02-12 02:34:32.420456+00
145	SC26	10	10	Display LCD multifunci├│n	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:32.568451+00	2026-02-12 02:34:32.568451+00
146	MP57	4	10	Pantalla LCD	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:32.862312+00	2026-02-12 02:34:32.862312+00
147	MP58	4	10	PCB control	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:33.16038+00	2026-02-12 02:34:33.16038+00
148	MP59	4	10	Carcasa ABS	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:33.38493+00	2026-02-12 02:34:33.38493+00
149	MP60	4	10	Sensor pedaleo	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:33.612115+00	2026-02-12 02:34:33.612115+00
150	SC27	10	10	Acelerador de pu├▒o	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:33.834664+00	2026-02-12 02:34:33.834664+00
151	MP61	4	10	Sensor efecto hall	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:34.060604+00	2026-02-12 02:34:34.060604+00
152	MP62	4	10	Cuerpo pl├ístico	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:34.352606+00	2026-02-12 02:34:34.352606+00
153	SC28	10	10	Faro LED 48V	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:34.577443+00	2026-02-12 02:34:34.577443+00
154	MP63	4	10	LED CREE	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:34.802316+00	2026-02-12 02:34:34.802316+00
155	MP64	4	10	Lente policarbonato	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:35.101881+00	2026-02-12 02:34:35.101881+00
156	MP65	4	10	Carcasa aluminio	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:35.326656+00	2026-02-12 02:34:35.326656+00
157	MP66	4	10	Piloto trasero	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:35.55213+00	2026-02-12 02:34:35.55213+00
158	SC29	10	10	Cableado completo	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:35.776981+00	2026-02-12 02:34:35.776981+00
159	MP67	4	10	Manguera protectora	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:36.0024+00	2026-02-12 02:34:36.0024+00
160	MP68	4	10	Cables colores	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:36.318694+00	2026-02-12 02:34:36.318694+00
161	MP69	4	10	Conectores impermeables	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:36.54351+00	2026-02-12 02:34:36.54351+00
162	SC30	10	10	ACCESORIOS Y EMPAQUE E-ACC	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:36.768605+00	2026-02-12 02:34:36.768605+00
163	SC31	10	10	Guardabarros aluminio	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:36.919837+00	2026-02-12 02:34:36.919837+00
164	MP70	4	10	Perfil aluminio	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:37.219878+00	2026-02-12 02:34:37.219878+00
165	MP71	4	10	Soportes acero	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:37.512951+00	2026-02-12 02:34:37.512951+00
166	SC32	10	10	Portaequipajes trasero	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:37.734922+00	2026-02-12 02:34:37.734922+00
167	MP72	4	10	Tubo acero	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:37.952311+00	2026-02-12 02:34:37.952311+00
168	MO3 (Operaci├│n)	7	10	Soldadura	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:38.235148+00	2026-02-12 02:34:38.235148+00
169	MP73	4	10	Caballete lateral	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:38.452469+00	2026-02-12 02:34:38.452469+00
170	MP74	4	10	Timbre cl├ísico	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:38.668551+00	2026-02-12 02:34:38.668551+00
171	SC33	10	10	Caja cart├│n bicicleta	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:38.892838+00	2026-02-12 02:34:38.892838+00
172	MP75	4	10	Cart├│n microcanal	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:39.112418+00	2026-02-12 02:34:39.112418+00
173	MO4 (Operaci├│n)	7	10	Impresi├│n serigr├ífica	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:39.411761+00	2026-02-12 02:34:39.411761+00
174	MP76	4	10	Espumas protecci├│n	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:39.634795+00	2026-02-12 02:34:39.634795+00
175	SC34	10	10	Manual usuario	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:39.86036+00	2026-02-12 02:34:39.86036+00
176	MP77	4	10	Papel reciclado	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:40.087108+00	2026-02-12 02:34:40.087108+00
177	MP78	4	10	´╝ì´╝ì Grapas	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:40.386602+00	2026-02-12 02:34:40.386602+00
178	SC35	10	10	Herramienta ensamble b├ísica	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:40.609842+00	2026-02-12 02:34:40.609842+00
179	MP79	4	10	Llaves Allen	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:40.835491+00	2026-02-12 02:34:40.835491+00
180	MP80	4	10	Llave 15mm	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-02-12 02:34:41.135451+00	2026-02-12 02:34:41.135451+00
\.


--
-- Data for Name: planificacion_recursos; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.planificacion_recursos (id, orden_produccion_id, operacion_id, centro_trabajo_id, periodo, estado, created_at) FROM stdin;
\.


--
-- Data for Name: rutas_produccion; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.rutas_produccion (id, bom_id, secuencia, centro_trabajo_id, descripcion, tiempo_setup_mins, tiempo_proceso_unitario_mins, tiempo_cola_mins, tiempo_movimiento_mins, capacidad_requerida, costo_operacion_fijo, costo_operacion_variable, instrucciones, created_at) FROM stdin;
1	1	10	3	Aplicar base y pintura roja	0	45.00	0	0	1.00	0.00	0.00	\N	2026-02-11 19:38:11.67773+00
\.


--
-- Data for Name: tipos_partes; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.tipos_partes (id, codigo, nombre, descripcion, orden, activo, fecha_creacion, fecha_modificacion) FROM stdin;
4	MP	Materia Prima	\N	1	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
5	SA	Sub-Ensamblaje	\N	2	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
6	PT	Producto Terminado	\N	3	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
7	MO	Mano de Obra	Servicios de labor	10	t	2026-02-11 20:30:02.042129+00	2026-02-11 20:30:02.042129+00
8	MAT	Matriz/Herramental	Moldes y matrices	11	t	2026-02-11 20:30:02.042129+00	2026-02-11 20:30:02.042129+00
9	PART	Parte Standard	\N	0	t	2026-02-12 02:15:46.815179+00	2026-02-12 02:15:46.815179+00
10	SC	Subconjunto	\N	0	t	2026-02-12 02:33:07.732531+00	2026-02-12 02:33:07.732531+00
\.


--
-- Data for Name: unidades_medida; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.unidades_medida (id, tipo, unidad, simbolo, equivalencia_base, es_base, activo, fecha_creacion, fecha_modificacion) FROM stdin;
70	longitud	Kil├│metro	km	1000.00000000	f	t	2026-02-14 20:03:54.312413+00	2026-02-14 20:03:54.312413+00
71	longitud	Pulgada	in	0.02540000	f	t	2026-02-14 20:03:54.368892+00	2026-02-14 20:03:54.368892+00
72	longitud	Pie	ft	0.30480000	f	t	2026-02-14 20:03:54.427207+00	2026-02-14 20:03:54.427207+00
73	longitud	Yarda	yd	0.91440000	f	t	2026-02-14 20:03:54.485226+00	2026-02-14 20:03:54.485226+00
74	longitud	Milla	mi	1609.34400000	f	t	2026-02-14 20:03:54.543858+00	2026-02-14 20:03:54.543858+00
75	superficie	Cent├¡metro cuadrado	cm┬▓	0.00010000	f	t	2026-02-14 20:03:54.601934+00	2026-02-14 20:03:54.601934+00
7	unidad	Unidad	unid	1.00000000	t	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
8	masa	Kilogramo	kg	1.00000000	t	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
9	longitud	Metro	m	1.00000000	t	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
76	superficie	Metro cuadrado	m┬▓	1.00000000	t	t	2026-02-14 20:03:54.660659+00	2026-02-14 20:03:54.660659+00
11	volumen	Litro	l	0.00100000	f	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
12	tiempo	Hora	h	3600.00000000	f	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
10	longitud	Cent├¡metro	cm	0.01000000	f	t	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
77	superficie	Hect├írea	ha	10000.00000000	f	t	2026-02-14 20:03:54.726881+00	2026-02-14 20:03:54.726881+00
78	superficie	Kil├│metro cuadrado	km┬▓	1000000.00000000	f	t	2026-02-14 20:03:54.785572+00	2026-02-14 20:03:54.785572+00
79	superficie	Pulgada cuadrada	in┬▓	0.00064516	f	t	2026-02-14 20:03:54.851905+00	2026-02-14 20:03:54.851905+00
80	superficie	Pie cuadrado	ft┬▓	0.09290304	f	t	2026-02-14 20:03:54.910617+00	2026-02-14 20:03:54.910617+00
81	volumen	Centilitro	cl	0.01000000	f	t	2026-02-14 20:03:54.96859+00	2026-02-14 20:03:54.96859+00
82	volumen	Decilitro	dl	0.10000000	f	t	2026-02-14 20:03:55.02762+00	2026-02-14 20:03:55.02762+00
83	volumen	Metro c├║bico	m┬│	1000.00000000	f	t	2026-02-14 20:03:55.094091+00	2026-02-14 20:03:55.094091+00
84	volumen	Gal├│n (US)	gal	3.78541000	f	t	2026-02-14 20:03:55.152218+00	2026-02-14 20:03:55.152218+00
85	volumen	Onza l├¡quida (US)	fl oz	0.02957350	f	t	2026-02-14 20:03:55.218798+00	2026-02-14 20:03:55.218798+00
86	volumen	Pinta (US)	pt	0.47317600	f	t	2026-02-14 20:03:55.277312+00	2026-02-14 20:03:55.277312+00
87	volumen	Cuarto (US)	qt	0.94635300	f	t	2026-02-14 20:03:55.343635+00	2026-02-14 20:03:55.343635+00
88	masa	Gramo	g	0.00100000	f	t	2026-02-14 20:03:55.402524+00	2026-02-14 20:03:55.402524+00
89	masa	Tonelada m├®trica	t	1000.00000000	f	t	2026-02-14 20:03:55.469636+00	2026-02-14 20:03:55.469636+00
90	masa	Onza	oz	0.02834950	f	t	2026-02-14 20:03:55.535605+00	2026-02-14 20:03:55.535605+00
91	masa	Libra	lb	0.45359200	f	t	2026-02-14 20:03:55.593729+00	2026-02-14 20:03:55.593729+00
92	masa	Tonelada corta (US)	ton	907.18500000	f	t	2026-02-14 20:03:55.652459+00	2026-02-14 20:03:55.652459+00
93	tiempo	Segundo	s	1.00000000	t	t	2026-02-14 20:03:55.718771+00	2026-02-14 20:03:55.718771+00
94	tiempo	Minuto	min	60.00000000	f	t	2026-02-14 20:03:55.777397+00	2026-02-14 20:03:55.777397+00
95	tiempo	D├¡a	d	86400.00000000	f	t	2026-02-14 20:03:55.835783+00	2026-02-14 20:03:55.835783+00
96	tiempo	Semana	sem	604800.00000000	f	t	2026-02-14 20:03:55.893847+00	2026-02-14 20:03:55.893847+00
97	temperatura	Fahrenheit	┬░F	1.00000000	f	t	2026-02-14 20:03:55.951958+00	2026-02-14 20:03:55.951958+00
98	temperatura	Kelvin	K	1.00000000	f	t	2026-02-14 20:03:56.010505+00	2026-02-14 20:03:56.010505+00
13	longitud	Unidad Est├índar	UN	1.00000000	t	f	2026-02-12 02:15:46.649767+00	2026-02-12 02:15:46.649767+00
\.


--
-- Data for Name: variantes; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.variantes (id, id_parte, codigo_variante, detalle, estado, lote_minimo, punto_pedido, stock_seguridad, anticipo_compra, lead_time_produccion, stock_actual, peso, id_um_peso, ubicacion_defecto, atributos, creado_por, fecha_creacion, fecha_modificacion) FROM stdin;
1	6	AL-6061-3M	Tubo Aluminio 3 metros	activa	1.00	0.00	0.00	0	0	500.00	\N	\N	\N	{}	\N	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
3	8	R-MTB-29	Rueda MTB 29 Pulgadas	activa	1.00	0.00	0.00	0	0	200.00	\N	\N	\N	{}	\N	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
5	9	CUADRO-X1-RED	Cuadro X1 Pintado Rojo	activa	5.00	0.00	0.00	0	0	5.00	\N	\N	\N	{}	\N	2026-02-11 19:38:11.67773+00	2026-02-11 19:38:11.67773+00
57	61	PT-STD	E-CITY PRO (BICICLETA EL├ëCTRICA URBANA)	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:12.3416+00	2026-02-12 02:34:12.3416+00
58	62	SC1-STD	CUADRO PRINCIPAL E-FRAME-AL01	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:12.496551+00	2026-02-12 02:34:12.496551+00
59	63	SC2-STD	Cuadro aluminio 6061	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:12.705862+00	2026-02-12 02:34:12.705862+00
60	64	MP1-STD	Tubo aluminio	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:13.012065+00	2026-02-12 02:34:13.012065+00
61	65	MP2-STD	Soldadura TIG	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:13.313161+00	2026-02-12 02:34:13.313161+00
62	66	SC3-STD	Pintura electrost├ítica	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:13.537543+00	2026-02-12 02:34:13.537543+00
63	67	MP3-STD	Polvo ep├│xico negro	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:13.763082+00	2026-02-12 02:34:13.763082+00
64	68	MP4-STD	Polvo ep├│xico mate	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:14.06331+00	2026-02-12 02:34:14.06331+00
65	69	SC4-STD	Horquilla suspensi├│n	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:14.288683+00	2026-02-12 02:34:14.288683+00
66	70	MP5-STD	Barras acero	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:14.513344+00	2026-02-12 02:34:14.513344+00
67	71	MP6-STD	Resortes	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:14.812088+00	2026-02-12 02:34:14.812088+00
68	72	MP7-STD	Aceite suspensi├│n	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:15.037747+00	2026-02-12 02:34:15.037747+00
69	73	MO1 (Operaci├│n)-STD	N├║mero serie grabado	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:15.262434+00	2026-02-12 02:34:15.262434+00
70	74	SC5-STD	SISTEMA DE TRACCI├ôN E-DRIVE-500	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:15.488789+00	2026-02-12 02:34:15.488789+00
71	75	SC6-STD	Motor hub trasero 500W	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:15.637661+00	2026-02-12 02:34:15.637661+00
72	76	MP8-STD	Estator cobre	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:15.937892+00	2026-02-12 02:34:15.937892+00
73	77	MP9-STD	Imanes neodimio	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:16.23804+00	2026-02-12 02:34:16.23804+00
74	78	MP10-STD	Carcasa motor aluminio	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:16.463653+00	2026-02-12 02:34:16.463653+00
75	79	MP11-STD	Rodamientos	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:16.689546+00	2026-02-12 02:34:16.689546+00
76	80	MP12-STD	Eje acero templado	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:16.912929+00	2026-02-12 02:34:16.912929+00
77	81	SC7-STD	Bater├¡a Li-Ion 48V 12Ah	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:17.146342+00	2026-02-12 02:34:17.146342+00
78	82	MP13-STD	Celdas 18650	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:17.379831+00	2026-02-12 02:34:17.379831+00
79	83	MP14-STD	BMS	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:17.688026+00	2026-02-12 02:34:17.688026+00
80	84	MP15-STD	Carcasa bater├¡a ABS	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:17.912743+00	2026-02-12 02:34:17.912743+00
81	85	MP16-STD	Conector XT60	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:18.137792+00	2026-02-12 02:34:18.137792+00
82	86	MO2 (Operaci├│n)-STD	Ensamble celdas	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:18.363249+00	2026-02-12 02:34:18.363249+00
83	87	SC8-STD	Controlador 48V 25A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:18.588419+00	2026-02-12 02:34:18.588419+00
84	88	SC9-STD	PCB potencia	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:18.814314+00	2026-02-12 02:34:18.814314+00
85	89	MP17-STD	Placa cobre	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:19.113947+00	2026-02-12 02:34:19.113947+00
86	90	MP18-STD	MOSFETs	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:19.414225+00	2026-02-12 02:34:19.414225+00
87	91	MP19-STD	Disipador aluminio	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:19.637418+00	2026-02-12 02:34:19.637418+00
88	92	MP20-STD	Caja controlador aluminio	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:19.863478+00	2026-02-12 02:34:19.863478+00
89	93	SC10-STD	Cableado interno	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:20.088656+00	2026-02-12 02:34:20.088656+00
90	94	MP21-STD	Cables silicona	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:20.320412+00	2026-02-12 02:34:20.320412+00
91	95	MP22-STD	Conectores JST	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:20.621365+00	2026-02-12 02:34:20.621365+00
92	96	SC11-STD	SISTEMA DE FRENOS E-BRAKE-HYD	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:20.846109+00	2026-02-12 02:34:20.846109+00
93	97	SC12-STD	Maneta freno izquierda/derecha	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:20.99744+00	2026-02-12 02:34:20.99744+00
94	98	MP23-STD	Cuerpo aleaci├│n aluminio	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:21.29589+00	2026-02-12 02:34:21.29589+00
95	99	MP24-STD	Bomba hidr├íulica	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:21.597076+00	2026-02-12 02:34:21.597076+00
96	100	MP25-STD	Dep├│sito l├¡quido	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:21.82239+00	2026-02-12 02:34:21.82239+00
97	101	SC13-STD	Pinza freno 4 pistones	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:22.046169+00	2026-02-12 02:34:22.046169+00
98	102	MP26-STD	Cuerpo pinza CNC	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:22.272654+00	2026-02-12 02:34:22.272654+00
99	103	MP27-STD	Pistones cer├ímicos	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:22.572002+00	2026-02-12 02:34:22.572002+00
100	104	MP28-STD	Pastillas sinterizadas	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:22.798307+00	2026-02-12 02:34:22.798307+00
101	105	MP29-STD	Discos 180mm	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:23.020981+00	2026-02-12 02:34:23.020981+00
102	106	SC14-STD	L├¡nea hidr├íulica	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:23.247064+00	2026-02-12 02:34:23.247064+00
103	107	MP30-STD	Manguera trenzada	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:23.472124+00	2026-02-12 02:34:23.472124+00
104	108	MP31-STD	Conectores lat├│n	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:23.77132+00	2026-02-12 02:34:23.77132+00
105	109	SC15-STD	TRANSMISI├ôN E-DRIVE-TRAIN	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:23.997095+00	2026-02-12 02:34:23.997095+00
106	110	MP32-STD	Plato 44T	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:24.146118+00	2026-02-12 02:34:24.146118+00
107	111	SC16-STD	Bielas aluminio forjado	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:24.447107+00	2026-02-12 02:34:24.447107+00
108	112	MP33-STD	Biela izquierda	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:24.671999+00	2026-02-12 02:34:24.671999+00
109	113	MP34-STD	Biela derecha	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:24.97171+00	2026-02-12 02:34:24.97171+00
110	114	SC17-STD	Pedales antideslizantes	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:25.196452+00	2026-02-12 02:34:25.196452+00
111	115	MP35-STD	Cuerpo pedal aleaci├│n	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:25.421072+00	2026-02-12 02:34:25.421072+00
112	116	MP36-STD	Eje cromoly	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:25.720587+00	2026-02-12 02:34:25.720587+00
113	117	MP37-STD	Pines acero	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:25.946639+00	2026-02-12 02:34:25.946639+00
114	118	MP38-STD	Cadena reforzada	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:26.171858+00	2026-02-12 02:34:26.171858+00
115	119	SC18-STD	SISTEMA DE RUEDAS E-WHEEL-26	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:26.396807+00	2026-02-12 02:34:26.396807+00
116	120	MP39-STD	Llanta aluminio doble pared	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:26.546441+00	2026-02-12 02:34:26.546441+00
117	121	MP40-STD	Radios acero inox	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:26.839746+00	2026-02-12 02:34:26.839746+00
118	122	SC19-STD	Buje sellado	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:27.063234+00	2026-02-12 02:34:27.063234+00
119	123	MP41-STD	Cuerpo buje	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:27.289473+00	2026-02-12 02:34:27.289473+00
120	124	MP42-STD	Rodamientos sellados	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:27.63028+00	2026-02-12 02:34:27.63028+00
121	125	MP43-STD	Eje 10mm	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:27.854681+00	2026-02-12 02:34:27.854681+00
122	126	MP44-STD	Neum├ítico 26x2.0	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:28.082448+00	2026-02-12 02:34:28.082448+00
123	127	MP45-STD	C├ímara aire	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:28.438652+00	2026-02-12 02:34:28.438652+00
124	128	SC20-STD	SISTEMA DE DIRECCI├ôN E-STEER	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:28.662699+00	2026-02-12 02:34:28.662699+00
125	129	MP46-STD	Manillar aluminio 720mm	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:28.812557+00	2026-02-12 02:34:28.812557+00
126	130	MP47-STD	Potencia aluminio	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:29.115634+00	2026-02-12 02:34:29.115634+00
127	131	SC21-STD	Juego direcci├│n sellado	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:29.339332+00	2026-02-12 02:34:29.339332+00
128	132	MP48-STD	Rodamientos direcci├│n	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:29.565922+00	2026-02-12 02:34:29.565922+00
129	133	MP49-STD	Espaciadores	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:29.864126+00	2026-02-12 02:34:29.864126+00
130	134	SC22-STD	Pu├▒os ergon├│micos	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:30.088761+00	2026-02-12 02:34:30.088761+00
131	135	MP50-STD	Base caucho	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:30.31481+00	2026-02-12 02:34:30.31481+00
132	136	MP51-STD	Bloqueo aluminio	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:30.613407+00	2026-02-12 02:34:30.613407+00
133	137	SC23-STD	SISTEMA DE ASIENTO E-SEAT	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:30.838886+00	2026-02-12 02:34:30.838886+00
134	138	MP52-STD	Tija sill├¡n aluminio	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:30.989231+00	2026-02-12 02:34:30.989231+00
135	139	SC24-STD	Sill├¡n gel confort	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:31.287998+00	2026-02-12 02:34:31.287998+00
136	140	MP53-STD	Base pl├ística	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:31.521523+00	2026-02-12 02:34:31.521523+00
137	141	MP54-STD	Espuma moldeada	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:31.821275+00	2026-02-12 02:34:31.821275+00
138	142	MP55-STD	Tapiz impermeable	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:32.045408+00	2026-02-12 02:34:32.045408+00
139	143	MP56-STD	Abrazadera tija	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:32.268659+00	2026-02-12 02:34:32.268659+00
140	144	SC25-STD	SISTEMA EL├ëCTRICO AUXILIAR E-ELEC	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:32.493556+00	2026-02-12 02:34:32.493556+00
141	145	SC26-STD	Display LCD multifunci├│n	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:32.644048+00	2026-02-12 02:34:32.644048+00
142	146	MP57-STD	Pantalla LCD	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:32.935601+00	2026-02-12 02:34:32.935601+00
143	147	MP58-STD	PCB control	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:33.235453+00	2026-02-12 02:34:33.235453+00
144	148	MP59-STD	Carcasa ABS	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:33.459678+00	2026-02-12 02:34:33.459678+00
145	149	MP60-STD	Sensor pedaleo	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:33.685457+00	2026-02-12 02:34:33.685457+00
146	150	SC27-STD	Acelerador de pu├▒o	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:33.910744+00	2026-02-12 02:34:33.910744+00
147	151	MP61-STD	Sensor efecto hall	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:34.134784+00	2026-02-12 02:34:34.134784+00
148	152	MP62-STD	Cuerpo pl├ístico	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:34.426777+00	2026-02-12 02:34:34.426777+00
149	153	SC28-STD	Faro LED 48V	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:34.651546+00	2026-02-12 02:34:34.651546+00
150	154	MP63-STD	LED CREE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:34.87694+00	2026-02-12 02:34:34.87694+00
151	155	MP64-STD	Lente policarbonato	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:35.177687+00	2026-02-12 02:34:35.177687+00
152	156	MP65-STD	Carcasa aluminio	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:35.401201+00	2026-02-12 02:34:35.401201+00
153	157	MP66-STD	Piloto trasero	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:35.626144+00	2026-02-12 02:34:35.626144+00
154	158	SC29-STD	Cableado completo	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:35.851992+00	2026-02-12 02:34:35.851992+00
155	159	MP67-STD	Manguera protectora	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:36.077661+00	2026-02-12 02:34:36.077661+00
156	160	MP68-STD	Cables colores	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:36.392998+00	2026-02-12 02:34:36.392998+00
157	161	MP69-STD	Conectores impermeables	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:36.617779+00	2026-02-12 02:34:36.617779+00
158	162	SC30-STD	ACCESORIOS Y EMPAQUE E-ACC	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:36.843916+00	2026-02-12 02:34:36.843916+00
159	163	SC31-STD	Guardabarros aluminio	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:36.995259+00	2026-02-12 02:34:36.995259+00
160	164	MP70-STD	Perfil aluminio	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:37.296427+00	2026-02-12 02:34:37.296427+00
161	165	MP71-STD	Soportes acero	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:37.585045+00	2026-02-12 02:34:37.585045+00
162	166	SC32-STD	Portaequipajes trasero	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:37.809205+00	2026-02-12 02:34:37.809205+00
163	167	MP72-STD	Tubo acero	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:38.026827+00	2026-02-12 02:34:38.026827+00
164	168	MO3 (Operaci├│n)-STD	Soldadura	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:38.30931+00	2026-02-12 02:34:38.30931+00
165	169	MP73-STD	Caballete lateral	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:38.526052+00	2026-02-12 02:34:38.526052+00
166	170	MP74-STD	Timbre cl├ísico	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:38.74409+00	2026-02-12 02:34:38.74409+00
167	171	SC33-STD	Caja cart├│n bicicleta	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:38.960017+00	2026-02-12 02:34:38.960017+00
168	172	MP75-STD	Cart├│n microcanal	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:39.186505+00	2026-02-12 02:34:39.186505+00
169	173	MO4 (Operaci├│n)-STD	Impresi├│n serigr├ífica	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:39.485825+00	2026-02-12 02:34:39.485825+00
170	174	MP76-STD	Espumas protecci├│n	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:39.71194+00	2026-02-12 02:34:39.71194+00
171	175	SC34-STD	Manual usuario	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:39.934959+00	2026-02-12 02:34:39.934959+00
172	176	MP77-STD	Papel reciclado	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:40.164648+00	2026-02-12 02:34:40.164648+00
173	177	MP78-STD	´╝ì´╝ì Grapas	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:40.461462+00	2026-02-12 02:34:40.461462+00
174	178	SC35-STD	Herramienta ensamble b├ísica	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:40.685866+00	2026-02-12 02:34:40.685866+00
175	179	MP79-STD	Llaves Allen	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:40.912193+00	2026-02-12 02:34:40.912193+00
176	180	MP80-STD	Llave 15mm	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-02-12 02:34:41.260548+00	2026-02-12 02:34:41.260548+00
\.


--
-- Name: almacenes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.almacenes_id_seq', 1, true);


--
-- Name: bom_cabecera_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.bom_cabecera_id_seq', 40, true);


--
-- Name: bom_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.bom_detalle_id_seq', 176, true);


--
-- Name: centros_trabajo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.centros_trabajo_id_seq', 4, true);


--
-- Name: composicion_variantes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.composicion_variantes_id_seq', 1, false);


--
-- Name: configuracion_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.configuracion_id_seq', 1, false);


--
-- Name: grupos_partes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.grupos_partes_id_seq', 10, true);


--
-- Name: movimientos_inventario_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.movimientos_inventario_id_seq', 1, false);


--
-- Name: mrp_calculos_cabecera_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.mrp_calculos_cabecera_id_seq', 1, false);


--
-- Name: mrp_sugerencias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.mrp_sugerencias_id_seq', 1, false);


--
-- Name: ordenes_produccion_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.ordenes_produccion_id_seq', 1, true);


--
-- Name: partes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.partes_id_seq', 180, true);


--
-- Name: planificacion_recursos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.planificacion_recursos_id_seq', 1, false);


--
-- Name: rutas_produccion_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.rutas_produccion_id_seq', 3, true);


--
-- Name: tipos_partes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.tipos_partes_id_seq', 10, true);


--
-- Name: unidades_medida_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.unidades_medida_id_seq', 98, true);


--
-- Name: variantes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.variantes_id_seq', 176, true);


--
-- Name: almacenes almacenes_codigo_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.almacenes
    ADD CONSTRAINT almacenes_codigo_key UNIQUE (codigo);


--
-- Name: almacenes almacenes_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.almacenes
    ADD CONSTRAINT almacenes_pkey PRIMARY KEY (id);


--
-- Name: bom_cabecera bom_cabecera_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.bom_cabecera
    ADD CONSTRAINT bom_cabecera_pkey PRIMARY KEY (id);


--
-- Name: bom_cabecera bom_cabecera_variante_padre_id_version_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.bom_cabecera
    ADD CONSTRAINT bom_cabecera_variante_padre_id_version_key UNIQUE (variante_padre_id, version);


--
-- Name: bom_detalle bom_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.bom_detalle
    ADD CONSTRAINT bom_detalle_pkey PRIMARY KEY (id);


--
-- Name: centros_trabajo centros_trabajo_codigo_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.centros_trabajo
    ADD CONSTRAINT centros_trabajo_codigo_key UNIQUE (codigo);


--
-- Name: centros_trabajo centros_trabajo_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.centros_trabajo
    ADD CONSTRAINT centros_trabajo_pkey PRIMARY KEY (id);


--
-- Name: composicion_variantes composicion_variantes_id_padre_id_hijo_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.composicion_variantes
    ADD CONSTRAINT composicion_variantes_id_padre_id_hijo_key UNIQUE (id_padre, id_hijo);


--
-- Name: composicion_variantes composicion_variantes_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.composicion_variantes
    ADD CONSTRAINT composicion_variantes_pkey PRIMARY KEY (id);


--
-- Name: configuracion configuracion_clave_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.configuracion
    ADD CONSTRAINT configuracion_clave_key UNIQUE (clave);


--
-- Name: configuracion configuracion_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.configuracion
    ADD CONSTRAINT configuracion_pkey PRIMARY KEY (id);


--
-- Name: grupos_partes grupos_partes_codigo_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.grupos_partes
    ADD CONSTRAINT grupos_partes_codigo_key UNIQUE (codigo);


--
-- Name: grupos_partes grupos_partes_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.grupos_partes
    ADD CONSTRAINT grupos_partes_pkey PRIMARY KEY (id);


--
-- Name: movimientos_inventario movimientos_inventario_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_pkey PRIMARY KEY (id);


--
-- Name: mrp_calculos_cabecera mrp_calculos_cabecera_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.mrp_calculos_cabecera
    ADD CONSTRAINT mrp_calculos_cabecera_pkey PRIMARY KEY (id);


--
-- Name: mrp_sugerencias mrp_sugerencias_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.mrp_sugerencias
    ADD CONSTRAINT mrp_sugerencias_pkey PRIMARY KEY (id);


--
-- Name: ordenes_produccion ordenes_produccion_numero_orden_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.ordenes_produccion
    ADD CONSTRAINT ordenes_produccion_numero_orden_key UNIQUE (numero_orden);


--
-- Name: ordenes_produccion ordenes_produccion_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.ordenes_produccion
    ADD CONSTRAINT ordenes_produccion_pkey PRIMARY KEY (id);


--
-- Name: partes partes_codigo_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_codigo_key UNIQUE (codigo);


--
-- Name: partes partes_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_pkey PRIMARY KEY (id);


--
-- Name: planificacion_recursos planificacion_recursos_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.planificacion_recursos
    ADD CONSTRAINT planificacion_recursos_pkey PRIMARY KEY (id);


--
-- Name: rutas_produccion rutas_produccion_bom_id_secuencia_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.rutas_produccion
    ADD CONSTRAINT rutas_produccion_bom_id_secuencia_key UNIQUE (bom_id, secuencia);


--
-- Name: rutas_produccion rutas_produccion_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.rutas_produccion
    ADD CONSTRAINT rutas_produccion_pkey PRIMARY KEY (id);


--
-- Name: tipos_partes tipos_partes_codigo_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.tipos_partes
    ADD CONSTRAINT tipos_partes_codigo_key UNIQUE (codigo);


--
-- Name: tipos_partes tipos_partes_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.tipos_partes
    ADD CONSTRAINT tipos_partes_pkey PRIMARY KEY (id);


--
-- Name: unidades_medida unidades_medida_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_pkey PRIMARY KEY (id);


--
-- Name: unidades_medida unidades_medida_tipo_simbolo_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_tipo_simbolo_key UNIQUE (tipo, simbolo);


--
-- Name: unidades_medida unidades_medida_tipo_unidad_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_tipo_unidad_key UNIQUE (tipo, unidad);


--
-- Name: variantes variantes_id_parte_codigo_variante_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.variantes
    ADD CONSTRAINT variantes_id_parte_codigo_variante_key UNIQUE (id_parte, codigo_variante);


--
-- Name: variantes variantes_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.variantes
    ADD CONSTRAINT variantes_pkey PRIMARY KEY (id);


--
-- Name: idx_movimientos_fecha; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_movimientos_fecha ON public.movimientos_inventario USING btree (fecha_movimiento);


--
-- Name: idx_movimientos_variante; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_movimientos_variante ON public.movimientos_inventario USING btree (variante_id);


--
-- Name: idx_planificacion_periodo; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_planificacion_periodo ON public.planificacion_recursos USING gist (centro_trabajo_id, periodo);


--
-- Name: idx_variantes_atributos; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_variantes_atributos ON public.variantes USING gin (atributos);


--
-- Name: ordenes_produccion set_timestamp_ordenes; Type: TRIGGER; Schema: public; Owner: mrp
--

CREATE TRIGGER set_timestamp_ordenes BEFORE UPDATE ON public.ordenes_produccion FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: partes set_timestamp_partes; Type: TRIGGER; Schema: public; Owner: mrp
--

CREATE TRIGGER set_timestamp_partes BEFORE UPDATE ON public.partes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: variantes set_timestamp_variantes; Type: TRIGGER; Schema: public; Owner: mrp
--

CREATE TRIGGER set_timestamp_variantes BEFORE UPDATE ON public.variantes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: movimientos_inventario trg_actualizar_stock; Type: TRIGGER; Schema: public; Owner: mrp
--

CREATE TRIGGER trg_actualizar_stock AFTER INSERT ON public.movimientos_inventario FOR EACH ROW EXECUTE FUNCTION public.actualizar_stock_trigger();


--
-- Name: bom_cabecera bom_cabecera_variante_padre_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.bom_cabecera
    ADD CONSTRAINT bom_cabecera_variante_padre_id_fkey FOREIGN KEY (variante_padre_id) REFERENCES public.variantes(id);


--
-- Name: bom_detalle bom_detalle_bom_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.bom_detalle
    ADD CONSTRAINT bom_detalle_bom_id_fkey FOREIGN KEY (bom_id) REFERENCES public.bom_cabecera(id) ON DELETE CASCADE;


--
-- Name: bom_detalle bom_detalle_unidad_medida_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.bom_detalle
    ADD CONSTRAINT bom_detalle_unidad_medida_id_fkey FOREIGN KEY (unidad_medida_id) REFERENCES public.unidades_medida(id);


--
-- Name: bom_detalle bom_detalle_variante_componente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.bom_detalle
    ADD CONSTRAINT bom_detalle_variante_componente_id_fkey FOREIGN KEY (variante_componente_id) REFERENCES public.variantes(id);


--
-- Name: composicion_variantes composicion_variantes_id_hijo_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.composicion_variantes
    ADD CONSTRAINT composicion_variantes_id_hijo_fkey FOREIGN KEY (id_hijo) REFERENCES public.variantes(id) ON DELETE CASCADE;


--
-- Name: composicion_variantes composicion_variantes_id_padre_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.composicion_variantes
    ADD CONSTRAINT composicion_variantes_id_padre_fkey FOREIGN KEY (id_padre) REFERENCES public.variantes(id) ON DELETE CASCADE;


--
-- Name: movimientos_inventario movimientos_inventario_almacen_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_almacen_id_fkey FOREIGN KEY (almacen_id) REFERENCES public.almacenes(id);


--
-- Name: movimientos_inventario movimientos_inventario_orden_produccion_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_orden_produccion_id_fkey FOREIGN KEY (orden_produccion_id) REFERENCES public.ordenes_produccion(id);


--
-- Name: movimientos_inventario movimientos_inventario_variante_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_variante_id_fkey FOREIGN KEY (variante_id) REFERENCES public.variantes(id);


--
-- Name: mrp_sugerencias mrp_sugerencias_calculo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.mrp_sugerencias
    ADD CONSTRAINT mrp_sugerencias_calculo_id_fkey FOREIGN KEY (calculo_id) REFERENCES public.mrp_calculos_cabecera(id) ON DELETE CASCADE;


--
-- Name: mrp_sugerencias mrp_sugerencias_variante_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.mrp_sugerencias
    ADD CONSTRAINT mrp_sugerencias_variante_id_fkey FOREIGN KEY (variante_id) REFERENCES public.variantes(id);


--
-- Name: ordenes_produccion ordenes_produccion_bom_id_utilizada_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.ordenes_produccion
    ADD CONSTRAINT ordenes_produccion_bom_id_utilizada_fkey FOREIGN KEY (bom_id_utilizada) REFERENCES public.bom_cabecera(id);


--
-- Name: ordenes_produccion ordenes_produccion_variante_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.ordenes_produccion
    ADD CONSTRAINT ordenes_produccion_variante_id_fkey FOREIGN KEY (variante_id) REFERENCES public.variantes(id);


--
-- Name: partes partes_id_grupo_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_grupo_fkey FOREIGN KEY (id_grupo) REFERENCES public.grupos_partes(id);


--
-- Name: partes partes_id_tipo_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_tipo_fkey FOREIGN KEY (id_tipo) REFERENCES public.tipos_partes(id);


--
-- Name: partes partes_id_um_ancho_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_ancho_fkey FOREIGN KEY (id_um_ancho) REFERENCES public.unidades_medida(id);


--
-- Name: partes partes_id_um_espesor_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_espesor_fkey FOREIGN KEY (id_um_espesor) REFERENCES public.unidades_medida(id);


--
-- Name: partes partes_id_um_largo_alto_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_largo_alto_fkey FOREIGN KEY (id_um_largo_alto) REFERENCES public.unidades_medida(id);


--
-- Name: partes partes_id_um_superficie_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_superficie_fkey FOREIGN KEY (id_um_superficie) REFERENCES public.unidades_medida(id);


--
-- Name: partes partes_id_um_volumen_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_volumen_fkey FOREIGN KEY (id_um_volumen) REFERENCES public.unidades_medida(id);


--
-- Name: planificacion_recursos planificacion_recursos_centro_trabajo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.planificacion_recursos
    ADD CONSTRAINT planificacion_recursos_centro_trabajo_id_fkey FOREIGN KEY (centro_trabajo_id) REFERENCES public.centros_trabajo(id);


--
-- Name: planificacion_recursos planificacion_recursos_operacion_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.planificacion_recursos
    ADD CONSTRAINT planificacion_recursos_operacion_id_fkey FOREIGN KEY (operacion_id) REFERENCES public.rutas_produccion(id);


--
-- Name: planificacion_recursos planificacion_recursos_orden_produccion_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.planificacion_recursos
    ADD CONSTRAINT planificacion_recursos_orden_produccion_id_fkey FOREIGN KEY (orden_produccion_id) REFERENCES public.ordenes_produccion(id) ON DELETE CASCADE;


--
-- Name: rutas_produccion rutas_produccion_bom_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.rutas_produccion
    ADD CONSTRAINT rutas_produccion_bom_id_fkey FOREIGN KEY (bom_id) REFERENCES public.bom_cabecera(id) ON DELETE CASCADE;


--
-- Name: rutas_produccion rutas_produccion_centro_trabajo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.rutas_produccion
    ADD CONSTRAINT rutas_produccion_centro_trabajo_id_fkey FOREIGN KEY (centro_trabajo_id) REFERENCES public.centros_trabajo(id);


--
-- Name: variantes variantes_id_parte_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.variantes
    ADD CONSTRAINT variantes_id_parte_fkey FOREIGN KEY (id_parte) REFERENCES public.partes(id) ON DELETE CASCADE;


--
-- Name: variantes variantes_id_um_peso_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.variantes
    ADD CONSTRAINT variantes_id_um_peso_fkey FOREIGN KEY (id_um_peso) REFERENCES public.unidades_medida(id);


--
-- PostgreSQL database dump complete
--

\unrestrict ZclBTJrQjmWfvrOP1SYzKriXbgxj4jlyXgeAisDwDhQykHIZCrAX7p2gkCLXiVH

