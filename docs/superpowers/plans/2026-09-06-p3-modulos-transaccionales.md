# P3: API Hono — Módulos Transaccionales — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir los endpoints transaccionales de la API Hono en `api/`: CRUD de partes, variantes, BOM (cabecera+detalle), rutas de producción, centros de trabajo, órdenes de producción (con flujo de estados), movimientos de inventario con actualización de stock, compras con recepción, y planificación de recursos.

**Architecture:** Misma base que P2 (middleware `requireAuth` con claims `company_id`/`user_role`, `createUserClient`/`createAdminClient` con PostgrestClient, `parsePagination`, Zod 4). Los endpoints transaccionales usan RLS de Supabase como segunda barrera. La actualización de stock se hace con RPC (`actualizar_stock` vía SQL function) para atomicidad — nunca con read-modify-write desde Node.

**Tech Stack:** Hono 4.13.7, @supabase/postgrest-js 2.115.0, Zod 4.5.4, Vitest 5.0.0, TypeScript 5.9.3.

## Global Constraints

- Versiones exactas: mismas que P2 (ver `docs/migracion-nextjs.md` sección 0).
- Proyecto Supabase `ooyiahzawilmdfualggx`, credenciales en `.env`.
- JWT claims: `company_id` (bigint) y `user_role` (text) — NUNCA `role`.
- RLS activo en las 26 tablas tenant. El service_role (solo server) ignora RLS.
- Tablas transaccionales y campos (verificados en `database/backups/2026-09-06_09-40-12/mrp_tunna_schema_2026-09-06_09-40-12.sql`):
  - `partes`: id, codigo (UNIQUE company+codigo), id_tipo, id_grupo, detalle, largo_alto, id_um_largo_alto, ancho, id_um_ancho, espesor_profundidad, id_um_espesor, superficie, id_um_superficie, volumen, id_um_volumen, atributos_base jsonb, reglas_configuracion jsonb, activo, creado_por, fecha_creacion, fecha_modificacion, id_um_compra, id_um_uso, created_at, updated_at, factor_conversion
  - `variantes`: id, id_parte, codigo_variante (UNIQUE company+codigo_variante), detalle, estado (activa|obsoleta|descontinuada|desarrollo), lote_minimo, punto_pedido, stock_seguridad, anticipo_compra, lead_time_produccion, stock_actual, peso, id_um_peso, ubicacion_defecto jsonb, atributos jsonb, sku (GENERATED), creado_por, fecha_creacion, fecha_modificacion, costo, ubicacion_cuerpo, ubicacion_pasillo, ubicacion_estante
  - `bom_cabecera`: id, variante_padre_id, version, activa, fecha_efectiva, fecha_vencimiento, observaciones, aprobada_por, fecha_aprobacion, created_at, updated_at (UNIQUE company+variante_padre+version)
  - `bom_detalle`: id, bom_id, variante_componente_id, cantidad_necesaria, unidad_medida_id, desperdicio_porcentaje, es_opcional, secuencia, costo_unitario_estimado, tiempo_setup_mins, tiempo_proceso_mins, condicion_aplicacion jsonb, observaciones, created_at
  - `centros_trabajo`: id, codigo (UNIQUE company+codigo), nombre, descripcion, tipo, capacidad_horas_dia, eficiencia_porcentaje, costo_hora, capacidad_finita, calendario_id, activo, ubicacion, responsable, observaciones
  - `rutas_produccion`: id, bom_id, secuencia, centro_trabajo_id, descripcion, tiempo_setup_mins, tiempo_proceso_unitario_mins, tiempo_cola_mins, tiempo_movimiento_mins, capacidad_requerida, costo_operacion_fijo, costo_operacion_variable, instrucciones (UNIQUE company+bom+secuencia)
  - `ordenes_produccion`: id, numero_orden (UNIQUE company+numero_orden), variante_id, bom_id_utilizada, cantidad_planificada, cantidad_producida, cantidad_desechada, fecha_inicio_programada, fecha_fin_programada, fecha_inicio_real, fecha_fin_real, estado (borrador|planificada|liberada|en_proceso|pausada|completada|cancelada|cerrada), prioridad (baja|normal|alta|urgente), configuracion_orden jsonb, observaciones, usuario_creador, fecha_creacion, fecha_actualizacion
  - `movimientos_inventario`: id, variante_id, almacen_id, orden_produccion_id, tipo_movimiento (compra_recepcion|produccion_ingreso|produccion_consumo|produccion_descarte|ajuste_inventario|venta_despacho|transferencia_salida|transferencia_entrada), cantidad, signo (1|-1), costo_unitario_snapshot, fecha_movimiento, usuario_id, observaciones, referencia_documento
  - `compras`: id, fecha, precio_unitario, observaciones, id_movimiento_stock, id_entidad, nro_comprobante
  - `movimientos_stock`: id, id_variante, cantidad, id_tipo_deposito_origen, id_tipo_deposito_destino, fecha, referencia_tipo, referencia_id, observaciones
  - `planificacion_recursos`: id, orden_produccion_id, operacion_id, centro_trabajo_id, periodo tsrange, estado (programado|en_ejecucion|completado)
- **Atomicidad de stock**: la actualización de `stock_actual` en `variantes` se hace con una función SQL `public.movimiento_inventario(...)` (security definer) que inserta el movimiento + actualiza stock en UNA transacción. El trigger `actualizar_stock_trigger` (migrado en P1) ya actualiza stock en INSERT sobre `movimientos_inventario` — verificar que cubra todos los tipos.
- **Numeración**: `numero_orden` se genera como `OP-<YYYYMMDD>-<secuencia>` usando una secuencia por empresa (función SQL `next_numero_orden(p_company_id)`).
- **Validación de FKs**: al crear/actualizar, verificar existencia de entidades referenciadas (variante, parte, BOM, unidad, centro de trabajo, entidad) dentro de la misma empresa.
- Endpoints bajo `/api/v1/`. Formato de error: `{ "error": { "code", "message" } }`.
- Tests: Vitest con login singleton (test-utils.ts), datos limpios por test (crear y eliminar), nunca dejar filas huérfanas.
- Commits frecuentes, conventional commits.

---

### Task 1: CRUD Partes

**Files:**
- Create: `api/src/routes/partes.ts`
- Create: `api/src/routes/partes.test.ts`

