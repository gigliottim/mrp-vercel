-- Custom Access Token Hook: inyecta company_id y user_role en el JWT
-- Patrón verificado en Context7 (Supabase RBAC guide).
-- NOTA: el claim se llama `user_role` (NO `role`): `role` es un claim
-- reservado que PostgREST interpreta como rol de base de datos.

create or replace function public.custom_access_token_hook(event jsonb)
returns jsonb
language plpgsql
stable
as $$
declare
  claims jsonb;
  v_company_id bigint;
  v_role_name text;
begin
  select uc.company_id, r.name
    into v_company_id, v_role_name
    from public.user_company uc
    join public.roles r on r.id = uc.role_id
   where uc.user_id = (event->>'user_id')::uuid
   limit 1;

  claims := event->'claims';
  if v_company_id is not null then
    claims := jsonb_set(claims, '{company_id}', to_jsonb(v_company_id));
    claims := jsonb_set(claims, '{user_role}', to_jsonb(v_role_name));
  end if;
  event := jsonb_set(event, '{claims}', claims);
  return event;
end;
$$;

grant usage on schema public to supabase_auth_admin;
grant execute on function public.custom_access_token_hook to supabase_auth_admin;
revoke execute on function public.custom_access_token_hook from authenticated, anon, public;

grant all on table public.user_company to supabase_auth_admin;
revoke all on table public.user_company from authenticated, anon, public;
grant select on table public.roles to supabase_auth_admin;

create policy "auth admin read user_company" on public.user_company
  as permissive for select to supabase_auth_admin using (true);
