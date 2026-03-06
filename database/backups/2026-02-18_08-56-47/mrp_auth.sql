--
-- PostgreSQL database dump
--

\restrict EWt2z5bMqe9TLyk7qWuBoytCdRllwV1aRefWe8HPZTPv0c4bHACyUSFy8cqlbiJ

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
-- Name: users id; Type: DEFAULT; Schema: public; Owner: mrp
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: audit_logs; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.audit_logs (id, user_id, company_id, action, description, ip_address, user_agent, created_at) FROM stdin;
\.


--
-- Data for Name: companies; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.companies (id, name, slug, tax_id, contact_email, status, created_at, updated_at) FROM stdin;
1	Demo Manufacturing	demo-manufacturing	J-12345678-9	contacto@demo-mrp.test	active	2026-02-11 18:04:52.89919+00	2026-02-11 18:04:52.89919+00
\.


--
-- Data for Name: company_databases; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.company_databases (id, company_id, host, port, database_name, username, password_encrypted, created_at, updated_at) FROM stdin;
1	1	lemp-postgresql	5432	mrp	mrp	ojp9Q6aYT3KHDE8sMS2u	2026-02-11 18:04:52.89919+00	2026-02-11 18:04:52.89919+00
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
1	administrator	web	2026-02-11 18:04:52.89919+00	2026-02-11 18:04:52.89919+00
2	supervisor	web	2026-02-11 18:04:52.89919+00	2026-02-11 18:04:52.89919+00
3	operator	web	2026-02-11 18:04:52.89919+00	2026-02-11 18:04:52.89919+00
\.


--
-- Data for Name: user_company; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.user_company (user_id, company_id, role_id) FROM stdin;
1	1	1
\.


--
-- Data for Name: user_has_roles; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.user_has_roles (user_id, role_id) FROM stdin;
1	1
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: mrp
--

COPY public.users (id, name, email, password, two_factor_enabled, two_factor_secret, two_factor_expire_at, default_company_id, last_login_at, created_at, updated_at) FROM stdin;
1	MRP Admin	admin@demo-mrp.test	$2y$12$HnVQVECDWKSqwWD8Osz76uvjboWzYmLz90BqMD3VK8dxMp8azy6DW	f	\N	\N	1	\N	2026-02-11 18:04:52.89919+00	2026-02-11 18:31:17.289+00
\.


--
-- Name: audit_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.audit_logs_id_seq', 1, false);


--
-- Name: companies_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.companies_id_seq', 1, true);


--
-- Name: company_databases_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.company_databases_id_seq', 1, true);


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
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: mrp
--

SELECT pg_catalog.setval('public.users_id_seq', 1, true);


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

\unrestrict EWt2z5bMqe9TLyk7qWuBoytCdRllwV1aRefWe8HPZTPv0c4bHACyUSFy8cqlbiJ

