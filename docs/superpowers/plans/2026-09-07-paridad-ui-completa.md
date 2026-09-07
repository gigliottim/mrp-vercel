# Paridad UI completa Next.js ↔ PHP — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Alcanzar paridad funcional completa con las ~44 vistas del PHP legacy en el frontend Next.js, manteniendo el diseño actual simple de shadcn/ui (sin embellecimiento hasta terminar el proyecto).

**Architecture:** Monorepo con API Hono (`api/`) y frontend Next.js 16 App Router (`frontend/`). Cada fase cierra un módulo completo (API endpoint si falta + página + actions). El patrón dominante es `CrudPage` + `DataTable` + server actions (`apiFetch` + `revalidatePath`); páginas complejas usan client components con estado propio. Todo con shadcn/ui existente (Base UI, `render` prop, no `asChild`).

**Tech Stack:** Next.js 16 (App Router, Server Components), React 19, shadcn/ui (Base UI 1.8), react-hook-form + zod, `@hookform/resolvers@^5.9.1` (compat zod 4 — NO usar 3.x), zod 4.5.4, sonner, Supabase (RLS multi-tenant), Hono 4.

## Global Constraints

- Estética: shadcn/ui base existente, CERO embellecimiento visual (decisión del usuario).
- Server actions: `'use server'` en `actions.ts` por carpeta de página; siempre `getSession()` → `apiFetch(...)` → `revalidatePath(...)` → `return { ok?: boolean; error?: string }`.
- API devuelve `{error: {code, message}}`; los actions propagan `message` a toast.
- Escrituras nuevas en API: `requireRole('Super Administrador', 'Administrador')`; lecturas solo `requireAuth`.
- Rutas Hono: registrar rutas estáticas (`/clonar`, `/validar-candidatos`) ANTES que paramétricas (`/:id`) — Hono matchea en orden de registro (no prioriza estáticas).
- Forms: RHF + `zodResolver` desde `@hookform/resolvers/zod` (v5, compatible con zod 4).
- Selects Base UI: `value` como string, `onValueChange` con `Number(v)`; no aceptan `SelectItem value=""`.
- Refresh patterns: components con server actions usan `router.refresh()`; NUNCA `window.location.reload()` (reemplazar los 3 existentes en roles-client, calcular-automatico, editor-client).
- Tests API: vitest contra Supabase real (`cd api && npx vitest run src/routes/<archivo>.test.ts`); tests auto-limpios (crear con timestamp único + delete al final). Suite completa: `npx vitest run` (115+ tests, ~110s). Verificación frontend: `npx tsc --noEmit && npx next build`.
- Fuente de verdad del comportamiento PHP: `views/pages/**` + `app/controllers/**` del mismo repo.
- No tocar: auth/registro/agent (ya paritarios), reportes export (ya paritario), configuración CRUD ×8 (ya paritario).

---

## FASE 0 — Limpieza de dead code (30 min)

### Task 0.1: Eliminar actions y stubs muertos

**Files:**
- Modify: `api` no — solo frontend
- Modify: `frontend/app/(dashboard)/productos/bom/actions.ts` (eliminar `reemplazarDetalles` y `noop`), `frontend/app/(dashboard)/produccion/ordenes/actions.ts` (eliminar `eliminar` muerto), `frontend/app/(dashboard)/configuracion/general/actions.ts` (eliminar `upsertClave` muerto)

**Interfaces:** `CrudPage` exige `onUpdate` — si se quita `noop` de compras/planificacion/rutas, esas 3 páginas recibirán edit real en Fase 3 (Task 3.4). NO eliminar los `noop` de esas 3 todavía.

