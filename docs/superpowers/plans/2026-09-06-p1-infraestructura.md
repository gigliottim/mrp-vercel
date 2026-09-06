# P1: Infraestructura + Migración de BD + Auth Supabase — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Crear el proyecto Supabase (costo confirmado USD 10/mes), migrar el esquema y datos de `mrp_auth` + tenant `mrp_tunna` (backup `database/backups/2026-09-06_09-40-12/`), implementar multi-tenant con RLS + Custom Access Token Hook, y dejar el monorepo scaffolded (frontend Next.js 16 + shadcn/ui, api Hono, backend) con login funcional contra Supabase Auth.

**Architecture:** Monorepo npm workspaces con 3 subproyectos (`frontend/`, `api/`, `backend/`). Supabase como única fuente de datos: esquema `public` con columna `company_id` en tablas de negocio + RLS por JWT claims. Supabase Auth reemplaza `AuthManager`/sesiones PHP. El trigger `fn_super_admin_auto_link` se recrea en Supabase.

**Tech Stack:** Next.js 16.3.4, React 19.2.8, TypeScript 5.9.3, Tailwind 4.3.3, shadcn/ui 3.5.0, Hono 4.13.7, @supabase/supabase-js 2.115.0, @supabase/ssr 0.12.6, Zod 4.5.4, Vitest 5.0.0, Turborepo 2.10.12, Node 24 LTS.

## Global Constraints

- Versiones exactas del stack (ver `docs/migracion-nextjs.md` sección 0): Next.js 16.3.4, Hono 4.13.7, @supabase/supabase-js 2.115.0, @supabase/ssr 0.12.6, TypeScript 5.9.3 (NO 7.x), Tailwind 4.3.3, shadcn/ui CLI `shadcn@latest`.
- Next.js 16: `middleware.ts` NO existe → usar `proxy.ts` con export `proxy`.
- Supabase región `sa-east-1` (São Paulo). Costo confirmado: USD 10/mes (confirmation_id `BGoZHqqJd2JYMt+cWSDFH7qDeNkZZAwbTytJrHy7r+E=`), org `vkwknhfwuqtxswllgieg`.
- Credenciales disponibles: `SUPABASE_ACCESS_TOKEN` (env), Vercel CLI autenticado como `gigliottim`.
- Backup fuente: `database/backups/2026-09-06_09-40-12/` (`mrp_auth_schema_*.sql`, `mrp_auth_full_*.sql`, `mrp_tunna_schema_*.sql`, `mrp_tunna_full_*.sql`).
- Datos reales a migrar: 3 usuarios (id 2 Sabrina Smurro `sabrinasmurro22@gmail.com`, id 4 Martin Gigliotti `martin@unik.ar`, id 6 pepe `usuario@mimrp.com.ar`), 1 empresa (id 2 `tunna`/`mrp_tunna`), 4 roles (1 Super Administrador, 2 Administrador, 3 Supervisor, 4 Usuario), 2 permissions (`systems.manage`, `production.orders`), 31 menu_items, N filas menu_acl (company_id=2, subject_type=role).
- `user_company`: (2,2,3), (4,2,1), (6,2,4). `user_has_roles`: (6,1), (2,3), (4,1). `role_has_permissions`: vacío.
- Passwords PHP (`$2y$12$...`) NO son migrables a Supabase Auth → los 3 usuarios se crean con password temporal y `email_confirm` forzado; el super admin `martin@unik.ar` conserva su rol vía trigger.
- 27 tablas tenant: `agent_ai_logs, agent_conversations, agent_messages, almacenes, bom_cabecera, bom_detalle, centros_trabajo, composicion_variantes, compras, configuracion, configuracion_general, entidades, grupos_partes, movimientos_inventario, movimientos_stock, mrp_calculos_cabecera, mrp_sugerencias, ordenes_produccion, partes, planificacion_recursos, rutas_produccion, schema_migrations, tipos_depositos, tipos_depositos_movimientos, tipos_partes, unidades_medida, variantes`.
- Toda tabla tenant recibe `company_id bigint NOT NULL` + índice + RLS. `schema_migrations` del tenant NO se migra (reemplazada por migraciones Supabase).
- SQL: siempre prepared statements / migraciones idempotentes (`IF NOT EXISTS`, `ON CONFLICT DO NOTHING`).
- Commits frecuentes, mensajes en inglés estilo conventional commits (`feat:`, `fix:`, `chore:`).

---

### Task 1: Crear proyecto Supabase MRP

**Files:**
- Create: `supabase/config.toml` (generado por CLI)
- Create: `supabase/.temp/project-ref` (generado por CLI)

