# P4: Frontend Next.js 16 — Layout, Sidebar dinámico y Módulos Maestros — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir el layout del dashboard en Next.js 16 con sidebar dinámico (menú desde la BD vía API con ACL por rol), página de dashboard con KPIs, y los CRUD de módulos maestros (unidades de medida, tipos de partes, grupos de partes, tipos de depósitos, validaciones, entidades, almacenes, configuración) consumiendo la API `mrp-api` (v0.3.0).

**Architecture:** Server Components para datos (fetch a la API con token de la sesión Supabase), Client Components con shadcn/ui para interactividad (tablas, dialogs, formularios con react-hook-form + zod). Sidebar generado desde `menu_items` + `menu_acl` (API `companies`/menú). Dark/light mode con next-themes.

**Tech Stack:** Next.js 16.3.4, React 19.2.8, Tailwind 4.3.3, shadcn/ui, @supabase/ssr 0.12.6, react-hook-form, zod 4.5.4, next-themes, lucide-react.

## Global Constraints

- API base: `https://api.mimrp.com.ar` (producción). En dev: variable `NEXT_PUBLIC_API_URL`.
- Auth: sesión Supabase via `proxy.ts` (ya funcional). El token se pasa a la API con `Authorization: Bearer <access_token>`.
- JWT claims: `company_id`, `user_role` (en el payload, no en app_metadata).
- El menú (33 items, 6 secciones) vive en `menu_items`; la visibilidad por rol en `menu_acl` (subject_type='role', effect='allow'). Endpoint: GET `/api/v1/companies` devuelve `user_company` con role_id; el menú filtrado se obtiene de la BD (server component consulta directa vía RLS, o endpoint nuevo).
- Secciones reales (del dump): `taller` (Taller), `reportes` (Reportes), `catalogo_productos` (Desarrollo y Maestros), `empresa_usuarios` (Empresa y Usuarios), `planificacion_compras` (Planificación y Compras), `administracion` (Administración), `produccion` (Producción).
- Páginas de P4 (módulos maestros): unidades de medida, tipos de partes, grupos de partes, tipos de depósitos, validaciones de movimientos, entidades (clientes/proveedores), almacenes, configuración general.
- Componentes shadcn disponibles: button, input, label, card, table, dialog, select, form (falta instalar), dropdown-menu, tabs, badge, alert-dialog, sonner, sheet, tooltip, skeleton, pagination, checkbox, switch, calendar, chart.
- Formularios con react-hook-form + zod (schema compartido con la API).
- Dark/light: `next-themes` (ya instalado) con `ThemeProvider` y toggle en el navbar.
- Rutas Next.js (App Router): `app/(dashboard)/...` con layout compartido; `app/(auth)/login` ya existe (fuera del layout dashboard).
- Commits frecuentes, conventional commits.

---

### Task 1: API client + hook de sesión

**Files:**
- Create: `frontend/lib/api.ts`
- Create: `frontend/lib/use-session.ts`
- Create: `frontend/lib/api.test.ts` (test del cliente)

**Interfaces:**
- Produces: `apiFetch<T>(path, options)` (server-side: recibe token; client-side: obtiene token de supabase browser y llama); `getSession()` (server: lee cookie de supabase y devuelve user + access_token + companyId + role); `useSession()` (client hook con estado).
- Consumes: `lib/supabase/server.ts`, `lib/supabase/client.ts` (P1).

- [x] **Step 1: Escribir el API client**

`frontend/lib/api.ts`:
```ts
const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'https://api.mimrp.com.ar'

export class ApiError extends Error {
  code: string
  status: number
  constructor(code: string, message: string, status: number) {
    super(message)
    this.code = code
    this.status = status
  }
}

export async function apiFetch<T>(
  path: string,
  token: string,
  options: RequestInit = {}
): Promise<T> {
  const res = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
      ...options.headers,
    },
  })
  if (!res.ok) {
    const body = await res.json().catch(() => null)
    throw new ApiError(
      body?.error?.code ?? 'UNKNOWN',
      body?.error?.message ?? `HTTP ${res.status}`,
      res.status
    )
  }
  if (res.status === 204) return undefined as T
  return res.json() as Promise<T>
}

export type Paginated<T> = {
  data: T[]
  pagination: { page: number; perPage: number; total: number }
}
```