- [ ] **Step 1:** `grep -rn "reemplazarDetalles\|upsertClave" frontend/app/` → verificar solo acciones.ts + imports en page (bom: page no importa `reemplazarDetalles`; general: page importa `upsertClave` → quitar import de `configuracion/general/page.tsx:6`).
- [ ] **Step 2:** Eliminar `reemplazarDetalles` de `productos/bom/actions.ts`, `eliminar` de `produccion/ordenes/actions.ts`, `upsertClave` de `configuracion/general/actions.ts` + su import. NO tocar `noop` de compras/planificacion/rutas (se usan como onUpdate y serán reemplazados en Fase 3).
- [ ] **Step 3:** `cd frontend && npx tsc --noEmit` → PASS. Commit: `chore: eliminar dead actions (reemplazarDetalles, eliminar ordenes, upsertClave)`.

---

## FASE 1 — Dashboard real (paridad con pages/dashboard.php + produccion/index.php)

### Task 1.1: Panel con métricas de operaciones

**Files:**
- Modify: `frontend/app/(dashboard)/panel/page.tsx` (reemplazo completo)
- Create: `frontend/app/(dashboard)/panel/acciones-rapidas.tsx` (client, grid de links)
- Create: `frontend/app/(dashboard)/panel/alertas-pendientes.tsx` (server component, secciones)

**Interfaces:**
- Consumes: `GET /api/v1/operaciones` → `{data: {metricas: {centros_activos, rutas_configuradas, ordenes_activas, capacidad_utilizada}, ordenes_recientes: [{id, numero_orden, estado, cantidad_planificada, cantidad_producida, prioridad, variante_id, variantes: {codigo_variante, detalle}}]}}`; `GET /api/v1/inventario/critico?estado=crítico` → `{data: [...], pagination}`; `GET /api/v1/sugerencias?estado=sin_stock`.
- Produces: página `/panel` con KPI cards ×4 (partes, variantes, centros activos, órdenes activas), sección "Órdenes activas" (tabla con link a detalle), sección "Alertas y pendientes" (stock crítico count + sugerencias sin stock count con links), Acciones rápidas (4 links: Nueva orden, Registrar movimiento, Sugerencias MRP, Maestro).

- [ ] **Step 1:** Reescribir `panel/page.tsx`: mantener los 2 KPI actuales (partes, variantes) + agregar fetch paralelo a `/api/v1/operaciones` e `/inventario/critico?perPage=1` (solo count) y `/sugerencias?estado=sin_stock&perPage=1`. KPI cards ×4: Total Partes, Total Variantes, Órdenes activas, Stock crítico. Debajo: Card "Órdenes activas" con tabla de `ordenes_recientes` (número, producto, cantidad, estado Badge, link a `/produccion/ordenes`) y Card "Acciones rápidas" (links). Estado vacío: texto simple.
- [ ] **Step 2:** tsc + build + verificación visual. Commit: `feat(frontend): panel con métricas de operaciones y alertas`.

---

## FASE 2 — Detalle de orden + timeline (paridad con ordenes/show.php)

### Task 2.1: API — cambiar estado ya existe; agregar PATCH de orden si falta

**Files:**
- Verify: `api/src/routes/ordenes-produccion.ts` — ya tiene `GET /:id`, `PATCH /:id`, `POST /:id/estado`. Sin cambios API.
- Create: `frontend/app/(dashboard)/produccion/ordenes/[id]/page.tsx` (server)
- Create: `frontend/app/(dashboard)/produccion/ordenes/[id]/orden-detalle.tsx` (client: dropdown de acciones según estado + timeline)
- Modify: `frontend/app/(dashboard)/produccion/ordenes/actions.ts` (agregar `cambiarEstadoOrden`)
- Create: `frontend/app/(dashboard)/produccion/ordenes/[id]/page` usa `GET /api/v1/ordenes-produccion/{id}` → datos orden + variantes join.

**Interfaces:**
- Consumes: `GET /api/v1/ordenes-produccion/:id` (ya existe), `POST /api/v1/ordenes-produccion/:id/estado` (ya existe, body `{estado}`).
- Produces: `cambiarEstado(id: number, estado: string): Promise<ActionResult>` — reutilizable por `ordenes-table.tsx` (ya tiene su propio cambiarEstado local: unificar en el de actions.ts).

