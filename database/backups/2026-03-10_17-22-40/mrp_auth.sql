--
-- PostgreSQL database dump
--

\restrict QbXrsanzn9gRarhOztzUeox7nSa5jB4duraoN6L6JFiFymS9ll2ACgmH8uGZT7m

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
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


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
-- Name: audit_logs; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.audit_logs (
    id bigint NOT NULL,
    user_id bigint,
    company_id bigint,
    action character varying(255) NOT NULL,
    description text,
    ip_address character varying(45),
    user_agent text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.audit_logs OWNER TO mrp;

--
-- Name: audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.audit_logs_id_seq OWNER TO mrp;

--
-- Name: audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.audit_logs_id_seq OWNED BY public.audit_logs.id;


--
-- Name: companies; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.companies (
    id bigint NOT NULL,
    name character varying(120) NOT NULL,
    slug character varying(160) NOT NULL,
    tax_id character varying(50),
    contact_email character varying(120),
    status character varying(20) DEFAULT 'active'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT companies_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'suspended'::character varying])::text[])))
);


ALTER TABLE public.companies OWNER TO mrp;

--
-- Name: companies_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.companies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.companies_id_seq OWNER TO mrp;

--
-- Name: companies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.companies_id_seq OWNED BY public.companies.id;


--
-- Name: company_databases; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.company_databases (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    host character varying(120) NOT NULL,
    port character varying(10) DEFAULT '5432'::character varying NOT NULL,
    database_name character varying(190) NOT NULL,
    username character varying(120) NOT NULL,
    password_encrypted character varying(255) NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.company_databases OWNER TO mrp;

--
-- Name: company_databases_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.company_databases_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.company_databases_id_seq OWNER TO mrp;

--
-- Name: company_databases_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.company_databases_id_seq OWNED BY public.company_databases.id;


--
-- Name: menu_acl; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.menu_acl (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    menu_item_id bigint NOT NULL,
    subject_type character varying(10) NOT NULL,
    subject_id bigint NOT NULL,
    scope character varying(10) NOT NULL,
    permission_level character varying(10) NOT NULL,
    effect character varying(10) DEFAULT 'allow'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT menu_acl_effect_check CHECK (((effect)::text = ANY ((ARRAY['allow'::character varying, 'deny'::character varying])::text[]))),
    CONSTRAINT menu_acl_permission_level_check CHECK (((permission_level)::text = ANY ((ARRAY['read'::character varying, 'write'::character varying])::text[]))),
    CONSTRAINT menu_acl_scope_check CHECK (((scope)::text = ANY ((ARRAY['item'::character varying, 'branch'::character varying])::text[]))),
    CONSTRAINT menu_acl_subject_type_check CHECK (((subject_type)::text = ANY ((ARRAY['role'::character varying, 'user'::character varying])::text[])))
);


ALTER TABLE public.menu_acl OWNER TO mrp;

--
-- Name: menu_acl_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.menu_acl_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.menu_acl_id_seq OWNER TO mrp;

--
-- Name: menu_acl_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.menu_acl_id_seq OWNED BY public.menu_acl.id;


--
-- Name: menu_items; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.menu_items (
    id bigint NOT NULL,
    code character varying(120) NOT NULL,
    label character varying(150) NOT NULL,
    route character varying(255),
    icon character varying(120),
    section_key character varying(80) NOT NULL,
    section_label character varying(120) NOT NULL,
    parent_id bigint,
    sort_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.menu_items OWNER TO mrp;

--
-- Name: menu_items_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.menu_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.menu_items_id_seq OWNER TO mrp;

--
-- Name: menu_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.menu_items_id_seq OWNED BY public.menu_items.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(120) NOT NULL,
    module character varying(120) NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.permissions OWNER TO mrp;

--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.permissions_id_seq OWNER TO mrp;

--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(120) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(120) NOT NULL,
    token character(64) NOT NULL,
    abilities text,
    last_used_at timestamp with time zone,
    expires_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.personal_access_tokens OWNER TO mrp;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.personal_access_tokens_id_seq OWNER TO mrp;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.role_has_permissions (
    role_id bigint NOT NULL,
    permission_id bigint NOT NULL
);


ALTER TABLE public.role_has_permissions OWNER TO mrp;

--
-- Name: roles; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(120) NOT NULL,
    guard_name character varying(60) DEFAULT 'web'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.roles OWNER TO mrp;

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.roles_id_seq OWNER TO mrp;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


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
-- Name: user_company; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.user_company (
    user_id bigint NOT NULL,
    company_id bigint NOT NULL,
    role_id bigint
);


ALTER TABLE public.user_company OWNER TO mrp;

--
-- Name: user_has_roles; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.user_has_roles (
    user_id bigint NOT NULL,
    role_id bigint NOT NULL
);


ALTER TABLE public.user_has_roles OWNER TO mrp;

--
-- Name: users; Type: TABLE; Schema: public; Owner: mrp
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(120) NOT NULL,
    email character varying(150) NOT NULL,
    password character varying(255) NOT NULL,
    two_factor_enabled boolean DEFAULT false NOT NULL,
    two_factor_secret character varying(255),
    two_factor_expire_at timestamp with time zone,
    default_company_id bigint,
    last_login_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.users OWNER TO mrp;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: mrp
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO mrp;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mrp
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: audit_logs id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.audit_logs ALTER COLUMN id SET DEFAULT nextval('public.audit_logs_id_seq'::regclass);


--
-- Name: companies id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.companies ALTER COLUMN id SET DEFAULT nextval('public.companies_id_seq'::regclass);


--
-- Name: company_databases id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.company_databases ALTER COLUMN id SET DEFAULT nextval('public.company_databases_id_seq'::regclass);


--
-- Name: menu_acl id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.menu_acl ALTER COLUMN id SET DEFAULT nextval('public.menu_acl_id_seq'::regclass);


--
-- Name: menu_items id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.menu_items ALTER COLUMN id SET DEFAULT nextval('public.menu_items_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: schema_migrations id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.schema_migrations ALTER COLUMN id SET DEFAULT nextval('public.schema_migrations_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: audit_logs; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.audit_logs (id, user_id, company_id, action, description, ip_address, user_agent, created_at) FROM stdin;
1	2	2	tenant.registered	Alta via wizard. Base creada: mrp_tunna	\N	\N	2026-03-06 10:10:52.171944+00
\.


--
-- Data for Name: companies; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.companies (id, name, slug, tax_id, contact_email, status, created_at, updated_at) FROM stdin;
1	Demo Manufacturing	demo-manufacturing	J-12345678-9	contacto@demo-mrp.test	active	2026-02-11 18:04:52.89919+00	2026-02-11 18:04:52.89919+00
2	tunna	mrp_tunna	20323837857	martin@unik.ar	active	2026-03-06 10:10:52.171944+00	2026-03-06 10:10:52.171944+00
\.


--
-- Data for Name: company_databases; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.company_databases (id, company_id, host, port, database_name, username, password_encrypted, created_at, updated_at) FROM stdin;
2	2	lemp-postgresql	5432	mrp_tunna	mrp	ojp9Q6aYT3KHDE8sMS2u	2026-03-06 10:10:52.171944+00	2026-03-06 10:10:52.171944+00
1	1	lemp-postgresql	5432	mrp_demo	mrp	ojp9Q6aYT3KHDE8sMS2u	2026-02-11 18:04:52.89919+00	2026-02-11 18:04:52.89919+00
\.


--
-- Data for Name: menu_acl; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.menu_acl (id, company_id, menu_item_id, subject_type, subject_id, scope, permission_level, effect, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: menu_items; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.menu_items (id, code, label, route, icon, section_key, section_label, parent_id, sort_order, is_active, created_at, updated_at) FROM stdin;
1	panel.inicio	Panel inicial	/dashboard	fa-solid fa-gauge	panel	Panel	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
2	planeamiento.sugerencias	Sugerencias MRP	/planeamiento/sugerencias	fa-solid fa-list-check	planeamiento_mrp	Planeamiento MRP	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
3	planeamiento.ordenes	Ordenes planificadas	/planeamiento/ordenes	fa-solid fa-calendar-check	planeamiento_mrp	Planeamiento MRP	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
4	produccion.dashboard	Dashboard de Operaciones	/produccion	fa-solid fa-gauge-high	produccion	Produccion	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
5	produccion.centros_trabajo	Centros de Trabajo	/produccion/centros-trabajo	fa-solid fa-industry	produccion	Produccion	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
6	produccion.rutas	Rutas de Produccion	/produccion/rutas	fa-solid fa-route	produccion	Produccion	\N	30	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
7	produccion.ordenes	Ordenes de Produccion	/produccion/ordenes	fa-solid fa-clipboard-list	produccion	Produccion	\N	40	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
8	produccion.planificacion	Planificacion de Recursos	/produccion/planificacion	fa-solid fa-calendar-alt	produccion	Produccion	\N	50	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
9	produccion.gantt	Vista Gantt	/produccion/planificacion/gantt	fa-solid fa-chart-gantt	produccion	Produccion	\N	60	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
10	productos.partes	Listado de Partes	/productos/partes	fa-solid fa-puzzle-piece	productos_bom	Productos y BOM	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
11	productos.manager	Gestor de partes	/productos/partes/manager	fa-solid fa-wrench	productos_bom	Productos y BOM	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
12	productos.bom	BOM activas	/productos/bom	fa-solid fa-diagram-project	productos_bom	Productos y BOM	\N	30	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
13	productos.maestro	Composicion de variantes	/productos/maestro	fa-solid fa-layer-group	productos_bom	Productos y BOM	\N	40	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
14	inventario.critico	Stock critico	/inventario/critico	fa-solid fa-triangle-exclamation	inventario_stock	Inventario y stock	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
15	transacciones.movimientos	Movimientos de Partes	/transacciones/movimientos-partes	fa-solid fa-arrow-right-arrow-left	transacciones	Transacciones	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
16	transacciones.compras	Gestion de Compras	/compras	fa-solid fa-shopping-cart	transacciones	Transacciones	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
17	reportes.destino_partes	Destino de Partes	/reportes/destino-partes	fa-solid fa-sitemap	reportes	Reportes	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
18	reportes.listado_ingenieria	Listado de Ingenieria	/reportes/listado-ingenieria	fa-solid fa-list-check	reportes	Reportes	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
19	reportes.planificacion_produccion	Planificacion de Produccion	/reportes/planificacion-produccion	fa-solid fa-calendar-days	reportes	Reportes	\N	30	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
20	reportes.resumen_grupos	Resumen por grupos	/reportes/resumen-grupos	fa-solid fa-layer-group	reportes	Reportes	\N	40	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
21	catalogos.configuracion	Configuracion	/configuracion/general	fa-solid fa-sliders	parametros_catalogos	Parametros y catalogos	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
22	catalogos.unidades	Unidades de medida	/configuracion/unidades	fa-solid fa-ruler-combined	parametros_catalogos	Parametros y catalogos	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
23	catalogos.tipos_partes	Tipos de partes	/configuracion/tipos-partes	fa-solid fa-tags	parametros_catalogos	Parametros y catalogos	\N	30	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
24	catalogos.tipos_depositos	Tipos de deposito	/configuracion/tipos-depositos	fa-solid fa-warehouse	parametros_catalogos	Parametros y catalogos	\N	40	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
25	catalogos.validaciones_depositos	Validaciones de movimientos	/configuracion/depositos-validaciones	fa-solid fa-arrow-right-arrow-left	parametros_catalogos	Parametros y catalogos	\N	50	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
26	catalogos.grupos_partes	Grupos de partes	/configuracion/grupos-partes	fa-solid fa-layer-group	parametros_catalogos	Parametros y catalogos	\N	60	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
27	admin.empresa	Empresa	/empresa-usuarios/empresa	fa-solid fa-building	empresa_usuarios	Empresa y Usuarios	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:48:30.906269+00
28	admin.usuarios	Usuarios	/empresa-usuarios/usuarios	fa-solid fa-users	empresa_usuarios	Empresa y Usuarios	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:48:30.906269+00
29	admin.roles	Roles	/empresa-usuarios/roles	fa-solid fa-user-shield	empresa_usuarios	Empresa y Usuarios	\N	30	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:48:30.906269+00
30	admin.permisos	Permisos	/empresa-usuarios/permisos	fa-solid fa-key	empresa_usuarios	Empresa y Usuarios	\N	40	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:48:30.906269+00
31	catalogos.entidades	Clientes y proveedores	/configuracion/entidades	fa-solid fa-address-book	parametros_catalogos	Parametros y catalogos	\N	70	t	2026-03-09 23:02:15.634322+00	2026-03-09 23:02:15.634322+00
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.permissions (id, name, module, created_at, updated_at) FROM stdin;
1	systems.manage	systems	2026-02-11 18:04:52.89919+00	2026-02-11 18:04:52.89919+00
2	production.orders	production	2026-02-11 18:04:52.89919+00	2026-02-11 18:04:52.89919+00
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: role_has_permissions; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.role_has_permissions (role_id, permission_id) FROM stdin;
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.roles (id, name, guard_name, created_at, updated_at) FROM stdin;
1	administrator	adm	2026-02-11 18:04:52.89919+00	2026-03-06 17:08:42.610711+00
3	operator	op	2026-02-11 18:04:52.89919+00	2026-03-06 17:08:50.221786+00
2	supervisor	sup	2026-02-11 18:04:52.89919+00	2026-03-06 17:08:59.966982+00
\.


--
-- Data for Name: schema_migrations; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.schema_migrations (id, filename, executed_at) FROM stdin;
1	2026-03-06_create_menu_acl_sidebar.sql	2026-03-06 13:15:36.780108
2	2026-03-06_fix_empresa_usuarios_menu_routes.sql	2026-03-06 13:48:30.906269
3	2026-03-09_add_entidades_menu_item.sql	2026-03-09 23:02:15.634322
\.


--
-- Data for Name: user_company; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.user_company (user_id, company_id, role_id) FROM stdin;
1	1	1
2	2	1
\.


--
-- Data for Name: user_has_roles; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.user_has_roles (user_id, role_id) FROM stdin;
1	1
2	1
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.users (id, name, email, password, two_factor_enabled, two_factor_secret, two_factor_expire_at, default_company_id, last_login_at, created_at, updated_at) FROM stdin;
1	MRP Admin	admin@demo-mrp.test	$2y$12$HnVQVECDWKSqwWD8Osz76uvjboWzYmLz90BqMD3VK8dxMp8azy6DW	f	\N	\N	1	\N	2026-02-11 18:04:52.89919+00	2026-02-11 18:31:17.289+00
2	Sabrina Smurro	martin@unik.ar	$2y$12$naHG4nIdFlrPZ6R4Z/sd9u5Thc37FqunzjjcobqnqPPC/sNpcFDci	f	\N	\N	2	\N	2026-03-06 10:10:52.171944+00	2026-03-06 10:10:52.171944+00
\.


--
-- Name: audit_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.audit_logs_id_seq', 2, true);


--
-- Name: companies_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.companies_id_seq', 3, true);


--
-- Name: company_databases_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.company_databases_id_seq', 3, true);


--
-- Name: menu_acl_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.menu_acl_id_seq', 3, true);


--
-- Name: menu_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.menu_items_id_seq', 31, true);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.permissions_id_seq', 2, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 1, false);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.roles_id_seq', 3, true);


--
-- Name: schema_migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.schema_migrations_id_seq', 3, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.users_id_seq', 3, true);


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);


--
-- Name: companies companies_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_pkey PRIMARY KEY (id);


--
-- Name: companies companies_slug_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_slug_key UNIQUE (slug);


--
-- Name: company_databases company_databases_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.company_databases
    ADD CONSTRAINT company_databases_pkey PRIMARY KEY (id);


--
-- Name: menu_acl menu_acl_company_id_menu_item_id_subject_type_subject_id_sc_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.menu_acl
    ADD CONSTRAINT menu_acl_company_id_menu_item_id_subject_type_subject_id_sc_key UNIQUE (company_id, menu_item_id, subject_type, subject_id, scope);


--
-- Name: menu_acl menu_acl_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.menu_acl
    ADD CONSTRAINT menu_acl_pkey PRIMARY KEY (id);


--
-- Name: menu_items menu_items_code_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.menu_items
    ADD CONSTRAINT menu_items_code_key UNIQUE (code);


--
-- Name: menu_items menu_items_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.menu_items
    ADD CONSTRAINT menu_items_pkey PRIMARY KEY (id);


--
-- Name: permissions permissions_name_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_key UNIQUE (name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_key UNIQUE (token);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (role_id, permission_id);


--
-- Name: roles roles_name_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_key UNIQUE (name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


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
-- Name: user_company user_company_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.user_company
    ADD CONSTRAINT user_company_pkey PRIMARY KEY (user_id, company_id);


--
-- Name: user_has_roles user_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.user_has_roles
    ADD CONSTRAINT user_has_roles_pkey PRIMARY KEY (user_id, role_id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: idx_menu_acl_company_subject; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_menu_acl_company_subject ON public.menu_acl USING btree (company_id, subject_type, subject_id);


--
-- Name: idx_menu_items_parent; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_menu_items_parent ON public.menu_items USING btree (parent_id);


--
-- Name: idx_menu_items_section_sort; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_menu_items_section_sort ON public.menu_items USING btree (section_key, sort_order);


--
-- Name: idx_pat_tokenable; Type: INDEX; Schema: public; Owner: mrp
--

CREATE INDEX idx_pat_tokenable ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: companies set_timestamp_companies; Type: TRIGGER; Schema: public; Owner: mrp
--

CREATE TRIGGER set_timestamp_companies BEFORE UPDATE ON public.companies FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: roles set_timestamp_roles; Type: TRIGGER; Schema: public; Owner: mrp
--

CREATE TRIGGER set_timestamp_roles BEFORE UPDATE ON public.roles FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: users set_timestamp_users; Type: TRIGGER; Schema: public; Owner: mrp
--

CREATE TRIGGER set_timestamp_users BEFORE UPDATE ON public.users FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: audit_logs audit_logs_company_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_company_id_fkey FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: audit_logs audit_logs_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: company_databases company_databases_company_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.company_databases
    ADD CONSTRAINT company_databases_company_id_fkey FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: menu_acl menu_acl_company_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.menu_acl
    ADD CONSTRAINT menu_acl_company_id_fkey FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: menu_acl menu_acl_menu_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.menu_acl
    ADD CONSTRAINT menu_acl_menu_item_id_fkey FOREIGN KEY (menu_item_id) REFERENCES public.menu_items(id);


--
-- Name: menu_items menu_items_parent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.menu_items
    ADD CONSTRAINT menu_items_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES public.menu_items(id);


--
-- Name: role_has_permissions role_has_permissions_permission_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_fkey FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: user_company user_company_company_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.user_company
    ADD CONSTRAINT user_company_company_id_fkey FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: user_company user_company_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.user_company
    ADD CONSTRAINT user_company_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE SET NULL;


--
-- Name: user_company user_company_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.user_company
    ADD CONSTRAINT user_company_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_has_roles user_has_roles_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.user_has_roles
    ADD CONSTRAINT user_has_roles_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: user_has_roles user_has_roles_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.user_has_roles
    ADD CONSTRAINT user_has_roles_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_default_company_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_default_company_id_fkey FOREIGN KEY (default_company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: pg_database_owner
--

GRANT ALL ON SCHEMA public TO mrp;


--
-- PostgreSQL database dump complete
--

\unrestrict QbXrsanzn9gRarhOztzUeox7nSa5jB4duraoN6L6JFiFymS9ll2ACgmH8uGZT7m