- [x] **Step 2: Escribir getSession (server) y useSession (client)**

`frontend/lib/use-session.ts`:
```ts
import { createClient } from '@/lib/supabase/server'
import { createClient as createBrowserClient } from '@/lib/supabase/client'
import { useEffect, useState } from 'react'

export type SessionInfo = {
  userId: string
  email: string
  companyId: number
  role: string
  accessToken: string
}

export async function getSession(): Promise<SessionInfo | null> {
  const supabase = await createClient()
  const { data: { session } } = await supabase.auth.getSession()
  if (!session) return null
  const payload = JSON.parse(
    Buffer.from(session.access_token.split('.')[1], 'base64url').toString()
  )
  return {
    userId: session.user.id,
    email: session.user.email ?? '',
    companyId: Number(payload.company_id),
    role: String(payload.user_role ?? ''),
    accessToken: session.access_token,
  }
}

export function useSession() {
  const [session, setSession] = useState<SessionInfo | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const supabase = createBrowserClient()
    supabase.auth.getSession().then(({ data }) => {
      const s = data.session
      if (!s) { setLoading(false); return }
      const payload = JSON.parse(
        Buffer.from(s.access_token.split('.')[1], 'base64url').toString()
      )
      setSession({
        userId: s.user.id,
        email: s.user.email ?? '',
        companyId: Number(payload.company_id),
        role: String(payload.user_role ?? ''),
        accessToken: s.access_token,
      })
      setLoading(false)
    })
  }, [])

  return { session, loading }
}
```

- [x] **Step 3: Escribir test del cliente**

`frontend/lib/api.test.ts`:
```ts
import { describe, it, expect } from 'vitest'
import { ApiError, apiFetch } from './api'

describe('apiFetch', () => {
  it('lanza ApiError con codigo en 401', async () => {
    // mock global fetch
    const original = global.fetch
    global.fetch = (async () =>
      new Response(JSON.stringify({ error: { code: 'UNAUTHORIZED', message: 'x' } }), { status: 401 })) as typeof fetch
    try {
      await apiFetch('/x', 'token')
      expect.unreachable()
    } catch (e) {
      expect(e).toBeInstanceOf(ApiError)
      expect((e as ApiError).code).toBe('UNAUTHORIZED')
    } finally {
      global.fetch = original
    }
  })

  it('parsea respuesta paginada', async () => {
    const original = global.fetch
    global.fetch = (async () =>
      new Response(JSON.stringify({ data: [{ id: 1 }], pagination: { page: 1, perPage: 20, total: 1 } }), { status: 200 })) as typeof fetch
    const r = await apiFetch<{ data: { id: number }[] }>('/x', 'token')
    expect(r.data[0].id).toBe(1)
    global.fetch = original
  })
})
```

- [x] **Step 4: Ejecutar tests**

Run: `npm run test -w frontend`
Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add frontend/lib/api.ts frontend/lib/use-session.ts frontend/lib/api.test.ts
git commit -m "feat: add api client and session hooks"
```

---

### Task 2: Layout dashboard + Sidebar dinámico

**Files:**
- Create: `frontend/app/(dashboard)/layout.tsx`
- Create: `frontend/components/dashboard/sidebar.tsx`
- Create: `frontend/components/dashboard/navbar.tsx`
- Create: `frontend/components/dashboard/theme-toggle.tsx`
- Modify: `frontend/app/layout.tsx` (agregar ThemeProvider)

**Interfaces:**
- Produces: layout `(dashboard)` que: (1) server-side obtiene sesión (`getSession`) y redirige a `/login` si no hay; (2) consulta el menú (server component fetch a API `/api/v1/companies` para role + fetch a `/api/v1/menu` — ver Task 3) y renderiza sidebar; (3) navbar con toggle de tema + usuario + signOut.
- Consumes: Task 1, shadcn/ui (sheet, dropdown-menu, button, tooltip).

- [x] **Step 1: Agregar ThemeProvider al layout raíz**

`frontend/app/layout.tsx`: envolver `children` con `ThemeProvider` (next-themes, attribute="class", defaultTheme="system", enableSystem).

- [x] **Step 2: Crear el layout del dashboard**

`frontend/app/(dashboard)/layout.tsx`:
```tsx
import { redirect } from 'next/navigation'
import { getSession } from '@/lib/use-session'
import { Sidebar } from '@/components/dashboard/sidebar'
import { Navbar } from '@/components/dashboard/navbar'