- [ ] **Step 1:** Agregar a `produccion/ordenes/actions.ts`:
```ts
export async function cambiarEstado(id: number, estado: string): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`/api/v1/ordenes-produccion/${id}/estado`, session.accessToken, {
      method: 'POST',
      body: JSON.stringify({ estado }),
    })
    revalidatePath(`/produccion/ordenes/${id}`)
    revalidatePath('/produccion/ordenes')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}
```
- [ ] **Step 2:** Página detalle server component: fetch orden → Card con badges estado/prioridad + dl grid (producto, cantidad planificada/producida/pendiente, fechas, prioridad, avance con progress bar div) + `OrdenDetalle` client con DropdownMenu de transiciones válidas según estado (mapa TRANSICIONES ya existe en ordenes-table.tsx — reutilizar) + Timeline (estático: estado inicial `borrador` @created_at + estado actual @updated_at, con iconos lucide por estado — paridad con `_orden_timeline.php`) + botón editar → `/produccion/ordenes/{id}/edit` (crear página simple reusando nueva-orden.tsx en modo edición si aplica; si PATCH es parcial, solo prioridad/fechas/cantidad).
- [ ] **Step 3:** tsc + build. Commit: `feat(frontend): detalle de orden con transiciones y timeline`.

---

## FASE 3 — Ediciones faltantes (matar los 3 noop)

### Task 3.1: Edit de compras

**Files:**
- Verify: `api/src/routes/compras.ts` — `PATCH` existe? verificar; si no, agregar `compras.patch('/:id', requireRole(...), ...)` con zod parcial (fecha, nro_comprobante, importe).
- Modify: `frontend/app/(dashboard)/compras/actions.ts` + `columns.tsx` (botón Editar) + `compras-form.tsx` (modo edición).
- [ ] **Step 1:** Verificar/crear PATCH en API. **Step 2:** Reemplazar `noop` por `actualizar` en actions, editar `columns.tsx` para pasar `onEdit`, dialog edit de CrudPage (ya soportado por CrudPage — pasa `initial=row`). **Step 3:** vitest si hay API nueva; tsc. Commit.

### Task 3.2: Edit de planificación

**Files:** igual patrón sobre `produccion/planificacion/` (API `planificacion.ts` — verificar PATCH `/:id`; añadir si falta con zod parcial). Reemplazar `noop` por `actualizar`. Commit.

### Task 3.3: Edit de rutas

**Files:** igual patrón sobre `produccion/rutas/` (API `rutas-produccion.ts` ya tiene `PATCH /:id` en L69). Reemplazar `noop` por `actualizar` (edit de descripción/observaciones de la ruta cabecera). Commit.

### Task 3.4: Editor de ruta — reordenar con ▲▼ (paridad rutaEditor del PHP)

**Files:**
- Verify: `api/src/routes/rutas-produccion.ts` — verificar endpoint de reorden (si no hay, agregar `POST /bom/:bomId/operaciones/reordenar` con body `{ids: number[]}` actualizando `secuencia` en transacción SQL).
- Modify: `frontend/app/(dashboard)/produccion/rutas/[id]/editor/editor-client.tsx` — agregar botones ▲▼ por fila que llaman action `reordenarOperaciones(bomId, idsOrdenados)` (mover el op con los vecinos, actualizar estado local + server).
- [ ] **Step 1:** API: si falta endpoint, agregar `POST /bom/:bomId/operaciones/reordenar` con `z.object({ ids: z.array(z.number().int().positive()) })`, loop UPDATE `secuencia` por id (transacción RPC si existe `verificar_solapamiento`-style; si no, updates secuenciales). Test: crear ruta test → reordenar → verificar secuencia → cleanup.
- [ ] **Step 2:** Frontend: botones ▲▼ que computan el nuevo orden de la lista local y llaman al action `reordenarOperaciones(bomId, ids)` → revalidate. Commit: `feat: reordenar operaciones de ruta (paridad PHP)`.

---

## FASE 4 — Producción: completar patrones PHP faltantes

### Task 4.1: Filtros en órdenes (search + estado + prioridad)

