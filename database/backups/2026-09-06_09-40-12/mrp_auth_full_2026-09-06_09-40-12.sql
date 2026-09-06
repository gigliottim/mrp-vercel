--
-- PostgreSQL database dump
--

\restrict 60cx4cxPE8SNaddEBdhdmiupXaypc75OKPcPv6q6s2ujjCbm9sczqLjyPVDv4GE

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
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


--
-- Name: fn_super_admin_auto_link(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.fn_super_admin_auto_link() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_super_email  TEXT    := 'martin@unik.ar';
    v_role_name    TEXT    := 'super_admin';
    v_user_id      INTEGER;
    v_role_id      INTEGER;
BEGIN
    SELECT id INTO v_user_id FROM users WHERE lower(email) = v_super_email LIMIT 1;
    SELECT id INTO v_role_id FROM roles  WHERE name = v_role_name            LIMIT 1;
    IF v_user_id IS NOT NULL AND v_role_id IS NOT NULL THEN
        INSERT INTO user_company (user_id, company_id, role_id)
        VALUES (v_user_id, NEW.id, v_role_id)
        ON CONFLICT DO NOTHING;
    END IF;
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
-- Name: audit_logs; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_logs_id_seq OWNED BY public.audit_logs.id;


--
-- Name: companies; Type: TABLE; Schema: public; Owner: -
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
    CONSTRAINT companies_status_check CHECK (((status)::text = ANY (ARRAY[('active'::character varying)::text, ('suspended'::character varying)::text])))
);


--
-- Name: companies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.companies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: companies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.companies_id_seq OWNED BY public.companies.id;


--
-- Name: company_databases; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: company_databases_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_databases_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_databases_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_databases_id_seq OWNED BY public.company_databases.id;


--
-- Name: menu_acl; Type: TABLE; Schema: public; Owner: -
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
    CONSTRAINT menu_acl_effect_check CHECK (((effect)::text = ANY (ARRAY[('allow'::character varying)::text, ('deny'::character varying)::text]))),
    CONSTRAINT menu_acl_permission_level_check CHECK (((permission_level)::text = ANY (ARRAY[('read'::character varying)::text, ('write'::character varying)::text]))),
    CONSTRAINT menu_acl_scope_check CHECK (((scope)::text = ANY (ARRAY[('item'::character varying)::text, ('branch'::character varying)::text]))),
    CONSTRAINT menu_acl_subject_type_check CHECK (((subject_type)::text = ANY (ARRAY[('role'::character varying)::text, ('user'::character varying)::text])))
);


--
-- Name: menu_acl_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.menu_acl_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: menu_acl_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.menu_acl_id_seq OWNED BY public.menu_acl.id;


--
-- Name: menu_items; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: menu_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.menu_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: menu_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.menu_items_id_seq OWNED BY public.menu_items.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(120) NOT NULL,
    module character varying(120) NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    role_id bigint NOT NULL,
    permission_id bigint NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(120) NOT NULL,
    guard_name character varying(60) DEFAULT 'web'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


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
-- Name: user_company; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_company (
    user_id bigint NOT NULL,
    company_id bigint NOT NULL,
    role_id bigint
);