export default async function DashboardLayout({
  children,
}: {
  children: React.ReactNode
}) {
  const session = await getSession()
  if (!session) redirect('/login')

  return (
    <div className="flex min-h-screen">
      <Sidebar session={session} />
      <div className="flex flex-1 flex-col">
        <Navbar session={session} />
        <main className="flex-1 p-6">{children}</main>
      </div>
    </div>
  )
}
```

- [x] **Step 3: Crear el Sidebar**

`frontend/components/dashboard/sidebar.tsx` (Client Component):
- Prop: `session` (serializable).
- Server: fetch del menú se hace en el layout y se pasa como prop (SSR) — ver Task 3 para el tipo `MenuItem`.
- Render: secciones agrupadas por `section_label`, items con `icon` (Font Awesome — mapear a lucide-react o usar el CSS class `fa-solid`), ruta `route` con `next/link`, `active` según `usePathname()`.
- Mobile: `Sheet` de shadcn con botón hamburguesa.

- [x] **Step 4: Crear el Navbar + ThemeToggle**

`frontend/components/dashboard/theme-toggle.tsx`: botón con `useTheme()` (next-themes) que alterna light/dark con icono sol/luna.

`frontend/components/dashboard/navbar.tsx`: header sticky con: título de la sección actual, ThemeToggle, dropdown con email del usuario y botón "Cerrar sesión" (`signOut` de `app/actions/auth.ts`).

- [x] **Step 5: Verificar build**

Run: `npm run build -w frontend`
Expected: build OK.

- [x] **Step 6: Commit**

```bash
git add frontend/app/layout.tsx frontend/app/'(dashboard)' frontend/components/dashboard
git commit -m "feat: add dashboard layout with dynamic sidebar and navbar"
```

---

### Task 3: Endpoint menú en la API + Server Component del menú

**Files:**
- Create: `api/src/routes/menu.ts`
- Modify: `api/src/index.ts` (montar ruta)
- Create: `api/src/routes/menu.test.ts`
- Create: `frontend/lib/menu.ts`
- Create: `frontend/lib/menu.test.ts`

**Interfaces:**
- Produces: GET `/api/v1/menu` → `{ data: MenuItem[] }` donde `MenuItem = { id, code, label, route, icon, section_key, section_label, sort_order, parent_id }` filtrado por RLS (authenticated ve solo su empresa) y **filtrado por ACL**: items cuyo `menu_acl` con `subject_type='role'`, `subject_id` = role del claim, `effect='allow'`. Si no hay ACL para el item, NO se muestra (default deny).
- Consumes: `requireAuth` (P2), tabla `menu_items` + `menu_acl`.

- [x] **Step 1: Escribir el endpoint**

`api/src/routes/menu.ts`:
```ts
import { Hono } from 'hono'
import { requireAuth, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'

export const menu = new Hono<AuthEnv>()
menu.use('*', requireAuth)

menu.get('/', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const companyId = c.get('companyId')

  // Obtener role_id del usuario en esta empresa
  const { data: uc } = await supabase
    .from('user_company')
    .select('role_id')
    .eq('company_id', companyId)
    .eq('user_id', c.get('userId'))
    .single()
  if (!uc?.role_id) {
    return c.json({ data: [] })
  }

  // Items permitidos por ACL (default deny)
  const { data: acl } = await supabase
    .from('menu_acl')
    .select('menu_item_id')
    .eq('company_id', companyId)
    .eq('subject_type', 'role')
    .eq('subject_id', uc.role_id)
    .eq('effect', 'allow')

  if (!acl || acl.length === 0) {
    return c.json({ data: [] })
  }
  const allowedIds = acl.map((a) => a.menu_item_id)

  const { data, error } = await supabase
    .from('menu_items')
    .select('*')
    .in('id', allowedIds)
    .eq('is_active', true)
    .order('section_key')
    .order('sort_order')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})