**Files:**
- Verify: `api/src/routes/ordenes-produccion.ts` GET `/` — agregar `?q=`, `?estado=`, `?prioridad=` si no existen.
- Modify: `frontend/app/(dashboard)/produccion/ordenes/page.tsx` (form GET con 3 filtros sobre searchParams) + `ordenes-table.tsx` (leer searchParams para render inicial).
- [ ] **Step 1:** API: extender GET con `q` (ilike en numero_orden), `estado`, `prioridad` (eq). Test: crear orden test → filtrar por estado → cleanup.
- [ ] **Step 2:** Frontend: `<form method="get">` con Input (q) + Select (estado) + Select (prioridad) → server lee searchParams y los pasa al API. Commit.

### Task 4.2: KPI cards en órdenes (paridad con KPI por estado del PHP)

**Files:**
- Modify: `frontend/app/(dashboard)/produccion/ordenes/page.tsx` — fetch counts por estado (`GET /api/v1/ordenes-produccion?estado=X&perPage=1` × estados, paralelo) y mostrar KpiCard row arriba de la tabla.
- [ ] **Step 1:** Implementar. Commit: `feat(frontend): KPI cards por estado en órdenes`.

### Task 4.3: Clonar ruta (paridad botón Clonar del PHP)

**Files:**
- Verify: API — agregar `POST /rutas-produccion/bom/:bomId/operaciones/clonar` (copia operaciones de un BOM a otro con `secuencia` recorrida) o `POST /rutas-produccion/clonar` con `{bom_origen_id, bom_destino_id}`.
- Modify: `frontend/app/(dashboard)/produccion/rutas/columns.tsx` + actions (`clonarRuta`).
- [ ] **Step 1:** API endpoint con test (origen con 3 ops → clonar → destino tiene 3 con mismas secuencia/duraciones → cleanup). **Step 2:** Botón "Clonar" en columns con dialog de selección destino (Select de BOMs) → action → toast + refresh. Commit.

### Task 4.4: Planificación — Gantt con filtros por centro (ya existe) + paridad del filtro PHP

**Files:**
- Modify: `frontend/app/(dashboard)/produccion/planificacion/page.tsx` — agregar Select de centro con auto-submit GET (paridad del PHP `filter select centro auto-submit`).
- [ ] **Step 1:** Implementar (form GET con select → searchParams). Commit.

---

## FASE 5 — Transacciones y compras

### Task 5.1: Modal de confirmación dinámico en movimientos (paridad del PHP)

**Files:**
- Modify: `frontend/app/(dashboard)/transacciones/movimientos-partes/movimientos-form.tsx` — reemplazar confirm nativo por AlertDialog de confirmación antes de `registrarMovimientoPartes` (patrón de crud-page.tsx delete).
- [ ] **Step 1:** Envolver submit del form en AlertDialog ("¿Registrar movimiento? …"). Commit.

### Task 5.2: Historial de compras en compras/create (paridad)

**Files:**
- Modify: `frontend/app/(dashboard)/compras/page.tsx` — Card de "Historial" ya existe (tabla compras). Paridad PHP: el PHP muestra historial en create.php. Ya cubierto; SKIP.
- [ ] **Step 1:** SKIP (verificado paritario).

### Task 5.3: Edición de compra (fue Task 3.1 — unificar)

Nota: Task 3.1 cubre este gap. SKIP duplicado.

---

## FASE 6 — Empresa y usuarios (patrones faltantes)

### Task 6.1: Modal de eliminar empresa con advertencia de DB (paridad PHP modalEliminarEmpresa)

**Files:**
- Verify: `api/src/routes/empresa.ts` — verificar DELETE de empresa existe; si no, agregar (service role, porque borra DB tenant vía RPC de provisioning si existe).
- Modify: `frontend/app/(dashboard)/empresa-usuarios/empresa/` — agregar tabla de empresas (si hay más de una para el usuario) con AlertDialog de eliminación con advertencia fuerte ("Se eliminará la base de datos de la empresa. Esta acción no se puede deshacer.").
- [ ] **Step 1:** Verificar/crear DELETE en API (con confirm de rol admin). **Step 2:** UI con AlertDialog. Commit.

