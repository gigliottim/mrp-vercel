-- Wizard SQL 00 - Estructura completa MRP (sin datos)
-- Generado desde: mrp_tunna
-- Fecha: 2026-03-23 15:43:29
--
--
-- PostgreSQL database dump
--

\restrict GTElbXYaPfT4MW3qdF9kQO6nDSfDfs2rO7ahrNQLp6D5VeRhb4Yx3GLHX8dXv42

-- Dumped from database version 18.3
-- Dumped by pg_dump version 18.3

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
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

-- *not* creating schema, since initdb creates it


--
-- Name: btree_gist; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS btree_gist WITH SCHEMA public;


--
-- Name: EXTENSION btree_gist; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION btree_gist IS 'support for indexing common datatypes in GiST';


--
-- Name: ltree; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS ltree WITH SCHEMA public;


--
-- Name: EXTENSION ltree; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION ltree IS 'data type for hierarchical tree-like structures';


--
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


--
-- Name: actualizar_stock_trigger(); Type: FUNCTION; Schema: public; Owner: -
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


--
-- Name: update_fecha_modificacion_column(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_fecha_modificacion_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.fecha_modificacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


--
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: almacenes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.almacenes (
    id integer NOT NULL,
    codigo character varying(20) NOT NULL,
    nombre character varying(100) NOT NULL,
    es_deposito_venta boolean DEFAULT true,
    es_deposito_produccion boolean DEFAULT false,
    activo boolean DEFAULT true
);


--
-- Name: almacenes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: bom_cabecera; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: bom_cabecera_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: bom_detalle; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: bom_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: centros_trabajo; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: centros_trabajo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: composicion_variantes; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: composicion_variantes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: compras; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: compras_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: configuracion; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: configuracion_general; Type: TABLE; Schema: public; Owner: -
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
    CONSTRAINT chk_configuracion_general_rounding_mode CHECK (((rounding_mode)::text = ANY (ARRAY[('half_up'::character varying)::text, ('half_down'::character varying)::text, ('half_even'::character varying)::text, ('truncate'::character varying)::text]))),
    CONSTRAINT chk_configuracion_general_separators CHECK (((thousand_separator)::text <> (decimal_separator)::text)),
    CONSTRAINT configuracion_general_decimal_places_check CHECK (((decimal_places >= 1) AND (decimal_places <= 10))),
    CONSTRAINT configuracion_general_id_check CHECK ((id = 1))
);


--
-- Name: configuracion_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: entidades; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: entidades_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: grupos_partes; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: grupos_partes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: movimientos_inventario; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: movimientos_inventario_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: movimientos_stock; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: TABLE movimientos_stock; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.movimientos_stock IS 'Historial de movimientos de stock entre depÔö£Ôöésitos (tipos)';


--
-- Name: COLUMN movimientos_stock.cantidad; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.movimientos_stock.cantidad IS 'Cantidad movida en Unidad de Uso de la variante';


--
-- Name: movimientos_stock_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: mrp_calculos_cabecera; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mrp_calculos_cabecera (
    id integer NOT NULL,
    fecha_calculo timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    escenario character varying(50) DEFAULT 'OFICIAL'::character varying,
    usuario_id bigint,
    parametros_usados jsonb
);


--
-- Name: mrp_calculos_cabecera_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: mrp_sugerencias; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: mrp_sugerencias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: ordenes_produccion; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: ordenes_produccion_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: partes; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: COLUMN partes.id_um_compra; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.partes.id_um_compra IS 'Unidad de medida en la que se compra el Ôö£├óÔö¼┬ítem';


--
-- Name: COLUMN partes.id_um_uso; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.partes.id_um_uso IS 'Unidad de medida en la que se usa el Ôö£├óÔö¼┬ítem en producciÔö£├óÔö¼Ôöén';


--
-- Name: COLUMN partes.factor_conversion; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.partes.factor_conversion IS 'Factor de conversiÔö£Ôöén: 1 UM Compra = X UM Uso';