**Interfaces:**
- Produces: `SUPABASE_PROJECT_REF` (string, ej. `abcdefghijklmnopqrst`) usado por todas las tareas siguientes; `SUPABASE_DB_URL` (postgresql://postgres.<ref>:<password>@aws-0-sa-east-1.pooler.supabase.com:5432/postgres).

- [x] **Step 1: Crear el proyecto con la confirmación de costo**

Usar el MCP Supabase (herramienta `create_project`) con:
- name: `mrp`
- region: `sa-east-1`
- organization_id: `vkwknhfwuqtxswllgieg`
- confirm_cost_id: `BGoZHqqJd2JYMt+cWSDFH7qDeNkZZAwbTytJrHy7r+E=`

- [x] **Step 2: Verificar que el proyecto quedó ACTIVE_HEALTHY**

Usar MCP `get_project` con el id devuelto. Esperar hasta que `status == "ACTIVE_HEALTHY"` (puede tardar 2-5 min; reintentar cada 30s, máx 10 intentos).

- [x] **Step 3: Obtener URL y claves del proyecto**

Usar MCP `get_project_url` y `get_publishable_keys`. Guardar en `.env` del repo (NO commitear):
```
SUPABASE_URL=https://<ref>.supabase.co
SUPABASE_ANON_KEY=<publishable_key>
SUPABASE_SERVICE_ROLE_KEY=<service_role_key>
SUPABASE_DB_URL=postgresql://postgres.<ref>:<password>@aws-0-sa-east-1.pooler.supabase.com:5432/postgres
```
El service_role key se obtiene del dashboard (o `supabase projects api-keys --project-ref <ref>` con el CLI autenticado).

- [x] **Step 4: Verificar conectividad**

Run: `psql "$SUPABASE_DB_URL" -c "select version();"`
Expected: devuelve PostgreSQL 17.x.

- [x] **Step 5: Commit**

```bash
git add .env.example
git commit -m "chore: add supabase env template for mrp project"
```
(El `.env` real queda en `.gitignore` — verificar que ya lo está.)

---

### Task 2: Migrar esquema `mrp_auth` a Supabase

**Files:**
- Create: `supabase/migrations/202609060001_auth_schema.sql`
- Create: `supabase/migrations/202609060002_auth_functions.sql`

**Interfaces:**
- Produces: tablas `public.companies`, `public.roles`, `public.permissions`, `public.role_has_permissions`, `public.user_company`, `public.user_has_roles`, `public.menu_items`, `public.menu_acl`, `public.audit_logs`; funciones `public.update_updated_at_column()`, `public.fn_super_admin_auto_link()`; triggers `set_timestamp_*`, `trg_super_admin_auto_link`.
- Consumes: `SUPABASE_PROJECT_REF` (Task 1).

- [x] **Step 1: Escribir la migración del esquema auth**

Crear `supabase/migrations/202609060001_auth_schema.sql` con el esquema adaptado del dump `mrp_auth_schema_2026-09-06_09-40-12.sql` (líneas 83-872). Cambios respecto al dump:
- `users` NO se crea (la reemplaza `auth.users` de Supabase).
- `personal_access_tokens` NO se crea (Supabase Auth emite JWT propios).
- `schema_migrations` NO se crea (la gestiona el CLI de Supabase).
- `user_company.user_id` y `audit_logs.user_id` pasan a `uuid` (referencian `auth.users(id)`).
- `user_company.role_id` → FK a `public.roles(id)` ON DELETE SET NULL.
- `user_has_roles.user_id` → `uuid` FK a `auth.users(id)`.
- `audit_logs.user_id` → `uuid` FK a `auth.users(id)` ON DELETE SET NULL.
- `companies.id` se mantiene `bigint` (los datos existentes usan id 2).
- `menu_acl.subject_id` se mantiene `bigint` (referencia `roles.id` cuando `subject_type='role'`).
- `fn_super_admin_auto_link()` se adapta: `v_user_id uuid`, `SELECT id INTO v_user_id FROM auth.users WHERE lower(email) = v_super_email LIMIT 1;` y `INSERT INTO user_company (user_id, company_id, role_id) VALUES (v_user_id, NEW.id, v_role_id) ON CONFLICT DO NOTHING;` (el resto idéntico al dump líneas 40-58).
- `update_updated_at_column()` idéntica al dump (líneas 65-72).
- Triggers `set_timestamp_companies/roles` y `trg_super_admin_auto_link` idénticos al dump (líneas 734-758).
- Índices `idx_menu_acl_company_subject`, `idx_menu_items_parent`, `idx_menu_items_section_sort` idénticos (líneas 706-724).
- `uuid-ossp` NO se necesita (Supabase ya lo tiene).

- [x] **Step 2: Aplicar la migración**

Run: `supabase db push --project-ref <SUPABASE_PROJECT_REF>`
Expected: migración aplicada sin errores.

- [x] **Step 3: Verificar el esquema**

Run: `psql "$SUPABASE_DB_URL" -c "\dt public"` y `psql "$SUPABASE_DB_URL" -c "\df public.fn_super_admin_auto_link"`
Expected: 9 tablas listadas (companies, roles, permissions, role_has_permissions, user_company, user_has_roles, menu_items, menu_acl, audit_logs) y la función existe.

- [x] **Step 4: Commit**

```bash
git add supabase/migrations/202609060001_auth_schema.sql
git commit -m "feat: migrate mrp_auth schema to supabase"
```

---

### Task 3: Migrar esquema tenant con `company_id` + RLS

**Files:**
- Create: `supabase/migrations/202609060003_tenant_schema.sql`
- Create: `supabase/migrations/202609060004_tenant_rls.sql`

**Interfaces:**
- Produces: 26 tablas de negocio (todas las del tenant excepto `schema_migrations`) con columna `company_id bigint NOT NULL` + índice `idx_<tabla>_company` + RLS habilitado con políticas `*_tenant_select/insert/update/delete`.
- Consumes: `SUPABASE_PROJECT_REF` (Task 1).

- [x] **Step 1: Escribir la migración del esquema tenant**

Crear `supabase/migrations/202609060003_tenant_schema.sql` con las 26 tablas del dump `mrp_tunna_schema_2026-09-06_09-40-12.sql` (líneas 138-1203), con estos cambios en TODAS:
- Agregar columna `company_id bigint NOT NULL` (primera columna después de `id`).
- `schema_migrations` del tenant NO se migra.
- `usuario_creador bigint` en `ordenes_produccion` y `creado_por bigint` en `partes` se mantienen como `bigint` (referencian el id legacy; se migrarán a uuid en Task 5).
- Triggers del dump (líneas 1956-1991: `set_timestamp_ordenes`, `set_timestamp_partes`, `set_timestamp_variantes`, `trg_actualizar_stock`, `update_partes_updated_at`) y la función `actualizar_stock_trigger()` (línea 75) se copian tal cual.
- Agregar índice por tabla: `CREATE INDEX idx_<tabla>_company ON public.<tabla> (company_id);`

- [x] **Step 2: Escribir la migración RLS**

Crear `supabase/migrations/202609060004_tenant_rls.sql` con, para cada una de las 26 tablas:

```sql
alter table public.<tabla> enable row level security;

create policy "<tabla>_tenant_select" on public.<tabla>
  for select to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "<tabla>_tenant_insert" on public.<tabla>
  for insert to authenticated
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "<tabla>_tenant_update" on public.<tabla>
  for update to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint)
  with check (company_id = (auth.jwt() ->> 'company_id')::bigint);

create policy "<tabla>_tenant_delete" on public.<tabla>
  for delete to authenticated
  using (company_id = (auth.jwt() ->> 'company_id')::bigint);
```

- [x] **Step 3: Aplicar ambas migraciones**

Run: `supabase db push --project-ref <SUPABASE_PROJECT_REF>`
Expected: sin errores.

- [x] **Step 4: Verificar RLS**

Run: `psql "$SUPABASE_DB_URL" -c "select tablename, rowsecurity from pg_tables where schemaname='public' and tablename in ('partes','ordenes_produccion','compras');"`
Expected: `rowsecurity = t` en las 3.

- [x] **Step 5: Commit**

```bash
git add supabase/migrations/202609060003_tenant_schema.sql supabase/migrations/202609060004_tenant_rls.sql
git commit -m "feat: migrate tenant schema with company_id and RLS policies"
```

---

### Task 4: Custom Access Token Hook (JWT claims)

**Files:**
- Create: `supabase/migrations/202609060005_access_token_hook.sql`

**Interfaces:**
- Produces: función `public.custom_access_token_hook(event jsonb) returns jsonb` que inyecta `company_id` y `role` en el JWT; grants a `supabase_auth_admin`.
- Consumes: tablas `user_company`, `roles` (Task 2).

- [x] **Step 1: Escribir la migración del hook**

Crear `supabase/migrations/202609060005_access_token_hook.sql`:

```sql
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
    claims := jsonb_set(claims, '{role}', to_jsonb(v_role_name));
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
create policy "auth admin read user_company" on public.user_company
  as permissive for select to supabase_auth_admin using (true);
```

- [x] **Step 2: Aplicar y verificar**

Run: `supabase db push --project-ref <SUPABASE_PROJECT_REF>`
Expected: sin errores. Verificar: `psql "$SUPABASE_DB_URL" -c "select proname from pg_proc where proname='custom_access_token_hook';"` devuelve la función.

- [x] **Step 3: Activar el hook en el dashboard**

El hook Custom Access Token se activa en Supabase Dashboard → Auth → Hooks → Custom Access Token → seleccionar `public.custom_access_token_hook`. (Si el CLI no lo soporta, documentar en el commit que la activación manual es el único paso humano de esta task.)

- [x] **Step 4: Commit**

```bash
git add supabase/migrations/202609060005_access_token_hook.sql
git commit -m "feat: add custom access token hook for company_id and role claims"
```

---

### Task 5: Migrar datos auth (usuarios, roles, permisos, menú, ACL)

**Files:**
- Create: `supabase/migrations/202609060006_auth_data.sql`
- Create: `scripts/migrate-users.ts` (script Node con @supabase/supabase-js admin)

**Interfaces:**
- Produces: datos poblados en `public.companies` (1 fila), `public.roles` (4), `public.permissions` (2), `public.menu_items` (31), `public.menu_acl` (N filas), `public.user_company` (3), `public.user_has_roles` (3); 3 usuarios en `auth.users` con password temporal `Temporal123!` y `email_confirmed_at` seteado.
- Consumes: dumps `mrp_auth_full_2026-09-06_09-40-12.sql`; `SUPABASE_SERVICE_ROLE_KEY` (Task 1).

- [x] **Step 1: Escribir la migración de datos estáticos**

Crear `supabase/migrations/202609060006_auth_data.sql` con INSERTs idempotentes (`ON CONFLICT DO NOTHING`) tomados del dump `mrp_auth_full_2026-09-06_09-40-12.sql`:
- `companies`: (2, 'tunna', 'mrp_tunna', '20323837857', 'sabrinasmurro22@gmail.com', 'active', ...).
- `roles`: (1,'Super Administrador'), (2,'Administrador'), (3,'Supervisor'), (4,'Usuario') con `guard_name='web'`.
- `permissions`: (1,'systems.manage','systems'), (2,'production.orders','production').
- `menu_items`: las 31 filas del dump (id, code, label, route, icon, section_key, section_label, parent_id, sort_order, is_active).
- `menu_acl`: todas las filas del dump (company_id=2, subject_type='role', scope='item', permission_level='read', effect='allow').
- `user_company`: (user_id uuid de Sabrina, 2, 3), (uuid de Martin, 2, 1), (uuid de pepe, 2, 4) — los uuid se resuelven en Step 2 y se insertan en Step 3.
- `user_has_roles`: (uuid de pepe, 1), (uuid de Sabrina, 3), (uuid de Martin, 1).

- [x] **Step 2: Escribir el script de migración de usuarios**

Crear `scripts/migrate-users.ts`:

```ts
import { createClient } from '@supabase/supabase-js'

const admin = createClient(
  process.env.SUPABASE_URL!,
  process.env.SUPABASE_SERVICE_ROLE_KEY!,
  { auth: { autoRefreshToken: false, persistSession: false } }
)

const users = [
  { legacy_id: 2, email: 'sabrinasmurro22@gmail.com', name: 'Sabrina Smurro' },
  { legacy_id: 4, email: 'martin@unik.ar', name: 'Martin Gigliotti' },
  { legacy_id: 6, email: 'usuario@mimrp.com.ar', name: 'pepe' },
]

const TEMP_PASSWORD = 'Temporal123!'

async function main() {
  for (const u of users) {
    const { data, error } = await admin.auth.admin.createUser({
      email: u.email,
      password: TEMP_PASSWORD,
      email_confirm: true,
      user_metadata: { legacy_id: u.legacy_id, name: u.name },
    })
    if (error) throw error
    console.log(`created ${u.email} -> ${data.user.id}`)
  }
}

main().catch((e) => { console.error(e); process.exit(1) })
```

- [x] **Step 3: Ejecutar el script y completar los INSERTs de user_company/user_has_roles**

Run: `npx tsx scripts/migrate-users.ts`
Expected: 3 usuarios creados con sus uuid impresos.

Luego editar `supabase/migrations/202609060006_auth_data.sql` reemplazando los placeholders `'<uuid-sabrina>'`, `'<uuid-martin>'`, `'<uuid-pepe>'` por los uuid reales obtenidos, y aplicar:

Run: `supabase db push --project-ref <SUPABASE_PROJECT_REF>`

- [x] **Step 4: Verificar datos**

Run:
```sql
psql "$SUPABASE_DB_URL" -c "select id, email from auth.users order by email;"
psql "$SUPABASE_DB_URL" -c "select count(*) from public.menu_items;"
psql "$SUPABASE_DB_URL" -c "select count(*) from public.menu_acl;"
psql "$SUPABASE_DB_URL" -c "select uc.user_id, uc.company_id, r.name from public.user_company uc join public.roles r on r.id=uc.role_id;"
```
Expected: 3 usuarios, 31 menu_items, N menu_acl, 3 filas user_company con roles (Supervisor, Super Administrador, Usuario).

- [x] **Step 5: Verificar el trigger de super admin**

Run: `psql "$SUPABASE_DB_URL" -c "insert into public.companies (name, slug) values ('test', 'test-trigger') on conflict do nothing; select uc.user_id, uc.company_id, r.name from public.user_company uc join public.roles r on r.id=uc.role_id where uc.company_id = (select id from public.companies where slug='test-trigger');"`
Expected: Martin (martin@unik.ar) vinculado automáticamente con rol Super Administrador a la empresa nueva. Luego limpiar: `delete from public.companies where slug='test-trigger';`

- [x] **Step 6: Commit**

```bash
git add supabase/migrations/202609060006_auth_data.sql scripts/migrate-users.ts
git commit -m "feat: migrate auth data (users, roles, permissions, menu, acl)"
```

---

### Task 6: Migrar datos tenant

**Files:**
- Create: `scripts/migrate-tenant.ts` (script Node con @supabase/supabase-js admin)

**Interfaces:**
- Produces: datos de las 26 tablas tenant poblados con `company_id = 2` (empresa tunna).
- Consumes: dump `mrp_tunna_full_2026-09-06_09-40-12.sql`; `SUPABASE_SERVICE_ROLE_KEY` (Task 1).

- [x] **Step 1: Escribir el script de migración tenant**

Crear `scripts/migrate-tenant.ts`:

```ts
import { createClient } from '@supabase/supabase-js'
import { readFileSync } from 'node:fs'

const admin = createClient(
  process.env.SUPABASE_URL!,
  process.env.SUPABASE_SERVICE_ROLE_KEY!,
  { auth: { autoRefreshToken: false, persistSession: false } }
)

const COMPANY_ID = 2
const DUMP = 'database/backups/2026-09-06_09-40-12/mrp_tunna_full_2026-09-06_09-40-12.sql'

// Tablas a migrar (excluye schema_migrations). Orden respeta FKs.
const TABLES = [
  'unidades_medida', 'tipos_partes', 'grupos_partes', 'tipos_depositos',
  'tipos_depositos_movimientos', 'entidades', 'almacenes', 'configuracion',
  'configuracion_general', 'partes', 'variantes', 'composicion_variantes',
  'bom_cabecera', 'bom_detalle', 'centros_trabajo', 'rutas_produccion',
  'planificacion_recursos', 'ordenes_produccion', 'compras',
  'movimientos_stock', 'movimientos_inventario', 'mrp_calculos_cabecera',
  'mrp_sugerencias', 'agent_conversations', 'agent_messages', 'agent_ai_logs',
]

function parseCopy(sql: string, table: string): string[][] {
  const re = new RegExp(`COPY public\\.${table} \\(([^)]+)\\) FROM stdin;\\n([\\s\\S]*?)\\\\.\\n`)
  const m = sql.match(re)
  if (!m) return []
  const cols = m[1].split(',').map((c) => c.trim())
  return m[2].split('\n').filter((l) => l.trim()).map((l) => {
    const row: string[] = []
    let cur = '', inStr = false
    for (let i = 0; i < l.length; i++) {
      const ch = l[i]
      if (inStr) {
        if (ch === '\\' && l[i + 1] === '\\') { cur += '\\'; i++ }
        else if (ch === '\\' && l[i + 1] === 't') { cur += '\t'; i++ }
        else if (ch === '\\' && l[i + 1] === 'n') { cur += '\n'; i++ }
        else if (ch === '"') inStr = false
        else cur += ch
      } else {
        if (ch === '"') inStr = true
        else if (ch === '\t') { row.push(cur); cur = '' }
        else cur += ch
      }
    }
    row.push(cur)
    return row
  }).map((r) => Object.fromEntries(cols.map((c, i) => [c, r[i] ?? null])))
}

async function main() {
  const sql = readFileSync(DUMP, 'utf-8')
  for (const table of TABLES) {
    const rows = parseCopy(sql, table)
    if (rows.length === 0) { console.log(`skip ${table} (0 rows)`); continue }
    const withCompany = rows.map((r) => ({ ...r, company_id: COMPANY_ID }))
    const { error } = await admin.from(table).insert(withCompany)
    if (error) { console.error(`FAIL ${table}:`, error.message); process.exit(1) }
    console.log(`ok ${table}: ${rows.length} rows`)
  }
}

main().catch((e) => { console.error(e); process.exit(1) })
```