### Task 6.2: Usuarios — convertir tabla custom a CrudPage con Dialog (paridad del form PHP)

**Files:**
- Modify: `frontend/app/(dashboard)/empresa-usuarios/usuarios/*` — reemplazar toggle inline por CrudPage + Dialog (crear/editar/eliminar), usando los actions existentes.
- [ ] **Step 1:** Refactor a CrudPage (columns: nombre/email/rol/último acceso/estado; form: nombre/email/password (solo crear)/rol select). Commit.

### Task 6.3: Roles — convertir a CrudPage (paridad)

**Files:** `empresa-usuarios/roles/*` — CrudPage con Dialog (crear/editar nombre/código/desc) + eliminar con AlertDialog. Reutilizar actions existentes (agregar `actualizarRol` si falta en API). Commit.

### Task 6.4: Permisos — árbol filtrable (paridad permisos-tree)

**Files:**
- Modify: `frontend/app/(dashboard)/empresa-usuarios/permisos/permisos-client.tsx` — agregar Input de búsqueda que filtra secciones/items del menú, y estructura de árbol expandible (details/summary simple con ChevronRight) por sección.
- [ ] **Step 1:** Implementar search + collapsible por sección. Commit.

---

## FASE 7 — Reportes y planeamiento (patrones faltantes)

### Task 7.1: Sugerencias — accordion con tabla de componentes y acciones (paridad)

**Files:**
- Verify: `GET /api/v1/sugerencias` — verificar que devuelve componentes por variante con stock; si falta el detalle, el PHP lo calcula client-side desde la estructura; verificar payload.
- Modify: `frontend/app/(dashboard)/planeamiento/sugerencias/page.tsx` — reemplazar `<details>` nativo por estructura con botones de planificación (crear orden por variante con cantidad sugerida) si la API lo permite; si la creación de orden desde sugerencia no existe en API, dejar solo lectura (YAGNI) y documentar.
- [ ] **Step 1:** Verificar payload API. **Step 2:** UI con estado vacío + chips de filtro existentes + coverage table con badges (ya existe — agregar solo acciones si API disponible). Commit.

### Task 7.2: Panel planeamiento/ordenes — agregar acciones de liberar (paridad parcial PHP)

**Files:**
- Modify: `frontend/app/(dashboard)/planeamiento/ordenes/page.tsx` — agregar botón "Liberar" por fila (POST estado) si `cambiarEstado` existe (reutilizar de Fase 2), gated por rol.
- [ ] **Step 1:** Implementar con `cambiarEstado` de `produccion/ordenes/actions.ts` (import cross-module OK) o mover a `actions` compartido. Commit.

### Task 7.3: Inventario crítico — nada pendiente (ya paritario). SKIP.

---

## FASE 8 — UI primitives de shadcn que el PHP usa y Next no

### Task 8.1: Reemplazar selects/checkboxes nativos por shadcn en reportes/permisos/roles

**Files:**
- Modify: `frontend/app/(dashboard)/reportes/resumen-grupos/page.tsx` (Select), `reportes/listado-ingenieria/page.tsx` (Checkbox para flags), `reportes/planificacion-produccion/page.tsx` (Select de variante), `empresa-usuarios/permisos/permisos-client.tsx` (Checkbox), `empresa-usuarios/roles/roles-client.tsx` (Input/Select), `produccion/planificacion/calcular-automatico.tsx` (Select + router.refresh en vez de window.location.reload).
- [ ] **Step 1:** Reemplazar selects/checkboxes nativos por `ui/select` y `ui/checkbox` (nota Base UI: Checkbox controlado con `checked`/`onCheckedChange`). Commit: `refactor(frontend): ui primitives shadcn en formularios nativos`.

### Task 8.2: Tabs shadcn en destino-partes (paridad de 3 tabs del PHP)