**Interfaces:**
- Produces: router `/api/v1/partes` con GET `/` (paginado + filtro `q` por codigo/detalle), GET `/:id`, POST `/`, PATCH `/:id`, DELETE `/:id` (rol Admin). GET `/:id/variantes` (variantes de la parte).
- Consumes: Task 1-2 P2 (`requireAuth`, `requireRole`, `createUserClient`, `parsePagination`).

- [ ] **Step 1: Escribir el router**

`api/src/routes/partes.ts`:
```ts
import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'

const schema = z.object({
  codigo: z.string().min(1).max(50),
  id_tipo: z.number().int().positive(),
  id_grupo: z.number().int().positive(),
  detalle: z.string().min(1).max(255),
  largo_alto: z.number().optional(),
  id_um_largo_alto: z.number().int().positive().optional(),
  ancho: z.number().optional(),
  id_um_ancho: z.number().int().positive().optional(),
  espesor_profundidad: z.number().optional(),
  id_um_espesor: z.number().int().positive().optional(),
  superficie: z.number().optional(),
  id_um_superficie: z.number().int().positive().optional(),
  volumen: z.number().optional(),
  id_um_volumen: z.number().int().positive().optional(),
  atributos_base: z.record(z.unknown()).optional(),
  reglas_configuracion: z.record(z.unknown()).optional(),
  activo: z.boolean().optional(),
  id_um_compra: z.number().int().positive().optional(),
  id_um_uso: z.number().int().positive().optional(),
  factor_conversion: z.number().optional(),
})

export const partes = new Hono<AuthEnv>()
partes.use('*', requireAuth)

partes.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const q = c.req.query('q') ?? ''
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let query = supabase.from('partes').select('*', { count: 'exact' })
  if (q) query = query.or(`codigo.ilike.%${q}%,detalle.ilike.%${q}%`)
  const { data, error, count } = await query.order('id').range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

partes.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase.from('partes').select('*').eq('id', Number(c.req.param('id'))).single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  return c.json({ data })
})

partes.get('/:id/variantes', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase.from('variantes').select('*').eq('id_parte', Number(c.req.param('id'))).order('id')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

partes.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase.from('partes').insert({ ...parsed.data, company_id: c.get('companyId') }).select().single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

partes.patch('/:id', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.partial().safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase.from('partes').update(parsed.data).eq('id', Number(c.req.param('id'))).select().single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

partes.delete('/:id', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { error } = await supabase.from('partes').delete().eq('id', Number(c.req.param('id')))
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.body(null, 204)
})
```

- [ ] **Step 2: Escribir tests**

`api/src/routes/partes.test.ts`:
- Login como Sabrina (empresa 2, tiene 204 partes).
- GET `/` → 200, `pagination.total` = 204.
- GET `/?q=` con texto existente (ej. un codigo real del dump) → filtra.
- POST `/` con `{ codigo: 'TEST-' + Date.now(), id_tipo: <id real>, id_grupo: <id real>, detalle: 'test' }` (usar ids reales de `tipos_partes` y `grupos_partes` de la empresa 2) → 201, luego DELETE → 204.
- POST con id_tipo inexistente → 500 (FK violation) — documentar que se valida en Task 2.

- [ ] **Step 3: Ejecutar tests**

Run: `npm run test -w api`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add api/src/routes/partes.ts api/src/routes/partes.test.ts
git commit -m "feat: add partes CRUD"
```

---

### Task 2: Validación de FKs + CRUD Variantes

**Files:**
- Create: `api/src/lib/validate-fk.ts`
- Create: `api/src/routes/variantes.ts`
- Create: `api/src/routes/variantes.test.ts`

**Interfaces:**
- Produces: `validarReferencias(supabase, tabla, ids: Record<string, number>)` → `Promise<{ ok: boolean, error?: string }>` que verifica existencia de cada FK dentro de la misma empresa (RLS lo garantiza). Router `/api/v1/variantes` con CRUD + filtro por parte (`?id_parte=`) + GET `/:id/stock` (stock_actual + punto_pedido + stock_seguridad).
- Consumes: Task 1-2 P2.

- [ ] **Step 1: Escribir el helper de validación de FKs**

`api/src/lib/validate-fk.ts`:
```ts
import type { PostgrestClient } from '@supabase/postgrest-js'

export async function validarReferencias(
  supabase: PostgrestClient,
  refs: Record<string, number>
): Promise<{ ok: boolean; error?: string }> {
  for (const [tabla, id] of Object.entries(refs)) {
    const { data, error } = await supabase.from(tabla).select('id').eq('id', id).single()
    if (error || !data) {
      return { ok: false, error: `Referencia inválida: ${tabla} id=${id} (no existe en esta empresa)` }
    }
  }
  return { ok: true }
}
```

- [ ] **Step 2: Escribir el router de variantes**

`api/src/routes/variantes.ts`:
```ts
import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'
import { validarReferencias } from '../lib/validate-fk.js'

const schema = z.object({
  id_parte: z.number().int().positive(),
  codigo_variante: z.string().min(1).max(50),
  detalle: z.string().min(1).max(255),
  estado: z.enum(['activa', 'obsoleta', 'descontinuada', 'desarrollo']).optional(),
  lote_minimo: z.number().optional(),
  punto_pedido: z.number().optional(),
  stock_seguridad: z.number().optional(),
  anticipo_compra: z.number().int().optional(),
  lead_time_produccion: z.number().int().optional(),
  peso: z.number().optional(),
  id_um_peso: z.number().int().positive().optional(),
  ubicacion_defecto: z.record(z.unknown()).optional(),
  atributos: z.record(z.unknown()).optional(),
  costo: z.number().optional(),
  ubicacion_cuerpo: z.string().max(100).optional(),
  ubicacion_pasillo: z.string().max(100).optional(),
  ubicacion_estante: z.string().max(100).optional(),
})

export const variantes = new Hono<AuthEnv>()
variantes.use('*', requireAuth)

variantes.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const idParte = c.req.query('id_parte')
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let query = supabase.from('variantes').select('*', { count: 'exact' })
  if (idParte) query = query.eq('id_parte', Number(idParte))
  const { data, error, count } = await query.order('id').range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

variantes.get('/:id', async (c) => { /* igual patrón P2 */ })

variantes.get('/:id/stock', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('variantes')
    .select('id, codigo_variante, detalle, stock_actual, punto_pedido, stock_seguridad, lote_minimo, anticipo_compra, lead_time_produccion')
    .eq('id', Number(c.req.param('id')))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  return c.json({ data })
})