--
-- Name: user_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_has_roles (
    user_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: audit_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs ALTER COLUMN id SET DEFAULT nextval('public.audit_logs_id_seq'::regclass);


--
-- Name: companies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies ALTER COLUMN id SET DEFAULT nextval('public.companies_id_seq'::regclass);


--
-- Name: company_databases id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_databases ALTER COLUMN id SET DEFAULT nextval('public.company_databases_id_seq'::regclass);


--
-- Name: menu_acl id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.menu_acl ALTER COLUMN id SET DEFAULT nextval('public.menu_acl_id_seq'::regclass);


--
-- Name: menu_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.menu_items ALTER COLUMN id SET DEFAULT nextval('public.menu_items_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: schema_migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_migrations ALTER COLUMN id SET DEFAULT nextval('public.schema_migrations_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: audit_logs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.audit_logs (id, user_id, company_id, action, description, ip_address, user_agent, created_at) FROM stdin;
1	2	2	tenant.registered	Alta via wizard. Base creada: mrp_tunna	\N	\N	2026-03-06 10:10:52.171944+00
\.


--
-- Data for Name: companies; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.companies (id, name, slug, tax_id, contact_email, status, created_at, updated_at) FROM stdin;
2	tunna	mrp_tunna	20323837857	sabrinasmurro22@gmail.com	active	2026-03-06 10:10:52.171944+00	2026-03-23 11:42:49.803467+00
\.


--
-- Data for Name: company_databases; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.company_databases (id, company_id, host, port, database_name, username, password_encrypted, created_at, updated_at) FROM stdin;
2	2	postgresql	5432	mrp_tunna	mrp_unik_2026	jqAe@Sy96^&z3vu2wK@@	2026-03-06 10:10:52.171944+00	2026-03-11 17:06:33.492726+00
\.


--
-- Data for Name: menu_acl; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.menu_acl (id, company_id, menu_item_id, subject_type, subject_id, scope, permission_level, effect, created_at, updated_at) FROM stdin;
7	2	15	role	2	item	read	allow	2026-03-23 23:15:17.663839+00	2026-03-23 23:15:17.663839+00
26	2	32	role	3	item	read	allow	2026-03-24 01:10:27.423183+00	2026-03-24 15:28:43.333106+00
427	2	18	role	3	item	read	allow	2026-03-24 15:22:46.988323+00	2026-03-24 15:25:32.96796+00
32	2	1	role	3	item	read	allow	2026-03-24 01:10:50.881098+00	2026-03-24 15:24:54.903219+00
15	2	10	role	4	item	read	allow	2026-03-24 01:10:27.243499+00	2026-03-24 15:28:43.156085+00
27	2	32	role	4	item	read	allow	2026-03-24 01:10:27.449452+00	2026-03-24 15:28:43.3436+00
28	2	33	role	1	item	read	allow	2026-03-24 01:10:27.465707+00	2026-03-24 15:28:43.357906+00
58	2	3	role	4	item	read	allow	2026-03-24 01:11:30.903487+00	2026-03-24 15:22:29.7227+00
59	2	8	role	1	item	read	allow	2026-03-24 01:11:30.919955+00	2026-03-24 15:22:29.736807+00
60	2	8	role	3	item	read	allow	2026-03-24 01:11:30.938619+00	2026-03-24 15:22:29.752812+00
61	2	8	role	4	item	read	allow	2026-03-24 01:11:30.952497+00	2026-03-24 15:22:29.771226+00
62	2	16	role	1	item	read	allow	2026-03-24 01:11:30.97569+00	2026-03-24 15:22:29.783674+00
36	2	4	role	4	item	read	allow	2026-03-24 01:10:50.947413+00	2026-03-24 15:24:54.991545+00
37	2	7	role	1	item	read	allow	2026-03-24 01:10:50.962752+00	2026-03-24 15:24:55.028472+00
38	2	7	role	3	item	read	allow	2026-03-24 01:10:50.979584+00	2026-03-24 15:24:55.042186+00
39	2	7	role	4	item	read	allow	2026-03-24 01:10:50.999212+00	2026-03-24 15:24:55.062972+00
10	2	9	role	1	item	read	allow	2026-03-23 23:50:43.714132+00	2026-03-24 15:24:55.096392+00
11	2	9	role	3	item	read	allow	2026-03-23 23:50:43.750041+00	2026-03-24 15:24:55.113195+00
12	2	9	role	4	item	read	allow	2026-03-23 23:50:43.768675+00	2026-03-24 15:24:55.129038+00
43	2	14	role	1	item	read	allow	2026-03-24 01:10:51.066351+00	2026-03-24 15:24:55.158583+00
44	2	14	role	3	item	read	allow	2026-03-24 01:10:51.077388+00	2026-03-24 15:24:55.172141+00
45	2	14	role	4	item	read	allow	2026-03-24 01:10:51.099686+00	2026-03-24 15:24:55.18409+00
8	2	15	role	1	item	read	allow	2026-03-23 23:15:17.727083+00	2026-03-24 15:24:55.221806+00
16	2	11	role	1	item	read	allow	2026-03-24 01:10:27.26481+00	2026-03-24 15:28:43.175339+00
17	2	11	role	3	item	read	allow	2026-03-24 01:10:27.284914+00	2026-03-24 15:28:43.205506+00
18	2	11	role	4	item	read	allow	2026-03-24 01:10:27.308595+00	2026-03-24 15:28:43.217582+00
19	2	12	role	1	item	read	allow	2026-03-24 01:10:27.319898+00	2026-03-24 15:28:43.22989+00
210	2	6	role	1	item	read	allow	2026-03-24 03:01:34.654489+00	2026-03-24 15:22:35.19128+00
211	2	6	role	3	item	read	allow	2026-03-24 03:01:34.671651+00	2026-03-24 15:22:35.23926+00
212	2	6	role	4	item	read	allow	2026-03-24 03:01:34.68422+00	2026-03-24 15:22:35.265511+00
20	2	12	role	3	item	read	allow	2026-03-24 01:10:27.332855+00	2026-03-24 15:28:43.240929+00
29	2	33	role	3	item	read	allow	2026-03-24 01:10:27.47992+00	2026-03-24 15:28:43.369778+00
30	2	33	role	4	item	read	allow	2026-03-24 01:10:27.496357+00	2026-03-24 15:28:43.385839+00
33	2	1	role	4	item	read	allow	2026-03-24 01:10:50.895389+00	2026-03-24 15:24:54.919355+00
14	2	10	role	3	item	read	allow	2026-03-24 01:10:27.230643+00	2026-03-24 15:28:43.141076+00
63	2	16	role	3	item	read	allow	2026-03-24 01:11:30.995945+00	2026-03-24 15:22:29.798143+00
64	2	16	role	4	item	read	allow	2026-03-24 01:11:31.013224+00	2026-03-24 15:22:29.810987+00
435	2	21	role	1	item	read	allow	2026-03-24 15:23:00.163323+00	2026-03-24 15:23:00.163323+00
53	2	2	role	1	item	read	allow	2026-03-24 01:11:30.8187+00	2026-03-24 15:22:29.655741+00
47	2	15	role	3	item	read	allow	2026-03-24 01:10:51.131374+00	2026-03-24 15:24:55.23826+00
436	2	21	role	3	item	read	allow	2026-03-24 15:23:00.175309+00	2026-03-24 15:23:00.175309+00
65	2	31	role	1	item	read	allow	2026-03-24 01:11:31.032835+00	2026-03-24 15:22:29.82787+00
66	2	31	role	3	item	read	allow	2026-03-24 01:11:31.046538+00	2026-03-24 15:22:29.846256+00
67	2	31	role	4	item	read	allow	2026-03-24 01:11:31.063899+00	2026-03-24 15:22:29.863645+00
9	2	15	role	4	item	read	allow	2026-03-23 23:15:17.796817+00	2026-03-24 15:24:55.252252+00
437	2	21	role	4	item	read	allow	2026-03-24 15:23:00.197014+00	2026-03-24 15:23:00.197014+00
206	2	5	role	1	item	read	allow	2026-03-24 03:01:34.525966+00	2026-03-24 15:22:35.066787+00
207	2	5	role	3	item	read	allow	2026-03-24 03:01:34.542751+00	2026-03-24 15:22:35.097469+00
208	2	5	role	4	item	read	allow	2026-03-24 03:01:34.576321+00	2026-03-24 15:22:35.1363+00
21	2	12	role	4	item	read	allow	2026-03-24 01:10:27.344223+00	2026-03-24 15:28:43.254946+00
22	2	13	role	1	item	read	allow	2026-03-24 01:10:27.360949+00	2026-03-24 15:28:43.27442+00
23	2	13	role	3	item	read	allow	2026-03-24 01:10:27.384665+00	2026-03-24 15:28:43.284735+00
24	2	13	role	4	item	read	allow	2026-03-24 01:10:27.401123+00	2026-03-24 15:28:43.303942+00
54	2	2	role	3	item	read	allow	2026-03-24 01:11:30.838613+00	2026-03-24 15:22:29.671713+00
25	2	32	role	1	item	read	allow	2026-03-24 01:10:27.412637+00	2026-03-24 15:28:43.318389+00
55	2	2	role	4	item	read	allow	2026-03-24 01:11:30.856038+00	2026-03-24 15:22:29.682787+00
56	2	3	role	1	item	read	allow	2026-03-24 01:11:30.872512+00	2026-03-24 15:22:29.694358+00
57	2	3	role	3	item	read	allow	2026-03-24 01:11:30.88552+00	2026-03-24 15:22:29.70867+00
34	2	4	role	1	item	read	allow	2026-03-24 01:10:50.91735+00	2026-03-24 15:24:54.962509+00
35	2	4	role	3	item	read	allow	2026-03-24 01:10:50.930801+00	2026-03-24 15:24:54.977194+00
438	2	22	role	1	item	read	allow	2026-03-24 15:23:00.211925+00	2026-03-24 15:23:00.211925+00
439	2	22	role	3	item	read	allow	2026-03-24 15:23:00.228267+00	2026-03-24 15:23:00.228267+00
440	2	22	role	4	item	read	allow	2026-03-24 15:23:00.244702+00	2026-03-24 15:23:00.244702+00
441	2	23	role	1	item	read	allow	2026-03-24 15:23:00.261696+00	2026-03-24 15:23:00.261696+00
442	2	23	role	3	item	read	allow	2026-03-24 15:23:00.277687+00	2026-03-24 15:23:00.277687+00
428	2	18	role	4	item	read	allow	2026-03-24 15:22:47.012512+00	2026-03-24 15:25:32.980344+00
429	2	19	role	1	item	read	allow	2026-03-24 15:22:47.038987+00	2026-03-24 15:25:33.009028+00
423	2	17	role	1	item	read	allow	2026-03-24 15:22:46.928585+00	2026-03-24 15:25:32.88044+00
424	2	17	role	3	item	read	allow	2026-03-24 15:22:46.945591+00	2026-03-24 15:25:32.902377+00
425	2	17	role	4	item	read	allow	2026-03-24 15:22:46.956757+00	2026-03-24 15:25:32.923528+00
426	2	18	role	1	item	read	allow	2026-03-24 15:22:46.976795+00	2026-03-24 15:25:32.945585+00
430	2	19	role	3	item	read	allow	2026-03-24 15:22:47.055662+00	2026-03-24 15:25:33.023753+00
431	2	19	role	4	item	read	allow	2026-03-24 15:22:47.07314+00	2026-03-24 15:25:33.036196+00
433	2	20	role	3	item	read	allow	2026-03-24 15:22:47.115236+00	2026-03-24 15:25:33.072789+00
434	2	20	role	4	item	read	allow	2026-03-24 15:22:47.128664+00	2026-03-24 15:25:33.087395+00
13	2	10	role	1	item	read	allow	2026-03-24 01:10:27.154521+00	2026-03-24 15:28:43.129432+00
443	2	23	role	4	item	read	allow	2026-03-24 15:23:00.296822+00	2026-03-24 15:23:00.296822+00
444	2	24	role	1	item	read	allow	2026-03-24 15:23:00.313956+00	2026-03-24 15:23:00.313956+00
445	2	24	role	3	item	read	allow	2026-03-24 15:23:00.333722+00	2026-03-24 15:23:00.333722+00
446	2	24	role	4	item	read	allow	2026-03-24 15:23:00.351092+00	2026-03-24 15:23:00.351092+00
447	2	25	role	1	item	read	allow	2026-03-24 15:23:00.369909+00	2026-03-24 15:23:00.369909+00
448	2	25	role	3	item	read	allow	2026-03-24 15:23:00.393498+00	2026-03-24 15:23:00.393498+00
449	2	25	role	4	item	read	allow	2026-03-24 15:23:00.410875+00	2026-03-24 15:23:00.410875+00
450	2	26	role	1	item	read	allow	2026-03-24 15:23:00.435421+00	2026-03-24 15:23:00.435421+00
451	2	26	role	3	item	read	allow	2026-03-24 15:23:00.454997+00	2026-03-24 15:23:00.454997+00
452	2	26	role	4	item	read	allow	2026-03-24 15:23:00.469115+00	2026-03-24 15:23:00.469115+00
461	2	29	role	4	item	read	allow	2026-03-24 15:23:04.044937+00	2026-03-24 15:26:58.597165+00
31	2	1	role	1	item	read	allow	2026-03-24 01:10:50.862115+00	2026-03-24 15:24:54.891904+00
522	2	1	user	2	item	read	allow	2026-03-24 15:24:37.285394+00	2026-03-24 15:24:54.937212+00
526	2	4	user	2	item	read	allow	2026-03-24 15:24:37.655193+00	2026-03-24 15:24:55.010083+00
530	2	7	user	2	item	read	allow	2026-03-24 15:24:37.972433+00	2026-03-24 15:24:55.074903+00
534	2	9	user	2	item	read	allow	2026-03-24 15:24:38.116572+00	2026-03-24 15:24:55.14344+00
538	2	14	user	2	item	read	allow	2026-03-24 15:24:38.279591+00	2026-03-24 15:24:55.201658+00
542	2	15	user	2	item	read	allow	2026-03-24 15:24:38.447763+00	2026-03-24 15:24:55.266382+00
432	2	20	role	1	item	read	allow	2026-03-24 15:22:47.092253+00	2026-03-24 15:25:33.053918+00
462	2	30	role	1	item	read	allow	2026-03-24 15:23:04.060394+00	2026-03-24 15:26:58.609918+00
463	2	30	role	3	item	read	allow	2026-03-24 15:23:04.070468+00	2026-03-24 15:26:58.623856+00
464	2	30	role	4	item	read	allow	2026-03-24 15:23:04.087309+00	2026-03-24 15:26:58.640642+00
453	2	27	role	1	item	read	allow	2026-03-24 15:23:03.92212+00	2026-03-24 15:26:58.380416+00
454	2	27	role	3	item	read	allow	2026-03-24 15:23:03.940653+00	2026-03-24 15:26:58.411152+00
455	2	27	role	4	item	read	allow	2026-03-24 15:23:03.956589+00	2026-03-24 15:26:58.425381+00
456	2	28	role	1	item	read	allow	2026-03-24 15:23:03.971447+00	2026-03-24 15:26:58.443846+00
457	2	28	role	3	item	read	allow	2026-03-24 15:23:03.984251+00	2026-03-24 15:26:58.505224+00
458	2	28	role	4	item	read	allow	2026-03-24 15:23:04.001012+00	2026-03-24 15:26:58.528949+00
459	2	29	role	1	item	read	allow	2026-03-24 15:23:04.015676+00	2026-03-24 15:26:58.572344+00
460	2	29	role	3	item	read	allow	2026-03-24 15:23:04.031284+00	2026-03-24 15:26:58.585407+00
\.


--
-- Data for Name: menu_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.menu_items (id, code, label, route, icon, section_key, section_label, parent_id, sort_order, is_active, created_at, updated_at) FROM stdin;
1	panel.inicio	Panel inicial	/dashboard	fa-solid fa-gauge	taller	Taller	\N	5	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
4	produccion.dashboard	Dashboard de Operaciones	/produccion	fa-solid fa-gauge-high	taller	Taller	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
7	produccion.ordenes	Ordenes de Produccion	/produccion/ordenes	fa-solid fa-clipboard-list	taller	Taller	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
15	transacciones.movimientos	Movimientos de Partes	/transacciones/movimientos-partes	fa-solid fa-arrow-right-arrow-left	taller	Taller	\N	30	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
14	inventario.critico	Stock critico	/inventario/critico	fa-solid fa-triangle-exclamation	taller	Taller	\N	40	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
9	produccion.gantt	Vista Gantt	/produccion/planificacion/gantt	fa-solid fa-chart-gantt	taller	Taller	\N	50	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
17	reportes.destino_partes	Destino de Partes	/reportes/destino-partes	fa-solid fa-sitemap	reportes	Reportes	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
18	reportes.listado_ingenieria	Listado de Ingenieria	/reportes/listado-ingenieria	fa-solid fa-list-check	reportes	Reportes	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
19	reportes.planificacion_produccion	Planificacion de Produccion	/reportes/planificacion-produccion	fa-solid fa-calendar-days	reportes	Reportes	\N	30	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
20	reportes.resumen_grupos	Resumen por grupos	/reportes/resumen-grupos	fa-solid fa-layer-group	reportes	Reportes	\N	40	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
10	productos.partes	Listado de Partes	/productos/partes	fa-solid fa-puzzle-piece	catalogo_productos	Desarrollo y Maestros	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
11	productos.manager	Gestor de partes	/productos/partes/manager	fa-solid fa-wrench	catalogo_productos	Desarrollo y Maestros	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
12	productos.bom	BOM activas	/productos/bom	fa-solid fa-diagram-project	catalogo_productos	Desarrollo y Maestros	\N	30	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
13	productos.maestro	Composicion de variantes	/productos/maestro	fa-solid fa-layer-group	catalogo_productos	Desarrollo y Maestros	\N	40	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
27	admin.empresa	Empresa	/empresa-usuarios/empresa	fa-solid fa-building	empresa_usuarios	Empresa y Usuarios	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:48:30.906269+00
28	admin.usuarios	Usuarios	/empresa-usuarios/usuarios	fa-solid fa-users	empresa_usuarios	Empresa y Usuarios	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:48:30.906269+00
29	admin.roles	Roles	/empresa-usuarios/roles	fa-solid fa-user-shield	empresa_usuarios	Empresa y Usuarios	\N	30	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:48:30.906269+00
30	admin.permisos	Permisos	/empresa-usuarios/permisos	fa-solid fa-key	empresa_usuarios	Empresa y Usuarios	\N	40	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:48:30.906269+00
31	catalogos.entidades	Clientes y proveedores	/configuracion/entidades	fa-solid fa-address-book	planificacion_compras	Planificación y Compras	\N	50	t	2026-03-09 23:02:15.634322+00	2026-03-09 23:02:15.634322+00
2	planeamiento.sugerencias	Sugerencias MRP	/planeamiento/sugerencias	fa-solid fa-list-check	planificacion_compras	Planificación y Compras	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
3	planeamiento.ordenes	Ordenes planificadas	/planeamiento/ordenes	fa-solid fa-calendar-check	planificacion_compras	Planificación y Compras	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
8	produccion.planificacion	Planificacion de Recursos	/produccion/planificacion	fa-solid fa-calendar-alt	planificacion_compras	Planificación y Compras	\N	30	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
16	transacciones.compras	Gestion de Compras	/compras	fa-solid fa-shopping-cart	planificacion_compras	Planificación y Compras	\N	40	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
21	catalogos.configuracion	Configuracion	/configuracion/general	fa-solid fa-sliders	administracion	Administración	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
22	catalogos.unidades	Unidades de medida	/configuracion/unidades	fa-solid fa-ruler-combined	administracion	Administración	\N	30	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
23	catalogos.tipos_partes	Tipos de partes	/configuracion/tipos-partes	fa-solid fa-tags	administracion	Administración	\N	40	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
24	catalogos.tipos_depositos	Tipos de deposito	/configuracion/tipos-depositos	fa-solid fa-warehouse	administracion	Administración	\N	50	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
25	catalogos.validaciones_depositos	Validaciones de movimientos	/configuracion/depositos-validaciones	fa-solid fa-arrow-right-arrow-left	administracion	Administración	\N	60	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
26	catalogos.grupos_partes	Grupos de partes	/configuracion/grupos-partes	fa-solid fa-layer-group	administracion	Administración	\N	70	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
5	produccion.centros_trabajo	Centros de Trabajo	/produccion/centros-trabajo	fa-solid fa-industry	produccion	Producción	\N	10	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
6	produccion.rutas	Rutas de Produccion	/produccion/rutas	fa-solid fa-route	produccion	Producción	\N	20	t	2026-03-06 13:15:36.780108+00	2026-03-06 13:15:36.780108+00
32	productos.copiar_componentes	Copiar Componentes	/productos/copiar-componentes	fa-solid fa-copy	catalogo_productos	Desarrollo y Maestros	\N	50	t	2026-03-15 19:33:06.109514+00	2026-03-15 19:33:06.109514+00
33	productos.reemplazar_partes	Reemplazar Partes en el Maestro	/productos/reemplazar-partes	fa-solid fa-shuffle	catalogo_productos	Desarrollo y Maestros	\N	60	t	2026-03-15 19:33:06.109514+00	2026-03-15 19:33:06.109514+00
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permissions (id, name, module, created_at, updated_at) FROM stdin;
1	systems.manage	systems	2026-02-11 18:04:52.89919+00	2026-02-11 18:04:52.89919+00
2	production.orders	production	2026-02-11 18:04:52.89919+00	2026-02-11 18:04:52.89919+00
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: role_has_permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.role_has_permissions (role_id, permission_id) FROM stdin;
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (id, name, guard_name, created_at, updated_at) FROM stdin;
1	Super Administrador	web	2026-03-23 22:29:12.898868+00	2026-03-23 23:29:47.914209+00
2	Administrador	web	2026-03-23 22:29:12.898868+00	2026-03-23 23:29:47.914209+00
3	Supervisor	web	2026-03-23 22:29:12.898868+00	2026-03-23 23:29:47.914209+00
4	Usuario	web	2026-03-23 22:29:12.898868+00	2026-03-23 23:29:47.914209+00
\.


--
-- Data for Name: schema_migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.schema_migrations (id, filename, executed_at) FROM stdin;
1	2026-03-06_create_menu_acl_sidebar.sql	2026-03-06 13:15:36.780108
2	2026-03-06_fix_empresa_usuarios_menu_routes.sql	2026-03-06 13:48:30.906269
3	2026-03-09_add_entidades_menu_item.sql	2026-03-09 23:02:15.634322
4	2026_03_15_001_menu_items_herramientas_bom.sql	2026-03-16 21:14:35.996916
5	2026_03_15_002_reorganize_menu_sections_minipyme.sql	2026-03-16 21:14:36.020316
7	2026_03_16_001_menu_reorganize_produccion_maestros.sql	2026-03-16 21:14:36.041164
9	2026_03_16_002_menu_empresa_usuarios_section.sql	2026-03-16 22:03:08.902848
10	2026_03_21_001_default_roles_setup.sql	2026-03-23 23:29:47.842933
11	2026_03_23_001_system_roles_reorganize.sql	2026-03-23 23:29:47.914209
12	2026_04_03_001_create_agent_conversations.sql	2026-04-05 00:16:23.278844
13	2026_04_03_002_create_agent_messages.sql	2026-04-05 00:16:23.325726
14	2026_04_03_003_create_agent_ai_logs.sql	2026-04-05 00:16:23.343914
17	2026_04_03_004_add_agent_ai_menu_item.sql	2026-04-05 00:16:23.367361
\.


--
-- Data for Name: user_company; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.user_company (user_id, company_id, role_id) FROM stdin;
2	2	3
4	2	1
6	2	4
\.


--
-- Data for Name: user_has_roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.user_has_roles (user_id, role_id) FROM stdin;
6	1
2	3
4	1
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, name, email, password, two_factor_enabled, two_factor_secret, two_factor_expire_at, default_company_id, last_login_at, created_at, updated_at) FROM stdin;
2	Sabrina Smurro	sabrinasmurro22@gmail.com	$2y$12$7U19L7EcgZLYmUK9pxZDrOhCUaW.A9XOoLc5cqLKB3oLQkZKKTKUK	f	\N	\N	2	\N	2026-03-06 10:10:52.171944+00	2026-03-12 12:43:18.545044+00
6	pepe	usuario@mimrp.com.ar	$2y$12$AamTbnGGZtTRLU4N1bQOD.paCX.grWb74qfwlfB/7zRZsPzEnm2Rm	f	\N	\N	\N	\N	2026-03-23 22:38:40.705194+00	2026-03-23 22:38:40.705194+00
4	Martin Gigliotti	martin@unik.ar	$2y$12$w23SYIWHYeIi3x8fM43clOfP39ND8k01Oh538kgpfsJy1jZRegFWy	f	\N	\N	\N	\N	2026-03-12 12:50:18.428058+00	2026-03-23 23:33:16.028457+00
\.


--
-- Name: audit_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.audit_logs_id_seq', 2, true);


--
-- Name: companies_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.companies_id_seq', 3, true);


--
-- Name: company_databases_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.company_databases_id_seq', 3, true);


--
-- Name: menu_acl_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.menu_acl_id_seq', 639, true);


--
-- Name: menu_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.menu_items_id_seq', 36, true);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permissions_id_seq', 2, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 1, false);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 10, true);


