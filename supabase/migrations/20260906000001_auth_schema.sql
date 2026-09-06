-- Migración del esquema mrp_auth a Supabase
-- Fuente: database/backups/2026-09-06_09-40-12/mrp_auth_schema_2026-09-06_09-40-12.sql
-- Cambios: users -> auth.users (uuid), personal_access_tokens eliminada,
-- schema_migrations eliminada, user_id pasa a uuid.

-- ── audit_logs ──
CREATE TABLE public.audit_logs (
    id bigint NOT NULL,
    user_id uuid,
    company_id bigint,
    action character varying(255) NOT NULL,
    description text,
    ip_address character varying(45),
    user_agent text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE SEQUENCE public.audit_logs_id_seq
    START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.audit_logs_id_seq OWNED BY public.audit_logs.id;
ALTER TABLE ONLY public.audit_logs ALTER COLUMN id SET DEFAULT nextval('public.audit_logs_id_seq'::regclass);

-- ── companies ──
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

CREATE SEQUENCE public.companies_id_seq
    START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.companies_id_seq OWNED BY public.companies.id;
ALTER TABLE ONLY public.companies ALTER COLUMN id SET DEFAULT nextval('public.companies_id_seq'::regclass);

-- ── roles ──
CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(120) NOT NULL,
    guard_name character varying(60) DEFAULT 'web'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE SEQUENCE public.roles_id_seq
    START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;
ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);

-- ── permissions ──
CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(120) NOT NULL,
    module character varying(120) NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;
ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);

-- ── role_has_permissions ──
CREATE TABLE public.role_has_permissions (
    role_id bigint NOT NULL,
    permission_id bigint NOT NULL
);

-- ── user_company (user_id uuid -> auth.users) ──
CREATE TABLE public.user_company (
    user_id uuid NOT NULL,
    company_id bigint NOT NULL,
    role_id bigint
);

-- ── user_has_roles (user_id uuid -> auth.users) ──
CREATE TABLE public.user_has_roles (
    user_id uuid NOT NULL,
    role_id bigint NOT NULL
);

-- ── menu_items ──
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

CREATE SEQUENCE public.menu_items_id_seq
    START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.menu_items_id_seq OWNED BY public.menu_items.id;
ALTER TABLE ONLY public.menu_items ALTER COLUMN id SET DEFAULT nextval('public.menu_items_id_seq'::regclass);

-- ── menu_acl ──
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

CREATE SEQUENCE public.menu_acl_id_seq
    START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.menu_acl_id_seq OWNED BY public.menu_acl.id;
ALTER TABLE ONLY public.menu_acl ALTER COLUMN id SET DEFAULT nextval('public.menu_acl_id_seq'::regclass);

-- ── Primary keys ──
ALTER TABLE ONLY public.audit_logs ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.companies ADD CONSTRAINT companies_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.companies ADD CONSTRAINT companies_slug_key UNIQUE (slug);
ALTER TABLE ONLY public.menu_acl ADD CONSTRAINT menu_acl_company_id_menu_item_id_subject_type_subject_id_sc_key UNIQUE (company_id, menu_item_id, subject_type, subject_id, scope);
ALTER TABLE ONLY public.menu_acl ADD CONSTRAINT menu_acl_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.menu_items ADD CONSTRAINT menu_items_code_key UNIQUE (code);
ALTER TABLE ONLY public.menu_items ADD CONSTRAINT menu_items_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.permissions ADD CONSTRAINT permissions_name_key UNIQUE (name);
ALTER TABLE ONLY public.permissions ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.role_has_permissions ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (role_id, permission_id);
ALTER TABLE ONLY public.roles ADD CONSTRAINT roles_name_key UNIQUE (name);
ALTER TABLE ONLY public.roles ADD CONSTRAINT roles_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.user_company ADD CONSTRAINT user_company_pkey PRIMARY KEY (user_id, company_id);
ALTER TABLE ONLY public.user_has_roles ADD CONSTRAINT user_has_roles_pkey PRIMARY KEY (user_id, role_id);

-- ── Índices ──
CREATE INDEX idx_menu_acl_company_subject ON public.menu_acl USING btree (company_id, subject_type, subject_id);
CREATE INDEX idx_menu_items_parent ON public.menu_items USING btree (parent_id);
CREATE INDEX idx_menu_items_section_sort ON public.menu_items USING btree (section_key, sort_order);

-- ── Foreign keys ──
ALTER TABLE ONLY public.audit_logs ADD CONSTRAINT audit_logs_company_id_fkey FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.audit_logs ADD CONSTRAINT audit_logs_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.menu_acl ADD CONSTRAINT menu_acl_company_id_fkey FOREIGN KEY (company_id) REFERENCES public.companies(id);
ALTER TABLE ONLY public.menu_acl ADD CONSTRAINT menu_acl_menu_item_id_fkey FOREIGN KEY (menu_item_id) REFERENCES public.menu_items(id);
ALTER TABLE ONLY public.menu_items ADD CONSTRAINT menu_items_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES public.menu_items(id);
ALTER TABLE ONLY public.role_has_permissions ADD CONSTRAINT role_has_permissions_permission_id_fkey FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.role_has_permissions ADD CONSTRAINT role_has_permissions_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.user_company ADD CONSTRAINT user_company_company_id_fkey FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.user_company ADD CONSTRAINT user_company_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.user_company ADD CONSTRAINT user_company_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.user_has_roles ADD CONSTRAINT user_has_roles_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.user_has_roles ADD CONSTRAINT user_has_roles_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users(id) ON DELETE CASCADE;
