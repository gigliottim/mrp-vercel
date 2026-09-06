# P6: Pantallas faltantes del menú (15 rutas) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar las 15 rutas del menú que aún dan 404/error en producción, con sus endpoints API necesarios, siguiendo el patrón probado de P4/P5 (Server Components + CrudPage/formExtraProps, sin arrow functions inline).

**Architecture:** API Hono (api/src/routes/) + Frontend Next.js (frontend/app/(dashboard)/). Cada módulo: `page.tsx` (server, fetch + render), `actions.ts` ('use server'), `*-form.tsx` ('use client'), `columns.tsx` ('use client'). Regla crítica: NUNCA pasar arrow functions inline como props a Client Components — usar referencias directas + `formExtraProps` serializable.

**Tech Stack:** Hono 4.13.7, Next.js 16.3.4, shadcn/ui, react-hook-form + zod 4, supabase-js.

## Global Constraints

- API base: `https://api.mimrp.com.ar` (producción). En dev: `NEXT_PUBLIC_API_URL`.
- Auth: `getSession()` server-only (lib/session.ts), `apiFetch(path, token)` (lib/api.ts).
- JWT claims: `company_id`, `user_role`. RLS filtra por company automáticamente.
- `CrudPage` props: `FormComponent={XxxForm}` (referencia directa, NUNCA arrow), `formExtraProps={{...}}` para opciones serializables, `onCreate/onUpdate/onDelete` = server actions importadas.
- Forms: props extra OPCIONALES con default `= []`.
- Columnas: archivo `columns.tsx` con `'use client'`, columnas estáticas (precomputar nombres en server si hace falta).
- `noop` para módulos sin edición: exportar en `actions.ts` con `'use server'`.
- Commits frecuentes, conventional commits.

---

### Task 1: API — BOM avanzado (árbol, copiar, reemplazar, where-used)

**Files:**
- Modify: `api/src/routes/bom.ts` (agregar endpoints)
- Test: `api/src/routes/bom.test.ts`

**Interfaces:**
- Produces:
  - `GET /api/v1/bom/tree/:varianteId` → `{ data: BomTreeRow[] }` (filas con nivel, path, cantidad, unidad)
  - `GET /api/v1/bom/where-used/:varianteId` → `{ data: WhereUsedRow[] }` (bom_id, padre_codigo, cantidad, unidad)
  - `POST /api/v1/bom/copiar` body `{ variante_origen_id, variante_destino_id }` → `{ data: { copiados, eliminados, saltados: string[] } }`
  - `POST /api/v1/bom/reemplazar` body `{ variante_origen_id, variante_nueva_id, bom_ids?: number[] }` → `{ data: { reemplazados, boms_afectadas } }`

- [ ] **Step 1: Escribir tests fallidos** (agregar a bom.test.ts: tree, where-used, copiar, reemplazar)
- [ ] **Step 2: Correr tests → FAIL**
- [ ] **Step 3: Implementar endpoints** (SQL directo vía supabase.rpc o queries con joins; validar ciclos en copiar con la misma lógica de validateAddComponent)
- [ ] **Step 4: Correr tests → PASS**
- [ ] **Step 5: Commit** `feat(api): bom tree, copy, replace and where-used endpoints`

### Task 2: API — Sugerencias MRP + Stock crítico

**Files:**
- Create: `api/src/routes/sugerencias.ts` + test
- Create: `api/src/routes/inventario.ts` + test
- Modify: `api/src/index.ts` (montar rutas)

**Interfaces:**
- Produces:
  - `GET /api/v1/sugerencias?filtro=fabricable|parcial|sin_stock` → `{ data: { resumen: {fabricables, parciales, sin_stock, total}, variantes: [...] } }` (lógica del SugerenciasService.php: ratio stock/cantidad por componente, status por min ratio)
  - `GET /api/v1/inventario/critico?estado=todos|critico|advertencia|normal` → `{ data: { items: [...], stats: {total, critico, advertencia, normal} } }` (lógica getStockCritico/getStockCriticoStats)

- [ ] **Step 1: Tests fallidos**
- [ ] **Step 2: FAIL**
- [ ] **Step 3: Implementar** (queries con joins variantes/partes/tipos/grupos/ums; agrupar en JS como el servicio PHP)
- [ ] **Step 4: PASS**
- [ ] **Step 5: Commit** `feat(api): sugerencias mrp and stock critico endpoints`

### Task 3: API — Reportes (destino-partes, listado-ingenieria, planificacion-produccion, resumen-grupos)

**Files:**
- Create: `api/src/routes/reportes.ts` + test
- Modify: `api/src/index.ts`