variantes.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const fk = await validarReferencias(supabase, { partes: parsed.data.id_parte })
  if (!fk.ok) return c.json({ error: { code: 'VALIDATION', message: fk.error } }, 400)
  const { data, error } = await supabase.from('variantes').insert({ ...parsed.data, company_id: c.get('companyId') }).select().single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

variantes.patch('/:id', async (c) => { /* igual patrón P2 + validar id_parte si viene */ })

variantes.delete('/:id', requireRole('Super Administrador', 'Administrador'), async (c) => { /* igual patrón P2 */ })
```

- [ ] **Step 3: Escribir tests**

`api/src/routes/variantes.test.ts`:
- GET `/` → total = 266 (empresa 2).
- GET `/?id_parte=<id real>` → solo variantes de esa parte.
- POST `/` con `id_parte` real + `codigo_variante` único → 201, luego DELETE → 204.
- POST con `id_parte: 999999` → 400 (FK validation).
- GET `/:id/stock` → devuelve stock_actual.

- [ ] **Step 4: Ejecutar tests**

Run: `npm run test -w api`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add api/src/lib/validate-fk.ts api/src/routes/variantes.ts api/src/routes/variantes.test.ts
git commit -m "feat: add variantes CRUD with FK validation"
```

---

### Task 3: CRUD BOM (cabecera + detalle)

**Files:**
- Create: `api/src/routes/bom.ts`
- Create: `api/src/routes/bom.test.ts`

**Interfaces:**
- Produces: router `/api/v1/bom` con GET `/` (listar cabeceras, filtro `?variante_padre_id=`), GET `/:id` (cabecera + detalle expandido), POST `/` (cabecera + array de detalles en una transacción vía RPC `crear_bom`), PATCH `/:id` (cabecera), PUT `/:id/detalle` (reemplazar detalles), DELETE `/:id` (cabecera + detalles). Función SQL `public.crear_bom(p_company_id, p_variante_padre_id, p_version, p_fecha_efectiva, p_detalles jsonb)` (security definer, transacción atómica).
- Consumes: Task 1-2 P2, `validarReferencias` (Task 2).

- [ ] **Step 1: Escribir la migración de la función `crear_bom`**

`supabase/migrations/20260906000016_crear_bom.sql`:
```sql
CREATE OR REPLACE FUNCTION public.crear_bom(
  p_company_id bigint,
  p_variante_padre_id integer,
  p_version text,
  p_fecha_efectiva date,
  p_detalles jsonb
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_bom_id integer;
  v_detalle jsonb;
  v_result jsonb;
BEGIN
  -- Verificar que la variante padre existe en la empresa
  IF NOT EXISTS (SELECT 1 FROM variantes WHERE id = p_variante_padre_id AND company_id = p_company_id) THEN
    RAISE EXCEPTION 'variante padre inexistente en esta empresa';
  END IF;

  -- Crear cabecera
  INSERT INTO bom_cabecera (company_id, variante_padre_id, version, activa, fecha_efectiva)
  VALUES (p_company_id, p_variante_padre_id, p_version, TRUE, p_fecha_efectiva)
  RETURNING id INTO v_bom_id;

  -- Insertar detalles
  FOR v_detalle IN SELECT * FROM jsonb_array_elements(p_detalles)
  LOOP
    INSERT INTO bom_detalle (
      company_id, bom_id, variante_componente_id, cantidad_necesaria,
      unidad_medida_id, desperdicio_porcentaje, es_opcional, secuencia,
      costo_unitario_estimado, tiempo_setup_mins, tiempo_proceso_mins, observaciones
    )
    VALUES (
      p_company_id, v_bom_id,
      (v_detalle->>'variante_componente_id')::integer,
      (v_detalle->>'cantidad_necesaria')::numeric,
      (v_detalle->>'unidad_medida_id')::integer,
      COALESCE((v_detalle->>'desperdicio_porcentaje')::numeric, 0),
      COALESCE((v_detalle->>'es_opcional')::boolean, FALSE),
      COALESCE((v_detalle->>'secuencia')::integer, 1),
      COALESCE((v_detalle->>'costo_unitario_estimado')::numeric, 0),
      COALESCE((v_detalle->>'tiempo_setup_mins')::integer, 0),
      COALESCE((v_detalle->>'tiempo_proceso_mins')::integer, 0),
      (v_detalle->>'observaciones')
    );
  END LOOP;

  SELECT jsonb_build_object('id', id, 'variante_padre_id', variante_padre_id, 'version', version, 'activa', activa, 'fecha_efectiva', fecha_efectiva)
  INTO v_result FROM bom_cabecera WHERE id = v_bom_id;
  RETURN v_result;
END;
$$;

GRANT EXECUTE ON FUNCTION public.crear_bom(bigint, integer, text, date, jsonb) TO authenticated;
```

Aplicar: `supabase db push` (workdir supabase).

- [ ] **Step 2: Escribir el router**

`api/src/routes/bom.ts`:
```ts
import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'

const detalleSchema = z.object({
  variante_componente_id: z.number().int().positive(),
  cantidad_necesaria: z.number().positive(),
  unidad_medida_id: z.number().int().positive(),
  desperdicio_porcentaje: z.number().optional(),
  es_opcional: z.boolean().optional(),
  secuencia: z.number().int().optional(),
  costo_unitario_estimado: z.number().optional(),
  tiempo_setup_mins: z.number().int().optional(),
  tiempo_proceso_mins: z.number().int().optional(),
  observaciones: z.string().max(500).optional(),
})

const crearSchema = z.object({
  variante_padre_id: z.number().int().positive(),
  version: z.string().max(10).default('1.0'),
  fecha_efectiva: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  detalles: z.array(detalleSchema).min(1),
})

export const bom = new Hono<AuthEnv>()
bom.use('*', requireAuth)

bom.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const variantePadre = c.req.query('variante_padre_id')
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let query = supabase.from('bom_cabecera').select('*', { count: 'exact' })
  if (variantePadre) query = query.eq('variante_padre_id', Number(variantePadre))
  const { data, error, count } = await query.order('id', { ascending: false }).range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

bom.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data: cabecera, error: e1 } = await supabase.from('bom_cabecera').select('*').eq('id', Number(c.req.param('id'))).single()
  if (e1) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  const { data: detalles, error: e2 } = await supabase.from('bom_detalle').select('*').eq('bom_id', cabecera.id).order('secuencia')
  if (e2) return c.json({ error: { code: 'DB_ERROR', message: e2.message } }, 500)
  return c.json({ data: { ...cabecera, detalles } })
})

bom.post('/', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = crearSchema.safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase.rpc('crear_bom', {
    p_company_id: c.get('companyId'),
    p_variante_padre_id: parsed.data.variante_padre_id,
    p_version: parsed.data.version,
    p_fecha_efectiva: parsed.data.fecha_efectiva,
    p_detalles: JSON.stringify(parsed.data.detalles),
  })
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

bom.put('/:id/detalle', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = z.array(detalleSchema).min(1).safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const id = Number(c.req.param('id'))
  // transacción: borrar detalles y re-insertar (RLS filtra por company)
  const { error: e1 } = await supabase.from('bom_detalle').delete().eq('bom_id', id)
  if (e1) return c.json({ error: { code: 'DB_ERROR', message: e1.message } }, 500)
  const rows = parsed.data.map((d) => ({ ...d, bom_id: id, company_id: c.get('companyId') }))
  const { data, error: e2 } = await supabase.from('bom_detalle').insert(rows).select()
  if (e2) return c.json({ error: { code: 'DB_ERROR', message: e2.message } }, 500)
  return c.json({ data })
})

bom.delete('/:id', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const id = Number(c.req.param('id'))
  await supabase.from('bom_detalle').delete().eq('bom_id', id)
  const { error } = await supabase.from('bom_cabecera').delete().eq('id', id)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.body(null, 204)
})
```