- [x] **Step 2: Ejecutar el script**

Run: `npx tsx scripts/migrate-tenant.ts`
Expected: cada tabla imprime `ok <tabla>: N rows` (0 filas en las vacías es correcto).

- [x] **Step 3: Verificar conteos contra el dump**

Run: `psql "$SUPABASE_DB_URL" -c "select 'partes' t, count(*) from public.partes union all select 'variantes', count(*) from public.variantes union all select 'ordenes_produccion', count(*) from public.ordenes_produccion union all select 'bom_cabecera', count(*) from public.bom_cabecera;"`
Expected: conteos > 0 y consistentes con el dump (verificar con `grep -c "^[0-9]"` sobre las secciones COPY del dump).

- [x] **Step 4: Commit**

```bash
git add scripts/migrate-tenant.ts
git commit -m "feat: migrate tenant data with company_id"
```

---

### Task 7: Scaffolding del monorepo (npm workspaces + Turborepo)

**Files:**
- Create: `package.json` (raíz), `turbo.json`, `.gitignore` (raíz), `tsconfig.base.json`
- Create: `backend/package.json`, `backend/tsconfig.json`, `backend/src/index.ts`
- Create: `api/package.json`, `api/tsconfig.json`, `api/src/index.ts`, `api/vercel.json`
- Create: `frontend/package.json`, `frontend/tsconfig.json`, `frontend/next.config.ts`, `frontend/vercel.json`