```

- [x] **Step 2: Montar y testear**

Modificar `api/src/index.ts` para montar `app.route('/api/v1/menu', menu)`.

`api/src/routes/menu.test.ts`:
- Login como Sabrina (Supervisor, empresa 2) → GET `/api/v1/menu` → 200, array con items (verificar que `panel.inicio` está, dado el ACL del dump).
- Login como Martin (Super Administrador) → también 200.

Run: `npm run test -w api` — Expected: PASS.

- [x] **Step 3: Escribir el cliente de menú en frontend**

`frontend/lib/menu.ts`:
```ts
import type { Paginated } from './api'

export type MenuItem = {
  id: number
  code: string
  label: string
  route: string | null
  icon: string | null
  section_key: string
  section_label: string
  parent_id: number | null
  sort_order: number
}

export async function fetchMenu(token: string): Promise<MenuItem[]> {
  const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL ?? 'https://api.mimrp.com.ar'}/api/v1/menu`, {
    headers: { Authorization: `Bearer ${token}` },
    cache: 'no-store',
  })
  if (!res.ok) return []
  const body = await res.json()
  return body.data ?? []
}
```

`frontend/lib/menu.test.ts`: test de agrupación por secciones (función pura `groupBySection(items)`).

- [x] **Step 4: Integrar en el layout**

Modificar `app/(dashboard)/layout.tsx`: server-side `fetchMenu(session.accessToken)` y pasar `items` a `<Sidebar items={items} />`.

- [x] **Step 5: Verificar build + tests**

Run: `npm run build && npm run test`
Expected: todo OK.

- [x] **Step 6: Commit**

```bash
git add api/src/routes/menu.ts api/src/routes/menu.test.ts api/src/index.ts frontend/lib/menu.ts frontend/lib/menu.test.ts frontend/app/'(dashboard)'/layout.tsx frontend/components/dashboard/sidebar.tsx
git commit -m "feat: add dynamic menu endpoint with ACL filtering"
```

---

### Task 4: Página Dashboard (KPIs)

**Files:**
- Create: `frontend/app/(dashboard)/page.tsx`
- Create: `frontend/components/dashboard/kpi-card.tsx`
- Create: `frontend/components/dashboard/kpi-card.test.tsx` (test de render con @testing-library/react — instalar)

**Interfaces:**
- Produces: página `/` (dashboard) con KPIs: total de partes, variantes, órdenes activas, stock crítico. Data vía API: GET `/api/v1/partes?perPage=1` (count), `/api/v1/variantes?perPage=1`, `/api/v1/ordenes-produccion?estado=en_proceso`, `/api/v1/variantes/stock-critico` (nuevo endpoint o filtro en frontend).
- Consumes: Task 1, shadcn (card, badge).

- [x] **Step 1: Crear KpiCard**

`frontend/components/dashboard/kpi-card.tsx`: card con título, valor grande, ícono, subtítulo opcional.

- [x] **Step 2: Crear la página dashboard**

`frontend/app/(dashboard)/page.tsx` (Server Component):
```tsx
import { getSession } from '@/lib/use-session'
import { apiFetch, type Paginated } from '@/lib/api'
import { KpiCard } from '@/components/dashboard/kpi-card'

export default async function DashboardPage() {
  const session = await getSession()
  if (!session) return null // layout redirige

  const [partes, variantes, ordenes] = await Promise.all([
    apiFetch<Paginated<unknown>>('/api/v1/partes?perPage=1', session.accessToken).catch(() => null),
    apiFetch<Paginated<unknown>>('/api/v1/variantes?perPage=1', session.accessToken).catch(() => null),
    apiFetch<Paginated<unknown>>('/api/v1/ordenes-produccion?estado=en_proceso', session.accessToken).catch(() => null),
  ])

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Panel inicial</h1>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard title="Partes" value={partes?.pagination.total ?? 0} icon="puzzle" />
        <KpiCard title="Variantes" value={variantes?.pagination.total ?? 0} icon="layers" />
        <KpiCard title="Órdenes en proceso" value={ordenes?.pagination.total ?? 0} icon="factory" />
        <KpiCard title="Empresa" value={session.companyId} icon="building" subtitle={session.role} />
      </div>
    </div>
  )
}
```