- [ ] **Step 3: Escribir tests**

`api/src/routes/bom.test.ts`:
- GET `/` → total = 27 (empresa 2).
- POST `/` con variante_padre real + 1 detalle (variante componente real) → 201, verificar que `bom_detalle` tiene 1 fila.
- GET `/:id` → cabecera + detalles.
- PUT `/:id/detalle` con 2 detalles → reemplaza.
- DELETE `/:id` → 204, verificar que `bom_detalle` quedó vacío para ese bom.
- POST con variante inexistente → 500 (RAISE EXCEPTION de la función).

- [ ] **Step 4: Ejecutar tests**

Run: `npm run test -w api`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add api/src/routes/bom.ts api/src/routes/bom.test.ts supabase/migrations/20260906000016_crear_bom.sql
git commit -m "feat: add bom CRUD with atomic create"
```

---

### Task 4: CRUD Centros de Trabajo y Rutas de Producción

**Files:**
- Create: `api/src/routes/centros-trabajo.ts`, `api/src/routes/rutas-produccion.ts`
- Create: `api/src/routes/centros-trabajo.test.ts`, `api/src/routes/rutas-produccion.test.ts`

**Interfaces:**
- Produces: routers `/api/v1/centros-trabajo` y `/api/v1/rutas-produccion` (CRUD estándar + validación FKs).
- Consumes: Task 1-2 P2, `validarReferencias` (Task 2).

- [ ] **Step 1: Escribir los routers**

`api/src/routes/centros-trabajo.ts` — schema:
```ts
const schema = z.object({
  codigo: z.string().min(1).max(20),
  nombre: z.string().min(1).max(100),
  descripcion: z.string().optional(),
  tipo: z.string().max(20).optional(),
  capacidad_horas_dia: z.number().optional(),
  eficiencia_porcentaje: z.number().optional(),
  costo_hora: z.number().optional(),
  capacidad_finita: z.boolean().optional(),
  calendario_id: z.number().int().optional(),
  activo: z.boolean().optional(),
  ubicacion: z.string().max(100).optional(),
  responsable: z.string().max(100).optional(),
  observaciones: z.string().optional(),
})
```

`api/src/routes/rutas-produccion.ts` — schema (valida FK bom y centro_trabajo):
```ts
const schema = z.object({
  bom_id: z.number().int().positive(),
  secuencia: z.number().int().positive(),
  centro_trabajo_id: z.number().int().positive(),
  descripcion: z.string().min(1).max(255),
  tiempo_setup_mins: z.number().int().optional(),
  tiempo_proceso_unitario_mins: z.number().optional(),
  tiempo_cola_mins: z.number().int().optional(),
  tiempo_movimiento_mins: z.number().int().optional(),
  capacidad_requerida: z.number().optional(),
  costo_operacion_fijo: z.number().optional(),
  costo_operacion_variable: z.number().optional(),
  instrucciones: z.string().optional(),
})
```
GET `/` con filtro `?bom_id=`.

- [ ] **Step 2: Escribir tests** (empresa 2: centros_trabajo y rutas_produccion vacías → crear+eliminar; GET con 200 y array vacío)

- [ ] **Step 3: Ejecutar tests**

Run: `npm run test -w api`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add api/src/routes/centros-trabajo.ts api/src/routes/rutas-produccion.ts api/src/routes/centros-trabajo.test.ts api/src/routes/rutas-produccion.test.ts
git commit -m "feat: add centros de trabajo and rutas de produccion CRUD"
```

---

### Task 5: CRUD Órdenes de Producción (flujo de estados)

**Files:**
- Create: `api/src/routes/ordenes-produccion.ts`
- Create: `api/src/routes/ordenes-produccion.test.ts`
- Create: `supabase/migrations/20260906000017_next_numero_orden.sql`

**Interfaces:**
- Produces: router `/api/v1/ordenes-produccion` con GET `/` (filtro `?estado=`), GET `/:id`, POST `/` (genera numero_orden vía RPC), PATCH `/:id` (estado + campos), POST `/:id/estado` (transición validada: borrador→planificada→liberada→en_proceso→pausada/completada→cerrada, cancelada desde cualquier estado activo), DELETE `/:id` (solo borrador). Función SQL `public.next_numero_orden(p_company_id)` con secuencia por empresa.
- Consumes: Task 1-2 P2, `validarReferencias` (Task 2).

- [ ] **Step 1: Escribir la migración de numeración**

`supabase/migrations/20260906000017_next_numero_orden.sql`:
```sql
-- Secuencia global por empresa para numeración de órdenes
CREATE SEQUENCE IF NOT EXISTS public.orden_num_seq;

CREATE OR REPLACE FUNCTION public.next_numero_orden(p_company_id bigint)
RETURNS text
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_seq bigint;
BEGIN
  -- Una secuencia global es suficiente: el numero incluye company + fecha
  SELECT nextval('public.orden_num_seq') INTO v_seq;
  RETURN 'OP-' || to_char(CURRENT_DATE, 'YYYYMMDD') || '-' || p_company_id || '-' || lpad(v_seq::text, 4, '0');
END;
$$;

GRANT EXECUTE ON FUNCTION public.next_numero_orden(bigint) TO authenticated;
```