**Files:**
- Modify: `frontend/app/(dashboard)/reportes/destino-partes/page.tsx` — las 4 secciones ya son tabs PHP (Rama 1 / Plana / Árbol / Where-used); envolver en `ui/tabs` (componente existe, sin consumirse).
- [ ] **Step 1:** Implementar Tabs con 4 items. Commit.

### Task 8.3: Skeletons en páginas de listado

**Files:**
- Modify: CrudPage/DataTable para aceptar `loading` opcional... YAGNI: skip. Los server components ya bloquean el render hasta tener datos.
- [ ] **Step 1:** SKIP (decisión).

---

## FASE 8B — Registro wizard 3 pasos (paridad del PHP)

### Task 8B.1: Alinear registro con wizard de 3 pasos del PHP

**Files:**
- Verify: `frontend/app/registro/page.tsx` — ya tiene wizard 2 pasos (empresa → admin). El PHP tiene 3 (empresa → DB → admin). El 3er paso del PHP es confirmación de DB creada (provisioning). El Next ya muestra pantalla "done" tras el paso 2 — paridad funcional equivalente.
- [ ] **Step 1:** SKIP — verificado paritario (2 pasos + done screen ≡ 3 pasos PHP).

---

## FASE 9 — Verificación final integral

### Task 9.1: Suite completa + E2E manual checklist

- [ ] **Step 1:** `cd api && npx vitest run` (115+ PASS) + `cd frontend && npx tsc --noEmit && npx next build`.
- [ ] **Step 2:** Checklist manual por página (login admin): /panel métricas + alertas, órdenes filtros + detalle + timeline + transiciones, rutas edit/clonar, planificación filtro centro, movimientos AlertDialog, empresa/usuarios/roles/permisos CRUD + árbol filtrable, reportes con Tabs/Checkbox, sugerencias. Corregir lo que falle con commits individuales.

---

## Resumen de cobertura (PHP → Next)

| Módulo PHP | Páginas | Estado Next | Fase |
|---|---|---|---|
| Dashboard/panel | dashboard.php, produccion/index | stub básico | **1** |
| Ordenes (detalle/timeline/filtros) | ordenes/{show,create,edit,index} | lista+crear sin detalle | **2, 4** |
| Rutas (edit/clonar) | rutas/{index,editor} | CRUD+editor sin clonar ni edit cabecera | **3, 4** |
| Compras (edit) | compras/{index,create} | create sin edit | **3** |
| Planificación (edit + filtro centro) | planificacion/index | create sin edit ni filtro | **3, 4** |
| Movimientos (modal confirm) | movimientos-partes | form+tabla sin confirm modal | **5** |
| Empresa (delete modal) | empresa.php | edit single sin delete | **6** |
| Usuarios/Roles (dialog CRUD) | usuarios/roles | tablas custom sin dialog | **6** |
| Permisos (árbol filtrable) | permisos | matrix sin search/tree | **6** |
| Reportes (tabs/checkbox nativos) | destino-partes, listado, planificación | server-render con nativos | **8** |
| Sugerencias (accordion+acciones) | sugerencias | details nativo solo lectura | **7** |
| Panel/Órdenes planificadas | planeamiento/ordenes | solo lectura | **7** |
| Configuración ×8 | catálogos | ✅ paritario | — |
| Partes/Manager/Importar | partes/* | ✅ paritario (+variante default) | — |
| Maestro/BOM/herramientas | maestro, bom, copiar, reemplazar | ✅ paritario (esta branch) | — |
| Import/export ×2 | import.php, composicion/import | ✅ paritario | — |
| Auth/registro/agent | auth, agent_chat | ✅ paritario | — |
| Inventario crítico | critico.php | ✅ paritario | — |
| Menu modal global | unified_menu_modal | sidebar equivalente | — (SKIP) |
| Gantt | gantt.php | ✅ paritario (CSS custom) | — |

**Decisión SKIP documentada:** unified_menu_modal ≡ sidebar con Sheet móvil ya existente; Gantt ya paritario; wizard registro equivalente; compras historial ya incluido.