- [x] **Step 3: Test KpiCard**

Instalar: `npm install -D @testing-library/react @testing-library/jest-dom jsdom --legacy-peer-deps`
Configurar vitest frontend con environment jsdom para tests de componentes (ajustar `vitest.config.ts` con `environmentMatchGlobs` o por archivo `// @vitest-environment jsdom`).

`kpi-card.test.tsx`: renderiza título y valor.

- [x] **Step 4: Verificar build + tests**

Run: `npm run build && npm run test`
Expected: OK.

- [x] **Step 5: Commit**

```bash
git add frontend/app/'(dashboard)'/page.tsx frontend/components/dashboard/kpi-card.tsx frontend/components/dashboard/kpi-card.test.tsx frontend/vitest.config.ts frontend/package.json
git commit -m "feat: add dashboard page with KPIs"
```

---

### Task 5: Patrón de listado CRUD + Módulo Unidades de Medida

**Files:**
- Create: `frontend/components/crud/data-table.tsx` (tabla genérica con paginación)
- Create: `frontend/components/crud/crud-page.tsx` (page wrapper genérico: header + botón nuevo + tabla + dialog form)
- Create: `frontend/app/(dashboard)/configuracion/unidades/page.tsx`
- Create: `frontend/app/(dashboard)/configuracion/unidades/unidades-form.tsx`
- Create: `frontend/app/(dashboard)/configuracion/unidades/actions.ts`
- Create: `frontend/app/(dashboard)/configuracion/unidades/page.test.ts` (test de la función de parseo de datos)

**Interfaces:**
- Produces: página `/configuracion/unidades` (rutas del menú: `catalogos.unidades` → `/configuracion/unidades`): tabla con paginación (server), dialog de alta/edición (client con react-hook-form + zod), delete con confirmación (alert-dialog) y rol Admin.
- Consumes: Task 1 (apiFetch), Task 2 (layout), shadcn (table, dialog, form, select, alert-dialog, sonner para toasts).

- [x] **Step 1: Crear DataTable genérica**

`frontend/components/crud/data-table.tsx`: props `columns` (definición), `data`, `pagination`, `onPageChange`. Renderiza `<Table>` con `<TablePagination>`.

- [x] **Step 2: Crear el form de unidad**

`unidades-form.tsx` (Client): `useForm` con zod schema (tipo, unidad, simbolo, equivalencia_base, es_base, activo). Props: `initial?: UnidadMedida | null`, `onSubmit(data)`, `onCancel`. Usa `Form`, `Input`, `Select`, `Switch` de shadcn.

- [x] **Step 3: Crear las actions (server actions para mutaciones)**

`actions.ts`:
```ts
'use server'
import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/use-session'
import { apiFetch } from '@/lib/api'

export async function crearUnidad(data: unknown) {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch('/api/v1/unidades-medida', session.accessToken, {
      method: 'POST',
      body: JSON.stringify(data),
    })
    revalidatePath('/configuracion/unidades')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function actualizarUnidad(id: number, data: unknown) { /* PATCH */ }
export async function eliminarUnidad(id: number) { /* DELETE con role check client-side */ }
```

- [x] **Step 4: Crear la página**

`page.tsx` (Server Component): obtiene sesión, fetches `GET /api/v1/unidades-medida?page&perPage`, renderiza CrudPage con columns (tipo, unidad, simbolo, equivalencia, activo badge) y acciones (editar/eliminar → dialog client).

- [x] **Step 5: Test de helpers**

`page.test.ts`: test de la función que mapea `tipo` a label en español (longitud→Longitud, etc.) — función pura exportada.

- [x] **Step 6: Verificar build + tests + deploy**

Run: `npm run build && npm run test`
Expected: OK.

- [x] **Step 7: Commit**

```bash
git add frontend/components/crud frontend/app/'(dashboard)'/configuracion/unidades
git commit -m "feat: add unidades de medida module"
```