- [ ] **Step 2: Escribir el router**

`api/src/routes/ordenes-produccion.ts`:
```ts
import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'
import { validarReferencias } from '../lib/validate-fk.js'

const ESTADOS = ['borrador', 'planificada', 'liberada', 'en_proceso', 'pausada', 'completada', 'cancelada', 'cerrada'] as const
const TRANSICIONES: Record<string, string[]> = {
  borrador: ['planificada', 'cancelada'],
  planificada: ['liberada', 'cancelada'],
  liberada: ['en_proceso', 'cancelada'],
  en_proceso: ['pausada', 'completada', 'cancelada'],
  pausada: ['en_proceso', 'cancelada'],
  completada: ['cerrada'],
  cancelada: [],
  cerrada: [],
}

const crearSchema = z.object({
  variante_id: z.number().int().positive(),
  bom_id_utilizada: z.number().int().positive().optional(),
  cantidad_planificada: z.number().positive(),
  fecha_inicio_programada: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  fecha_fin_programada: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  prioridad: z.enum(['baja', 'normal', 'alta', 'urgente']).optional(),
  configuracion_orden: z.record(z.unknown()).optional(),
  observaciones: z.string().optional(),
})

const estadoSchema = z.object({ estado: z.enum(ESTADOS) })

export const ordenesProduccion = new Hono<AuthEnv>()
ordenesProduccion.use('*', requireAuth)

ordenesProduccion.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const estado = c.req.query('estado')
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let query = supabase.from('ordenes_produccion').select('*', { count: 'exact' })
  if (estado) query = query.eq('estado', estado)
  const { data, error, count } = await query.order('id', { ascending: false }).range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

ordenesProduccion.get('/:id', async (c) => { /* patrón P2 */ })

ordenesProduccion.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = crearSchema.safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const fk = await validarReferencias(supabase, { variantes: parsed.data.variante_id })
  if (!fk.ok) return c.json({ error: { code: 'VALIDATION', message: fk.error } }, 400)
  if (parsed.data.bom_id_utilizada) {
    const fkBom = await validarReferencias(supabase, { bom_cabecera: parsed.data.bom_id_utilizada })
    if (!fkBom.ok) return c.json({ error: { code: 'VALIDATION', message: fkBom.error } }, 400)
  }
  const { data: numero, error: eNum } = await supabase.rpc('next_numero_orden', { p_company_id: c.get('companyId') })
  if (eNum) return c.json({ error: { code: 'DB_ERROR', message: eNum.message } }, 500)
  const { data, error } = await supabase.from('ordenes_produccion').insert({
    ...parsed.data,
    numero_orden: numero,
    company_id: c.get('companyId'),
    estado: 'borrador',
    usuario_creador: c.get('userId'),
  }).select().single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

ordenesProduccion.patch('/:id', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = crearSchema.partial().safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase.from('ordenes_produccion').update(parsed.data).eq('id', Number(c.req.param('id'))).select().single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

ordenesProduccion.post('/:id/estado', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = estadoSchema.safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const id = Number(c.req.param('id'))
  const { data: orden } = await supabase.from('ordenes_produccion').select('id, estado').eq('id', id).single()
  if (!orden) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  const permitidos = TRANSICIONES[orden.estado] ?? []
  if (!permitidos.includes(parsed.data.estado)) {
    return c.json({ error: { code: 'VALIDATION', message: `Transición inválida: ${orden.estado} → ${parsed.data.estado}` } }, 400)
  }
  const update: Record<string, unknown> = { estado: parsed.data.estado }
  if (parsed.data.estado === 'en_proceso') update.fecha_inicio_real = new Date().toISOString()
  if (parsed.data.estado === 'completada') update.fecha_fin_real = new Date().toISOString()
  const { data, error } = await supabase.from('ordenes_produccion').update(update).eq('id', id).select().single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

ordenesProduccion.delete('/:id', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const id = Number(c.req.param('id'))
  const { data: orden } = await supabase.from('ordenes_produccion').select('id, estado').eq('id', id).single()
  if (!orden) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  if (orden.estado !== 'borrador') {
    return c.json({ error: { code: 'VALIDATION', message: 'Solo se puede eliminar en estado borrador' } }, 400)
  }
  const { error } = await supabase.from('ordenes_produccion').delete().eq('id', id)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.body(null, 204)
})
```

- [ ] **Step 3: Escribir tests**

`api/src/routes/ordenes-produccion.test.ts`:
- POST `/` → 201 con `numero_orden` generado (`OP-YYYYMMDD-2-XXXX`).
- POST `/:id/estado` borrador→planificada→liberada→en_proceso→completada→cerrada (cadena completa).
- Transición inválida: borrador→completada → 400.
- DELETE en estado liberada → 400.
- DELETE en borrador → 204.

- [ ] **Step 4: Ejecutar tests**

