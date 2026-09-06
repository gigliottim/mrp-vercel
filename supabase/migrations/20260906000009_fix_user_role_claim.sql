-- Fix: claim `role` -> `user_role` en custom_access_token_hook
-- `role` es un claim reservado que PostgREST interpreta como rol de BD
-- (rompía las queries REST con "role \"Supervisor\" does not exist").

CREATE OR REPLACE FUNCTION public.custom_access_token_hook(event jsonb)
RETURNS jsonb
LANGUAGE plpgsql
STABLE
AS $$
DECLARE
    claims jsonb;
    v_company_id bigint;
    v_role_name text;
BEGIN
    SELECT uc.company_id, r.name
      INTO v_company_id, v_role_name
      FROM public.user_company uc
      JOIN public.roles r ON r.id = uc.role_id
     WHERE uc.user_id = (event->>'user_id')::uuid
     LIMIT 1;

    claims := event->'claims';
    IF v_company_id IS NOT NULL THEN
        claims := jsonb_set(claims, '{company_id}', to_jsonb(v_company_id));
        claims := jsonb_set(claims, '{user_role}', to_jsonb(v_role_name));
    END IF;
    event := jsonb_set(event, '{claims}', claims);
    RETURN event;
END;
$$;