---

### Task 6: Módulos Tipos de Partes, Grupos de Partes, Tipos de Depósitos

**Files:**
- Create: `frontend/app/(dashboard)/configuracion/tipos-partes/page.tsx` + `tipos-partes-form.tsx` + `actions.ts`
- Create: `frontend/app/(dashboard)/configuracion/grupos-partes/page.tsx` + `grupos-partes-form.tsx` + `actions.ts`
- Create: `frontend/app/(dashboard)/configuracion/tipos-depositos/page.tsx` + `tipos-depositos-form.tsx` + `actions.ts`

**Interfaces:**
- Produces: 3 páginas CRUD con el mismo patrón de Task 5 (rutas del menú: `catalogos.tipos_partes` → `/configuracion/tipos-partes`, `catalogos.grupos_partes` → `/configuracion/grupos-partes`, `catalogos.tipos_depositos` → `/configuracion/tipos-depositos`).
- Consumes: Task 5 (CrudPage/DataTable), API endpoints de P2.

- [x] **Step 1: Tipos de Partes**

Campos: codigo, nombre, descripcion, orden, activo, requiere_stock. Form con Input + Switch.

- [x] **Step 2: Grupos de Partes**

Campos: codigo, nombre, descripcion, color (Input type=color con preview), activo.

- [x] **Step 3: Tipos de Depósitos**

Campos: codigo, nombre, descripcion, orden, es_sistema (solo lectura si true), activo.

- [x] **Step 4: Verificar build + tests**

Run: `npm run build && npm run test`
Expected: OK.

- [x] **Step 5: Commit**

```bash
git add frontend/app/'(dashboard)'/configuracion
git commit -m "feat: add tipos de partes, grupos and depositos modules"
```

---

### Task 7: Módulos Validaciones, Entidades y Almacenes

**Files:**
- Create: `frontend/app/(dashboard)/configuracion/depositos-validaciones/page.tsx` + form + actions
- Create: `frontend/app/(dashboard)/configuracion/entidades/page.tsx` + form + actions
- Create: `frontend/app/(dashboard)/configuracion/almacenes/page.tsx` + form + actions

**Interfaces:**
- Produces: 3 páginas CRUD (rutas: `catalogos.validaciones_depositos` → `/configuracion/depositos-validaciones`, `catalogos.entidades` → `/configuracion/entidades`, `catalogos.almacenes` → `/configuracion/almacenes`).
- Consumes: Task 5, API endpoints de P2.

- [x] **Step 1: Validaciones de movimientos**

Form: selects origen/destino (cargados de `/api/v1/tipos-depositos`), activo, observaciones. Validación origen≠destino.

- [x] **Step 2: Entidades (clientes y proveedores)**

Form: razon_social, tipo (select PROVEEDOR/CLIENTE/AMBOS), identificacion_tributaria, contacto_email, contacto_telefono, direccion.

- [x] **Step 3: Almacenes**

Form: codigo, nombre, es_deposito_venta, es_deposito_produccion, activo (switches).

- [x] **Step 4: Verificar build + tests**

Run: `npm run build && npm run test`
Expected: OK.

- [x] **Step 5: Commit**

```bash
git add frontend/app/'(dashboard)'/configuracion
git commit -m "feat: add validaciones, entidades and almacenes modules"
```

---

### Task 8: Módulo Configuración General (configuracion_general + KV)

**Files:**
- Create: `frontend/app/(dashboard)/configuracion/general/page.tsx` + `general-form.tsx` + `actions.ts`

**Interfaces:**
- Produces: página `/configuracion/general` (ruta `catalogos.configuracion`): formulario de configuracion_general (decimal_places, rounding_mode, thousand_separator, decimal_separator, date_format, time_format) + sección KV (tabla clave/valor con upsert).
- Consumes: Task 1, API `/api/v1/configuracion` (P2).

- [x] **Step 1: Crear el form de configuración general**

`general-form.tsx` (Client): campos del schema de la API (P2 Task 7), guardar con PATCH.

- [x] **Step 2: Crear la sección KV**