--
-- Name: partes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: planificacion_recursos; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: planificacion_recursos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: rutas_produccion; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: rutas_produccion_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: schema_migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.schema_migrations (
    id integer NOT NULL,
    filename character varying(255) NOT NULL,
    executed_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: schema_migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.schema_migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: schema_migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.schema_migrations_id_seq OWNED BY public.schema_migrations.id;


--
-- Name: tipos_depositos; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: TABLE tipos_depositos; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.tipos_depositos IS 'CatÔö£├óÔö¼├¡logo de tipos de depÔö£├óÔö¼Ôöésito del sistema';


--
-- Name: COLUMN tipos_depositos.es_sistema; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.tipos_depositos.es_sistema IS 'Indica si el tipo es del sistema y no puede ser eliminado';


--
-- Name: tipos_depositos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: tipos_depositos_movimientos; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: TABLE tipos_depositos_movimientos; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.tipos_depositos_movimientos IS 'Configuraci??n de movimientos permitidos entre tipos de dep??sitos';


--
-- Name: COLUMN tipos_depositos_movimientos.tipo_deposito_origen_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.tipos_depositos_movimientos.tipo_deposito_origen_id IS 'Tipo de dep??sito de origen del movimiento';


--
-- Name: COLUMN tipos_depositos_movimientos.tipo_deposito_destino_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.tipos_depositos_movimientos.tipo_deposito_destino_id IS 'Tipo de dep??sito de destino del movimiento';


--
-- Name: COLUMN tipos_depositos_movimientos.activo; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.tipos_depositos_movimientos.activo IS 'Indica si el movimiento est?? habilitado';


--
-- Name: tipos_depositos_movimientos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: tipos_partes; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: tipos_partes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: unidades_medida; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: unidades_medida_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: variantes; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: variantes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

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


--
-- Name: almacenes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.almacenes ALTER COLUMN id SET DEFAULT nextval('public.almacenes_id_seq'::regclass);


--
-- Name: bom_cabecera id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bom_cabecera ALTER COLUMN id SET DEFAULT nextval('public.bom_cabecera_id_seq'::regclass);


--
-- Name: bom_detalle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bom_detalle ALTER COLUMN id SET DEFAULT nextval('public.bom_detalle_id_seq'::regclass);


--
-- Name: centros_trabajo id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.centros_trabajo ALTER COLUMN id SET DEFAULT nextval('public.centros_trabajo_id_seq'::regclass);


--
-- Name: composicion_variantes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.composicion_variantes ALTER COLUMN id SET DEFAULT nextval('public.composicion_variantes_id_seq'::regclass);


--
-- Name: compras id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras ALTER COLUMN id SET DEFAULT nextval('public.compras_id_seq'::regclass);


--
-- Name: configuracion id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracion ALTER COLUMN id SET DEFAULT nextval('public.configuracion_id_seq'::regclass);


--
-- Name: entidades id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entidades ALTER COLUMN id SET DEFAULT nextval('public.entidades_id_seq'::regclass);


--
-- Name: grupos_partes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grupos_partes ALTER COLUMN id SET DEFAULT nextval('public.grupos_partes_id_seq'::regclass);


--
-- Name: movimientos_inventario id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_inventario ALTER COLUMN id SET DEFAULT nextval('public.movimientos_inventario_id_seq'::regclass);


--
-- Name: movimientos_stock id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_stock ALTER COLUMN id SET DEFAULT nextval('public.movimientos_stock_id_seq'::regclass);


--
-- Name: mrp_calculos_cabecera id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mrp_calculos_cabecera ALTER COLUMN id SET DEFAULT nextval('public.mrp_calculos_cabecera_id_seq'::regclass);


--
-- Name: mrp_sugerencias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mrp_sugerencias ALTER COLUMN id SET DEFAULT nextval('public.mrp_sugerencias_id_seq'::regclass);


--
-- Name: ordenes_produccion id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_produccion ALTER COLUMN id SET DEFAULT nextval('public.ordenes_produccion_id_seq'::regclass);


--
-- Name: partes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes ALTER COLUMN id SET DEFAULT nextval('public.partes_id_seq'::regclass);


--
-- Name: planificacion_recursos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planificacion_recursos ALTER COLUMN id SET DEFAULT nextval('public.planificacion_recursos_id_seq'::regclass);


--
-- Name: rutas_produccion id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rutas_produccion ALTER COLUMN id SET DEFAULT nextval('public.rutas_produccion_id_seq'::regclass);


--
-- Name: schema_migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_migrations ALTER COLUMN id SET DEFAULT nextval('public.schema_migrations_id_seq'::regclass);


--
-- Name: tipos_depositos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_depositos ALTER COLUMN id SET DEFAULT nextval('public.tipos_depositos_id_seq'::regclass);


--
-- Name: tipos_depositos_movimientos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_depositos_movimientos ALTER COLUMN id SET DEFAULT nextval('public.tipos_depositos_movimientos_id_seq'::regclass);


--
-- Name: tipos_partes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_partes ALTER COLUMN id SET DEFAULT nextval('public.tipos_partes_id_seq'::regclass);


--
-- Name: unidades_medida id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unidades_medida ALTER COLUMN id SET DEFAULT nextval('public.unidades_medida_id_seq'::regclass);


--
-- Name: variantes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.variantes ALTER COLUMN id SET DEFAULT nextval('public.variantes_id_seq'::regclass);


--
-- Name: almacenes almacenes_codigo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.almacenes
    ADD CONSTRAINT almacenes_codigo_key UNIQUE (codigo);


--
-- Name: almacenes almacenes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.almacenes
    ADD CONSTRAINT almacenes_pkey PRIMARY KEY (id);


--
-- Name: bom_cabecera bom_cabecera_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bom_cabecera
    ADD CONSTRAINT bom_cabecera_pkey PRIMARY KEY (id);


--
-- Name: bom_cabecera bom_cabecera_variante_padre_id_version_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bom_cabecera
    ADD CONSTRAINT bom_cabecera_variante_padre_id_version_key UNIQUE (variante_padre_id, version);


--
-- Name: bom_detalle bom_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bom_detalle
    ADD CONSTRAINT bom_detalle_pkey PRIMARY KEY (id);


--
-- Name: centros_trabajo centros_trabajo_codigo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.centros_trabajo
    ADD CONSTRAINT centros_trabajo_codigo_key UNIQUE (codigo);


--
-- Name: centros_trabajo centros_trabajo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.centros_trabajo
    ADD CONSTRAINT centros_trabajo_pkey PRIMARY KEY (id);


--
-- Name: composicion_variantes composicion_variantes_id_padre_id_hijo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.composicion_variantes
    ADD CONSTRAINT composicion_variantes_id_padre_id_hijo_key UNIQUE (id_padre, id_hijo);


--
-- Name: composicion_variantes composicion_variantes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.composicion_variantes
    ADD CONSTRAINT composicion_variantes_pkey PRIMARY KEY (id);


--
-- Name: compras compras_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_pkey PRIMARY KEY (id);


--
-- Name: configuracion configuracion_clave_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracion
    ADD CONSTRAINT configuracion_clave_key UNIQUE (clave);


--
-- Name: configuracion_general configuracion_general_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracion_general
    ADD CONSTRAINT configuracion_general_pkey PRIMARY KEY (id);


--
-- Name: configuracion configuracion_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracion
    ADD CONSTRAINT configuracion_pkey PRIMARY KEY (id);


--
-- Name: entidades entidades_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.entidades
    ADD CONSTRAINT entidades_pkey PRIMARY KEY (id);


--
-- Name: grupos_partes grupos_partes_codigo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grupos_partes
    ADD CONSTRAINT grupos_partes_codigo_key UNIQUE (codigo);


--
-- Name: grupos_partes grupos_partes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.grupos_partes
    ADD CONSTRAINT grupos_partes_pkey PRIMARY KEY (id);


--
-- Name: movimientos_inventario movimientos_inventario_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_pkey PRIMARY KEY (id);


--
-- Name: movimientos_stock movimientos_stock_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_stock
    ADD CONSTRAINT movimientos_stock_pkey PRIMARY KEY (id);


--
-- Name: mrp_calculos_cabecera mrp_calculos_cabecera_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mrp_calculos_cabecera
    ADD CONSTRAINT mrp_calculos_cabecera_pkey PRIMARY KEY (id);


--
-- Name: mrp_sugerencias mrp_sugerencias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mrp_sugerencias
    ADD CONSTRAINT mrp_sugerencias_pkey PRIMARY KEY (id);


--
-- Name: ordenes_produccion ordenes_produccion_numero_orden_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_produccion
    ADD CONSTRAINT ordenes_produccion_numero_orden_key UNIQUE (numero_orden);


--
-- Name: ordenes_produccion ordenes_produccion_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_produccion
    ADD CONSTRAINT ordenes_produccion_pkey PRIMARY KEY (id);


--
-- Name: partes partes_codigo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_codigo_key UNIQUE (codigo);


--
-- Name: partes partes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_pkey PRIMARY KEY (id);


--
-- Name: planificacion_recursos planificacion_recursos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planificacion_recursos
    ADD CONSTRAINT planificacion_recursos_pkey PRIMARY KEY (id);


--
-- Name: rutas_produccion rutas_produccion_bom_id_secuencia_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rutas_produccion
    ADD CONSTRAINT rutas_produccion_bom_id_secuencia_key UNIQUE (bom_id, secuencia);


--
-- Name: rutas_produccion rutas_produccion_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rutas_produccion
    ADD CONSTRAINT rutas_produccion_pkey PRIMARY KEY (id);


--
-- Name: schema_migrations schema_migrations_filename_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_migrations
    ADD CONSTRAINT schema_migrations_filename_key UNIQUE (filename);


--
-- Name: schema_migrations schema_migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_migrations
    ADD CONSTRAINT schema_migrations_pkey PRIMARY KEY (id);


--
-- Name: tipos_depositos tipos_depositos_codigo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_depositos
    ADD CONSTRAINT tipos_depositos_codigo_key UNIQUE (codigo);


--
-- Name: tipos_depositos_movimientos tipos_depositos_movimientos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_depositos_movimientos
    ADD CONSTRAINT tipos_depositos_movimientos_pkey PRIMARY KEY (id);


--
-- Name: tipos_depositos tipos_depositos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_depositos
    ADD CONSTRAINT tipos_depositos_pkey PRIMARY KEY (id);


--
-- Name: tipos_partes tipos_partes_codigo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_partes
    ADD CONSTRAINT tipos_partes_codigo_key UNIQUE (codigo);


--
-- Name: tipos_partes tipos_partes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_partes
    ADD CONSTRAINT tipos_partes_pkey PRIMARY KEY (id);


--
-- Name: unidades_medida unidades_medida_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_pkey PRIMARY KEY (id);


--
-- Name: unidades_medida unidades_medida_tipo_simbolo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_tipo_simbolo_key UNIQUE (tipo, simbolo);


--
-- Name: unidades_medida unidades_medida_tipo_unidad_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_tipo_unidad_key UNIQUE (tipo, unidad);


--
-- Name: tipos_depositos_movimientos uq_origen_destino; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_depositos_movimientos
    ADD CONSTRAINT uq_origen_destino UNIQUE (tipo_deposito_origen_id, tipo_deposito_destino_id);


--
-- Name: variantes variantes_id_parte_codigo_variante_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.variantes
    ADD CONSTRAINT variantes_id_parte_codigo_variante_key UNIQUE (id_parte, codigo_variante);


--
-- Name: variantes variantes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.variantes
    ADD CONSTRAINT variantes_pkey PRIMARY KEY (id);


--
-- Name: idx_mov_destino; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_mov_destino ON public.movimientos_stock USING btree (id_tipo_deposito_destino);


--
-- Name: idx_mov_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_mov_fecha ON public.movimientos_stock USING btree (fecha);


--
-- Name: idx_mov_origen; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_mov_origen ON public.movimientos_stock USING btree (id_tipo_deposito_origen);


--
-- Name: idx_mov_referencia; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_mov_referencia ON public.movimientos_stock USING btree (referencia_tipo, referencia_id);


--
-- Name: idx_mov_variante; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_mov_variante ON public.movimientos_stock USING btree (id_variante);


--
-- Name: idx_movimientos_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimientos_fecha ON public.movimientos_inventario USING btree (fecha_movimiento);


--
-- Name: idx_movimientos_variante; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimientos_variante ON public.movimientos_inventario USING btree (variante_id);


--
-- Name: idx_partes_id_um_compra; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_partes_id_um_compra ON public.partes USING btree (id_um_compra);


--
-- Name: idx_partes_id_um_uso; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_partes_id_um_uso ON public.partes USING btree (id_um_uso);


--
-- Name: idx_planificacion_periodo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_planificacion_periodo ON public.planificacion_recursos USING gist (centro_trabajo_id, periodo);


--
-- Name: idx_tdm_activo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_tdm_activo ON public.tipos_depositos_movimientos USING btree (activo);


--
-- Name: idx_tdm_destino; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_tdm_destino ON public.tipos_depositos_movimientos USING btree (tipo_deposito_destino_id);


--
-- Name: idx_tdm_origen; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_tdm_origen ON public.tipos_depositos_movimientos USING btree (tipo_deposito_origen_id);


--
-- Name: idx_tipos_depositos_activo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_tipos_depositos_activo ON public.tipos_depositos USING btree (activo);


--
-- Name: idx_tipos_depositos_codigo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_tipos_depositos_codigo ON public.tipos_depositos USING btree (codigo);


--
-- Name: idx_variantes_atributos; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_variantes_atributos ON public.variantes USING gin (atributos);


--
-- Name: ordenes_produccion set_timestamp_ordenes; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER set_timestamp_ordenes BEFORE UPDATE ON public.ordenes_produccion FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: partes set_timestamp_partes; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER set_timestamp_partes BEFORE UPDATE ON public.partes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: variantes set_timestamp_variantes; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER set_timestamp_variantes BEFORE UPDATE ON public.variantes FOR EACH ROW EXECUTE FUNCTION public.update_fecha_modificacion_column();


--
-- Name: TRIGGER set_timestamp_variantes ON variantes; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TRIGGER set_timestamp_variantes ON public.variantes IS 'Actualiza autom├íticamente fecha_modificacion en cada UPDATE';


--
-- Name: movimientos_inventario trg_actualizar_stock; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_actualizar_stock AFTER INSERT ON public.movimientos_inventario FOR EACH ROW EXECUTE FUNCTION public.actualizar_stock_trigger();


--
-- Name: partes update_partes_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_partes_updated_at BEFORE UPDATE ON public.partes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: bom_cabecera bom_cabecera_variante_padre_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bom_cabecera
    ADD CONSTRAINT bom_cabecera_variante_padre_id_fkey FOREIGN KEY (variante_padre_id) REFERENCES public.variantes(id);


--
-- Name: bom_detalle bom_detalle_bom_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bom_detalle
    ADD CONSTRAINT bom_detalle_bom_id_fkey FOREIGN KEY (bom_id) REFERENCES public.bom_cabecera(id) ON DELETE CASCADE;


--
-- Name: bom_detalle bom_detalle_unidad_medida_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bom_detalle
    ADD CONSTRAINT bom_detalle_unidad_medida_id_fkey FOREIGN KEY (unidad_medida_id) REFERENCES public.unidades_medida(id);


--
-- Name: bom_detalle bom_detalle_variante_componente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bom_detalle
    ADD CONSTRAINT bom_detalle_variante_componente_id_fkey FOREIGN KEY (variante_componente_id) REFERENCES public.variantes(id);


--
-- Name: composicion_variantes composicion_variantes_id_hijo_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

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


--
-- Name: compras fk_compras_movimiento; Type: FK CONSTRAINT; Schema: public; Owner: -
--

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


--
-- Name: movimientos_inventario movimientos_inventario_orden_produccion_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_orden_produccion_id_fkey FOREIGN KEY (orden_produccion_id) REFERENCES public.ordenes_produccion(id);


--
-- Name: movimientos_inventario movimientos_inventario_variante_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_inventario
    ADD CONSTRAINT movimientos_inventario_variante_id_fkey FOREIGN KEY (variante_id) REFERENCES public.variantes(id);


--
-- Name: mrp_sugerencias mrp_sugerencias_calculo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mrp_sugerencias
    ADD CONSTRAINT mrp_sugerencias_calculo_id_fkey FOREIGN KEY (calculo_id) REFERENCES public.mrp_calculos_cabecera(id) ON DELETE CASCADE;


--
-- Name: mrp_sugerencias mrp_sugerencias_variante_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mrp_sugerencias
    ADD CONSTRAINT mrp_sugerencias_variante_id_fkey FOREIGN KEY (variante_id) REFERENCES public.variantes(id);


--
-- Name: ordenes_produccion ordenes_produccion_bom_id_utilizada_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_produccion
    ADD CONSTRAINT ordenes_produccion_bom_id_utilizada_fkey FOREIGN KEY (bom_id_utilizada) REFERENCES public.bom_cabecera(id);


--
-- Name: ordenes_produccion ordenes_produccion_variante_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_produccion
    ADD CONSTRAINT ordenes_produccion_variante_id_fkey FOREIGN KEY (variante_id) REFERENCES public.variantes(id);


--
-- Name: partes partes_id_grupo_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_grupo_fkey FOREIGN KEY (id_grupo) REFERENCES public.grupos_partes(id);


--
-- Name: partes partes_id_tipo_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_tipo_fkey FOREIGN KEY (id_tipo) REFERENCES public.tipos_partes(id);


--
-- Name: partes partes_id_um_ancho_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_ancho_fkey FOREIGN KEY (id_um_ancho) REFERENCES public.unidades_medida(id);


--
-- Name: partes partes_id_um_compra_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_compra_fkey FOREIGN KEY (id_um_compra) REFERENCES public.unidades_medida(id);


--
-- Name: partes partes_id_um_espesor_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_espesor_fkey FOREIGN KEY (id_um_espesor) REFERENCES public.unidades_medida(id);


--
-- Name: partes partes_id_um_largo_alto_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_largo_alto_fkey FOREIGN KEY (id_um_largo_alto) REFERENCES public.unidades_medida(id);


--
-- Name: partes partes_id_um_superficie_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_superficie_fkey FOREIGN KEY (id_um_superficie) REFERENCES public.unidades_medida(id);


--
-- Name: partes partes_id_um_uso_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_uso_fkey FOREIGN KEY (id_um_uso) REFERENCES public.unidades_medida(id);


--
-- Name: partes partes_id_um_volumen_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partes
    ADD CONSTRAINT partes_id_um_volumen_fkey FOREIGN KEY (id_um_volumen) REFERENCES public.unidades_medida(id);


--
-- Name: planificacion_recursos planificacion_recursos_centro_trabajo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planificacion_recursos
    ADD CONSTRAINT planificacion_recursos_centro_trabajo_id_fkey FOREIGN KEY (centro_trabajo_id) REFERENCES public.centros_trabajo(id);


--
-- Name: planificacion_recursos planificacion_recursos_operacion_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planificacion_recursos
    ADD CONSTRAINT planificacion_recursos_operacion_id_fkey FOREIGN KEY (operacion_id) REFERENCES public.rutas_produccion(id);


--
-- Name: planificacion_recursos planificacion_recursos_orden_produccion_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

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


--
-- Name: variantes variantes_id_parte_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.variantes
    ADD CONSTRAINT variantes_id_parte_fkey FOREIGN KEY (id_parte) REFERENCES public.partes(id) ON DELETE CASCADE;


--
-- Name: variantes variantes_id_um_peso_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.variantes
    ADD CONSTRAINT variantes_id_um_peso_fkey FOREIGN KEY (id_um_peso) REFERENCES public.unidades_medida(id);


--
-- PostgreSQL database dump complete
--

\unrestrict GTElbXYaPfT4MW3qdF9kQO6nDSfDfs2rO7ahrNQLp6D5VeRhb4Yx3GLHX8dXv42