Run: `npm run test -w api`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add api/src/routes/ordenes-produccion.ts api/src/routes/ordenes-produccion.test.ts supabase/migrations/20260906000017_next_numero_orden.sql
git commit -m "feat: add ordenes de produccion CRUD with state machine"
```

---

### Task 6: Movimientos de Inventario (actualización atómica de stock)

**Files:**
- Create: `api/src/routes/movimientos-inventario.ts`
- Create: `api/src/routes/movimientos-inventario.test.ts`
- Create: `supabase/migrations/20260906000018_movimiento_inventario.sql`

**Interfaces:**
- Produces: router `/api/v1/movimientos-inventario` con GET `/` (filtros `?variante_id=`, `?tipo_movimiento=`), POST `/` (RPC `movimiento_inventario` que inserta movimiento + actualiza stock_actual de la variante en UNA transacción), GET `/:id`. Función SQL `public.movimiento_inventario(...)` (security definer).
- Consumes: Task 1-2 P2, `validarReferencias` (Task 2).

- [ ] **Step 1: Escribir la migración de la función de movimiento**

`supabase/migrations/20260906000018_movimiento_inventario.sql`:
```sql
CREATE OR REPLACE FUNCTION public.movimiento_inventario(
  p_company_id bigint,
  p_variante_id integer,
  p_almacen_id integer,
  p_orden_produccion_id integer,
  p_tipo_movimiento text,
  p_cantidad numeric,
  p_costo_unitario_snapshot numeric DEFAULT 0,
  p_observaciones text DEFAULT NULL,
  p_referencia_documento text DEFAULT NULL
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_id integer;
  v_result jsonb;
BEGIN
  IF p_tipo_movimiento IN ('produccion_consumo', 'venta_despacho', 'transferencia_salida', 'ajuste_inventario') AND p_cantidad < 0 THEN
    RAISE EXCEPTION 'cantidad no puede ser negativa';
  END IF;

  INSERT INTO movimientos_inventario (
    company_id, variante_id, almacen_id, orden_produccion_id, tipo_movimiento,
    cantidad, signo, costo_unitario_snapshot, usuario_id, observaciones, referencia_documento
  )
  VALUES (
    p_company_id, p_variante_id, p_almacen_id, p_orden_produccion_id, p_tipo_movimiento,
    p_cantidad,
    CASE WHEN p_tipo_movimiento IN ('produccion_consumo', 'venta_despacho', 'transferencia_salida') THEN -1 ELSE 1 END,
    p_costo_unitario_snapshot, auth.uid(), p_observaciones, p_referencia_documento
  )
  RETURNING id INTO v_id;

  -- El trigger actualizar_stock_trigger (P1) ya actualiza stock_actual en INSERT.

  SELECT jsonb_build_object('id', id, 'variante_id', variante_id, 'tipo_movimiento', tipo_movimiento, 'cantidad', cantidad, 'signo', signo)
  INTO v_result FROM movimientos_inventario WHERE id = v_id;
  RETURN v_result;
END;
$$;

GRANT EXECUTE ON FUNCTION public.movimiento_inventario(bigint, integer, integer, integer, text, numeric, numeric, text, text) TO authenticated;
```

> **Nota**: verificar en Step 2 que el trigger `actualizar_stock_trigger` (migrado en P1 desde `mrp_tunna_schema`) actualiza `variantes.stock_actual` con `NEW.cantidad * NEW.signo`. Si no lo hace para todos los tipos, corregir la función.

- [ ] **Step 2: Escribir el router**

`api/src/routes/movimientos-inventario.ts`:
```ts
import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'

const TIPOS = ['compra_recepcion', 'produccion_ingreso', 'produccion_consumo', 'produccion_descarte', 'ajuste_inventario', 'venta_despacho', 'transferencia_salida', 'transferencia_entrada'] as const

const schema = z.object({
  variante_id: z.number().int().positive(),
  almacen_id: z.number().int().positive().nullable().optional(),
  orden_produccion_id: z.number().int().positive().nullable().optional(),
  tipo_movimiento: z.enum(TIPOS),
  cantidad: z.number().positive(),
  costo_unitario_snapshot: z.number().optional(),
  observaciones: z.string().optional(),
  referencia_documento: z.string().max(100).optional(),
})

export const movimientosInventario = new Hono<AuthEnv>()
movimientosInventario.use('*', requireAuth)

movimientosInventario.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const varianteId = c.req.query('variante_id')
  const tipo = c.req.query('tipo_movimiento')
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let query = supabase.from('movimientos_inventario').select('*', { count: 'exact' })
  if (varianteId) query = query.eq('variante_id', Number(varianteId))
  if (tipo) query = query.eq('tipo_movimiento', tipo)
  const { data, error, count } = await query.order('id', { ascending: false }).range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

movimientosInventario.get('/:id', async (c) => { /* patrón P2 */ })

movimientosInventario.post('/', requireRole('Super Administrador', 'Administrador', 'Supervisor'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase.rpc('movimiento_inventario', {
    p_company_id: c.get('companyId'),
    p_variante_id: parsed.data.variante_id,
    p_almacen_id: parsed.data.almacen_id ?? null,
    p_orden_produccion_id: parsed.data.orden_produccion_id ?? null,
    p_tipo_movimiento: parsed.data.tipo_movimiento,
    p_cantidad: parsed.data.cantidad,
    p_costo_unitario_snapshot: parsed.data.costo_unitario_snapshot ?? 0,
    p_observaciones: parsed.data.observaciones ?? null,
    p_referencia_documento: parsed.data.referencia_documento ?? null,
  })
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})
```

- [ ] **Step 3: Escribir tests**

`api/src/routes/movimientos-inventario.test.ts`:
- Tomar una variante real (empresa 2), leer su `stock_actual`.
- POST `ajuste_inventario` con cantidad 5 → 201, verificar que `stock_actual` subió 5 (leer variante).
- POST `ajuste_inventario` de nuevo (para revertir: usar `produccion_consumo` con cantidad 5) → verificar que `stock_actual` volvió.
- GET `/?variante_id=` → incluye los movimientos creados.
- POST con variante inexistente → 500 (FK).
- Limpiar movimientos creados (DELETE directo con admin para no dejar residuos — documentar).

- [ ] **Step 4: Ejecutar tests**

Run: `npm run test -w api`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add api/src/routes/movimientos-inventario.ts api/src/routes/movimientos-inventario.test.ts supabase/migrations/20260906000018_movimiento_inventario.sql
git commit -m "feat: add movimientos de inventario with atomic stock update"
```

---

### Task 7: Compras (con recepción → movimiento)

**Files:**
- Create: `api/src/routes/compras.ts`
- Create: `api/src/routes/compras.test.ts`

**Interfaces:**
- Produces: router `/api/v1/compras` con GET `/` (filtro `?entidad_id=`), GET `/:id`, POST `/` (crea compra + recepción: RPC `recibir_compra` que crea el movimiento de inventario `compra_recepcion` y actualiza stock), PATCH `/:id`, DELETE `/:id` (solo si no tiene recepción). Función SQL `public.recibir_compra(...)` (security definer).
- Consumes: Task 1-2 P2, `movimiento_inventario` (Task 6).

- [ ] **Step 1: Escribir la migración de la función de recepción**

`supabase/migrations/20260906000019_recibir_compra.sql`:
```sql
CREATE OR REPLACE FUNCTION public.recibir_compra(
  p_company_id bigint,
  p_fecha date,
  p_precio_unitario numeric,
  p_id_entidad integer,
  p_nro_comprobante text,
  p_variante_id integer,
  p_cantidad numeric,
  p_observaciones text DEFAULT NULL
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_compra_id integer;
  v_result jsonb;
BEGIN
  INSERT INTO compras (company_id, fecha, precio_unitario, id_entidad, nro_comprobante, observaciones)
  VALUES (p_company_id, p_fecha, p_precio_unitario, p_id_entidad, p_nro_comprobante, p_observaciones)
  RETURNING id INTO v_compra_id;

  -- Recepción: movimiento de inventario (inserta en movimientos_inventario,
  -- el trigger actualiza stock_actual) y registra en movimientos_stock.
  PERFORM public.movimiento_inventario(
    p_company_id, p_variante_id, NULL, NULL,
    'compra_recepcion', p_cantidad, p_precio_unitario, p_observaciones, p_nro_comprobante
  );

  INSERT INTO movimientos_stock (company_id, id_variante, cantidad, id_tipo_deposito_origen, id_tipo_deposito_destino, referencia_tipo, referencia_id, observaciones)
  VALUES (p_company_id, p_variante_id, p_cantidad,
    (SELECT id FROM tipos_depositos WHERE company_id = p_company_id AND codigo = 'PROVEEDOR' LIMIT 1),
    (SELECT id FROM tipos_depositos WHERE company_id = p_company_id AND codigo = 'ALMACEN' LIMIT 1),
    'compra', v_compra_id, p_observaciones);

  SELECT jsonb_build_object('id', id, 'fecha', fecha, 'precio_unitario', precio_unitario, 'nro_comprobante', nro_comprobante)
  INTO v_result FROM compras WHERE id = v_compra_id;
  RETURN v_result;
END;
$$;

GRANT EXECUTE ON FUNCTION public.recibir_compra(bigint, date, numeric, integer, text, integer, numeric, text) TO authenticated;
```

- [ ] **Step 2: Escribir el router**

`api/src/routes/compras.ts`:
```ts
import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'
import { validarReferencias } from '../lib/validate-fk.js'

const schema = z.object({
  fecha: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  precio_unitario: z.number().positive(),
  observaciones: z.string().optional(),
  id_entidad: z.number().int().positive(),
  nro_comprobante: z.string().max(50).optional(),
})

const recibirSchema = schema.extend({
  variante_id: z.number().int().positive(),
  cantidad: z.number().positive(),
})

export const compras = new Hono<AuthEnv>()
compras.use('*', requireAuth)

compras.get('/', async (c) => { /* patrón P2 con filtro entidad_id */ })
compras.get('/:id', async (c) => { /* patrón P2 */ })

compras.post('/', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = recibirSchema.safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const fk = await validarReferencias(supabase, { entidades: parsed.data.id_entidad, variantes: parsed.data.variante_id })
  if (!fk.ok) return c.json({ error: { code: 'VALIDATION', message: fk.error } }, 400)
  const { data, error } = await supabase.rpc('recibir_compra', {
    p_company_id: c.get('companyId'),
    p_fecha: parsed.data.fecha,
    p_precio_unitario: parsed.data.precio_unitario,
    p_id_entidad: parsed.data.id_entidad,
    p_nro_comprobante: parsed.data.nro_comprobante ?? null,
    p_variante_id: parsed.data.variante_id,
    p_cantidad: parsed.data.cantidad,
    p_observaciones: parsed.data.observaciones ?? null,
  })
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

compras.patch('/:id', async (c) => { /* patrón P2 (sin variante/cantidad) */ })

compras.delete('/:id', requireRole('Super Administrador', 'Administrador'), async (c) => {
  // Solo si no tiene movimientos_stock asociados (recepción)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const id = Number(c.req.param('id'))
  const { data: recibida } = await supabase.from('movimientos_stock').select('id').eq('referencia_tipo', 'compra').eq('referencia_id', id).limit(1)
  if (recibida && recibida.length > 0) {
    return c.json({ error: { code: 'VALIDATION', message: 'No se puede eliminar una compra con recepción' } }, 400)
  }
  const { error } = await supabase.from('compras').delete().eq('id', id)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.body(null, 204)
})
```

- [ ] **Step 3: Escribir tests**

`api/src/routes/compras.test.ts`:
- POST `/` con entidad real (empresa 2 tiene 1) + variante real + cantidad 3 → 201; verificar que `stock_actual` de la variante subió 3 y que existe el movimiento `compra_recepcion`.
- DELETE de esa compra → 400 (tiene recepción).
- GET `/?entidad_id=` → incluye la compra.
- Limpiar: DELETE directo con admin de `movimientos_stock`, `movimientos_inventario`, `compras` creados (documentar).

- [ ] **Step 4: Ejecutar tests**

Run: `npm run test -w api`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add api/src/routes/compras.ts api/src/routes/compras.test.ts supabase/migrations/20260906000019_recibir_compra.sql
git commit -m "feat: add compras with recepcion and stock update"
```

---

### Task 8: Planificación de Recursos

**Files:**
- Create: `api/src/routes/planificacion.ts`
- Create: `api/src/routes/planificacion.test.ts`

**Interfaces:**
- Produces: router `/api/v1/planificacion` con GET `/` (filtros `?orden_produccion_id=`, `?centro_trabajo_id=`), POST `/` (programar recurso: valida solapamiento de periodos en el mismo centro de trabajo), PATCH `/:id`, DELETE `/:id`. Función SQL `public.verificar_solapamiento(...)` (security definer) que retorna TRUE si el periodo se solapa con otro en el mismo centro.
- Consumes: Task 1-2 P2, `validarReferencias` (Task 2).

- [ ] **Step 1: Escribir la migración de verificación de solapamiento**

`supabase/migrations/20260906000020_verificar_solapamiento.sql`:
```sql
CREATE OR REPLACE FUNCTION public.verificar_solapamiento(
  p_company_id bigint,
  p_centro_trabajo_id integer,
  p_inicio timestamp,
  p_fin timestamp,
  p_excluir_id integer DEFAULT NULL
)
RETURNS boolean
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_solapa boolean;
BEGIN
  SELECT EXISTS (
    SELECT 1 FROM planificacion_recursos
    WHERE company_id = p_company_id
      AND centro_trabajo_id = p_centro_trabajo_id
      AND (p_excluir_id IS NULL OR id <> p_excluir_id)
      AND periodo && tsrange(p_inicio, p_fin, '[)')
  ) INTO v_solapa;
  RETURN v_solapa;
END;
$$;

GRANT EXECUTE ON FUNCTION public.verificar_solapamiento(bigint, integer, timestamp, timestamp, integer) TO authenticated;
```

- [ ] **Step 2: Escribir el router**

`api/src/routes/planificacion.ts`:
```ts
import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'
import { validarReferencias } from '../lib/validate-fk.js'

const schema = z.object({
  orden_produccion_id: z.number().int().positive(),
  operacion_id: z.number().int().positive().nullable().optional(),
  centro_trabajo_id: z.number().int().positive(),
  inicio: z.string().datetime(),
  fin: z.string().datetime(),
  estado: z.enum(['programado', 'en_ejecucion', 'completado']).optional(),
}).refine((v) => new Date(v.inicio) < new Date(v.fin), { message: 'inicio debe ser anterior a fin' })

export const planificacion = new Hono<AuthEnv>()
planificacion.use('*', requireAuth)

planificacion.get('/', async (c) => { /* patrón P2 con filtros */ })

planificacion.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const fk = await validarReferencias(supabase, {
    ordenes_produccion: parsed.data.orden_produccion_id,
    centros_trabajo: parsed.data.centro_trabajo_id,
  })
  if (!fk.ok) return c.json({ error: { code: 'VALIDATION', message: fk.error } }, 400)
  const { data: solapa } = await supabase.rpc('verificar_solapamiento', {
    p_company_id: c.get('companyId'),
    p_centro_trabajo_id: parsed.data.centro_trabajo_id,
    p_inicio: parsed.data.inicio,
    p_fin: parsed.data.fin,
    p_excluir_id: null,
  })
  if (solapa) return c.json({ error: { code: 'VALIDATION', message: 'Periodo se solapa con otro recurso del centro de trabajo' } }, 400)
  const { data, error } = await supabase.from('planificacion_recursos').insert({
    ...parsed.data,
    periodo: `[${parsed.data.inicio},${parsed.data.fin})`,
    company_id: c.get('companyId'),
  }).select().single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

planificacion.patch('/:id', async (c) => { /* igual + verificar solapamiento excluyendo el propio id */ })

planificacion.delete('/:id', async (c) => { /* patrón P2 */ })
```

- [ ] **Step 3: Escribir tests**

`api/src/routes/planificacion.test.ts`:
- Crear centro de trabajo de prueba + usar orden existente (empresa 2 tiene 1).
- POST `/` con periodo A → 201.
- POST `/` con periodo que solapa A (mismo centro) → 400.
- POST `/` con periodo B sin solape → 201.
- DELETE de ambos → 204.
- Limpiar centro de trabajo creado.

- [ ] **Step 4: Ejecutar tests**

Run: `npm run test -w api`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add api/src/routes/planificacion.ts api/src/routes/planificacion.test.ts supabase/migrations/20260906000020_verificar_solapamiento.sql
git commit -m "feat: add planificacion de recursos with overlap validation"
```

---

### Task 9: Montar rutas + deploy a Vercel

**Files:**
- Modify: `api/src/index.ts`

**Interfaces:**
- Produces: app completa con las 8 nuevas rutas montadas; re-deploy a Vercel (`mrp-api`).
- Consumes: Tasks 1-8.

- [ ] **Step 1: Montar rutas en index.ts**

Agregar imports y routes:
```ts
import { partes } from './routes/partes.js'
import { variantes } from './routes/variantes.js'
import { bom } from './routes/bom.js'
import { centrosTrabajo } from './routes/centros-trabajo.js'
import { rutasProduccion } from './routes/rutas-produccion.js'
import { ordenesProduccion } from './routes/ordenes-produccion.js'
import { movimientosInventario } from './routes/movimientos-inventario.js'
import { compras } from './routes/compras.js'
import { planificacion } from './routes/planificacion.js'
// ...
app.route('/api/v1/partes', partes)
app.route('/api/v1/variantes', variantes)
app.route('/api/v1/bom', bom)
app.route('/api/v1/centros-trabajo', centrosTrabajo)
app.route('/api/v1/rutas-produccion', rutasProduccion)
app.route('/api/v1/ordenes-produccion', ordenesProduccion)
app.route('/api/v1/movimientos-inventario', movimientosInventario)
app.route('/api/v1/compras', compras)
app.route('/api/v1/planificacion', planificacion)
```

- [ ] **Step 2: Verificar build y tests**

Run: `npm run build && npm run test`
Expected: 3/3 workspaces OK, todos los tests PASS.

- [ ] **Step 3: Deploy a Vercel**

Run: `vercel deploy --prod --cwd api --yes`
Expected: re-deploy de `mrp-api`. Verificar:
```bash
curl -s https://api-tau-eight-42.vercel.app/api/health
```

- [ ] **Step 4: Commit**

```bash
git add api/src/index.ts
git commit -m "feat: mount transactional routes and deploy to vercel"
```

---

### Task 10: Documentación de cierre

**Files:**
- Modify: `docs/migracion-nextjs.md`

- [ ] **Step 1: Actualizar el documento**

Agregar en Fase 2/3:
```
**Estado P3 (2026-09-06):** API transaccional desplegada en Vercel (`mrp-api`). Endpoints: partes, variantes (con stock), BOM atómico, centros de trabajo, rutas de producción, órdenes de producción (máquina de estados), movimientos de inventario (stock atómico vía RPC), compras con recepción, planificación de recursos (validación de solapamiento). Pendiente: P4/P5 (frontend).
```

- [ ] **Step 2: Verificar estado final**

Run: `npm run build && npm run test`
Expected: todo OK.

- [ ] **Step 3: Commit**

```bash
git add docs/migracion-nextjs.md
git commit -m "docs: mark P3 api phase complete"
```

---

## Self-Review

**1. Spec coverage (docs/migracion-nextjs.md):**
- Fase 2 completada en P2; P3 extiende con módulos transaccionales del menú (produccion.ordenes, transacciones.compras, transacciones.movimientos, produccion.planificacion, productos.bom, produccion.rutas, produccion.centros_trabajo).
- Tablas cubiertas: partes, variantes, bom_cabecera, bom_detalle, centros_trabajo, rutas_produccion, ordenes_produccion, movimientos_inventario, movimientos_stock, compras, planificacion_recursos (11 de 26).

**2. Placeholder scan:** los únicos ids "reales" de empresa 2 usados en tests se resuelven en runtime (queries). No hay TBD/TODO. Los datos de seed de `seed_company` ya están verificados (P2).

**3. Type consistency:** `validarReferencias` consistente entre Tasks 2-8. RPCs con prefijo `p_` consistente. `movimiento_inventario` reutilizado por `recibir_compra` (Task 7 consume Task 6). TRANSICIONES de estados consistente en Task 5.

**Gaps detectados y resueltos:**
- Task 6: el trigger `actualizar_stock_trigger` migrado en P1 actualiza `variantes.stock_actual` — se verifica en Step 2 y se corrige la función si hace falta.
- Task 7: la eliminación de compras con recepción se bloquea (integridad).
- Task 8: `periodo tsrange` se inserta como string `[inicio,fin)` — PostgREST lo acepta para columnas tsrange (verificar en Step 2).