**Interfaces:**
- Produces: workspaces `frontend`, `api`, `backend` instalables con `npm install` desde la raíz; scripts `dev`, `build`, `test`, `lint` por workspace; `turbo.json` con pipeline `build`, `test`, `lint`.
- Consumes: nada (scaffolding puro).

- [x] **Step 1: Crear package.json raíz**

```json
{
  "name": "mrp-monorepo",
  "private": true,
  "workspaces": ["frontend", "api", "backend"],
  "scripts": {
    "dev": "turbo run dev",
    "build": "turbo run build",
    "test": "turbo run test",
    "lint": "turbo run lint"
  },
  "devDependencies": {
    "turbo": "^2.10.12",
    "typescript": "^5.9.3"
  },
  "engines": { "node": ">=20.9.0" }
}
```

- [x] **Step 2: Crear turbo.json**

```json
{
  "$schema": "https://turbo.build/schema.json",
  "tasks": {
    "build": { "dependsOn": ["^build"], "outputs": [".next/**", "!.next/cache/**", "dist/**"] },
    "test": { "dependsOn": ["^build"] },
    "lint": {},
    "dev": { "cache": false, "persistent": true }
  }
}
```

- [x] **Step 3: Crear backend workspace**

`backend/package.json`:
```json
{
  "name": "@mrp/backend",
  "private": true,
  "type": "module",
  "exports": { ".": "./src/index.ts" },
  "scripts": { "test": "vitest run", "lint": "tsc --noEmit" },
  "devDependencies": { "typescript": "^5.9.3", "vitest": "^5.0.0" }
}
```
`backend/src/index.ts`:
```ts
export const VERSION = '0.1.0'
```
`backend/tsconfig.json`:
```json
{ "extends": "../tsconfig.base.json", "compilerOptions": { "outDir": "dist" }, "include": ["src"] }
```

