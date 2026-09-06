-- Funciones y triggers del esquema mrp_auth
-- Fuente: database/backups/2026-09-06_09-40-12/mrp_auth_schema_2026-09-06_09-40-12.sql
-- Cambio: fn_super_admin_auto_link usa auth.users (uuid) en lugar de users (bigint).

-- ── update_updated_at_column ──
CREATE OR REPLACE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

-- ── fn_super_admin_auto_link ──
CREATE OR REPLACE FUNCTION public.fn_super_admin_auto_link() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_super_email  TEXT    := 'martin@unik.ar';
    v_role_name    TEXT    := 'super_admin';
    v_user_id      UUID;
    v_role_id      INTEGER;
BEGIN
    SELECT id INTO v_user_id FROM auth.users WHERE lower(email) = v_super_email LIMIT 1;
    SELECT id INTO v_role_id FROM public.roles  WHERE name = v_role_name            LIMIT 1;
    IF v_user_id IS NOT NULL AND v_role_id IS NOT NULL THEN
        INSERT INTO public.user_company (user_id, company_id, role_id)
        VALUES (v_user_id, NEW.id, v_role_id)
        ON CONFLICT DO NOTHING;
    END IF;
    RETURN NEW;
END;
$$;

-- ── Triggers ──
CREATE TRIGGER set_timestamp_companies BEFORE UPDATE ON public.companies FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
CREATE TRIGGER set_timestamp_roles BEFORE UPDATE ON public.roles FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
CREATE TRIGGER trg_super_admin_auto_link AFTER INSERT ON public.companies FOR EACH ROW EXECUTE FUNCTION public.fn_super_admin_auto_link();