--
-- Name: schema_migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.schema_migrations_id_seq', 17, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 6, true);


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);


--
-- Name: companies companies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_pkey PRIMARY KEY (id);


--
-- Name: companies companies_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_slug_key UNIQUE (slug);


--
-- Name: company_databases company_databases_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_databases
    ADD CONSTRAINT company_databases_pkey PRIMARY KEY (id);


--
-- Name: menu_acl menu_acl_company_id_menu_item_id_subject_type_subject_id_sc_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.menu_acl
    ADD CONSTRAINT menu_acl_company_id_menu_item_id_subject_type_subject_id_sc_key UNIQUE (company_id, menu_item_id, subject_type, subject_id, scope);


--
-- Name: menu_acl menu_acl_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.menu_acl
    ADD CONSTRAINT menu_acl_pkey PRIMARY KEY (id);


--
-- Name: menu_items menu_items_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.menu_items
    ADD CONSTRAINT menu_items_code_key UNIQUE (code);


--
-- Name: menu_items menu_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.menu_items
    ADD CONSTRAINT menu_items_pkey PRIMARY KEY (id);


--
-- Name: permissions permissions_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_key UNIQUE (name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_key UNIQUE (token);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (role_id, permission_id);


--
-- Name: roles roles_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_key UNIQUE (name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


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
-- Name: user_company user_company_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_company
    ADD CONSTRAINT user_company_pkey PRIMARY KEY (user_id, company_id);


--
-- Name: user_has_roles user_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_has_roles
    ADD CONSTRAINT user_has_roles_pkey PRIMARY KEY (user_id, role_id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: idx_menu_acl_company_subject; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_menu_acl_company_subject ON public.menu_acl USING btree (company_id, subject_type, subject_id);


--
-- Name: idx_menu_items_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_menu_items_parent ON public.menu_items USING btree (parent_id);


--
-- Name: idx_menu_items_section_sort; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_menu_items_section_sort ON public.menu_items USING btree (section_key, sort_order);


--
-- Name: idx_pat_tokenable; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pat_tokenable ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: companies set_timestamp_companies; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER set_timestamp_companies BEFORE UPDATE ON public.companies FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: roles set_timestamp_roles; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER set_timestamp_roles BEFORE UPDATE ON public.roles FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: users set_timestamp_users; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER set_timestamp_users BEFORE UPDATE ON public.users FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: companies trg_super_admin_auto_link; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_super_admin_auto_link AFTER INSERT ON public.companies FOR EACH ROW EXECUTE FUNCTION public.fn_super_admin_auto_link();


--
-- Name: audit_logs audit_logs_company_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_company_id_fkey FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- Name: audit_logs audit_logs_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: company_databases company_databases_company_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_databases
    ADD CONSTRAINT company_databases_company_id_fkey FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: menu_acl menu_acl_company_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.menu_acl
    ADD CONSTRAINT menu_acl_company_id_fkey FOREIGN KEY (company_id) REFERENCES public.companies(id);


--
-- Name: menu_acl menu_acl_menu_item_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.menu_acl
    ADD CONSTRAINT menu_acl_menu_item_id_fkey FOREIGN KEY (menu_item_id) REFERENCES public.menu_items(id);


--
-- Name: menu_items menu_items_parent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.menu_items
    ADD CONSTRAINT menu_items_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES public.menu_items(id);


--
-- Name: role_has_permissions role_has_permissions_permission_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_fkey FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: user_company user_company_company_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_company
    ADD CONSTRAINT user_company_company_id_fkey FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: user_company user_company_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_company
    ADD CONSTRAINT user_company_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE SET NULL;


--
-- Name: user_company user_company_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_company
    ADD CONSTRAINT user_company_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_has_roles user_has_roles_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_has_roles
    ADD CONSTRAINT user_has_roles_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: user_has_roles user_has_roles_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_has_roles
    ADD CONSTRAINT user_has_roles_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_default_company_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_default_company_id_fkey FOREIGN KEY (default_company_id) REFERENCES public.companies(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

\unrestrict 60cx4cxPE8SNaddEBdhdmiupXaypc75OKPcPv6q6s2ujjCbm9sczqLjyPVDv4GE

