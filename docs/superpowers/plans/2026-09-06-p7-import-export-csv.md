# P7: Import/Export CSV (Partes-Variantes y Maestro BOM) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrar las funcionalidades de importación/exportación CSV del sistema original (Partes+Variantes y Maestro BOM) a la API Hono + frontend Next.js, con reportes de resultado por fila y validación de ciclos.

**Architecture:** API Hono (api/src/routes/) con endpoints de import (multipart) y export (CSV download). Frontend Next.js con páginas de import (file upload + reporte de resultados) y export (descarga). Port fiel de los servicios PHP: `PartesVariantesImportService`, `PartesVariantesImportRowMapper`, `PartesVariantesImportCsvParser`, `PartesVariantesImportTemplateService`, `MaestroImportExportService`, `PartesGeometryRecalculationService`.

**Tech Stack:** Hono 4.13.7, Next.js 16.3.4, supabase-js (PostgrestClient), zod 4.

## Global Constraints

- API base: `https://api.mimrp.com.ar`. Auth: `getSession()` + `apiFetch(path, token)`.
- NUNCA pasar funciones inline de Server Components a Client Components — usar referencias directas o client wrappers.
- `factor_conversion` NO existe en BD migrada — se ignora (default 1).
- RLS filtra por company automáticamente; escrituras a `menu_acl` vía service role (lección P6).
- CSV: soportar delimitador `,` y `;`; BOM UTF-8; encabezados en minúscula.
- Commits frecuentes, conventional commits.

---

### Task 1: API — Import/Export Partes y Variantes

**Files:**
- Create: `api/src/routes/import-export.ts` + test
- Modify: `api/src/index.ts`

**Interfaces:**
- Produces:
  - `GET /api/v1/import-export/partes/template` → CSV (headers: parte_codigo, parte_detalle, tipo_codigo, grupo_codigo, activo, um_compra_codigo, um_uso_codigo, factor_conversion, largo_alto, um_largo_alto_codigo, ancho, um_ancho_codigo, espesor_profundidad, um_espesor_codigo, superficie, um_superficie_codigo, volumen, um_volumen_codigo, variante_codigo, variante_detalle, variante_estado, lote_minimo, punto_pedido, peso, um_peso_codigo, ubicacion_cuerpo, ubicacion_pasillo, ubicacion_estante) + catálogos UM/tipos/grupos al final
  - `GET /api/v1/import-export/partes/export` → CSV con todas las partes+variantes
  - `POST /api/v1/import-export/partes/import` (multipart `archivo`) → `{ data: { total_rows, ok_rows, error_rows, created_parts, updated_parts, created_variants, updated_variants, rows: [{line, status, message}], fatal_error } }`

- [ ] **Step 1: Tests fallidos** (template 200 + csv, export 200 + csv, import con CSV válido creado en el test)
- [ ] **Step 2: FAIL**
- [ ] **Step 3: Implementar** (port RowMapper: parse decimal español, lookup maps por código; upsert parte por codigo, upsert variante por (id_parte, codigo_variante); reporte por fila)
- [ ] **Step 4: PASS**
- [ ] **Step 5: Commit** `feat(api): partes-variantes csv import/export`

### Task 2: API — Import/Export Maestro BOM

**Files:**
- Modify: `api/src/routes/import-export.ts` (agregar endpoints)
- Test: mismo archivo

**Interfaces:**
- Produces:
  - `GET /api/v1/import-export/maestro/template` → CSV (padre_parte_codigo, padre_variante_codigo, hijo_parte_codigo, hijo_variante_codigo, cantidad, unidad_codigo)
  - `GET /api/v1/import-export/maestro/export` → CSV de BOMs activas
  - `POST /api/v1/import-export/maestro/import` (multipart) → `{ data: { total_rows, ok_rows, error_rows, skipped_rows, created_links, rows: [{line, status, message}], fatal_error } }`

- [ ] **Step 1: Tests fallidos**
- [ ] **Step 2: FAIL**
- [ ] **Step 3: Implementar** (port MaestroImportExportService: varianteMap [parte][variante], unidadMap, grafo en memoria + BFS ciclo, getActiveByVariante, createHeader si falta, isDuplicate → skip)
- [ ] **Step 4: PASS**
- [ ] **Step 5: Commit** `feat(api): maestro bom csv import/export`

### Task 3: API — Recálculo de geometría + borrado seguro de variante

**Files:**
- Modify: `api/src/routes/import-export.ts`
- Modify: `api/src/routes/variantes.ts`

**Interfaces:**
- Produces:
  - `POST /api/v1/import-export/geometria/recalcular` body `{ solo_dimensiones_completas?: boolean }` → `{ data: { actualizados, errores, decimal_places } }` (port PartesGeometryRecalculationService: superficie = largo_alto × ancho, volumen = largo_alto × ancho × espesor, con unidades convertidas a m²/cm³)
  - `GET /api/v1/variantes/:id/verificar-borrado` → `{ data: { puede_borrar: boolean, errores: string[] } }` (port VarianteDeletionService: compras, movimientos, es hijo BOM, es padre BOM)

- [ ] **Step 1: Tests fallidos**
- [ ] **Step 2: FAIL**
- [ ] **Step 3: Implementar**
- [ ] **Step 4: PASS**
- [ ] **Step 5: Commit** `feat(api): geometry recalculation and safe variant deletion check`

### Task 4: Frontend — Import Partes y Variantes

**Files:**
- Create: `frontend/app/(dashboard)/configuracion/importar-partes/{page.tsx,import-client.tsx}`

**Interfaces:**
- Consumes: Task 1 endpoints.
- Produces: página con descarga de plantilla, upload CSV, reporte de resultados por fila (tabla line/status/message), resumen (creadas/actualizadas).

- [ ] **Step 1: page.tsx** (server: fetch template link, render client wrapper)
- [ ] **Step 2: import-client.tsx** (file input + fetch multipart a API + tabla reporte)
- [ ] **Step 3: Build + verificar en dev**
- [ ] **Step 4: Commit** `feat(frontend): partes-variantes import page`

### Task 5: Frontend — Import/Export Maestro BOM

**Files:**
- Create: `frontend/app/(dashboard)/productos/importar-maestro/{page.tsx,import-client.tsx}`

**Interfaces:**
- Consumes: Task 2 endpoints.
- Produces: página con descarga de plantilla/export, upload CSV, reporte por fila (creados/saltados/errores).

- [ ] **Step 1: page.tsx** + **Step 2: import-client.tsx**
- [ ] **Step 3: Build + verificar**
- [ ] **Step 4: Commit** `feat(frontend): maestro bom import/export page`

### Task 6: Deploy + verificación E2E

- [ ] **Step 1: Tests completos** (api + frontend)
- [ ] **Step 2: Deploy API + frontend**
- [ ] **Step 3: E2E**: export partes → import con archivo de 2 filas nuevas → verificar creadas → limpiar; export maestro → import 1 relación → verificar → limpiar
- [ ] **Step 4: Actualizar docs/migracion-nextjs.md + ledger**
- [ ] **Step 5: Commit final + push**
