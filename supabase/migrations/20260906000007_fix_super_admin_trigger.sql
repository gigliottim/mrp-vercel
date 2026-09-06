-- Fix: fn_super_admin_auto_link usa el rol real "Super Administrador" (id 1)
-- El dump original buscaba 'super_admin' (nombre legacy que ya no existe en datos).

CREATE OR REPLACE FUNCTION public.fn_super_admin_auto_link() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_super_email  TEXT    := 'martin@unik.ar';
    v_role_name    TEXT    := 'Super Administrador';
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
