--
-- PostgreSQL database dump
--

\restrict mrQN1Ev1SiESDBqvu3wRvLWVDBQGUTPFI0KwdUTCody03rZlH4L5beuTtmlCbid

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
-- Name: update_fecha_modificacion_column(); Type: FUNCTION; Schema: public; Owner: mrp
--

CREATE FUNCTION public.update_fecha_modificacion_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.fecha_modificacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_fecha_modificacion_column() OWNER TO mrp;

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
    CONSTRAINT centros_trabajo_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('manual'::character varying)::text, ('semi_automatico'::character varying)::text, ('automatico'::character varying)::text])))
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
-- Name: compras; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.compras (
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


ALTER TABLE public.compras OWNER TO mrp;

--
-- Name: compras_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.compras_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.compras_id_seq OWNER TO mrp;

--
-- Name: compras_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.compras_id_seq OWNED BY public.compras.id;


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
    CONSTRAINT configuracion_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('string'::character varying)::text, ('number'::character varying)::text, ('boolean'::character varying)::text, ('json'::character varying)::text])))
);


ALTER TABLE public.configuracion OWNER TO mrp;

--
-- Name: configuracion_general; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.configuracion_general (
    id smallint DEFAULT 1 NOT NULL,
    decimal_places smallint DEFAULT 4 NOT NULL,
    rounding_mode character varying(20) DEFAULT 'half_up'::character varying NOT NULL,
    thousand_separator character varying(1) DEFAULT '.'::character varying NOT NULL,
    decimal_separator character varying(1) DEFAULT ','::character varying NOT NULL,
    date_format character varying(20) DEFAULT 'd/m/Y'::character varying NOT NULL,
    time_format character varying(20) DEFAULT 'H:i'::character varying NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT chk_configuracion_general_rounding_mode CHECK (((rounding_mode)::text = ANY ((ARRAY['half_up'::character varying, 'half_down'::character varying, 'half_even'::character varying, 'truncate'::character varying])::text[]))),
    CONSTRAINT chk_configuracion_general_separators CHECK (((thousand_separator)::text <> (decimal_separator)::text)),
    CONSTRAINT configuracion_general_decimal_places_check CHECK (((decimal_places >= 1) AND (decimal_places <= 6))),
    CONSTRAINT configuracion_general_id_check CHECK ((id = 1))
);