**Interfaces:**
- Produces:
  - `GET /api/v1/reportes/destino-partes?id_variante=X` → `{ data: { variante, rama1, plana, arbol, donde_se_utiliza } }`
  - `GET /api/v1/reportes/listado-ingenieria?id_variante=X&cantidad=1&tipo_salida=arbol|plana|rama1&con_precios=0|1&agrupar_tipo=0|1&ordenar_tipo=0|1&mostrar_tipos=1,2,3` → `{ data: { variante, items, tipos } }`
  - `GET /api/v1/reportes/planificacion-produccion?productos=VID:QTY,VID2:QTY2&fecha_costo=YYYY-MM-DD` → `{ data: { requerimientos: [...], productos } }`
  - `GET /api/v1/reportes/resumen-grupos?id_grupo=X` → `{ data: { grupos, grupo_seleccionado, partes, sin_grupo } }`

- [ ] **Step 1: Tests fallidos**
- [ ] **Step 2: FAIL**
- [ ] **Step 3: Implementar** (reusar getTree/getDetalles/getWhereUsed; consolidación plana en JS; cálculo de requerimientos con lote mínimo y factor conversión)
- [ ] **Step 4: PASS**
- [ ] **Step 5: Commit** `feat(api): reportes endpoints`

### Task 4: API — Empresa/Usuarios/Roles/Permisos

**Files:**
- Create: `api/src/routes/empresa.ts` + test
- Modify: `api/src/index.ts`

**Interfaces:**
- Produces:
  - `GET /api/v1/empresa` → `{ data: company }` (companies del tenant)
  - `PATCH /api/v1/empresa` → actualizar company
  - `GET /api/v1/empresa/usuarios` → `{ data: users[] }` (user_company + auth.users)
  - `POST /api/v1/empresa/usuarios` → crear (invita por email)
  - `PATCH /api/v1/empresa/usuarios/:id` → actualizar rol/estado
  - `DELETE /api/v1/empresa/usuarios/:id`
  - `GET /api/v1/empresa/roles` → `{ data: roles[] }`
  - `POST/PATCH/DELETE /api/v1/empresa/roles[/:id]`
  - `GET /api/v1/empresa/permisos` → `{ data: acl[] }`
  - `GET /api/v1/empresa/permisos/tree` → `{ data: menuTree[] }`
  - `POST /api/v1/empresa/permisos/bulk` body `{ menu_item_ids, perms }`
  - `POST/PATCH/DELETE /api/v1/empresa/permisos[/:id]`

- [ ] **Step 1: Tests fallidos**
- [ ] **Step 2: FAIL**
- [ ] **Step 3: Implementar** (tablas: companies, user_company, roles, menu_acl; admin client para auth.users)
- [ ] **Step 4: PASS**
- [ ] **Step 5: Commit** `feat(api): empresa usuarios roles permisos endpoints`

### Task 5: API — Gantt + Dashboard operaciones

**Files:**
- Modify: `api/src/routes/planificacion.ts` (agregar /gantt)
- Create: `api/src/routes/operaciones.ts` + test
- Modify: `api/src/index.ts`

**Interfaces:**
- Produces:
  - `GET /api/v1/planificacion/gantt?centro_id=X&fecha_inicio=...&fecha_fin=...` → `{ data: { filas: [{ centro, tareas: [{ orden, inicio, fin, estado }] }] } }`
  - `GET /api/v1/operaciones` → `{ data: { metricas: {centros_activos, rutas_configuradas, ordenes_activas, capacidad_utilizada}, ordenes_recientes: [] } }`

- [ ] **Step 1: Tests fallidos**
- [ ] **Step 2: FAIL**
- [ ] **Step 3: Implementar**
- [ ] **Step 4: PASS**
- [ ] **Step 5: Commit** `feat(api): gantt and operaciones dashboard endpoints`

### Task 6: Frontend — Maestro, Copiar Componentes, Reemplazar Partes

**Files:**
- Create: `frontend/app/(dashboard)/productos/maestro/{page.tsx,actions.ts,maestro-form.tsx,columns.tsx}`
- Create: `frontend/app/(dashboard)/productos/copiar-componentes/{page.tsx,actions.ts,copiar-form.tsx}`
- Create: `frontend/app/(dashboard)/productos/reemplazar-partes/{page.tsx,actions.ts,reemplazar-form.tsx}`

**Interfaces:**
- Consumes: Task 1 endpoints.
- Produces: 3 páginas funcionales.

