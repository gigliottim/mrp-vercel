-- Hook v2: respeta user_metadata.active_company_id si el usuario pertenece a la empresa
-- NOTA: GoTrue pasa user_metadata dentro del path `event.claims.user_metadata`
-- (verificado capturando el evento real con un hook de debug); el path raíz
-- `event.user_metadata` NO existe en el evento de login. Se leen ambos paths
-- con COALESCE por si GoTrue cambia el shape entre versiones.
-- Blindaje: active_company_id es user-editable (PUT /auth/v1/user), así que se
-- valida con regex antes del cast ::bigint; un valor no numérico cae al
-- fallback (ORDER BY company_id) en vez de lanzar excepción y romper el login.
CREATE OR REPLACE FUNCTION public.custom_access_token_hook(event jsonb)
RETURNS jsonb
LANGUAGE plpgsql
STABLE
AS $$
DECLARE
  claims jsonb;
  v_company_id bigint;
  v_role_name text;
  v_active bigint;
  v_raw text;
BEGIN
  v_raw := COALESCE(
    NULLIF(event->'user_metadata'->>'active_company_id', ''),
    NULLIF(event->'claims'->'user_metadata'->>'active_company_id', '')
  );
  IF v_raw IS NOT NULL AND v_raw ~ '^[0-9]+$' THEN
    v_active := v_raw::bigint;
  END IF;

  IF v_active IS NOT NULL THEN
    SELECT uc.company_id, r.name
      INTO v_company_id, v_role_name
      FROM public.user_company uc
      JOIN public.roles r ON r.id = uc.role_id
     WHERE uc.user_id = (event->>'user_id')::uuid
       AND uc.company_id = v_active
     LIMIT 1;
  END IF;

  IF v_company_id IS NULL THEN
    SELECT uc.company_id, r.name
      INTO v_company_id, v_role_name
      FROM public.user_company uc
      JOIN public.roles r ON r.id = uc.role_id
     WHERE uc.user_id = (event->>'user_id')::uuid
     ORDER BY uc.company_id
     LIMIT 1;
  END IF;

  claims := event->'claims';
  IF v_company_id IS NOT NULL THEN
    claims := jsonb_set(claims, '{company_id}', to_jsonb(v_company_id));
    claims := jsonb_set(claims, '{user_role}', to_jsonb(v_role_name));
  END IF;
  event := jsonb_set(event, '{claims}', claims);
  RETURN event;
END;
$$;

grant usage on schema public to supabase_auth_admin;
grant execute on function public.custom_access_token_hook to supabase_auth_admin;
revoke execute on function public.custom_access_token_hook from authenticated, anon, public;
grant all on table public.user_company to supabase_auth_admin;
grant select on table public.roles to supabase_auth_admin;