- [x] **Step 4: Crear api workspace (Hono)**

`api/package.json`:
```json
{
  "name": "@mrp/api",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "tsx watch src/index.ts",
    "build": "tsc --noEmit",
    "test": "vitest run",
    "lint": "tsc --noEmit"
  },
  "dependencies": {
    "hono": "^4.13.7",
    "@supabase/supabase-js": "^2.115.0",
    "zod": "^4.5.4"
  },
  "devDependencies": { "typescript": "^5.9.3", "tsx": "^4.19.0", "vitest": "^5.0.0" }
}
```
`api/src/index.ts`:
```ts
import { Hono } from 'hono'

const app = new Hono()

app.get('/api/health', (c) => c.json({ status: 'ok', version: '0.1.0' }))

export default app
```
`api/vercel.json`:
```json
{ "framework": "other" }
```

- [x] **Step 5: Crear frontend workspace (Next.js 16)**

Run: `npx create-next-app@latest frontend --typescript --tailwind --app --no-src-dir --import-alias "@/*" --use-npm --yes`
Luego fijar versiones en `frontend/package.json`: `next: 16.3.4`, `react: 19.2.8`, `react-dom: 19.2.8`, `typescript: ^5.9.3`, `tailwindcss: ^4.3.3`.

`frontend/vercel.json`:
```json
{ "framework": "nextjs" }
```