ALTER TABLE public.configuracion_general OWNER TO mrp;

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
-- Name: entidades; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.entidades (
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


ALTER TABLE public.entidades OWNER TO mrp;

--
-- Name: entidades_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.entidades_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.entidades_id_seq OWNER TO mrp;

--
-- Name: entidades_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.entidades_id_seq OWNED BY public.entidades.id;


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
    CONSTRAINT movimientos_inventario_tipo_movimiento_check CHECK (((tipo_movimiento)::text = ANY (ARRAY[('compra_recepcion'::character varying)::text, ('produccion_ingreso'::character varying)::text, ('produccion_consumo'::character varying)::text, ('produccion_descarte'::character varying)::text, ('ajuste_inventario'::character varying)::text, ('venta_despacho'::character varying)::text, ('transferencia_salida'::character varying)::text, ('transferencia_entrada'::character varying)::text])))
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
-- Name: movimientos_stock; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.movimientos_stock (
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


ALTER TABLE public.movimientos_stock OWNER TO mrp;

--
-- Name: TABLE movimientos_stock; Type: COMMENT; Schema: public; Owner: mrp
--

COMMENT ON TABLE public.movimientos_stock IS 'Historial de movimientos de stock entre depÔö£Ôöésitos (tipos)';


--
-- Name: COLUMN movimientos_stock.cantidad; Type: COMMENT; Schema: public; Owner: mrp
--

COMMENT ON COLUMN public.movimientos_stock.cantidad IS 'Cantidad movida en Unidad de Uso de la variante';


--
-- Name: movimientos_stock_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.movimientos_stock_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.movimientos_stock_id_seq OWNER TO mrp;

--
-- Name: movimientos_stock_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.movimientos_stock_id_seq OWNED BY public.movimientos_stock.id;


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
    CONSTRAINT mrp_sugerencias_estado_check CHECK (((estado)::text = ANY (ARRAY[('pendiente'::character varying)::text, ('aprobada'::character varying)::text, ('rechazada'::character varying)::text, ('convertida'::character varying)::text]))),
    CONSTRAINT mrp_sugerencias_tipo_accion_check CHECK (((tipo_accion)::text = ANY (ARRAY[('producir'::character varying)::text, ('comprar'::character varying)::text, ('transferir'::character varying)::text, ('cancelar_orden'::character varying)::text])))
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
    CONSTRAINT ordenes_produccion_estado_check CHECK (((estado)::text = ANY (ARRAY[('borrador'::character varying)::text, ('planificada'::character varying)::text, ('liberada'::character varying)::text, ('en_proceso'::character varying)::text, ('pausada'::character varying)::text, ('completada'::character varying)::text, ('cancelada'::character varying)::text, ('cerrada'::character varying)::text]))),
    CONSTRAINT ordenes_produccion_prioridad_check CHECK (((prioridad)::text = ANY (ARRAY[('baja'::character varying)::text, ('normal'::character varying)::text, ('alta'::character varying)::text, ('urgente'::character varying)::text])))
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


ALTER TABLE public.partes OWNER TO mrp;

--
-- Name: COLUMN partes.id_um_compra; Type: COMMENT; Schema: public; Owner: mrp
--

COMMENT ON COLUMN public.partes.id_um_compra IS 'Unidad de medida en la que se compra el Ôö£├óÔö¼┬ítem';


--
-- Name: COLUMN partes.id_um_uso; Type: COMMENT; Schema: public; Owner: mrp
--

COMMENT ON COLUMN public.partes.id_um_uso IS 'Unidad de medida en la que se usa el Ôö£├óÔö¼┬ítem en producciÔö£├óÔö¼Ôöén';


--
-- Name: COLUMN partes.factor_conversion; Type: COMMENT; Schema: public; Owner: mrp
--

COMMENT ON COLUMN public.partes.factor_conversion IS 'Factor de conversiÔö£Ôöén: 1 UM Compra = X UM Uso';


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
    CONSTRAINT planificacion_recursos_estado_check CHECK (((estado)::text = ANY (ARRAY[('programado'::character varying)::text, ('en_ejecucion'::character varying)::text, ('completado'::character varying)::text])))
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
-- Name: schema_migrations; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.schema_migrations (
    id integer NOT NULL,
    filename character varying(255) NOT NULL,
    executed_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.schema_migrations OWNER TO mrp;

--
-- Name: schema_migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.schema_migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.schema_migrations_id_seq OWNER TO mrp;

--
-- Name: schema_migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.schema_migrations_id_seq OWNED BY public.schema_migrations.id;


--
-- Name: tipos_depositos; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.tipos_depositos (
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


ALTER TABLE public.tipos_depositos OWNER TO mrp;

--
-- Name: TABLE tipos_depositos; Type: COMMENT; Schema: public; Owner: mrp
--

COMMENT ON TABLE public.tipos_depositos IS 'CatÔö£├óÔö¼├¡logo de tipos de depÔö£├óÔö¼Ôöésito del sistema';


--
-- Name: COLUMN tipos_depositos.es_sistema; Type: COMMENT; Schema: public; Owner: mrp
--

COMMENT ON COLUMN public.tipos_depositos.es_sistema IS 'Indica si el tipo es del sistema y no puede ser eliminado';


--
-- Name: tipos_depositos_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.tipos_depositos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tipos_depositos_id_seq OWNER TO mrp;

--
-- Name: tipos_depositos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.tipos_depositos_id_seq OWNED BY public.tipos_depositos.id;


--
-- Name: tipos_depositos_movimientos; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.tipos_depositos_movimientos (
    id integer NOT NULL,
    tipo_deposito_origen_id integer NOT NULL,
    tipo_deposito_destino_id integer NOT NULL,
    activo boolean DEFAULT true,
    observaciones text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.tipos_depositos_movimientos OWNER TO mrp;

--
-- Name: TABLE tipos_depositos_movimientos; Type: COMMENT; Schema: public; Owner: mrp
--

COMMENT ON TABLE public.tipos_depositos_movimientos IS 'Configuraci??n de movimientos permitidos entre tipos de dep??sitos';


--
-- Name: COLUMN tipos_depositos_movimientos.tipo_deposito_origen_id; Type: COMMENT; Schema: public; Owner: mrp
--

COMMENT ON COLUMN public.tipos_depositos_movimientos.tipo_deposito_origen_id IS 'Tipo de dep??sito de origen del movimiento';


--
-- Name: COLUMN tipos_depositos_movimientos.tipo_deposito_destino_id; Type: COMMENT; Schema: public; Owner: mrp
--

COMMENT ON COLUMN public.tipos_depositos_movimientos.tipo_deposito_destino_id IS 'Tipo de dep??sito de destino del movimiento';


--
-- Name: COLUMN tipos_depositos_movimientos.activo; Type: COMMENT; Schema: public; Owner: mrp
--

COMMENT ON COLUMN public.tipos_depositos_movimientos.activo IS 'Indica si el movimiento est?? habilitado';


--
-- Name: tipos_depositos_movimientos_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.tipos_depositos_movimientos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tipos_depositos_movimientos_id_seq OWNER TO mrp;

--
-- Name: tipos_depositos_movimientos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.tipos_depositos_movimientos_id_seq OWNED BY public.tipos_depositos_movimientos.id;


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
    fecha_modificacion timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    requiere_stock boolean DEFAULT true
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
    is_system boolean DEFAULT false NOT NULL,
    locked boolean DEFAULT false NOT NULL,
    CONSTRAINT unidades_medida_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('longitud'::character varying)::text, ('superficie'::character varying)::text, ('volumen'::character varying)::text, ('masa'::character varying)::text, ('tiempo'::character varying)::text, ('temperatura'::character varying)::text, ('unidad'::character varying)::text])))
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
    costo numeric(15,2) DEFAULT 0,
    ubicacion_cuerpo character varying(100),
    ubicacion_pasillo character varying(100),
    ubicacion_estante character varying(100),
    CONSTRAINT variantes_estado_check CHECK (((estado)::text = ANY (ARRAY[('activa'::character varying)::text, ('obsoleta'::character varying)::text, ('descontinuada'::character varying)::text, ('desarrollo'::character varying)::text])))
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
-- Name: compras id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.compras ALTER COLUMN id SET DEFAULT nextval('public.compras_id_seq'::regclass);


--
-- Name: configuracion id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.configuracion ALTER COLUMN id SET DEFAULT nextval('public.configuracion_id_seq'::regclass);


--
-- Name: entidades id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.entidades ALTER COLUMN id SET DEFAULT nextval('public.entidades_id_seq'::regclass);


--
-- Name: grupos_partes id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.grupos_partes ALTER COLUMN id SET DEFAULT nextval('public.grupos_partes_id_seq'::regclass);


--
-- Name: movimientos_inventario id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.movimientos_inventario ALTER COLUMN id SET DEFAULT nextval('public.movimientos_inventario_id_seq'::regclass);


--
-- Name: movimientos_stock id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.movimientos_stock ALTER COLUMN id SET DEFAULT nextval('public.movimientos_stock_id_seq'::regclass);


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
-- Name: schema_migrations id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.schema_migrations ALTER COLUMN id SET DEFAULT nextval('public.schema_migrations_id_seq'::regclass);


--
-- Name: tipos_depositos id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.tipos_depositos ALTER COLUMN id SET DEFAULT nextval('public.tipos_depositos_id_seq'::regclass);


--
-- Name: tipos_depositos_movimientos id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.tipos_depositos_movimientos ALTER COLUMN id SET DEFAULT nextval('public.tipos_depositos_movimientos_id_seq'::regclass);


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
1	ALM-GRAL	Almacen General	t	f	t
2	PROD-LINEA	Produccion en Linea	f	t	t
\.


--
-- Data for Name: bom_cabecera; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.bom_cabecera (id, variante_padre_id, version, activa, fecha_efectiva, fecha_vencimiento, observaciones, aprobada_por, fecha_aprobacion, created_at, updated_at) FROM stdin;
1	4	1.0	t	2026-03-08	\N	\N	\N	\N	2026-03-08 13:55:10.779302+00	2026-03-08 13:55:10.779302+00
2	128	1.0	t	2026-03-08	\N	\N	\N	\N	2026-03-08 14:08:02.778134+00	2026-03-08 14:08:02.778134+00
3	132	1.0	t	2026-03-08	\N	\N	\N	\N	2026-03-08 18:17:22.073744+00	2026-03-08 18:17:22.073744+00
4	133	1.0	t	2026-03-08	\N	\N	\N	\N	2026-03-08 19:24:41.97619+00	2026-03-08 19:24:41.97619+00
5	134	1.0	t	2026-03-08	\N	\N	\N	\N	2026-03-08 19:26:23.113011+00	2026-03-08 19:26:23.113011+00
6	135	1.0	t	2026-03-08	\N	\N	\N	\N	2026-03-08 19:26:57.093951+00	2026-03-08 19:26:57.093951+00
7	137	1.0	t	2026-03-08	\N	\N	\N	\N	2026-03-08 19:27:18.063971+00	2026-03-08 19:27:18.063971+00
8	139	1.0	t	2026-03-08	\N	\N	\N	\N	2026-03-08 19:27:45.670358+00	2026-03-08 19:27:45.670358+00
9	141	1.0	t	2026-03-08	\N	\N	\N	\N	2026-03-08 19:28:02.444708+00	2026-03-08 19:28:02.444708+00
10	256	1.0	t	2026-03-08	\N	\N	\N	\N	2026-03-08 19:28:47.095379+00	2026-03-08 19:28:47.095379+00
11	7	1.0	t	2026-03-08	\N	\N	\N	\N	2026-03-08 19:36:05.025625+00	2026-03-08 19:36:05.025625+00
12	12	1.0	t	2026-03-08	\N	\N	\N	\N	2026-03-08 20:48:06.130817+00	2026-03-08 20:48:06.130817+00
13	122	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 00:07:45.849176+00	2026-03-09 00:07:45.849176+00
14	225	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:33:07.173546+00	2026-03-09 01:33:07.173546+00
15	226	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:34:12.762134+00	2026-03-09 01:34:12.762134+00
16	227	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:35:15.423712+00	2026-03-09 01:35:15.423712+00
17	228	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:35:58.849948+00	2026-03-09 01:35:58.849948+00
18	229	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:37:32.783712+00	2026-03-09 01:37:32.783712+00
19	230	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:38:09.440555+00	2026-03-09 01:38:09.440555+00
20	231	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:41:23.856312+00	2026-03-09 01:41:23.856312+00
21	232	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:42:56.550141+00	2026-03-09 01:42:56.550141+00
22	233	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:47:26.279288+00	2026-03-09 01:47:26.279288+00
23	235	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:48:01.286745+00	2026-03-09 01:48:01.286745+00
24	237	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:50:34.58054+00	2026-03-09 01:50:34.58054+00
25	236	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:52:00.851751+00	2026-03-09 01:52:00.851751+00
26	238	1.0	t	2026-03-09	\N	\N	\N	\N	2026-03-09 01:53:48.895555+00	2026-03-09 01:53:48.895555+00
\.


--
-- Data for Name: bom_detalle; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.bom_detalle (id, bom_id, variante_componente_id, cantidad_necesaria, unidad_medida_id, desperdicio_porcentaje, es_opcional, secuencia, costo_unitario_estimado, tiempo_setup_mins, tiempo_proceso_mins, condicion_aplicacion, observaciones, created_at) FROM stdin;
1	1	128	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 13:55:10.876253+00
67	23	54	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:48:09.850574+00
68	23	237	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:49:50.168427+00
66	23	47	0.45000	24	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:48:01.36162+00
69	24	92	0.02100	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:50:34.648042+00
3	2	119	0.02410	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 14:35:23.112179+00
5	1	132	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 18:17:05.428567+00
8	3	116	1.00000	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 18:32:03.465778+00
9	1	133	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:16:06.433144+00
10	1	134	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:16:20.66836+00
11	1	135	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:16:33.937058+00
12	1	137	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:16:59.081018+00
13	1	139	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:18:23.455221+00
14	1	141	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:18:38.815109+00
15	1	142	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:19:21.698068+00
16	1	144	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:21:01.255474+00
17	1	256	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:21:15.123288+00
18	4	116	1.00000	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:24:42.030044+00
19	5	116	1.00000	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:26:23.17567+00
20	6	116	1.00000	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:26:57.148267+00
21	7	87	0.02250	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:27:18.119995+00
22	8	87	0.04400	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:27:45.724872+00
23	9	87	0.01700	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:28:02.507778+00
24	10	87	0.04560	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:28:47.142043+00
25	11	128	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:36:05.080831+00
26	11	130	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:37:03.094687+00
27	11	132	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:37:14.064182+00
28	11	133	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:37:28.157118+00
29	11	134	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 19:37:41.010403+00
31	11	135	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:19:15.677965+00
32	11	137	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:26:25.8085+00
33	11	139	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:26:35.097882+00
34	11	141	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:26:37.465859+00
35	11	142	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:26:41.857825+00
36	11	144	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:26:46.735448+00
37	11	256	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:27:01.864658+00
39	12	225	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:48:12.658917+00
40	12	226	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:48:19.548913+00
41	12	227	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:48:24.042721+00
42	12	228	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:48:32.225185+00
43	12	229	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:48:49.757119+00
44	12	230	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:49:04.738434+00
48	12	235	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:49:20.313711+00
49	12	236	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:49:31.262996+00
52	13	61	0.35000	24	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 00:08:47.876707+00
45	12	231	2.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:49:08.025432+00
46	12	232	4.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:49:12.572619+00
47	12	233	2.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:49:16.471273+00
50	12	238	2.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:49:34.472037+00
53	13	28	1.00000	14	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:30:41.279038+00
58	18	92	0.30030	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:37:32.86189+00
59	19	116	0.07500	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:38:09.487414+00
61	19	63	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:38:37.172834+00
60	19	46	0.24000	24	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:38:26.309806+00
62	20	116	0.21420	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:41:23.899914+00
63	21	92	0.01000	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:42:56.605975+00
64	21	61	0.35000	24	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:43:09.184766+00
65	22	92	0.03510	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:47:26.331515+00
72	25	72	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:52:18.336018+00
73	25	75	1.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:52:25.369205+00
70	25	60	2.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:52:00.907556+00
71	25	64	1.50000	24	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:52:09.685789+00
74	26	92	0.02550	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:53:48.95199+00
38	12	122	4.00000	25	0.00	f	0	0.0000	0	0	\N	\N	2026-03-08 20:48:06.204955+00
51	13	92	0.00975	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 00:07:45.932193+00
55	15	118	0.04320	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:34:12.825754+00
56	16	93	0.03840	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:35:15.485133+00
57	17	92	0.04680	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:35:58.906495+00
54	14	93	0.05600	3	0.00	f	0	0.0000	0	0	\N	\N	2026-03-09 01:33:07.241427+00
\.


--
-- Data for Name: centros_trabajo; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.centros_trabajo (id, codigo, nombre, descripcion, tipo, capacidad_horas_dia, eficiencia_porcentaje, costo_hora, capacidad_finita, calendario_id, activo, ubicacion, responsable, observaciones, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: composicion_variantes; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.composicion_variantes (id, id_padre, id_hijo, cantidad, fecha_creacion, usuario_creacion, activo) FROM stdin;
\.


--
-- Data for Name: compras; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.compras (id, fecha, precio_unitario, observaciones, created_at, updated_at, id_movimiento_stock, id_entidad, nro_comprobante) FROM stdin;
1	2026-03-09	4142.86		2026-03-10 00:10:11.028118	2026-03-10 00:10:11.028118	2	1	
\.


--
-- Data for Name: configuracion; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.configuracion (id, clave, valor, descripcion, tipo, fecha_actualizacion) FROM stdin;
\.


--
-- Data for Name: configuracion_general; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.configuracion_general (id, decimal_places, rounding_mode, thousand_separator, decimal_separator, date_format, time_format, created_at, updated_at) FROM stdin;
1	6	half_up	.	,	Y-m-d	H:i:s	2026-03-08 15:51:21.95769	2026-03-09 12:53:28.367169
\.


--
-- Data for Name: entidades; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.entidades (id, razon_social, tipo, identificacion_tributaria, contacto_email, contacto_telefono, direccion, created_at, updated_at) FROM stdin;
1	Propio	AMBOS	20323837857	martin@unik.ar	2236894004	La Laura 4151	2026-03-09 23:05:53	2026-03-09 23:05:53
\.


--
-- Data for Name: grupos_partes; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.grupos_partes (id, codigo, nombre, descripcion, color, activo, fecha_creacion, fecha_modificacion) FROM stdin;
1	N/A	SIN DEFINIR		#0d6efd	t	2026-03-06 19:07:21.960044+00	2026-03-06 19:07:21.960044+00
\.


--
-- Data for Name: movimientos_inventario; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.movimientos_inventario (id, variante_id, almacen_id, orden_produccion_id, tipo_movimiento, cantidad, signo, costo_unitario_snapshot, fecha_movimiento, usuario_id, observaciones, referencia_documento) FROM stdin;
\.


--
-- Data for Name: movimientos_stock; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.movimientos_stock (id, id_variante, cantidad, id_tipo_deposito_origen, id_tipo_deposito_destino, fecha, referencia_tipo, referencia_id, observaciones, created_at, updated_at) FROM stdin;
2	93	1.400000	4	1	2026-03-09 20:51:00	compra_satelite	0		2026-03-09 23:52:37.026191	2026-03-09 23:52:37.026191
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

COPY public.partes (id, codigo, id_tipo, id_grupo, detalle, largo_alto, id_um_largo_alto, ancho, id_um_ancho, espesor_profundidad, id_um_espesor, superficie, id_um_superficie, volumen, id_um_volumen, atributos_base, reglas_configuracion, activo, creado_por, fecha_creacion, fecha_modificacion, id_um_compra, id_um_uso, created_at, updated_at, factor_conversion) FROM stdin;
1	BAN001	3	1	BANDOLERA CLOE	80.0000000000	20	230.0000000000	20	180.0000000000	20	0.0184000000	3	3312.0000000000	22	{}	{}	t	\N	2026-03-06 19:21:17.585139+00	2026-03-06 19:21:17.585139+00	25	25	2026-03-06 19:21:17.585139+00	2026-03-06 19:21:17.585139+00	1.000000
2	BIL001	3	1	SOBRE BILLETERA GRANDE	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-06 21:18:06.185921+00	2026-03-06 21:18:06.185921+00	25	25	2026-03-06 21:18:06.185921+00	2026-03-06 21:18:06.185921+00	1.000000
3	BIL002	3	1	SOBRE BILLETERA CHICA	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-06 22:11:21.111757+00	2026-03-06 22:11:21.111757+00	25	25	2026-03-06 22:11:21.111757+00	2026-03-06 22:11:21.111757+00	1.000000
4	BOL001	3	1	TOTE FRANCIA	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-06 23:20:23.816955+00	2026-03-06 23:20:23.816955+00	25	25	2026-03-06 23:20:23.816955+00	2026-03-06 23:20:23.816955+00	1.000000
7	MOC001	3	1	MOCHI BOMBON CHICA	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-07 18:30:21.60959+00	2026-03-07 18:30:21.60959+00	25	25	2026-03-07 18:30:21.60959+00	2026-03-07 18:30:21.60959+00	1.000000
8	BOL002	3	1	TOTE MIC	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-07 18:41:47.563339+00	2026-03-07 18:41:47.563339+00	25	25	2026-03-07 18:41:47.563339+00	2026-03-07 18:41:47.563339+00	1.000000
9	BOL003	3	1	TOTE CANELON	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-07 18:43:17.417754+00	2026-03-07 18:43:17.417754+00	25	25	2026-03-07 18:43:17.417754+00	2026-03-07 18:43:17.417754+00	1.000000
11	MOC002	3	1	MOCHI BOMBON GRANDE	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-07 19:21:16.890221+00	2026-03-07 19:21:16.890221+00	25	25	2026-03-07 19:21:16.890221+00	2026-03-07 19:21:16.890221+00	1.000000
12	MOC003	3	1	MOCHI CANELON	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-07 19:22:57.635525+00	2026-03-07 19:22:57.635525+00	25	25	2026-03-07 19:22:57.635525+00	2026-03-07 19:22:57.635525+00	1.000000
13	RI├æ001	3	1	RI├æONERA CHARO	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-07 19:54:53.89414+00	2026-03-07 19:54:53.89414+00	25	25	2026-03-07 19:54:53.89414+00	2026-03-07 19:54:53.89414+00	1.000000
14	RI├æ002	3	1	RI├æONERA DEPORTIVA	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-07 19:57:57.520002+00	2026-03-07 19:57:57.520002+00	25	25	2026-03-07 19:57:57.520002+00	2026-03-07 19:57:57.520002+00	1.000000
15	RI├æ003	3	1	RI├æONERA LUCY	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-07 19:58:41.280541+00	2026-03-07 19:58:41.280541+00	25	25	2026-03-07 19:58:41.280541+00	2026-03-07 19:58:41.280541+00	1.000000
16	TER001	6	1	BANDOLERA PORTA CELULAR	180.0000000000	20	115.0000000000	20	60.0000000000	20	0.0207000000	3	1242.0000000000	22	{}	{}	t	\N	2026-03-07 20:08:59.136866+00	2026-03-07 20:08:59.136866+00	25	25	2026-03-07 20:08:59.136866+00	2026-03-07 20:11:15.628567+00	1.000000
32	MAP029	1	1	DESLIZADOR PLASTICO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:49.765611+00	2026-03-08 01:56:49.765611+00	25	25	2026-03-08 01:56:49.765611+00	2026-03-08 02:06:53.733617+00	1.000000
18	MAN001	7	1	MO COSTURA	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-07 22:55:06.497495+00	2026-03-07 22:55:06.497495+00	14	14	2026-03-07 22:55:06.497495+00	2026-03-07 22:55:06.952413+00	1.000000
31	MAP028	1	1	SPAGUETTI	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:49.707075+00	2026-03-08 01:56:49.707075+00	9	24	2026-03-08 01:56:49.707075+00	2026-03-09 01:45:56.400433+00	10.000000
36	MAP033	1	1	REGULADOR	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:50.032258+00	2026-03-08 01:56:50.032258+00	25	25	2026-03-08 01:56:50.032258+00	2026-03-08 02:06:54.259206+00	1.000000
19	MAN002	7	1	MO CORTE	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-07 22:55:07.010912+00	2026-03-07 22:55:07.010912+00	14	14	2026-03-07 22:55:07.010912+00	2026-03-07 22:55:07.419377+00	1.000000
20	MAN003	7	1	MO PROPIA	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-07 22:55:07.527962+00	2026-03-07 22:55:07.527962+00	14	14	2026-03-07 22:55:07.527962+00	2026-03-07 22:55:07.527962+00	1.000000
26	MAP023	1	1	TIRACIERRE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:49.35739+00	2026-03-08 01:56:49.35739+00	25	25	2026-03-08 01:56:49.35739+00	2026-03-08 02:06:53.233565+00	1.000000
25	MAP022	1	1	DESLIZADOR METAL N5	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:49.232694+00	2026-03-08 01:56:49.232694+00	25	25	2026-03-08 01:56:49.232694+00	2026-03-08 02:06:53.174973+00	1.000000
30	MAP027	1	1	MOSQUETON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:49.590859+00	2026-03-08 01:56:49.590859+00	25	25	2026-03-08 01:56:49.590859+00	2026-03-08 02:06:53.525007+00	1.000000
44	MAP044	1	1	ARGOLLAS	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:50.973796+00	2026-03-08 01:56:50.973796+00	25	25	2026-03-08 01:56:50.973796+00	2026-03-08 02:06:55.075086+00	1.000000
43	MAP041	1	1	HEBILLA ACOPLE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:50.848968+00	2026-03-08 01:56:50.848968+00	25	25	2026-03-08 01:56:50.848968+00	2026-03-08 02:06:54.900924+00	1.000000
22	MAP019	1	1	CIERRE PLASTICO	1000.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:48.773298+00	2026-03-08 01:56:48.773298+00	24	24	2026-03-08 01:56:48.773298+00	2026-03-08 02:06:52.708344+00	1.000000
23	MAP020	1	1	CIERRE METAL	1000.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:48.824325+00	2026-03-08 01:56:48.824325+00	24	24	2026-03-08 01:56:48.824325+00	2026-03-08 02:06:52.766891+00	1.000000
24	MAP021	1	1	MEDIALUNA	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:48.894677+00	2026-03-08 01:56:48.894677+00	25	25	2026-03-08 01:56:48.894677+00	2026-03-08 02:06:53.058265+00	1.000000
27	MAP024	1	1	CHAPITA	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:49.415295+00	2026-03-08 01:56:49.415295+00	25	25	2026-03-08 01:56:49.415295+00	2026-03-08 02:06:53.29196+00	1.000000
28	MAP025	1	1	CADENA	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:49.473802+00	2026-03-08 01:56:49.473802+00	24	24	2026-03-08 01:56:49.473802+00	2026-03-08 02:06:53.351079+00	1.000000
29	MAP026	1	1	REMACHE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:49.531878+00	2026-03-08 01:56:49.531878+00	25	25	2026-03-08 01:56:49.531878+00	2026-03-08 02:06:53.409127+00	1.000000
35	MAP032	1	1	SOGA	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:49.973644+00	2026-03-08 01:56:49.973644+00	24	24	2026-03-08 01:56:49.973644+00	2026-03-08 02:06:53.909328+00	1.000000
38	MAP036	1	1	CAJA SOBRE BILLETERA	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:50.440402+00	2026-03-08 01:56:50.440402+00	25	25	2026-03-08 01:56:50.440402+00	2026-03-08 02:06:54.433626+00	1.000000
39	MAP037	1	1	DADO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:50.498858+00	2026-03-08 01:56:50.498858+00	25	25	2026-03-08 01:56:50.498858+00	2026-03-08 02:06:54.492555+00	1.000000
40	MAP038	1	1	ESPEJADO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:50.557241+00	2026-03-08 01:56:50.557241+00	25	25	2026-03-08 01:56:50.557241+00	2026-03-08 02:06:54.550371+00	1.000000
41	MAP039	1	1	VALENCIA	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:50.615536+00	2026-03-08 01:56:50.615536+00	25	25	2026-03-08 01:56:50.615536+00	2026-03-08 02:06:54.608354+00	1.000000
42	MAP040	1	1	BOLSA	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:50.674342+00	2026-03-08 01:56:50.674342+00	25	25	2026-03-08 01:56:50.674342+00	2026-03-08 02:06:54.783604+00	1.000000
33	MAP030	1	1	CINTA SUBLIMADA	10000.0000000000	20	40.0000000000	20	\N	\N	0.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 01:56:49.88198+00	2026-03-08 01:56:49.88198+00	24	24	2026-03-08 01:56:49.88198+00	2026-03-08 18:51:41.231195+00	1.000000
34	MAP031	1	1	POLIPROPILENO	1000.0000000000	20	1500.0000000000	20	\N	\N	1.5000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 01:56:49.939068+00	2026-03-08 01:56:49.939068+00	3	3	2026-03-08 01:56:49.939068+00	2026-03-08 18:51:41.231195+00	1.000000
21	MAP018	1	1	BIES	25000.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 01:56:48.653624+00	2026-03-08 01:56:48.653624+00	24	24	2026-03-08 01:56:48.653624+00	2026-03-08 02:06:52.650224+00	1.000000
64	MAP045	1	1	TRENZADO	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:55.133816+00	2026-03-08 02:06:55.133816+00	29	3	2026-03-08 02:06:55.133816+00	2026-03-08 02:15:51.905937+00	1.400000
66	PIE002	2	1	LLAVERO TIRACIERRE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:14.891752+00	2026-03-08 13:40:14.891752+00	25	25	2026-03-08 13:40:14.891752+00	2026-03-08 13:40:14.891752+00	1.000000
58	MAP015	1	1	GROSS	1000.0000000000	20	1500.0000000000	20	\N	\N	1.5000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:52.474962+00	2026-03-08 02:06:52.474962+00	29	3	2026-03-08 02:06:52.474962+00	2026-03-08 18:51:41.231195+00	1.500000
71	PIE008	2	1	FORRO FRENTE Y CONTRAFRENTE - BANDOLERA CLOE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:15.309416+00	2026-03-08 13:40:15.309416+00	25	25	2026-03-08 13:40:15.309416+00	2026-03-08 13:40:15.309416+00	1.000000
72	PIE009	2	1	FORRO FUELLE INFERIOR - BANDOLERA CLOE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:15.366789+00	2026-03-08 13:40:15.366789+00	25	25	2026-03-08 13:40:15.366789+00	2026-03-08 13:40:15.366789+00	1.000000
73	PIE010	2	1	FORRO FUELLE SUPERIOR - BANDOLERA CLOE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:15.424754+00	2026-03-08 13:40:15.424754+00	25	25	2026-03-08 13:40:15.424754+00	2026-03-08 13:40:15.424754+00	1.000000
74	PIE011	2	1	FORRO SOLAPA INTERNA - BANDOLERA CLOE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:15.48338+00	2026-03-08 13:40:15.48338+00	25	25	2026-03-08 13:40:15.48338+00	2026-03-08 13:40:15.48338+00	1.000000
78	PIE015	2	1	HERRAJES - BANDOLERA CLOE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:15.891639+00	2026-03-08 13:40:15.891639+00	25	25	2026-03-08 13:40:15.891639+00	2026-03-08 13:40:15.891639+00	1.000000
80	PIE017	2	1	CORREA CUERO - MOCHI	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.066748+00	2026-03-08 13:40:16.066748+00	25	25	2026-03-08 13:40:16.066748+00	2026-03-08 13:40:16.066748+00	1.000000
81	PIE018	2	1	CORREA SUBLIMADA - MOCHI	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.124819+00	2026-03-08 13:40:16.124819+00	25	25	2026-03-08 13:40:16.124819+00	2026-03-08 13:40:16.124819+00	1.000000
82	PIE019	2	1	MANIJA SUPERIOR - MOCHI	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.183332+00	2026-03-08 13:40:16.183332+00	25	25	2026-03-08 13:40:16.183332+00	2026-03-08 13:40:16.183332+00	1.000000
83	PIE020	2	1	REFUERZO TIRAS - MOCHI	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.24209+00	2026-03-08 13:40:16.24209+00	25	25	2026-03-08 13:40:16.24209+00	2026-03-08 13:40:16.24209+00	1.000000
84	PIE021	2	1	TIRA PORTA HERRAJE - MOCHI	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.300129+00	2026-03-08 13:40:16.300129+00	25	25	2026-03-08 13:40:16.300129+00	2026-03-08 13:40:16.300129+00	1.000000
95	PIE032	2	1	HERRAJES - MOCHI BOMBON CHICA	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.941593+00	2026-03-08 13:40:16.941593+00	25	25	2026-03-08 13:40:16.941593+00	2026-03-08 13:40:16.941593+00	1.000000
97	PIE034	2	1	FRENTE SOLAPA - MOCHI BOMBON GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.059194+00	2026-03-08 13:40:17.059194+00	25	25	2026-03-08 13:40:17.059194+00	2026-03-08 13:40:17.059194+00	1.000000
98	PIE035	2	1	FUELLE INFERIOR SOLAPA - MOCHI BOMBON GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.116274+00	2026-03-08 13:40:17.116274+00	25	25	2026-03-08 13:40:17.116274+00	2026-03-08 13:40:17.116274+00	1.000000
99	PIE036	2	1	FUELLE INFERIOR - MOCHI BOMBON GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.174944+00	2026-03-08 13:40:17.174944+00	25	25	2026-03-08 13:40:17.174944+00	2026-03-08 13:40:17.174944+00	1.000000
100	PIE037	2	1	FUELLE SUPERIOR SOLAPA - MOCHI BOMBON GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.233126+00	2026-03-08 13:40:17.233126+00	25	25	2026-03-08 13:40:17.233126+00	2026-03-08 13:40:17.233126+00	1.000000
101	PIE038	2	1	FUELLE SUPERIOR - MOCHI BOMBON GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.291788+00	2026-03-08 13:40:17.291788+00	25	25	2026-03-08 13:40:17.291788+00	2026-03-08 13:40:17.291788+00	1.000000
102	PIE039	2	1	HERRAJES - MOCHI BOMBON GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.349771+00	2026-03-08 13:40:17.349771+00	25	25	2026-03-08 13:40:17.349771+00	2026-03-08 13:40:17.349771+00	1.000000
103	PIE040	2	1	VIVO - MOCHI BOMBON GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.408372+00	2026-03-08 13:40:17.408372+00	25	25	2026-03-08 13:40:17.408372+00	2026-03-08 13:40:17.408372+00	1.000000
104	PIE041	2	1	BOLSILLO FRONTAL CUADRADO - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.466925+00	2026-03-08 13:40:17.466925+00	25	25	2026-03-08 13:40:17.466925+00	2026-03-08 13:40:17.466925+00	1.000000
59	MAP016	1	1	TROPICAL MECANICO ESTAMPADO	1000.0000000000	20	1500.0000000000	20	\N	\N	1.5000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:52.533531+00	2026-03-08 02:06:52.533531+00	29	3	2026-03-08 02:06:52.533531+00	2026-03-08 18:51:41.231195+00	1.500000
105	PIE042	2	1	BOLSILLO FRONTAL REDONDEADO - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.525017+00	2026-03-08 13:40:17.525017+00	25	25	2026-03-08 13:40:17.525017+00	2026-03-08 13:40:17.525017+00	1.000000
106	PIE043	2	1	BOLSILLO INTERNO - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.625116+00	2026-03-08 13:40:17.625116+00	25	25	2026-03-08 13:40:17.625116+00	2026-03-08 13:40:17.625116+00	1.000000
107	PIE044	2	1	CORREAS DE CUERO - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.68315+00	2026-03-08 13:40:17.68315+00	25	25	2026-03-08 13:40:17.68315+00	2026-03-08 13:40:17.68315+00	1.000000
108	PIE045	2	1	CORREAS SUBLIMADAS - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.741672+00	2026-03-08 13:40:17.741672+00	25	25	2026-03-08 13:40:17.741672+00	2026-03-08 13:40:17.741672+00	1.000000
109	PIE046	2	1	FORRO FRENTE Y CONTRAFRENTE - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.799811+00	2026-03-08 13:40:17.799811+00	25	25	2026-03-08 13:40:17.799811+00	2026-03-08 13:40:17.799811+00	1.000000
110	PIE047	2	1	FORRO FUELLE INFERIOR - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.858388+00	2026-03-08 13:40:17.858388+00	25	25	2026-03-08 13:40:17.858388+00	2026-03-08 13:40:17.858388+00	1.000000
111	PIE048	2	1	FORRO FUELLE SUPERIOR - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.916434+00	2026-03-08 13:40:17.916434+00	25	25	2026-03-08 13:40:17.916434+00	2026-03-08 13:40:17.916434+00	1.000000
112	PIE049	2	1	FORRO VIVO - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.975427+00	2026-03-08 13:40:17.975427+00	25	25	2026-03-08 13:40:17.975427+00	2026-03-08 13:40:17.975427+00	1.000000
113	PIE050	2	1	FRENTE Y CONTRAFRENTE - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.033133+00	2026-03-08 13:40:18.033133+00	25	25	2026-03-08 13:40:18.033133+00	2026-03-08 13:40:18.033133+00	1.000000
114	PIE051	2	1	FUELLE INFERIOR - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.09172+00	2026-03-08 13:40:18.09172+00	25	25	2026-03-08 13:40:18.09172+00	2026-03-08 13:40:18.09172+00	1.000000
115	PIE052	2	1	FUELLE SUPERIOR - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.149804+00	2026-03-08 13:40:18.149804+00	25	25	2026-03-08 13:40:18.149804+00	2026-03-08 13:40:18.149804+00	1.000000
116	PIE053	2	1	HERRAJES - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.208392+00	2026-03-08 13:40:18.208392+00	25	25	2026-03-08 13:40:18.208392+00	2026-03-08 13:40:18.208392+00	1.000000
117	PIE054	2	1	SPAGUETTI - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.266649+00	2026-03-08 13:40:18.266649+00	25	25	2026-03-08 13:40:18.266649+00	2026-03-08 13:40:18.266649+00	1.000000
118	PIE055	2	1	TIRA SUPERIOR - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.325192+00	2026-03-08 13:40:18.325192+00	25	25	2026-03-08 13:40:18.325192+00	2026-03-08 13:40:18.325192+00	1.000000
119	PIE056	2	1	ALA A y B - RI├æONERA CHARO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.383208+00	2026-03-08 13:40:18.383208+00	25	25	2026-03-08 13:40:18.383208+00	2026-03-08 13:40:18.383208+00	1.000000
120	PIE057	2	1	BOLSILLO INTERNO - RI├æONERA	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.441803+00	2026-03-08 13:40:18.441803+00	25	25	2026-03-08 13:40:18.441803+00	2026-03-08 13:40:18.441803+00	1.000000
121	PIE058	2	1	CONTRAFRENTE - RI├æONERA CHARO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.499922+00	2026-03-08 13:40:18.499922+00	25	25	2026-03-08 13:40:18.499922+00	2026-03-08 13:40:18.499922+00	1.000000
127	PIE064	2	1	FRENTE BOLSILLO - RI├æONERA CHARO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.858268+00	2026-03-08 13:40:18.858268+00	25	25	2026-03-08 13:40:18.858268+00	2026-03-08 13:40:18.858268+00	1.000000
128	PIE065	2	1	FUELLE INFERIOR BOLSILLO - RI├æONERA CHARO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.916824+00	2026-03-08 13:40:18.916824+00	25	25	2026-03-08 13:40:18.916824+00	2026-03-08 13:40:18.916824+00	1.000000
129	PIE066	2	1	FUELLE SUPERIOR BOLSILLO - RI├æONERA CHARO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.974885+00	2026-03-08 13:40:18.974885+00	25	25	2026-03-08 13:40:18.974885+00	2026-03-08 13:40:18.974885+00	1.000000
130	PIE067	2	1	HERRAJES - RI├æONERA CHARO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.03345+00	2026-03-08 13:40:19.03345+00	25	25	2026-03-08 13:40:19.03345+00	2026-03-08 13:40:19.03345+00	1.000000
131	PIE068	2	1	MEDIALUNA - RI├æONERA CHARO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.091557+00	2026-03-08 13:40:19.091557+00	25	25	2026-03-08 13:40:19.091557+00	2026-03-08 13:40:19.091557+00	1.000000
132	PIE069	2	1	PORTA HERRAJE - RI├æONERA CHARO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.150137+00	2026-03-08 13:40:19.150137+00	25	25	2026-03-08 13:40:19.150137+00	2026-03-08 13:40:19.150137+00	1.000000
135	PIE072	2	1	TIRA SUBLIMADA - RI├æONERA NIQUEL	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.325403+00	2026-03-08 13:40:19.325403+00	25	25	2026-03-08 13:40:19.325403+00	2026-03-08 13:40:19.325403+00	1.000000
136	PIE073	2	1	VIVO - RI├æONERA CHARO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.383534+00	2026-03-08 13:40:19.383534+00	25	25	2026-03-08 13:40:19.383534+00	2026-03-08 13:40:19.383534+00	1.000000
137	PIE074	2	1	BOLSILLO INTERNO - RI├æONERA LUCY	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.441508+00	2026-03-08 13:40:19.441508+00	25	25	2026-03-08 13:40:19.441508+00	2026-03-08 13:40:19.441508+00	1.000000
138	PIE075	2	1	CONTRAFRENTE - RI├æONERA LUCY	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.50018+00	2026-03-08 13:40:19.50018+00	25	25	2026-03-08 13:40:19.50018+00	2026-03-08 13:40:19.50018+00	1.000000
139	PIE076	2	1	CORREA CUERO - RI├æONERA LUCY	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.566592+00	2026-03-08 13:40:19.566592+00	25	25	2026-03-08 13:40:19.566592+00	2026-03-08 13:40:19.566592+00	1.000000
140	PIE077	2	1	CORREA SUBLIMADA M	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.625228+00	2026-03-08 13:40:19.625228+00	25	25	2026-03-08 13:40:19.625228+00	2026-03-08 13:40:19.625228+00	1.000000
141	PIE078	2	1	FORRO CONTRAFRENTE - RI├æONERA LUCY	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.683648+00	2026-03-08 13:40:19.683648+00	25	25	2026-03-08 13:40:19.683648+00	2026-03-08 13:40:19.683648+00	1.000000
142	PIE079	2	1	FORRO FRENTE - RI├æONERA LUCY	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.741646+00	2026-03-08 13:40:19.741646+00	25	25	2026-03-08 13:40:19.741646+00	2026-03-08 13:40:19.741646+00	1.000000
143	PIE080	2	1	FORRO MEDIALUNA - RI├æONERA LUCY	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.791544+00	2026-03-08 13:40:19.791544+00	25	25	2026-03-08 13:40:19.791544+00	2026-03-08 13:40:19.791544+00	1.000000
144	PIE081	2	1	FORRO VIVO - RI├æONERA LUCY	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.850783+00	2026-03-08 13:40:19.850783+00	25	25	2026-03-08 13:40:19.850783+00	2026-03-08 13:40:19.850783+00	1.000000
145	PIE082	2	1	FRENTE - RI├æONERA LUCY	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.908249+00	2026-03-08 13:40:19.908249+00	25	25	2026-03-08 13:40:19.908249+00	2026-03-08 13:40:19.908249+00	1.000000
146	PIE083	2	1	HERRAJES - RI├æONERA LUCY	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.966867+00	2026-03-08 13:40:19.966867+00	25	25	2026-03-08 13:40:19.966867+00	2026-03-08 13:40:19.966867+00	1.000000
147	PIE084	2	1	MEDIALUNA - RI├æONERA LUCY	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.024912+00	2026-03-08 13:40:20.024912+00	25	25	2026-03-08 13:40:20.024912+00	2026-03-08 13:40:20.024912+00	1.000000
148	PIE085	2	1	PORTA HERRAJE - RI├æONERA LUCY	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.083522+00	2026-03-08 13:40:20.083522+00	25	25	2026-03-08 13:40:20.083522+00	2026-03-08 13:40:20.083522+00	1.000000
149	PIE086	2	1	SPAGUETI - RI├æONERA LUCY	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.141583+00	2026-03-08 13:40:20.141583+00	25	25	2026-03-08 13:40:20.141583+00	2026-03-08 13:40:20.141583+00	1.000000
150	PIE087	2	1	BILLETERO GRANDE - SOBRE BILLETERA GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.200088+00	2026-03-08 13:40:20.200088+00	25	25	2026-03-08 13:40:20.200088+00	2026-03-08 13:40:20.200088+00	1.000000
151	PIE088	2	1	BILLETERO MEDIANO - SOBRE BILLETERA GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.258233+00	2026-03-08 13:40:20.258233+00	25	25	2026-03-08 13:40:20.258233+00	2026-03-08 13:40:20.258233+00	1.000000
152	PIE089	2	1	PORTACHAPA CHICO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.316797+00	2026-03-08 13:40:20.316797+00	25	25	2026-03-08 13:40:20.316797+00	2026-03-08 13:40:20.316797+00	1.000000
153	PIE090	2	1	FORRO - SOBRE BILLETERA GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.374896+00	2026-03-08 13:40:20.374896+00	25	25	2026-03-08 13:40:20.374896+00	2026-03-08 13:40:20.374896+00	1.000000
154	PIE091	2	1	FRENTE - SOBRE BILLETERA GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.433515+00	2026-03-08 13:40:20.433515+00	25	25	2026-03-08 13:40:20.433515+00	2026-03-08 13:40:20.433515+00	1.000000
155	PIE092	2	1	HERRAJES - SOBRE BILLETERA GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.491676+00	2026-03-08 13:40:20.491676+00	25	25	2026-03-08 13:40:20.491676+00	2026-03-08 13:40:20.491676+00	1.000000
156	PIE093	2	1	MONEDERO - SOBRE BILLETERA GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.550117+00	2026-03-08 13:40:20.550117+00	25	25	2026-03-08 13:40:20.550117+00	2026-03-08 13:40:20.550117+00	1.000000
157	PIE094	2	1	TARJETERO - SOBRE BILLETERA GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.608271+00	2026-03-08 13:40:20.608271+00	25	25	2026-03-08 13:40:20.608271+00	2026-03-08 13:40:20.608271+00	1.000000
158	PIE095	2	1	TIRA SUBLIMADA - SOBRE BILLETERA GRANDE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.666839+00	2026-03-08 13:40:20.666839+00	25	25	2026-03-08 13:40:20.666839+00	2026-03-08 13:40:20.666839+00	1.000000
159	PIE096	2	1	SPAGUETI	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.724934+00	2026-03-08 13:40:20.724934+00	25	25	2026-03-08 13:40:20.724934+00	2026-03-08 13:40:20.724934+00	1.000000
170	PIE109	2	1	CONJUNTO VIVO FRENTE	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.467868+00	2026-03-08 13:40:21.467868+00	25	25	2026-03-08 13:40:21.467868+00	2026-03-08 13:40:21.467868+00	1.000000
174	PIE113	2	1	BASE - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.675215+00	2026-03-08 13:40:21.675215+00	25	25	2026-03-08 13:40:21.675215+00	2026-03-08 13:40:21.675215+00	1.000000
175	PIE114	2	1	BOLSILLO FRONTAL - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.733255+00	2026-03-08 13:40:21.733255+00	25	25	2026-03-08 13:40:21.733255+00	2026-03-08 13:40:21.733255+00	1.000000
176	PIE115	2	1	BOLSILLO INTERNO - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.791919+00	2026-03-08 13:40:21.791919+00	25	25	2026-03-08 13:40:21.791919+00	2026-03-08 13:40:21.791919+00	1.000000
177	PIE116	2	1	CIERRE - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.858216+00	2026-03-08 13:40:21.858216+00	25	25	2026-03-08 13:40:21.858216+00	2026-03-08 13:40:21.858216+00	1.000000
178	PIE117	2	1	FORRO BASE - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.916669+00	2026-03-08 13:40:21.916669+00	25	25	2026-03-08 13:40:21.916669+00	2026-03-08 13:40:21.916669+00	1.000000
179	PIE118	2	1	FORRO FRENTE Y CONTRAFRENTE - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.974761+00	2026-03-08 13:40:21.974761+00	25	25	2026-03-08 13:40:21.974761+00	2026-03-08 13:40:21.974761+00	1.000000
180	PIE119	2	1	FRENTE Y CONTRAFRENTE - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.025002+00	2026-03-08 13:40:22.025002+00	25	25	2026-03-08 13:40:22.025002+00	2026-03-08 13:40:22.025002+00	1.000000
181	PIE120	2	1	HERRAJES - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.083063+00	2026-03-08 13:40:22.083063+00	25	25	2026-03-08 13:40:22.083063+00	2026-03-08 13:40:22.083063+00	1.000000
182	PIE121	2	1	LATERALES - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.142086+00	2026-03-08 13:40:22.142086+00	25	25	2026-03-08 13:40:22.142086+00	2026-03-08 13:40:22.142086+00	1.000000
183	PIE122	2	1	TIRA HERRAJES - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.200276+00	2026-03-08 13:40:22.200276+00	25	25	2026-03-08 13:40:22.200276+00	2026-03-08 13:40:22.200276+00	1.000000
184	PIE123	2	1	TIRA SUBLIMADA - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.258447+00	2026-03-08 13:40:22.258447+00	25	25	2026-03-08 13:40:22.258447+00	2026-03-08 13:40:22.258447+00	1.000000
185	PIE124	2	1	VISTAS - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.316347+00	2026-03-08 13:40:22.316347+00	25	25	2026-03-08 13:40:22.316347+00	2026-03-08 13:40:22.316347+00	1.000000
186	PIE125	2	1	VISTAS CIERRE - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.367079+00	2026-03-08 13:40:22.367079+00	25	25	2026-03-08 13:40:22.367079+00	2026-03-08 13:40:22.367079+00	1.000000
187	PIE126	2	1	FORRO BOLSILLO - TOTE CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.424755+00	2026-03-08 13:40:22.424755+00	25	25	2026-03-08 13:40:22.424755+00	2026-03-08 13:40:22.424755+00	1.000000
188	PIE127	2	1	TRIANGULOS - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.483348+00	2026-03-08 13:40:22.483348+00	25	25	2026-03-08 13:40:22.483348+00	2026-03-08 13:40:22.483348+00	1.000000
189	PIE128	2	1	REFUERZO TIRAS - MOCHI CANELON	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.541409+00	2026-03-08 13:40:22.541409+00	25	25	2026-03-08 13:40:22.541409+00	2026-03-08 13:40:22.541409+00	1.000000
207	PIE146	2	1	BOLSILLO FRONTAL - RI├æONERA CHARO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.658078+00	2026-03-08 13:40:23.658078+00	25	25	2026-03-08 13:40:23.658078+00	2026-03-08 13:40:23.658078+00	1.000000
208	PIE147	2	1	FORRO BOLSILLO FRONTAL - RI├æONERA CHARO	0.0000000000	20	0.0000000000	20	\N	\N	\N	\N	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.708303+00	2026-03-08 13:40:23.708303+00	25	25	2026-03-08 13:40:23.708303+00	2026-03-08 13:40:23.708303+00	1.000000
67	PIE003	2	1	RELLENO - SOBRE BILLETERA GRANDE	250.0000000000	20	240.0000000000	20	\N	\N	0.0600000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:14.949771+00	2026-03-08 13:40:14.949771+00	25	25	2026-03-08 13:40:14.949771+00	2026-03-08 13:46:03.252791+00	1.000000
68	PIE005	2	1	BOLSILLO INTERNO - BANDOLERA CLOE	200.0000000000	20	120.0000000000	20	\N	\N	0.0240000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:15.008349+00	2026-03-08 13:40:15.008349+00	25	25	2026-03-08 13:40:15.008349+00	2026-03-08 18:30:21.977225+00	1.000000
37	MAP034	1	1	CORDURA	1000.0000000000	20	1500.0000000000	20	\N	\N	1.5000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 01:56:50.382216+00	2026-03-08 01:56:50.382216+00	3	3	2026-03-08 01:56:50.382216+00	2026-03-08 18:51:41.231195+00	1.000000
45	MAP001	1	1	MATELASE	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:50.608439+00	2026-03-08 02:06:50.608439+00	29	3	2026-03-08 02:06:50.608439+00	2026-03-08 18:51:41.231195+00	1.400000
46	MAP002	1	1	CHAROL	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:50.849852+00	2026-03-08 02:06:50.849852+00	29	3	2026-03-08 02:06:50.849852+00	2026-03-08 18:51:41.231195+00	1.400000
47	MAP003	1	1	RASADA BERLIN	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:51.083247+00	2026-03-08 02:06:51.083247+00	29	3	2026-03-08 02:06:51.083247+00	2026-03-08 18:51:41.231195+00	1.400000
48	MAP004	1	1	GRANEADO ATESSA 0,9	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:51.25854+00	2026-03-08 02:06:51.25854+00	29	3	2026-03-08 02:06:51.25854+00	2026-03-08 18:51:41.231195+00	1.400000
49	MAP005	1	1	CANELON	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:51.491962+00	2026-03-08 02:06:51.491962+00	29	3	2026-03-08 02:06:51.491962+00	2026-03-08 18:51:41.231195+00	1.400000
50	MAP006	1	1	RIO	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:51.666621+00	2026-03-08 02:06:51.666621+00	29	3	2026-03-08 02:06:51.666621+00	2026-03-08 18:51:41.231195+00	1.400000
51	MAP007	1	1	CROCCO	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:51.783255+00	2026-03-08 02:06:51.783255+00	29	3	2026-03-08 02:06:51.783255+00	2026-03-08 18:51:41.231195+00	1.400000
52	MAP008	1	1	GLITTER	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:51.899963+00	2026-03-08 02:06:51.899963+00	29	3	2026-03-08 02:06:51.899963+00	2026-03-08 18:51:41.231195+00	1.400000
53	MAP009	1	1	HOLOGRAFICO	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:52.075945+00	2026-03-08 02:06:52.075945+00	29	3	2026-03-08 02:06:52.075945+00	2026-03-08 18:51:41.231195+00	1.400000
54	MAP010	1	1	LENTEJUELAS	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:52.133281+00	2026-03-08 02:06:52.133281+00	29	3	2026-03-08 02:06:52.133281+00	2026-03-08 18:51:41.231195+00	1.400000
55	MAP012	1	1	NAPA METAL	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:52.300216+00	2026-03-08 02:06:52.300216+00	29	3	2026-03-08 02:06:52.300216+00	2026-03-08 18:51:41.231195+00	1.400000
56	MAP013	1	1	PANAMA PUNTOS	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:52.358298+00	2026-03-08 02:06:52.358298+00	29	3	2026-03-08 02:06:52.358298+00	2026-03-08 18:51:41.231195+00	1.400000
57	MAP014	1	1	PRAGA METALICO	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:52.417271+00	2026-03-08 02:06:52.417271+00	29	3	2026-03-08 02:06:52.417271+00	2026-03-08 18:51:41.231195+00	1.400000
60	MAP017	1	1	TOKIO	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:52.591686+00	2026-03-08 02:06:52.591686+00	29	3	2026-03-08 02:06:52.591686+00	2026-03-08 18:51:41.231195+00	1.400000
61	MAP035	1	1	CUERO FLEX	1300.0000000000	20	690.0000000000	20	\N	\N	0.8970000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:54.375158+00	2026-03-08 02:06:54.375158+00	29	3	2026-03-08 02:06:54.375158+00	2026-03-08 18:51:41.231195+00	0.530000
62	MAP042	1	1	PRAGA COMUN	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:54.95843+00	2026-03-08 02:06:54.95843+00	29	3	2026-03-08 02:06:54.95843+00	2026-03-08 18:51:41.231195+00	1.400000
63	MAP043	1	1	RASADA IMPORTADA	1000.0000000000	20	1400.0000000000	20	\N	\N	1.4000000000	3	\N	\N	{}	{}	t	\N	2026-03-08 02:06:55.016981+00	2026-03-08 02:06:55.016981+00	29	3	2026-03-08 02:06:55.016981+00	2026-03-08 18:51:41.231195+00	1.400000
69	PIE006	2	1	CORREA SUBLIMADA L	1400.0000000000	20	40.0000000000	20	\N	\N	0.0560000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:15.191638+00	2026-03-08 13:40:15.191638+00	25	25	2026-03-08 13:40:15.191638+00	2026-03-08 18:51:41.231195+00	1.000000
70	PIE007	2	1	CONTRAFRENTE - RI├æO DEPORTIVA	150.0000000000	20	420.0000000000	20	\N	\N	0.0630000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:15.250015+00	2026-03-08 13:40:15.250015+00	25	25	2026-03-08 13:40:15.250015+00	2026-03-08 18:51:41.231195+00	1.000000
75	PIE012	2	1	FORRO VIVO - BANDOLERA CLOE	750.0000000000	20	30.0000000000	20	\N	\N	0.0225000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:15.541484+00	2026-03-08 13:40:15.541484+00	25	25	2026-03-08 13:40:15.541484+00	2026-03-08 18:51:41.231195+00	1.000000
76	PIE013	2	1	FUELLE INFERIOR - BANDOLERA CLOE	440.0000000000	20	100.0000000000	20	\N	\N	0.0440000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:15.658123+00	2026-03-08 13:40:15.658123+00	25	25	2026-03-08 13:40:15.658123+00	2026-03-08 18:51:41.231195+00	1.000000
77	PIE014	2	1	FUELLE SUPERIOR - BANDOLERA CLOE	340.0000000000	20	50.0000000000	20	\N	\N	0.0170000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:15.774774+00	2026-03-08 13:40:15.774774+00	25	25	2026-03-08 13:40:15.774774+00	2026-03-08 18:51:41.231195+00	1.000000
79	PIE016	2	1	TIRA HERRAJES - BANDOLERA CLOE	120.0000000000	20	60.0000000000	20	\N	\N	0.0072000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:15.950032+00	2026-03-08 13:40:15.950032+00	25	25	2026-03-08 13:40:15.950032+00	2026-03-08 18:51:41.231195+00	1.000000
85	PIE022	2	1	BOLSILLO INTERNO - MOCHI BOMBON CHICA	150.0000000000	20	200.0000000000	20	\N	\N	0.0300000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.358193+00	2026-03-08 13:40:16.358193+00	25	25	2026-03-08 13:40:16.358193+00	2026-03-08 18:51:41.231195+00	1.000000
87	PIE024	2	1	FRENTE BOLSILLO LAT - MOCHI BOMBON CHICA	180.0000000000	20	100.0000000000	20	\N	\N	0.0180000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.474805+00	2026-03-08 13:40:16.474805+00	25	25	2026-03-08 13:40:16.474805+00	2026-03-08 18:51:41.231195+00	1.000000
89	PIE026	2	1	FUELLE INFERIOR BOLSILLO LATERAL - MOCHI BOMBON CHICA	320.0000000000	20	50.0000000000	20	\N	\N	0.0160000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.591561+00	2026-03-08 13:40:16.591561+00	25	25	2026-03-08 13:40:16.591561+00	2026-03-08 18:51:41.231195+00	1.000000
91	PIE028	2	1	FUELLE INFERIOR - MOCHI BOMBON CHICA	745.0000000000	20	140.0000000000	20	\N	\N	0.1043000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.708165+00	2026-03-08 13:40:16.708165+00	25	25	2026-03-08 13:40:16.708165+00	2026-03-08 18:51:41.231195+00	1.000000
92	PIE029	2	1	FUELLE SUPERIOR BOLSILLO LATERAL - MOCHI BOMBON CHICA	45.0000000000	20	20.0000000000	20	\N	\N	0.0009000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.767061+00	2026-03-08 13:40:16.767061+00	25	25	2026-03-08 13:40:16.767061+00	2026-03-08 18:51:41.231195+00	1.000000
93	PIE030	2	1	FUELLE SUPERIOR SOLAPA - MOCHI BOMBON CHICA	330.0000000000	20	70.0000000000	20	\N	\N	0.0231000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.824942+00	2026-03-08 13:40:16.824942+00	25	25	2026-03-08 13:40:16.824942+00	2026-03-08 18:51:41.231195+00	1.000000
94	PIE031	2	1	FUELLE SUPERIOR - MOCHI BOMBON CHICA	425.0000000000	20	140.0000000000	20	\N	\N	0.0595000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.883404+00	2026-03-08 13:40:16.883404+00	25	25	2026-03-08 13:40:16.883404+00	2026-03-08 18:51:41.231195+00	1.000000
96	PIE033	2	1	VIVO - MOCHI BOMBON CHICA	3000.0000000000	20	25.0000000000	20	\N	\N	0.0750000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:17.000137+00	2026-03-08 13:40:17.000137+00	25	25	2026-03-08 13:40:17.000137+00	2026-03-08 18:51:41.231195+00	1.000000
88	PIE025	2	1	FRENTE SOLAPA - MOCHI BOMBON CHICA	315.0000000000	20	260.0000000000	20	\N	\N	0.0819000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.533479+00	2026-03-08 13:40:16.533479+00	25	25	2026-03-08 13:40:16.533479+00	2026-03-09 00:59:34.624197+00	1.000000
122	PIE059	2	1	CORREA CUERO 2,5CM - RI├æONERA	1000.0000000000	20	70.0000000000	20	\N	\N	0.0700000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.566774+00	2026-03-08 13:40:18.566774+00	25	25	2026-03-08 13:40:18.566774+00	2026-03-08 18:51:41.231195+00	1.000000
123	PIE060	2	1	CORREA CUERO 3CM - RI├æONERA	1000.0000000000	20	80.0000000000	20	\N	\N	0.0800000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.624838+00	2026-03-08 13:40:18.624838+00	25	25	2026-03-08 13:40:18.624838+00	2026-03-08 18:51:41.231195+00	1.000000
124	PIE061	2	1	CORREA CUERO Y CADENA 2,5CM - RI├æONERA	1000.0000000000	20	70.0000000000	20	\N	\N	0.0700000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.68339+00	2026-03-08 13:40:18.68339+00	25	25	2026-03-08 13:40:18.68339+00	2026-03-08 18:51:41.231195+00	1.000000
125	PIE062	2	1	CORREA CUERO Y CADENA 3CM - RI├æONERA	1000.0000000000	20	80.0000000000	20	\N	\N	0.0800000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.741545+00	2026-03-08 13:40:18.741545+00	25	25	2026-03-08 13:40:18.741545+00	2026-03-08 18:51:41.231195+00	1.000000
126	PIE063	2	1	FRENTE - RI├æONERA CHARO	150.0000000000	20	220.0000000000	20	\N	\N	0.0330000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:18.80022+00	2026-03-08 13:40:18.80022+00	25	25	2026-03-08 13:40:18.80022+00	2026-03-08 18:51:41.231195+00	1.000000
133	PIE070	2	1	FORRO CONTRAFRENTE - RI├æO DEPORTIVA	180.0000000000	20	380.0000000000	20	\N	\N	0.0684000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.208234+00	2026-03-08 13:40:19.208234+00	25	25	2026-03-08 13:40:19.208234+00	2026-03-08 18:51:41.231195+00	1.000000
134	PIE071	2	1	MEDIALUNA 1 - RI├æO DEPORTIVA	80.0000000000	20	360.0000000000	20	\N	\N	0.0288000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:19.266668+00	2026-03-08 13:40:19.266668+00	25	25	2026-03-08 13:40:19.266668+00	2026-03-08 18:51:41.231195+00	1.000000
160	PIE097	2	1	BASE - TOTE FRANCIA	160.0000000000	20	350.0000000000	20	\N	\N	0.0560000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.783657+00	2026-03-08 13:40:20.783657+00	25	25	2026-03-08 13:40:20.783657+00	2026-03-08 18:51:41.231195+00	1.000000
161	PIE098	2	1	BASE CUEROFLEX - TOTE FRANCIA	135.0000000000	20	320.0000000000	20	\N	\N	0.0432000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.841528+00	2026-03-08 13:40:20.841528+00	25	25	2026-03-08 13:40:20.841528+00	2026-03-08 18:51:41.231195+00	1.000000
162	PIE099	2	1	BOLSILLO INTERNO - TOTE FRANCIA	160.0000000000	20	240.0000000000	20	\N	\N	0.0384000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.892002+00	2026-03-08 13:40:20.892002+00	25	25	2026-03-08 13:40:20.892002+00	2026-03-08 18:51:41.231195+00	1.000000
163	PIE100	2	1	CENTRO FRENTE - TOTE FRANCIA	390.0000000000	20	120.0000000000	20	\N	\N	0.0468000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:20.949901+00	2026-03-08 13:40:20.949901+00	25	25	2026-03-08 13:40:20.949901+00	2026-03-08 18:51:41.231195+00	1.000000
164	PIE101	2	1	CONTRAFRENTE - TOTE FRANCIA	390.0000000000	20	770.0000000000	20	\N	\N	0.3003000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.00014+00	2026-03-08 13:40:21.00014+00	25	25	2026-03-08 13:40:21.00014+00	2026-03-08 18:51:41.231195+00	1.000000
165	PIE102	2	1	FORRO BOLSILLO DE CIERRE - TOTE FRANCIA	300.0000000000	20	250.0000000000	20	\N	\N	0.0750000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.058267+00	2026-03-08 13:40:21.058267+00	25	25	2026-03-08 13:40:21.058267+00	2026-03-08 18:51:41.231195+00	1.000000
166	PIE103	2	1	FORRO FRENTE Y CONTRAFRENTE - TOTE FRANCIA	420.0000000000	20	510.0000000000	20	\N	\N	0.2142000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.116885+00	2026-03-08 13:40:21.116885+00	25	25	2026-03-08 13:40:21.116885+00	2026-03-08 18:51:41.231195+00	1.000000
167	PIE105	2	1	FORRO SPAGUETTI - TOTE FRANCIA	400.0000000000	20	25.0000000000	20	\N	\N	0.0100000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.174908+00	2026-03-08 13:40:21.174908+00	25	25	2026-03-08 13:40:21.174908+00	2026-03-08 18:51:41.231195+00	1.000000
168	PIE107	2	1	LATERAL FRENTE - TOTE FRANCIA	390.0000000000	20	90.0000000000	20	\N	\N	0.0351000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.233511+00	2026-03-08 13:40:21.233511+00	25	25	2026-03-08 13:40:21.233511+00	2026-03-08 18:51:41.231195+00	1.000000
169	PIE108	2	1	VIVO SPAGUETTI - TOTE FRANCIA	1400.0000000000	20	30.0000000000	20	\N	\N	0.0420000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.420388+00	2026-03-08 13:40:21.420388+00	25	25	2026-03-08 13:40:21.420388+00	2026-03-08 18:51:41.231195+00	1.000000
171	PIE110	2	1	TIRA LARGA SUBLIMADA	1500.0000000000	20	40.0000000000	20	\N	\N	0.0600000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.49996+00	2026-03-08 13:40:21.49996+00	25	25	2026-03-08 13:40:21.49996+00	2026-03-08 18:51:41.231195+00	1.000000
172	PIE111	2	1	VISTA DE CIERRE - TOTE FRANCIA	60.0000000000	20	350.0000000000	20	\N	\N	0.0210000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.55849+00	2026-03-08 13:40:21.55849+00	25	25	2026-03-08 13:40:21.55849+00	2026-03-08 18:51:41.231195+00	1.000000
173	PIE112	2	1	VISTA SUPERIOR - TOTE FRANCIA	50.0000000000	20	510.0000000000	20	\N	\N	0.0255000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:21.616604+00	2026-03-08 13:40:21.616604+00	25	25	2026-03-08 13:40:21.616604+00	2026-03-08 18:51:41.231195+00	1.000000
190	PIE129	2	1	FRENTE Y CONTRAFRENTE - BANDOLERA CLOE	240.0000000000	20	190.0000000000	20	\N	\N	0.0456000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.599975+00	2026-03-08 13:40:22.599975+00	25	25	2026-03-08 13:40:22.599975+00	2026-03-08 18:51:41.231195+00	1.000000
191	PIE130	2	1	TIRAHERRAJE MOSQUETON	70.0000000000	20	140.0000000000	20	\N	\N	0.0098000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.758036+00	2026-03-08 13:40:22.758036+00	25	25	2026-03-08 13:40:22.758036+00	2026-03-08 18:51:41.231195+00	1.000000
192	PIE131	2	1	MEDIALUNA 2 - RI├æO DEPORTIVA	40.0000000000	20	300.0000000000	20	\N	\N	0.0120000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.816762+00	2026-03-08 13:40:22.816762+00	25	25	2026-03-08 13:40:22.816762+00	2026-03-08 18:51:41.231195+00	1.000000
193	PIE132	2	1	MEDIALUNA 3 - RI├æO DEPORTIVA	40.0000000000	20	300.0000000000	20	\N	\N	0.0120000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.874769+00	2026-03-08 13:40:22.874769+00	25	25	2026-03-08 13:40:22.874769+00	2026-03-08 18:51:41.231195+00	1.000000
194	PIE133	2	1	MEDIALUNA 4 - RI├æO DEPORTIVA	60.0000000000	20	330.0000000000	20	\N	\N	0.0198000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.933366+00	2026-03-08 13:40:22.933366+00	25	25	2026-03-08 13:40:22.933366+00	2026-03-08 18:51:41.231195+00	1.000000
195	PIE134	2	1	FORRO A - RI├æO DEPORTIVA - RASADA NEGRO	115.0000000000	20	380.0000000000	20	\N	\N	0.0437000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:22.991952+00	2026-03-08 13:40:22.991952+00	25	25	2026-03-08 13:40:22.991952+00	2026-03-08 18:51:41.231195+00	1.000000
196	PIE135	2	1	FORRO 1 - RI├æO DEPORTIVA - RASADA NEGRO	150.0000000000	20	210.0000000000	20	\N	\N	0.0315000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.04991+00	2026-03-08 13:40:23.04991+00	25	25	2026-03-08 13:40:23.04991+00	2026-03-08 18:51:41.231195+00	1.000000
199	PIE138	2	1	BOLSILLO A - RI├æO DEPORTIVA	130.0000000000	20	30.0000000000	20	\N	\N	0.0039000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.208058+00	2026-03-08 13:40:23.208058+00	25	25	2026-03-08 13:40:23.208058+00	2026-03-08 18:51:41.231195+00	1.000000
202	PIE141	2	1	BOLSILLO 2 - RI├æO DEPORTIVA	100.0000000000	20	155.0000000000	20	\N	\N	0.0155000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.375116+00	2026-03-08 13:40:23.375116+00	25	25	2026-03-08 13:40:23.375116+00	2026-03-08 18:51:41.231195+00	1.000000
203	PIE142	2	1	BOLSILLO 3 - RI├æO DEPORTIVA	50.0000000000	20	160.0000000000	20	\N	\N	0.0080000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.433118+00	2026-03-08 13:40:23.433118+00	25	25	2026-03-08 13:40:23.433118+00	2026-03-08 18:51:41.231195+00	1.000000
204	PIE143	2	1	ALA GRANDE A y B - RI├æO DEPORTIVA	90.0000000000	20	90.0000000000	20	\N	\N	0.0081000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.491663+00	2026-03-08 13:40:23.491663+00	25	25	2026-03-08 13:40:23.491663+00	2026-03-08 18:51:41.231195+00	1.000000
206	PIE145	2	1	FORRO BOLSILLO INTERNO - MOCHI BOMBON CHICA	180.0000000000	20	40.0000000000	20	\N	\N	0.0072000000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.608299+00	2026-03-08 13:40:23.608299+00	25	25	2026-03-08 13:40:23.608299+00	2026-03-08 18:51:41.231195+00	1.000000
197	PIE136	2	1	FORRO 2 - RI├æO DEPORTIVA - RASADA NEGRO	115.0000000000	20	165.0000000000	20	\N	\N	0.0189750000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.099767+00	2026-03-08 13:40:23.099767+00	25	25	2026-03-08 13:40:23.099767+00	2026-03-09 14:08:00.369665+00	1.000000
198	PIE137	2	1	FORRO 3 - RI├æO DEPORTIVA - RASADA NEGRO	145.0000000000	20	195.0000000000	20	\N	\N	0.0282750000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.15823+00	2026-03-08 13:40:23.15823+00	25	25	2026-03-08 13:40:23.15823+00	2026-03-09 14:08:00.369665+00	1.000000
200	PIE139	2	1	BOLSILLO B - RI├æO DEPORTIVA	145.0000000000	20	195.0000000000	20	\N	\N	0.0282750000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.2666+00	2026-03-08 13:40:23.2666+00	25	25	2026-03-08 13:40:23.2666+00	2026-03-09 14:08:00.369665+00	1.000000
201	PIE140	2	1	BOLSILLO 1 - RI├æO DEPORTIVA	55.0000000000	20	145.0000000000	20	\N	\N	0.0079750000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.316499+00	2026-03-08 13:40:23.316499+00	25	25	2026-03-08 13:40:23.316499+00	2026-03-09 14:08:00.369665+00	1.000000
205	PIE144	2	1	ALA CHICA A y B - RI├æO DEPORTIVA	50.0000000000	20	125.0000000000	20	\N	\N	0.0062500000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:23.549794+00	2026-03-08 13:40:23.549794+00	25	25	2026-03-08 13:40:23.549794+00	2026-03-09 14:08:00.369665+00	1.000000
65	PIE001	2	1	FORRO VIVO - TOTE FRANCIA	390.0000000000	20	25.0000000000	20	\N	\N	0.0097500000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:14.694587+00	2026-03-08 13:40:14.694587+00	25	25	2026-03-08 13:40:14.694587+00	2026-03-09 14:06:10.542247+00	1.000000
86	PIE023	2	1	FRENTE Y CONTRAFRENTE - MOCHI BOMBON CHICA	370.0000000000	20	315.0000000000	20	\N	\N	0.1165500000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.416753+00	2026-03-08 13:40:16.416753+00	25	25	2026-03-08 13:40:16.416753+00	2026-03-09 14:08:00.369665+00	1.000000
90	PIE027	2	1	FUELLE INFERIOR SOLAPA - MOCHI BOMBON CHICA	645.0000000000	20	90.0000000000	20	\N	\N	0.0580500000	3	\N	\N	{}	{}	t	\N	2026-03-08 13:40:16.650115+00	2026-03-08 13:40:16.650115+00	25	25	2026-03-08 13:40:16.650115+00	2026-03-09 14:08:00.369665+00	1.000000
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
\.


--
-- Data for Name: schema_migrations; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.schema_migrations (id, filename, executed_at) FROM stdin;
\.


--
-- Data for Name: tipos_depositos; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.tipos_depositos (id, codigo, nombre, descripcion, orden, es_sistema, activo, created_at, updated_at) FROM stdin;
1	ALMACEN	Almacen	Deposito principal de almacenamiento	1	t	t	2026-03-06 10:10:51.930913	2026-03-06 10:10:51.930913
2	PRODUCCION	Produccion	Deposito de materiales en produccion	2	t	t	2026-03-06 10:10:51.930913	2026-03-06 10:10:51.930913
3	PRE-PRODUCCION	Pre-Produccion	Deposito de preparacion para produccion	3	t	t	2026-03-06 10:10:51.930913	2026-03-06 10:10:51.930913
4	PROVEEDOR	Proveedor	Deposito de proveedor	4	t	t	2026-03-06 10:10:51.930913	2026-03-06 10:10:51.930913
5	CLIENTE	Cliente	Deposito en ubicacion de cliente	5	t	t	2026-03-06 10:10:51.930913	2026-03-06 10:10:51.930913
6	AJUSTE	Ajuste	Deposito para ajustes de inventario	6	t	t	2026-03-06 10:10:51.930913	2026-03-06 10:10:51.930913
\.


--
-- Data for Name: tipos_depositos_movimientos; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.tipos_depositos_movimientos (id, tipo_deposito_origen_id, tipo_deposito_destino_id, activo, observaciones, created_at, updated_at) FROM stdin;
1	6	1	t	Ajuste de inventario - entrada	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
2	4	1	t	Ingreso de mercaderia desde proveedor	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
3	2	1	t	Retorno de material o producto terminado	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
4	6	2	t	Ajuste de inventario - entrada	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
5	1	2	t	Movimiento estandar de materiales a produccion	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
6	6	3	t	Ajuste de inventario - entrada	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
7	1	3	t	Movimiento de preparacion para produccion	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
8	6	4	t	Ajuste de inventario - entrada	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
9	6	5	t	Ajuste de inventario - entrada	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
10	1	5	t	Salida de mercaderia a cliente	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
11	5	6	t	Ajuste de inventario - salida	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
12	4	6	t	Ajuste de inventario - salida	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
13	3	6	t	Ajuste de inventario - salida	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
14	2	6	t	Ajuste de inventario - salida	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
15	1	6	t	Ajuste de inventario - salida	2026-03-06 10:10:52.008622	2026-03-06 10:10:52.008622
\.


--
-- Data for Name: tipos_partes; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.tipos_partes (id, codigo, nombre, descripcion, orden, activo, fecha_creacion, fecha_modificacion, requiere_stock) FROM stdin;
1	MP	MATERIA PRIMA		0	t	2026-03-06 18:58:35.885164+00	2026-03-06 18:58:35.885164+00	t
2	PZ	PIEZAS		0	t	2026-03-06 18:59:04.415968+00	2026-03-06 18:59:04.415968+00	t
3	PROD	PRODUCTOS		0	t	2026-03-06 19:04:02.630413+00	2026-03-06 19:04:02.630413+00	t
4	CONJ	CONJUNTOS		0	t	2026-03-06 19:04:29.379646+00	2026-03-06 19:04:29.379646+00	t
5	S-CONJ	SUB CONJUNTO		0	t	2026-03-06 19:04:56.47733+00	2026-03-06 19:04:56.47733+00	t
6	TER	TERCIARIZADOS		0	t	2026-03-06 19:05:20.249565+00	2026-03-06 19:05:20.249565+00	t
7	MO	MANO DE OBRA		0	t	2026-03-06 19:05:36.143431+00	2026-03-06 19:05:36.143431+00	f
\.


--
-- Data for Name: unidades_medida; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.unidades_medida (id, tipo, unidad, simbolo, equivalencia_base, es_base, activo, fecha_creacion, fecha_modificacion, is_system, locked) FROM stdin;
1	longitud	Pulgada	in	0.02540000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
2	superficie	Centimetro cuadrado	cm┬▓	0.00010000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
3	superficie	Metro cuadrado	m┬▓	1.00000000	t	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
4	tiempo	Hora	h	3600.00000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
5	longitud	Centimetro	cm	0.01000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
6	volumen	Centilitro	cl	0.01000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
7	volumen	Decilitro	dl	0.10000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
8	volumen	Metro cubico	m┬│	1000.00000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
9	masa	Gramo	g	0.00100000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
10	masa	Tonelada metrica	t	1000.00000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
11	masa	Onza	oz	0.02834950	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
12	masa	Libra	lb	0.45359200	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
13	tiempo	Segundo	s	1.00000000	t	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
14	tiempo	Minuto	min	60.00000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
15	tiempo	Dia	d	86400.00000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
16	tiempo	Semana	sem	604800.00000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
17	temperatura	Fahrenheit	┬░F	1.00000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
18	temperatura	Kelvin	K	1.00000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
19	temperatura	Celsius	┬░C	1.00000000	t	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
20	longitud	Milimetro	mm	0.00100000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
21	volumen	Litro	l	1.00000000	t	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
22	volumen	Mililitro/Centimetro cubico	ml/cm┬│	0.00100000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	f	f
23	masa	Kilogramo	kg	1.00000000	t	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	t	t
24	longitud	Metro	m	1.00000000	t	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	t	t
25	unidad	Unidad	u	1.00000000	t	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	t	t
26	unidad	Caja	caja	1.00000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	t	t
27	unidad	Rollo	rollo	1.00000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	t	t
28	unidad	Bobina	bobina	1.00000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	t	t
29	longitud	Metro lineal	mL	1.00000000	f	t	2026-03-06 10:10:52.073197+00	2026-03-06 10:10:52.073197+00	t	t
\.


--
-- Data for Name: variantes; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.variantes (id, id_parte, codigo_variante, detalle, estado, lote_minimo, punto_pedido, stock_seguridad, anticipo_compra, lead_time_produccion, stock_actual, peso, id_um_peso, ubicacion_defecto, atributos, creado_por, fecha_creacion, fecha_modificacion, costo, ubicacion_cuerpo, ubicacion_pasillo, ubicacion_estante) FROM stdin;
4	1	1	NEGRO PRAGA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-06 19:24:03.344566+00	2026-03-06 19:52:14.406959+00	0.00			
5	1	2	NEGRO MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-06 20:35:54.836763+00	2026-03-06 20:35:54.836763+00	0.00			
6	1	3	ROSA GRANEADO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-06 20:43:08.764826+00	2026-03-06 20:43:08.764826+00	0.00			
7	1	4	ROSA MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-06 20:43:19.606803+00	2026-03-06 20:43:19.606803+00	0.00			
8	1	5	CROCCO HOLO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-06 20:44:08.272052+00	2026-03-06 20:44:08.272052+00	0.00			
9	2	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-06 21:22:14.815773+00	2026-03-07 15:49:31.330995+00	0.00			
10	3	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-06 22:11:54.967454+00	2026-03-07 16:30:16.333352+00	0.00			
12	4	1	Negro	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 18:31:51.203428+00	2026-03-07 18:31:51.203428+00	0.00			
13	4	2	Rosa	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 18:32:11.466527+00	2026-03-07 18:32:11.466527+00	0.00			
14	4	3	Verde	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 18:33:15.494924+00	2026-03-07 18:33:15.494924+00	0.00			
16	8	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 18:41:47.563339+00	2026-03-07 18:41:59.624107+00	0.00			
17	9	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 18:43:17.417754+00	2026-03-07 18:43:30.192307+00	0.00			
11	7	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 18:30:21.60959+00	2026-03-07 19:00:32.542635+00	0.00			
18	11	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 19:21:16.890221+00	2026-03-07 19:21:29.260722+00	0.00			
19	12	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 19:22:57.635525+00	2026-03-07 19:23:06.695928+00	0.00			
20	13	1	NEGRO MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 19:54:53.89414+00	2026-03-07 19:55:32.832418+00	0.00			
22	14	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 19:57:57.520002+00	2026-03-07 19:58:08.214056+00	0.00			
23	15	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 19:58:41.280541+00	2026-03-07 19:58:55.333829+00	0.00			
24	16	1	NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 20:08:59.136866+00	2026-03-07 20:09:11.896415+00	0.00			
28	18	1	CHARO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:06.497495+00	2026-03-07 22:55:06.497495+00	0.00			
29	18	2	LUCY	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:06.585799+00	2026-03-07 22:55:06.585799+00	0.00			
30	18	3	FRANCIA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:06.661202+00	2026-03-07 22:55:06.661202+00	0.00			
31	18	4	TOTE CANELON	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:06.719064+00	2026-03-07 22:55:06.719064+00	0.00			
32	18	5	CLOE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:06.777523+00	2026-03-07 22:55:06.777523+00	0.00			
33	18	6	BILLETERA GRANDE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:06.835669+00	2026-03-07 22:55:06.835669+00	0.00			
34	18	7	RI├æONERA DEPORTIVA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:06.894283+00	2026-03-07 22:55:06.894283+00	0.00			
35	18	8	MOCHI BOMBON CHICA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:06.952413+00	2026-03-07 22:55:06.952413+00	0.00			
36	19	1	CHARO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:07.010912+00	2026-03-07 22:55:07.010912+00	0.00			
37	19	2	LUCY	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:07.069123+00	2026-03-07 22:55:07.069123+00	0.00			
38	19	3	FRANCIA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:07.127687+00	2026-03-07 22:55:07.127687+00	0.00			
39	19	4	TOTE CANELON	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:07.185819+00	2026-03-07 22:55:07.185819+00	0.00			
40	19	5	CLOE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:07.244763+00	2026-03-07 22:55:07.244763+00	0.00			
41	19	6	BILLETERA GRANDE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:07.302342+00	2026-03-07 22:55:07.302342+00	0.00			
42	19	7	RI├æONERA DEPORTIVA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:07.360872+00	2026-03-07 22:55:07.360872+00	0.00			
43	19	8	MOCHI BOMBON CHICA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:07.419377+00	2026-03-07 22:55:07.419377+00	0.00			
44	20	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-07 22:55:07.527962+00	2026-03-07 22:55:07.527962+00	0.00			
84	45	1	NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:50.608439+00	2026-03-08 02:06:50.608439+00	0.00			
85	45	2	BRILLO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:50.675226+00	2026-03-08 02:06:50.675226+00	0.00			
86	45	3	FINO negro	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:50.733223+00	2026-03-08 02:06:50.733223+00	0.00			
87	45	4	ROSA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:50.791901+00	2026-03-08 02:06:50.791901+00	0.00			
88	46	1	NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:50.849852+00	2026-03-08 02:06:50.849852+00	0.00			
89	46	2	BLANCO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:50.908467+00	2026-03-08 02:06:50.908467+00	0.00			
90	46	3	ROJO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:50.966525+00	2026-03-08 02:06:50.966525+00	0.00			
91	46	4	FUCSIA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.025218+00	2026-03-08 02:06:51.025218+00	0.00			
92	47	1	NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.083247+00	2026-03-08 02:06:51.083247+00	0.00			
95	48	1	NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.25854+00	2026-03-08 02:06:51.25854+00	0.00			
96	48	2	BORDO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.316606+00	2026-03-08 02:06:51.316606+00	0.00			
97	48	3	ROJO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.3751+00	2026-03-08 02:06:51.3751+00	0.00			
98	48	4	MARR├ôN	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.433284+00	2026-03-08 02:06:51.433284+00	0.00			
99	49	1	NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.491962+00	2026-03-08 02:06:51.491962+00	0.00			
100	49	2	ROSA TORNASOLADO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.55003+00	2026-03-08 02:06:51.55003+00	0.00			
101	49	3	LILA TORNASOLADO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.60852+00	2026-03-08 02:06:51.60852+00	0.00			
102	50	1	LISO NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.666621+00	2026-03-08 02:06:51.666621+00	0.00			
103	50	2	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.725214+00	2026-03-08 02:06:51.725214+00	0.00			
104	51	1	HOLO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.783255+00	2026-03-08 02:06:51.783255+00	0.00			
105	51	2	3D	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.841639+00	2026-03-08 02:06:51.841639+00	0.00			
106	52	1	PLATEADO GRUESO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.899963+00	2026-03-08 02:06:51.899963+00	0.00			
107	52	2	ROSA FINO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.958912+00	2026-03-08 02:06:51.958912+00	0.00			
108	52	3	NEGRO GRUESO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:52.016749+00	2026-03-08 02:06:52.016749+00	0.00			
109	53	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:52.075945+00	2026-03-08 02:06:52.075945+00	0.00			
110	54	1	NEGRO PLATEADO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:52.133281+00	2026-03-08 02:06:52.133281+00	0.00			
47	23	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:48.824325+00	2026-03-08 02:06:52.766891+00	0.00			
48	24	1	BAJA PESADA P30	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:48.894677+00	2026-03-08 02:06:52.825057+00	0.00			
49	24	2	ALTA PESADA P30	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:48.958384+00	2026-03-08 02:06:52.883569+00	0.00			
50	24	3	LIVIANA P25	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.045304+00	2026-03-08 02:06:52.941619+00	0.00			
51	24	4	BAJA LIVIANA P30	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.109514+00	2026-03-08 02:06:53.000401+00	0.00			
52	24	5	BAJA PESADA P25	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.173698+00	2026-03-08 02:06:53.058265+00	0.00			
53	25	1	PUENTE ABIERTO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.232694+00	2026-03-08 02:06:53.116927+00	0.00			
54	25	2	PALETON	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.298708+00	2026-03-08 02:06:53.174973+00	0.00			
55	26	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.35739+00	2026-03-08 02:06:53.233565+00	0.00			
56	27	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.415295+00	2026-03-08 02:06:53.29196+00	0.00			
57	28	1	GROUMET 250	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.473802+00	2026-03-08 02:06:53.351079+00	0.00			
58	29	1	TAPADO 8*8	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.531878+00	2026-03-08 02:06:53.409127+00	0.00			
59	30	1	CHATO P30	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.590859+00	2026-03-08 02:06:53.466866+00	0.00			
60	30	2	CHATO P25	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.648711+00	2026-03-08 02:06:53.525007+00	0.00			
61	31	1	100 CM, 10 GM	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.707075+00	2026-03-08 02:06:53.616945+00	0.00			
62	32	1	PUENTE ABIERTO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.765611+00	2026-03-08 02:06:53.675128+00	0.00			
63	32	2	PALETON	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.823888+00	2026-03-08 02:06:53.733617+00	0.00			
64	33	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.88198+00	2026-03-08 02:06:53.791767+00	0.00			
65	34	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.939068+00	2026-03-08 02:06:53.850219+00	0.00			
66	35	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:49.973644+00	2026-03-08 02:06:53.909328+00	0.00			
67	36	1	PESADO P30	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.032258+00	2026-03-08 02:06:53.966887+00	0.00			
68	36	2	LIVIANO P30	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.090405+00	2026-03-08 02:06:54.025019+00	0.00			
69	36	3	PESADO P25	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.149131+00	2026-03-08 02:06:54.083561+00	0.00			
70	36	4	LIVIANO P25	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.207272+00	2026-03-08 02:06:54.141651+00	0.00			
71	36	5	PLASTICO P40	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.265624+00	2026-03-08 02:06:54.200541+00	0.00			
72	36	6	LIVIANO P40	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.323809+00	2026-03-08 02:06:54.259206+00	0.00			
73	37	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.382216+00	2026-03-08 02:06:54.316942+00	0.00			
74	38	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.440402+00	2026-03-08 02:06:54.433626+00	0.00			
76	40	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.557241+00	2026-03-08 02:06:54.550371+00	0.00			
77	41	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.615536+00	2026-03-08 02:06:54.608354+00	0.00			
78	42	1	CHICA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.674342+00	2026-03-08 02:06:54.666925+00	0.00			
79	42	2	MEDIANA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.732262+00	2026-03-08 02:06:54.725023+00	0.00			
80	42	3	GRANDE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.790273+00	2026-03-08 02:06:54.783604+00	0.00			
81	43	1	PLASTICA P40	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.848968+00	2026-03-08 02:06:54.841684+00	0.00			
82	43	2	PLASTICA P30	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.915469+00	2026-03-08 02:06:54.900924+00	0.00			
83	44	1	P30	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.973796+00	2026-03-08 02:06:55.075086+00	0.00			
94	47	3	VERDE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:51.199971+00	2026-03-10 00:11:48.214705+00	0.00			
111	54	2	MULTICOLOR PLATEADO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:52.191827+00	2026-03-08 02:06:52.191827+00	0.00			
112	55	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:52.300216+00	2026-03-08 02:06:52.300216+00	0.00			
113	56	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:52.358298+00	2026-03-08 02:06:52.358298+00	0.00			
114	57	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:52.417271+00	2026-03-08 02:06:52.417271+00	0.00			
115	58	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:52.474962+00	2026-03-08 02:06:52.474962+00	0.00			
116	59	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:52.533531+00	2026-03-08 02:06:52.533531+00	0.00			
117	60	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:52.591686+00	2026-03-08 02:06:52.591686+00	0.00			
45	21	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:48.653624+00	2026-03-08 02:06:52.650224+00	0.00			
46	22	1	N5 - NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:48.773298+00	2026-03-08 02:06:52.708344+00	0.00			
118	61	1	0.6 MM	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:54.375158+00	2026-03-08 02:06:54.375158+00	0.00			
75	39	1	LIVIANO P40	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 01:56:50.498858+00	2026-03-08 02:06:54.492555+00	0.00			
119	62	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:54.95843+00	2026-03-08 02:06:54.95843+00	0.00			
120	63	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:55.016981+00	2026-03-08 02:06:55.016981+00	0.00			
121	64	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 02:06:55.133816+00	2026-03-08 02:06:55.133816+00	0.00			
122	65	1	NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:14.694587+00	2026-03-08 13:40:14.694587+00	0.00			
123	65	2	ROSA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:14.775173+00	2026-03-08 13:40:14.775173+00	0.00			
124	65	3	VERDE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:14.832952+00	2026-03-08 13:40:14.832952+00	0.00			
125	66	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:14.891752+00	2026-03-08 13:40:14.891752+00	0.00			
126	67	1	CUEROFLEX	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:14.949771+00	2026-03-08 13:40:14.949771+00	0.00			
127	68	1	CROCCO HOLO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.008349+00	2026-03-08 13:40:15.008349+00	0.00			
128	68	2	NEGRO PRAGA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.066543+00	2026-03-08 13:40:15.066543+00	0.00			
129	68	3	ROSA MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.133585+00	2026-03-08 13:40:15.133585+00	0.00			
130	69	1	NIQUEL	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.191638+00	2026-03-08 13:40:15.191638+00	0.00			
131	70	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.250015+00	2026-03-08 13:40:15.250015+00	0.00			
132	71	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.309416+00	2026-03-08 13:40:15.309416+00	0.00			
133	72	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.366789+00	2026-03-08 13:40:15.366789+00	0.00			
134	73	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.424754+00	2026-03-08 13:40:15.424754+00	0.00			
135	74	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.48338+00	2026-03-08 13:40:15.48338+00	0.00			
136	75	1	CROCCO HOLO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.541484+00	2026-03-08 13:40:15.541484+00	0.00			
137	75	2	NEGRO PRAGA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.600056+00	2026-03-08 13:40:15.600056+00	0.00			
138	76	1	CROCCO HOLO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.658123+00	2026-03-08 13:40:15.658123+00	0.00			
139	76	2	NEGRO PRAGA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.716786+00	2026-03-08 13:40:15.716786+00	0.00			
140	77	1	CROCCO HOLO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.774774+00	2026-03-08 13:40:15.774774+00	0.00			
141	77	2	NEGRO PRAGA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.83336+00	2026-03-08 13:40:15.83336+00	0.00			
142	78	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.891639+00	2026-03-08 13:40:15.891639+00	0.00			
143	79	1	CROCCO HOLO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:15.950032+00	2026-03-08 13:40:15.950032+00	0.00			
144	79	2	NEGRO PRAGA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.008138+00	2026-03-08 13:40:16.008138+00	0.00			
145	80	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.066748+00	2026-03-08 13:40:16.066748+00	0.00			
146	81	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.124819+00	2026-03-08 13:40:16.124819+00	0.00			
147	82	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.183332+00	2026-03-08 13:40:16.183332+00	0.00			
148	83	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.24209+00	2026-03-08 13:40:16.24209+00	0.00			
149	84	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.300129+00	2026-03-08 13:40:16.300129+00	0.00			
150	85	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.358193+00	2026-03-08 13:40:16.358193+00	0.00			
151	86	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.416753+00	2026-03-08 13:40:16.416753+00	0.00			
152	87	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.474805+00	2026-03-08 13:40:16.474805+00	0.00			
153	88	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.533479+00	2026-03-08 13:40:16.533479+00	0.00			
154	89	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.591561+00	2026-03-08 13:40:16.591561+00	0.00			
155	90	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.650115+00	2026-03-08 13:40:16.650115+00	0.00			
156	91	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.708165+00	2026-03-08 13:40:16.708165+00	0.00			
157	92	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.767061+00	2026-03-08 13:40:16.767061+00	0.00			
158	93	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.824942+00	2026-03-08 13:40:16.824942+00	0.00			
159	94	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.883404+00	2026-03-08 13:40:16.883404+00	0.00			
160	95	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:16.941593+00	2026-03-08 13:40:16.941593+00	0.00			
161	96	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.000137+00	2026-03-08 13:40:17.000137+00	0.00			
162	97	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.059194+00	2026-03-08 13:40:17.059194+00	0.00			
163	98	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.116274+00	2026-03-08 13:40:17.116274+00	0.00			
164	99	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.174944+00	2026-03-08 13:40:17.174944+00	0.00			
165	100	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.233126+00	2026-03-08 13:40:17.233126+00	0.00			
166	101	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.291788+00	2026-03-08 13:40:17.291788+00	0.00			
167	102	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.349771+00	2026-03-08 13:40:17.349771+00	0.00			
168	103	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.408372+00	2026-03-08 13:40:17.408372+00	0.00			
169	104	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.466925+00	2026-03-08 13:40:17.466925+00	0.00			
170	105	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.525017+00	2026-03-08 13:40:17.525017+00	0.00			
171	106	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.625116+00	2026-03-08 13:40:17.625116+00	0.00			
172	107	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.68315+00	2026-03-08 13:40:17.68315+00	0.00			
173	108	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.741672+00	2026-03-08 13:40:17.741672+00	0.00			
174	109	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.799811+00	2026-03-08 13:40:17.799811+00	0.00			
175	110	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.858388+00	2026-03-08 13:40:17.858388+00	0.00			
176	111	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.916434+00	2026-03-08 13:40:17.916434+00	0.00			
177	112	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:17.975427+00	2026-03-08 13:40:17.975427+00	0.00			
178	113	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.033133+00	2026-03-08 13:40:18.033133+00	0.00			
179	114	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.09172+00	2026-03-08 13:40:18.09172+00	0.00			
180	115	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.149804+00	2026-03-08 13:40:18.149804+00	0.00			
181	116	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.208392+00	2026-03-08 13:40:18.208392+00	0.00			
182	117	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.266649+00	2026-03-08 13:40:18.266649+00	0.00			
183	118	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.325192+00	2026-03-08 13:40:18.325192+00	0.00			
184	119	1	NEGRO MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.383208+00	2026-03-08 13:40:18.383208+00	0.00			
185	120	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.441803+00	2026-03-08 13:40:18.441803+00	0.00			
186	121	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.499922+00	2026-03-08 13:40:18.499922+00	0.00			
187	122	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.566774+00	2026-03-08 13:40:18.566774+00	0.00			
188	123	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.624838+00	2026-03-08 13:40:18.624838+00	0.00			
189	124	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.68339+00	2026-03-08 13:40:18.68339+00	0.00			
190	125	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.741545+00	2026-03-08 13:40:18.741545+00	0.00			
191	126	1	NEGRO MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.80022+00	2026-03-08 13:40:18.80022+00	0.00			
192	127	1	NEGRO MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.858268+00	2026-03-08 13:40:18.858268+00	0.00			
193	128	1	NEGRO MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.916824+00	2026-03-08 13:40:18.916824+00	0.00			
194	129	1	NEGRO MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:18.974885+00	2026-03-08 13:40:18.974885+00	0.00			
195	130	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.03345+00	2026-03-08 13:40:19.03345+00	0.00			
196	131	1	NEGRO MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.091557+00	2026-03-08 13:40:19.091557+00	0.00			
197	132	1	NEGRO MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.150137+00	2026-03-08 13:40:19.150137+00	0.00			
198	133	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.208234+00	2026-03-08 13:40:19.208234+00	0.00			
199	134	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.266668+00	2026-03-08 13:40:19.266668+00	0.00			
200	135	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.325403+00	2026-03-08 13:40:19.325403+00	0.00			
201	136	1	NEGRO MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.383534+00	2026-03-08 13:40:19.383534+00	0.00			
202	137	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.441508+00	2026-03-08 13:40:19.441508+00	0.00			
203	138	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.50018+00	2026-03-08 13:40:19.50018+00	0.00			
204	139	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.566592+00	2026-03-08 13:40:19.566592+00	0.00			
205	140	1	NIQUEL	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.625228+00	2026-03-08 13:40:19.625228+00	0.00			
206	141	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.683648+00	2026-03-08 13:40:19.683648+00	0.00			
207	142	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.741646+00	2026-03-08 13:40:19.741646+00	0.00			
208	143	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.791544+00	2026-03-08 13:40:19.791544+00	0.00			
209	144	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.850783+00	2026-03-08 13:40:19.850783+00	0.00			
210	145	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.908249+00	2026-03-08 13:40:19.908249+00	0.00			
211	146	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:19.966867+00	2026-03-08 13:40:19.966867+00	0.00			
212	147	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.024912+00	2026-03-08 13:40:20.024912+00	0.00			
213	148	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.083522+00	2026-03-08 13:40:20.083522+00	0.00			
214	149	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.141583+00	2026-03-08 13:40:20.141583+00	0.00			
215	150	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.200088+00	2026-03-08 13:40:20.200088+00	0.00			
216	151	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.258233+00	2026-03-08 13:40:20.258233+00	0.00			
217	152	1	HOLOGRAFICO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.316797+00	2026-03-08 13:40:20.316797+00	0.00			
218	153	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.374896+00	2026-03-08 13:40:20.374896+00	0.00			
219	154	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.433515+00	2026-03-08 13:40:20.433515+00	0.00			
220	155	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.491676+00	2026-03-08 13:40:20.491676+00	0.00			
221	156	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.550117+00	2026-03-08 13:40:20.550117+00	0.00			
222	157	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.608271+00	2026-03-08 13:40:20.608271+00	0.00			
223	158	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.666839+00	2026-03-08 13:40:20.666839+00	0.00			
224	159	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.724934+00	2026-03-08 13:40:20.724934+00	0.00			
225	160	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.783657+00	2026-03-08 13:40:20.783657+00	0.00			
226	161	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.841528+00	2026-03-08 13:40:20.841528+00	0.00			
227	162	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.892002+00	2026-03-08 13:40:20.892002+00	0.00			
228	163	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:20.949901+00	2026-03-08 13:40:20.949901+00	0.00			
229	164	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.00014+00	2026-03-08 13:40:21.00014+00	0.00			
230	165	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.058267+00	2026-03-08 13:40:21.058267+00	0.00			
231	166	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.116885+00	2026-03-08 13:40:21.116885+00	0.00			
232	167	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.174908+00	2026-03-08 13:40:21.174908+00	0.00			
233	168	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.233511+00	2026-03-08 13:40:21.233511+00	0.00			
234	169	1	NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.420388+00	2026-03-08 13:40:21.420388+00	0.00			
235	170	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.467868+00	2026-03-08 13:40:21.467868+00	0.00			
236	171	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.49996+00	2026-03-08 13:40:21.49996+00	0.00			
237	172	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.55849+00	2026-03-08 13:40:21.55849+00	0.00			
238	173	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.616604+00	2026-03-08 13:40:21.616604+00	0.00			
239	174	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.675215+00	2026-03-08 13:40:21.675215+00	0.00			
240	175	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.733255+00	2026-03-08 13:40:21.733255+00	0.00			
241	176	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.791919+00	2026-03-08 13:40:21.791919+00	0.00			
242	177	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.858216+00	2026-03-08 13:40:21.858216+00	0.00			
243	178	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.916669+00	2026-03-08 13:40:21.916669+00	0.00			
244	179	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:21.974761+00	2026-03-08 13:40:21.974761+00	0.00			
245	180	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.025002+00	2026-03-08 13:40:22.025002+00	0.00			
246	181	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.083063+00	2026-03-08 13:40:22.083063+00	0.00			
247	182	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.142086+00	2026-03-08 13:40:22.142086+00	0.00			
248	183	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.200276+00	2026-03-08 13:40:22.200276+00	0.00			
249	184	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.258447+00	2026-03-08 13:40:22.258447+00	0.00			
250	185	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.316347+00	2026-03-08 13:40:22.316347+00	0.00			
251	186	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.367079+00	2026-03-08 13:40:22.367079+00	0.00			
252	187	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.424755+00	2026-03-08 13:40:22.424755+00	0.00			
253	188	1	RIO NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.483348+00	2026-03-08 13:40:22.483348+00	0.00			
254	189	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.541409+00	2026-03-08 13:40:22.541409+00	0.00			
255	190	1	CROCCO HOLO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.599975+00	2026-03-08 13:40:22.599975+00	0.00			
256	190	2	NEGRO PRAGA	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.708243+00	2026-03-08 13:40:22.708243+00	0.00			
257	191	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.758036+00	2026-03-08 13:40:22.758036+00	0.00			
258	192	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.816762+00	2026-03-08 13:40:22.816762+00	0.00			
259	193	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.874769+00	2026-03-08 13:40:22.874769+00	0.00			
260	194	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.933366+00	2026-03-08 13:40:22.933366+00	0.00			
261	195	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:22.991952+00	2026-03-08 13:40:22.991952+00	0.00			
262	196	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.04991+00	2026-03-08 13:40:23.04991+00	0.00			
263	197	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.099767+00	2026-03-08 13:40:23.099767+00	0.00			
264	198	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.15823+00	2026-03-08 13:40:23.15823+00	0.00			
265	199	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.208058+00	2026-03-08 13:40:23.208058+00	0.00			
266	200	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.2666+00	2026-03-08 13:40:23.2666+00	0.00			
267	201	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.316499+00	2026-03-08 13:40:23.316499+00	0.00			
268	202	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.375116+00	2026-03-08 13:40:23.375116+00	0.00			
269	203	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.433118+00	2026-03-08 13:40:23.433118+00	0.00			
270	204	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.491663+00	2026-03-08 13:40:23.491663+00	0.00			
271	205	1	RASADA NEGRO	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.549794+00	2026-03-08 13:40:23.549794+00	0.00			
272	206	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.608299+00	2026-03-08 13:40:23.608299+00	0.00			
273	207	1	NEGRO MATELASE	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.658078+00	2026-03-08 13:40:23.658078+00	0.00			
274	208	1	N/A	activa	1.00	0.00	0.00	0	0	0.00	\N	\N	\N	{}	\N	2026-03-08 13:40:23.708303+00	2026-03-08 13:40:23.708303+00	0.00			
93	47	2	ROSA	activa	1.00	0.00	0.00	0	0	1.40	\N	\N	\N	{}	\N	2026-03-08 02:06:51.141777+00	2026-03-10 02:54:34.909549+00	0.00			
\.


--
-- Name: almacenes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.almacenes_id_seq', 2, true);


--
-- Name: bom_cabecera_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.bom_cabecera_id_seq', 26, true);


--
-- Name: bom_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.bom_detalle_id_seq', 78, true);


--
-- Name: centros_trabajo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.centros_trabajo_id_seq', 1, false);


--
-- Name: composicion_variantes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.composicion_variantes_id_seq', 1, false);


--
-- Name: compras_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.compras_id_seq', 1, true);


--
-- Name: configuracion_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.configuracion_id_seq', 1, false);


--
-- Name: entidades_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.entidades_id_seq', 1, true);


--
-- Name: grupos_partes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.grupos_partes_id_seq', 1, true);


--
-- Name: movimientos_inventario_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.movimientos_inventario_id_seq', 1, false);


--
-- Name: movimientos_stock_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.movimientos_stock_id_seq', 2, true);


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

SELECT pg_catalog.setval('public.ordenes_produccion_id_seq', 1, false);


--
-- Name: partes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.partes_id_seq', 209, true);


--
-- Name: planificacion_recursos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.planificacion_recursos_id_seq', 1, false);


--
-- Name: rutas_produccion_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.rutas_produccion_id_seq', 1, false);


--
-- Name: schema_migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.schema_migrations_id_seq', 1, false);


--
-- Name: tipos_depositos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.tipos_depositos_id_seq', 6, true);


--
-- Name: tipos_depositos_movimientos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.tipos_depositos_movimientos_id_seq', 15, true);


--
-- Name: tipos_partes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.tipos_partes_id_seq', 7, true);


--
-- Name: unidades_medida_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.unidades_medida_id_seq', 29, true);


--
-- Name: variantes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.variantes_id_seq', 275, true);


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
-- Name: compras compras_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_pkey PRIMARY KEY (id);


--
-- Name: configuracion configuracion_clave_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.configuracion
    ADD CONSTRAINT configuracion_clave_key UNIQUE (clave);


--
-- Name: configuracion_general configuracion_general_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.configuracion_general
    ADD CONSTRAINT configuracion_general_pkey PRIMARY KEY (id);


--
-- Name: configuracion configuracion_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.configuracion
    ADD CONSTRAINT configuracion_pkey PRIMARY KEY (id);


--
-- Name: entidades entidades_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.entidades
    ADD CONSTRAINT entidades_pkey PRIMARY KEY (id);


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
-- Name: movimientos_stock movimientos_stock_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.movimientos_stock
    ADD CONSTRAINT movimientos_stock_pkey PRIMARY KEY (id);


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
-- Name: schema_migrations schema_migrations_filename_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.schema_migrations
    ADD CONSTRAINT schema_migrations_filename_key UNIQUE (filename);


--
-- Name: schema_migrations schema_migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.schema_migrations
    ADD CONSTRAINT schema_migrations_pkey PRIMARY KEY (id);


--
-- Name: tipos_depositos tipos_depositos_codigo_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.tipos_depositos
    ADD CONSTRAINT tipos_depositos_codigo_key UNIQUE (codigo);


--
-- Name: tipos_depositos_movimientos tipos_depositos_movimientos_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.tipos_depositos_movimientos
    ADD CONSTRAINT tipos_depositos_movimientos_pkey PRIMARY KEY (id);


--
-- Name: tipos_depositos tipos_depositos_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.tipos_depositos
    ADD CONSTRAINT tipos_depositos_pkey PRIMARY KEY (id);


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
-- Name: tipos_depositos_movimientos uq_origen_destino; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.tipos_depositos_movimientos
    ADD CONSTRAINT uq_origen_destino UNIQUE (tipo_deposito_origen_id, tipo_deposito_destino_id);


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
-- Name: idx_mov_destino; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_mov_destino ON public.movimientos_stock USING btree (id_tipo_deposito_destino);


--
-- Name: idx_mov_fecha; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_mov_fecha ON public.movimientos_stock USING btree (fecha);


--
-- Name: idx_mov_origen; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_mov_origen ON public.movimientos_stock USING btree (id_tipo_deposito_origen);


--
-- Name: idx_mov_referencia; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_mov_referencia ON public.movimientos_stock USING btree (referencia_tipo, referencia_id);


--
-- Name: idx_mov_variante; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_mov_variante ON public.movimientos_stock USING btree (id_variante);


--
-- Name: idx_movimientos_fecha; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_movimientos_fecha ON public.movimientos_inventario USING btree (fecha_movimiento);


--
-- Name: idx_movimientos_variante; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_movimientos_variante ON public.movimientos_inventario USING btree (variante_id);


--
-- Name: idx_partes_id_um_compra; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_partes_id_um_compra ON public.partes USING btree (id_um_compra);


--
-- Name: idx_partes_id_um_uso; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_partes_id_um_uso ON public.partes USING btree (id_um_uso);


--
-- Name: idx_planificacion_periodo; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_planificacion_periodo ON public.planificacion_recursos USING gist (centro_trabajo_id, periodo);


--
-- Name: idx_tdm_activo; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_tdm_activo ON public.tipos_depositos_movimientos USING btree (activo);


--
-- Name: idx_tdm_destino; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_tdm_destino ON public.tipos_depositos_movimientos USING btree (tipo_deposito_destino_id);


--
-- Name: idx_tdm_origen; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_tdm_origen ON public.tipos_depositos_movimientos USING btree (tipo_deposito_origen_id);


--
-- Name: idx_tipos_depositos_activo; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_tipos_depositos_activo ON public.tipos_depositos USING btree (activo);


--
-- Name: idx_tipos_depositos_codigo; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_tipos_depositos_codigo ON public.tipos_depositos USING btree (codigo);


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

CREATE TRIGGER set_timestamp_variantes BEFORE UPDATE ON public.variantes FOR EACH ROW EXECUTE FUNCTION public.update_fecha_modificacion_column();


--
-- Name: TRIGGER set_timestamp_variantes ON variantes; Type: COMMENT; Schema: public; Owner: mrp
--

COMMENT ON TRIGGER set_timestamp_variantes ON public.variantes IS 'Actualiza automÔö£├¡ticamente fecha_modificacion en cada UPDATE';


--
-- Name: movimientos_inventario trg_actualizar_stock; Type: TRIGGER; Schema: public; Owner: mrp
--

CREATE TRIGGER trg_actualizar_stock AFTER INSERT ON public.movimientos_inventario FOR EACH ROW EXECUTE FUNCTION public.actualizar_stock_trigger();


--
-- Name: partes update_partes_updated_at; Type: TRIGGER; Schema: public; Owner: mrp
--

CREATE TRIGGER update_partes_updated_at BEFORE UPDATE ON public.partes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


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
-- Name: compras fk_compras_entidad; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT fk_compras_entidad FOREIGN KEY (id_entidad) REFERENCES public.entidades(id);


--
-- Name: compras fk_compras_movimiento; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT fk_compras_movimiento FOREIGN KEY (id_movimiento_stock) REFERENCES public.movimientos_stock(id) ON DELETE CASCADE;


--
-- Name: movimientos_stock fk_mov_variante; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.movimientos_stock
    ADD CONSTRAINT fk_mov_variante FOREIGN KEY (id_variante) REFERENCES public.variantes(id) ON DELETE RESTRICT;


--
-- Name: tipos_depositos_movimientos fk_tipo_destino; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.tipos_depositos_movimientos
    ADD CONSTRAINT fk_tipo_destino FOREIGN KEY (tipo_deposito_destino_id) REFERENCES public.tipos_depositos(id) ON DELETE CASCADE;


--
-- Name: tipos_depositos_movimientos fk_tipo_origen; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.tipos_depositos_movimientos
    ADD CONSTRAINT fk_tipo_origen FOREIGN KEY (tipo_deposito_origen_id) REFERENCES public.tipos_depositos(id) ON DELETE CASCADE;


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
-- Name: partes partes_id_um_compra_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_compra_fkey FOREIGN KEY (id_um_compra) REFERENCES public.unidades_medida(id);


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
-- Name: partes partes_id_um_uso_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_uso_fkey FOREIGN KEY (id_um_uso) REFERENCES public.unidades_medida(id);


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

\unrestrict mrQN1Ev1SiESDBqvu3wRvLWVDBQGUTPFI0KwdUTCody03rZlH4L5beuTtmlCbid