Tabla con GET `/api/v1/configuracion/kv` + dialog para PUT `/api/v1/configuracion/kv/:clave`.

- [x] **Step 3: Verificar build + tests**

Run: `npm run build && npm run test`
Expected: OK.

- [x] **Step 4: Commit**

```bash
git add frontend/app/'(dashboard)'/configuracion/general
git commit -m "feat: add configuracion general module"
```

---

### Task 9: Deploy frontend + verificación E2E

**Files:**
- Modify: `frontend/.env.local` (ya existe), `frontend/vercel.json`

**Interfaces:**
- Produces: deploy de `mrp-frontend` a Vercel; verificación end-to-end: login → sidebar con menú → módulos maestros → API.
- Consumes: Tasks 1-8.

- [x] **Step 1: Configurar env en Vercel**

Run: `vercel env add NEXT_PUBLIC_API_URL production --cwd frontend` con `https://api.mimrp.com.ar`; `vercel env add NEXT_PUBLIC_SUPABASE_URL production` y `NEXT_PUBLIC_SUPABASE_ANON_KEY` (reusar valores de `.env.local`).

- [x] **Step 2: Deploy**

Run: `vercel deploy --prod --cwd frontend --yes`
Expected: URL de producción + alias `mimrp.com.ar` (cuando DNS propague).

- [x] **Step 3: Verificación E2E con browser**

Abrir `https://mimrp.com.ar` (o la URL de preview):
1. Login con `martin@unik.ar` / `Temporal123!` → redirige a `/`
2. Sidebar muestra las secciones del menú según ACL de Martin (Super Administrador)
3. Navegar a `/configuracion/unidades` → tabla con 29+ unidades
4. Crear unidad → aparece en la tabla
5. Logout funciona

- [x] **Step 4: Commit**

```bash
git add frontend/vercel.json frontend/.env.local.example
git commit -m "feat: deploy frontend to vercel"
```

---

### Task 10: Documentación de cierre

**Files:**
- Modify: `docs/migracion-nextjs.md`

- [x] **Step 1: Actualizar el documento**

Marcar Fase 3 (parcial: layout + módulos maestros) y agregar estado P4.

- [x] **Step 2: Verificar estado final**

Run: `npm run build && npm run test`
Expected: OK.

- [x] **Step 3: Commit**

```bash
git add docs/migracion-nextjs.md
git commit -m "docs: mark P4 frontend phase complete"
```

---

## Self-Review

**1. Spec coverage (docs/migracion-nextjs.md):**
- §7 (auth): login ya funcional (P1), layout usa getSession + proxy.
- §5.1 (shadcn/ui): componentes usados en todas las páginas.
- Menú dinámico por ACL: Task 3 (endpoint) + Task 2 (sidebar).
- Módulos maestros del menú: Tasks 5-8 (8 de los 33 items del menú).
- Dashboard: Task 4.

**2. Placeholder scan:** los valores reales (ids de empresa, counts) se resuelven en runtime. No hay TBD/TODO. El KPI de stock crítico se simplifica (cuenta de variantes con stock_actual <= punto_pedido vía filtro de la API — se implementa en Task 4 con el endpoint existente o se agrega query param; documentar en el commit).

**3. Type consistency:** `SessionInfo` consistente entre Task 1, 2, 4. `MenuItem` (Task 3) consumido por Sidebar (Task 2). `apiFetch<T>` consistente. Rutas Next.js consistentes con `route` de `menu_items`.

**Gaps detectados y resueltos:**
- Task 3: `menu_items` se consulta con `in('id', allowedIds)` — RLS filtra por company; el ACL se filtra por company + subject_type='role'. Default deny si no hay ACL.
- Task 4: stock crítico requiere filtro `stock_actual<=punto_pedido` — PostgREST soporta `lte` en la URL (`/api/v1/variantes?punto_pedido=gt.0` no es suficiente); se implementa un helper en frontend que filtra client-side con la primera página o se agrega endpoint en P5. En P4 el KPI usa total de variantes (documentado).
- Task 9: el deploy requiere DNS propagado para `mimrp.com.ar`; se verifica con la URL de preview de Vercel si el DNS aún no propaga.