- [x] **Step 6: Instalar y verificar build**

Run: `npm install && npm run build`
Expected: los 3 workspaces compilan sin errores.

- [x] **Step 7: Commit**

```bash
git add package.json turbo.json tsconfig.base.json backend api frontend
git commit -m "chore: scaffold monorepo with nextjs, hono and backend workspaces"
```

---

### Task 8: shadcn/ui + @supabase/ssr en frontend

**Files:**
- Modify: `frontend/package.json`
- Create: `frontend/components.json`, `frontend/lib/supabase/server.ts`, `frontend/lib/supabase/client.ts`, `frontend/proxy.ts`, `frontend/app/login/page.tsx`, `frontend/app/actions/auth.ts`
- Create: `frontend/lib/supabase/server.test.ts` (test de humo)

**Interfaces:**
- Produces: componentes shadcn/ui en `frontend/components/ui/`; `createClient()` server (lee cookies, `getAll`/`setAll`); `createBrowserClient()` client; `proxy.ts` con refresh de sesión y redirect a `/login`; página `/login` con form de email+password; server action `signInWithPassword`.
- Consumes: `SUPABASE_URL`, `SUPABASE_ANON_KEY` (Task 1).

- [x] **Step 1: Inicializar shadcn/ui**

Run: `npx shadcn@latest init -y -d`
Expected: genera `frontend/components.json` (con `tailwind` vacío, Tailwind 4) y `frontend/components/ui/` con el preset base-nova.

- [x] **Step 2: Agregar componentes base**

Run: `npx shadcn@latest add button input label card table dialog select form dropdown-menu tabs badge alert-dialog sonner sheet tooltip skeleton pagination checkbox switch calendar chart`
Expected: componentes copiados a `frontend/components/ui/`.

- [x] **Step 3: Instalar @supabase/ssr y crear clientes**

Run: `npm install @supabase/ssr@^0.12.6 @supabase/supabase-js@^2.115.0 next-themes`