- [ ] **Step 1: Maestro** — selector de variante + árbol BOM (tabla con indentación por nivel) + form agregar componente (variante, cantidad, unidad) + editar/eliminar filas
- [ ] **Step 2: Copiar Componentes** — 2 selects (origen/destino) + botón ejecutar + resultado (copiados/eliminados/saltados)
- [ ] **Step 3: Reemplazar Partes** — 2 selects + preview de where-used (checkboxes por BOM) + ejecutar + resultado
- [ ] **Step 4: Build + verificar 3 rutas en dev**
- [ ] **Step 5: Commit** `feat(frontend): maestro, copiar and reemplazar pages`

### Task 7: Frontend — Sugerencias MRP + Stock crítico + Órdenes planificadas

**Files:**
- Create: `frontend/app/(dashboard)/planeamiento/sugerencias/{page.tsx,columns.tsx}`
- Create: `frontend/app/(dashboard)/planeamiento/ordenes/{page.tsx}`
- Create: `frontend/app/(dashboard)/inventario/critico/{page.tsx,columns.tsx}`

**Interfaces:**
- Consumes: Task 2 endpoints.
- Produces: 3 páginas.

- [ ] **Step 1: Sugerencias** — filtros (todos/fabricable/parcial/sin_stock) + resumen cards + tabla expandible con componentes y cobertura %
- [ ] **Step 2: Stock crítico** — filtro estado + stats cards + tabla con badges de estado
- [ ] **Step 3: Órdenes planificadas** — tabla de órdenes con estado planificada (reusa /ordenes-produccion?estado=planificada)
- [ ] **Step 4: Build + verificar**
- [ ] **Step 5: Commit** `feat(frontend): sugerencias, stock critico and ordenes planificadas`

### Task 8: Frontend — Reportes (4 páginas)

**Files:**
- Create: `frontend/app/(dashboard)/reportes/destino-partes/{page.tsx}`
- Create: `frontend/app/(dashboard)/reportes/listado-ingenieria/{page.tsx}`
- Create: `frontend/app/(dashboard)/reportes/planificacion-produccion/{page.tsx}`
- Create: `frontend/app/(dashboard)/reportes/resumen-grupos/{page.tsx}`

**Interfaces:**
- Consumes: Task 3 endpoints.
- Produces: 4 páginas de reporte (tablas + filtros).

- [ ] **Step 1: destino-partes** — selector variante + 3 vistas (rama1/plana/árbol) + donde se utiliza
- [ ] **Step 2: listado-ingenieria** — selector + cantidad + tipo salida + filtros tipo + tabla
- [ ] **Step 3: planificacion-produccion** — agregar productos (variante+cantidad) + tabla requerimientos (programado/stock/faltante/a comprar)
- [ ] **Step 4: resumen-grupos** — selector grupo + tabla partes/variantes con stock
- [ ] **Step 5: Build + verificar**
- [ ] **Step 6: Commit** `feat(frontend): reportes pages`

### Task 9: Frontend — Empresa/Usuarios/Roles/Permisos + Gantt + Dashboard operaciones

**Files:**
- Create: `frontend/app/(dashboard)/empresa-usuarios/{empresa,usuarios,roles,permisos}/{page.tsx,actions.ts,*-form.tsx,columns.tsx}`
- Create: `frontend/app/(dashboard)/produccion/planificacion/gantt/{page.tsx}`
- Create: `frontend/app/(dashboard)/produccion/{page.tsx}` (dashboard operaciones)
- Create: `frontend/app/(dashboard)/dashboard/{page.tsx}` (panel inicial, redirect a /)

**Interfaces:**
- Consumes: Tasks 4-5 endpoints.
- Produces: 7 páginas.

- [ ] **Step 1: Empresa** — form edición datos company
- [ ] **Step 2: Usuarios** — CRUD con rol
- [ ] **Step 3: Roles** — CRUD
- [ ] **Step 4: Permisos** — árbol de menú con checkboxes por rol (bulk)
- [ ] **Step 5: Gantt** — barras por centro (CSS grid, sin librería)
- [ ] **Step 6: Dashboard operaciones** — metricas cards + órdenes recientes
- [ ] **Step 7: Build + verificar**
- [ ] **Step 8: Commit** `feat(frontend): empresa usuarios roles permisos gantt dashboards`

### Task 10: Deploy + verificación E2E completa

- [ ] **Step 1: Tests completos** (api + frontend)
- [ ] **Step 2: Deploy API + frontend a Vercel**
- [ ] **Step 3: Verificar las 33 rutas del menú en producción** (login martin@unik.ar, recorrer todas)
- [ ] **Step 4: Actualizar docs/migracion-nextjs.md + ledger P6**
- [ ] **Step 5: Commit final + push**