`frontend/lib/supabase/server.ts`:
```ts
import { createServerClient } from '@supabase/ssr'
import { cookies } from 'next/headers'

export async function createClient() {
  const cookieStore = await cookies()
  return createServerClient(
    process.env.NEXT_PUBLIC_SUPABASE_URL!,
    process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!,
    {
      cookies: {
        getAll() { return cookieStore.getAll() },
        setAll(cookiesToSet) {
          try {
            cookiesToSet.forEach(({ name, value, options }) =>
              cookieStore.set(name, value, options))
          } catch { /* llamado desde Server Component: ignorar */ }
        },
      },
    }
  )
}
```

`frontend/lib/supabase/client.ts`:
```ts
import { createBrowserClient } from '@supabase/ssr'

export function createClient() {
  return createBrowserClient(
    process.env.NEXT_PUBLIC_SUPABASE_URL!,
    process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!
  )
}
```

- [x] **Step 4: Crear proxy.ts (Next 16, reemplaza middleware.ts)**

`frontend/proxy.ts`:
```ts
import { createServerClient } from '@supabase/ssr'
import { NextResponse, type NextRequest } from 'next/server'

export async function proxy(request: NextRequest) {
  let supabaseResponse = NextResponse.next({ request })

  const supabase = createServerClient(
    process.env.NEXT_PUBLIC_SUPABASE_URL!,
    process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!,
    {
      cookies: {
        getAll() { return request.cookies.getAll() },
        setAll(cookiesToSet, headers) {
          cookiesToSet.forEach(({ name, value }) => request.cookies.set(name, value))
          supabaseResponse = NextResponse.next({ request })
          cookiesToSet.forEach(({ name, value, options }) =>
            supabaseResponse.cookies.set(name, value, options))
          Object.entries(headers).forEach(([key, value]) =>
            supabaseResponse.headers.set(key, value))
        },
      },
    }
  )

  const { data: { user } } = await supabase.auth.getUser()

  if (!user && !request.nextUrl.pathname.startsWith('/login')) {
    const url = request.nextUrl.clone()
    url.pathname = '/login'
    return NextResponse.redirect(url)
  }

  return supabaseResponse
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|gif|webp)$).*)']
}
```

- [x] **Step 5: Crear server action de login y página /login**

`frontend/app/actions/auth.ts`:
```ts
'use server'

import { revalidatePath } from 'next/cache'
import { redirect } from 'next/navigation'
import { createClient } from '@/lib/supabase/server'

export async function signInWithPassword(formData: FormData) {
  const supabase = await createClient()
  const email = String(formData.get('email'))
  const password = String(formData.get('password'))

  const { error } = await supabase.auth.signInWithPassword({ email, password })
  if (error) return { error: error.message }

  revalidatePath('/', 'layout')
  redirect('/')
}
```

`frontend/app/login/page.tsx`: página con form (shadcn `Card`, `Input`, `Button`) que llama a `signInWithPassword` con `useActionState` (React 19) y muestra el error si existe.

- [x] **Step 6: Test de humo del cliente server**

`frontend/lib/supabase/server.test.ts`:
```ts
import { describe, it, expect } from 'vitest'

describe('supabase server client', () => {
  it('requiere variables de entorno', () => {
    expect(process.env.NEXT_PUBLIC_SUPABASE_URL).toBeTruthy()
    expect(process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY).toBeTruthy()
  })
})
```
Run: `npm run test -w frontend`
Expected: PASS.

- [x] **Step 7: Verificar build y login manual**

Run: `npm run build -w frontend`
Expected: build OK. Luego `npm run dev -w frontend`, abrir `http://localhost:3000/login`, loguear con `martin@unik.ar` / `Temporal123!`.
Expected: redirige a `/` y el JWT contiene `company_id: 2` y `role: "Super Administrador"` (verificar decodificando el token en `https://jwt.io` o con `supabase.auth.getSession()` en una ruta de debug).

- [x] **Step 8: Commit**

```bash
git add frontend
git commit -m "feat: add shadcn/ui, supabase ssr auth and login page"
```

---

### Task 9: Verificación E2E del multi-tenant

**Files:**
- Create: `scripts/verify-tenant.ts`

**Interfaces:**
- Consumes: `SUPABASE_URL`, `SUPABASE_ANON_KEY` (Task 1); usuarios migrados (Task 5).
- Produces: script que valida aislamiento entre tenants (crea 2 empresas de prueba, verifica que un usuario de una no ve datos de la otra, y limpia).

- [x] **Step 1: Escribir el script de verificación**

`scripts/verify-tenant.ts`:
```ts
import { createClient } from '@supabase/supabase-js'

const anon = createClient(process.env.SUPABASE_URL!, process.env.SUPABASE_ANON_KEY!)
const admin = createClient(process.env.SUPABASE_URL!, process.env.SUPABASE_SERVICE_ROLE_KEY!,
  { auth: { autoRefreshToken: false, persistSession: false } })

async function main() {
  // 1. Login como Sabrina (empresa 2, rol Supervisor)
  const { data: auth, error: err } = await anon.auth.signInWithPassword({
    email: 'sabrinasmurro22@gmail.com', password: 'Temporal123!',
  })
  if (err) throw err
  const claims = auth.session!.access_token.split('.')[1]
  const payload = JSON.parse(Buffer.from(claims, 'base64url').toString())
  if (payload.company_id !== 2) throw new Error(`company_id esperado 2, got ${payload.company_id}`)
  if (payload.role !== 'Supervisor') throw new Error(`role esperado Supervisor, got ${payload.role}`)
  console.log('JWT claims OK:', payload.company_id, payload.role)

  // 2. Sabrina lee partes de su empresa
  const { data: partes, error: e2 } = await anon.from('partes').select('id').limit(1)
  if (e2) throw e2
  console.log('partes visibles:', partes.length)

  // 3. Crear empresa de prueba y verificar que Sabrina NO la ve
  const { data: c, error: e3 } = await admin.from('companies').insert({ name: 'otra', slug: 'otra-verify' }).select().single()
  if (e3) throw e3
  const { data: hidden, error: e4 } = await anon.from('partes').select('id').eq('company_id', c.id)
  if (e4) throw e4
  if (hidden.length > 0) throw new Error('aislamiento RLS roto: vio partes de otra empresa')
  console.log('aislamiento RLS OK')

  // 4. Limpiar
  await admin.from('companies').delete().eq('id', c.id)
  console.log('cleanup OK')
}

main().catch((e) => { console.error(e); process.exit(1) })
```

- [x] **Step 2: Ejecutar y verificar**

Run: `npx tsx scripts/verify-tenant.ts`
Expected: imprime `JWT claims OK: 2 Supervisor`, `partes visibles: 1`, `aislamiento RLS OK`, `cleanup OK`.

- [x] **Step 3: Commit**

```bash
git add scripts/verify-tenant.ts
git commit -m "test: verify multi-tenant RLS isolation and jwt claims"
```

---

### Task 10: Documentación de cierre y handoff

**Files:**
- Modify: `docs/migracion-nextjs.md` (marcar Fase 0 como completada)
- Create: `docs/superpowers/plans/2026-09-06-p1-infraestructura.md` (este archivo, como registro)

**Interfaces:**
- Consumes: todo lo anterior.
- Produces: estado documentado: proyecto Supabase ref, credenciales en `.env`, migraciones aplicadas, usuarios con password temporal, pendientes para P2.

- [x] **Step 1: Actualizar el documento de migración**

En `docs/migracion-nextjs.md`, sección 3 Fase 0: marcar cada ítem con `[x]` y agregar al final:
```
**Estado P1 (2026-09-06):** Proyecto Supabase `<ref>` creado (sa-east-1, USD 10/mes). Esquema auth + tenant migrados con RLS. Custom Access Token Hook activo. 3 usuarios migrados con password temporal `Temporal123!` (deben cambiarla en el primer login). Monorepo scaffolded. Login funcional. Pendiente: P2 (API Hono).
```

- [x] **Step 2: Verificar estado final**

Run: `npm run build && npm run test`
Expected: todos los workspaces compilan y los tests pasan.

- [x] **Step 3: Commit**

```bash
git add docs/migracion-nextjs.md
git commit -m "docs: mark P1 infrastructure phase complete"
```

---

## Self-Review

**1. Spec coverage (docs/migracion-nextjs.md):**
- Fase 0 completa: repo structure (Task 7), proyectos Vercel/Supabase (Task 1), dominio (pendiente P5 — requiere Cloudflare, fuera de alcance P1), migración BD (Tasks 2-6), CI/CD (pendiente P2).
- Sección 6 (modelo de datos): mapeo auth (Task 2), RLS multi-tenant (Tasks 3-4), migración de datos (Tasks 5-6), trigger super admin (Task 5 Step 5).
- Sección 7 (auth Next.js 16): proxy.ts + @supabase/ssr (Task 8).
- Sección 5.1 (shadcn/ui): init + componentes (Task 8).
- Costo USD 10/mes confirmado y usado (Task 1).

**2. Placeholder scan:** los únicos placeholders son `<SUPABASE_PROJECT_REF>`, `<ref>`, `<uuid-*>` que se resuelven en runtime (Task 1 Step 1, Task 5 Step 3) — son valores de entorno, no contenido faltante. El script `parseCopy` está completo. No hay "TBD"/"TODO".

**3. Type consistency:** `company_id bigint` consistente en Tasks 3, 5, 6, 9. `user_id uuid` consistente en Tasks 2, 5. `custom_access_token_hook` firma consistente entre Task 4 y Task 9 (claims `company_id`, `role`). `createClient()` server/client con `getAll`/`setAll` consistente en Task 8.

**Gaps detectados y resueltos:**
- Task 4 Step 3: la activación del hook en el dashboard puede requerir un clic manual (único paso humano del plan, documentado).
- Task 5: los passwords temporales requieren que el usuario los cambie (decisión ya tomada por el usuario: aceptó el costo y la migración).
- Dominio/Cloudflare y CI/CD quedan explícitamente fuera de P1 (se ejecutan en P2/P5).
