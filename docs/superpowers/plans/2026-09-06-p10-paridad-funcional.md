# Paridad Funcional con PHP — Plan de Implementación (P10–P15)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar los gaps detectados en la auditoría PHP vs Next.js: validación de movimientos contra la matriz de depósitos, export XLSX/PDF de reportes, selector multi-empresa, persistencia del agente AI, landing + registro self-service y cambio de contraseña forzado.

**Architecture:** Monorepo Turborepo (`frontend/` Next.js 16 + shadcn/ui, `api/` Hono 4 en Vercel, `supabase/migrations/` SQL). La lógica transaccional crítica vive en RPCs SECURITY DEFINER de PostgreSQL (patrón existente: `movimiento_inventario`, `recibir_compra`, `seed_company`). Tests de integración reales contra Supabase.

**Tech Stack:** Hono 4.13 + Zod 4 (api), Next.js 16 + React 19 (frontend), PostgreSQL 17 (Supabase), `exceljs` + `pdf-lib` (nuevo, para export), Vitest 5 (tests).

## Global Constraints

- TypeScript estricto en todo archivo nuevo. UI y comentarios en español.
- Tests de integración corren contra Supabase real: `cd api && npm test` (singleFork, evita rate limit 429; tokens cacheados en `api/src/test-utils.ts`).
- Migraciones SQL en `supabase/migrations/` con prefijo fecha `20260906xxxxx_`. Aplicar con `npx supabase db push` (o el flujo ya usado en el proyecto).
- Commits con convención del repo: `feat(api):`, `feat(frontend):`, `fix(api):`, `test:`, `docs:`.
- No romper rutas/API existentes: `POST /api/v1/movimientos-inventario` (RPC directo) se mantiene para compras/producción; la lógica nueva va en rutas/RPCs nuevos.
- Depósitos del sistema (`tipos_depositos.codigo`): `ALMACEN`, `PRODUCCION`, `PRE-PRODUCCION`, `PROVEEDOR`, `CLIENTE`, `AJUSTE`.
- `partes.factor_conversion numeric(15,6) DEFAULT 1.0` — factor compra→uso.
- Usuario de tests: `martin@unik.ar` (Super Administrador). Passwords en `api/src/test-utils.ts`.
- claims JWT: `company_id`, `user_role` (inyectados por `custom_access_token_hook`).
- Deploy post-fase: los tests E2E de validación se corren contra producción solo al final de cada fase.

## Mapa de Fases (dependencias)

```
Fase A (P10) Reglas movimientos  ──┐
Fase B (P11) Export XLSX/PDF      ─┼── independientes entre sí
Fase C (P12) Multi-empresa        ─┤
Fase D (P13) Persistencia agente  ─┘
Fase E (P14) Landing + registro   ── depende de NADA, pero conviene tras C (usa companies)
Fase F (P15) Cierre + docs        ── siempre última
```

---

# Fase A (P10): Reglas de negocio de movimientos de partes

Port fiel de `MovimientosPartesController::store` (PHP): matriz de depósitos, stock negativo, conversión compra→uso, compras satélite, fecha manual TZ Argentina.

### Task A1: RPC `registrar_movimiento_partes` (validación matriz + stock + conversión + compra)

**Files:**
- Create: `supabase/migrations/20260906000024_registrar_movimiento_partes.sql`
- Modify: `api/src/routes/movimientos-inventario.test.ts` (nuevos tests al final)

**Interfaces:**
- Produces: RPC `public.registrar_movimiento_partes(p_company_id bigint, p_variante_id integer, p_cantidad numeric, p_tipo_deposito_origen_id integer, p_tipo_deposito_destino_id integer, p_tipo_movimiento text, p_entidad_id integer, p_importe_total numeric, p_fecha timestamptz, p_nro_comprobante text, p_observaciones text) RETURNS jsonb`. Errores via `RAISE EXCEPTION` con mensajes: `movimiento no permitido entre depósitos <origen> → <destino>`, `stock insuficiente en origen (<stock> disponible)`, `Debe seleccionar una entidad para movimientos desde PROVEEDOR`, `Debe ingresar el Importe Total en compras`, `La fecha no puede ser futura`, `La cantidad debe ser positiva`.

- [ ] **Step 1: Escribir la migración SQL**

```sql
-- registrar_movimiento_partes: port de MovimientosPartesController::store (PHP)
-- 1. Valida matriz tipos_depositos_movimientos (origen→destino activo)
-- 2. Rechaza stock negativo en origen (salvo AJUSTE y PROVEEDOR)
-- 3. Compra (origen PROVEEDOR): exige entidad + importe, aplica factor_conversion,
--    crea registro satélite en compras
-- 4. Escribe movimientos_inventario (trigger actualiza stock_actual) + movimientos_stock

CREATE OR REPLACE FUNCTION public.registrar_movimiento_partes(
  p_company_id bigint,
  p_variante_id integer,
  p_cantidad numeric,
  p_tipo_deposito_origen_id integer,
  p_tipo_deposito_destino_id integer,
  p_tipo_movimiento text,
  p_entidad_id integer DEFAULT NULL,
  p_importe_total numeric DEFAULT NULL,
  p_fecha timestamptz DEFAULT NULL,
  p_nro_comprobante text DEFAULT NULL,
  p_observaciones text DEFAULT NULL
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_codigo_origen text;
  v_codigo_destino text;
  v_stock_origen numeric;
  v_factor numeric;
  v_cantidad_uso numeric;
  v_es_compra boolean;
  v_compra_id integer;
  v_mov_inv_id integer;
  v_mov_stock_id integer;
  v_costo_unitario numeric;
BEGIN
  IF p_cantidad IS NULL OR p_cantidad <= 0 THEN
    RAISE EXCEPTION 'La cantidad debe ser positiva';
  END IF;

  IF p_fecha IS NOT NULL AND p_fecha > now() THEN
    RAISE EXCEPTION 'La fecha no puede ser futura';
  END IF;

  IF NOT EXISTS (SELECT 1 FROM variantes WHERE id = p_variante_id AND company_id = p_company_id) THEN
    RAISE EXCEPTION 'variante inexistente en esta empresa';
  END IF;

  SELECT codigo INTO v_codigo_origen FROM tipos_depositos
    WHERE id = p_tipo_deposito_origen_id AND company_id = p_company_id;
  SELECT codigo INTO v_codigo_destino FROM tipos_depositos
    WHERE id = p_tipo_deposito_destino_id AND company_id = p_company_id;
  IF v_codigo_origen IS NULL OR v_codigo_destino IS NULL THEN
    RAISE EXCEPTION 'depósito origen/destino inexistente';
  END IF;

  -- 1. Matriz de movimientos permitidos
  IF NOT EXISTS (
    SELECT 1 FROM tipos_depositos_movimientos
    WHERE company_id = p_company_id
      AND tipo_deposito_origen_id = p_tipo_deposito_origen_id
      AND tipo_deposito_destino_id = p_tipo_deposito_destino_id
      AND activo
  ) THEN
    RAISE EXCEPTION 'movimiento no permitido entre depósitos % → %', v_codigo_origen, v_codigo_destino;
  END IF;

  -- 2. Stock negativo: solo AJUSTE y PROVEEDOR permiten
  IF v_codigo_origen NOT IN ('AJUSTE', 'PROVEEDOR') THEN
    SELECT COALESCE((
      SELECT SUM(cantidad) FROM movimientos_stock
      WHERE company_id = p_company_id AND id_variante = p_variante_id
        AND id_tipo_deposito_destino = p_tipo_deposito_origen_id
    ), 0) - COALESCE((
      SELECT SUM(cantidad) FROM movimientos_stock
      WHERE company_id = p_company_id AND id_variante = p_variante_id
        AND id_tipo_deposito_origen = p_tipo_deposito_origen_id
    ), 0)
    INTO v_stock_origen;
    IF (v_stock_origen - p_cantidad) < 0 THEN
      RAISE EXCEPTION 'stock insuficiente en origen (% disponible)', v_stock_origen;
    END IF;
  END IF;

  -- 3. Compra desde PROVEEDOR
  v_es_compra := v_codigo_origen = 'PROVEEDOR';
  v_factor := 1;
  v_cantidad_uso := p_cantidad;
  v_compra_id := NULL;

  IF v_es_compra THEN
    IF p_entidad_id IS NULL THEN
      RAISE EXCEPTION 'Debe seleccionar una entidad para movimientos desde PROVEEDOR';
    END IF;
    IF p_importe_total IS NULL OR p_importe_total <= 0 THEN
      RAISE EXCEPTION 'Debe ingresar el Importe Total en compras';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM entidades WHERE id = p_entidad_id AND company_id = p_company_id) THEN
      RAISE EXCEPTION 'entidad inexistente en esta empresa';
    END IF;
    SELECT COALESCE(NULLIF(factor_conversion, 0), 1) INTO v_factor
      FROM partes WHERE id = (SELECT id_parte FROM variantes WHERE id = p_variante_id);
    v_cantidad_uso := round(p_cantidad * v_factor, 6);
  END IF;

  v_costo_unitario := CASE
    WHEN v_es_compra THEN round((p_importe_total / p_cantidad) / v_factor, 4)
    ELSE 0 END;

  -- 4a. movimientos_inventario (trigger actualiza stock_actual)
  INSERT INTO movimientos_inventario (
    company_id, variante_id, tipo_movimiento, cantidad, signo,
    costo_unitario_snapshot, fecha_movimiento, observaciones, referencia_documento
  ) VALUES (
    p_company_id, p_variante_id, p_tipo_movimiento, v_cantidad_uso,
    CASE WHEN p_tipo_movimiento IN ('produccion_consumo','venta_despacho','transferencia_salida') THEN -1 ELSE 1 END,
    v_costo_unitario, COALESCE(p_fecha, now()), p_observaciones, p_nro_comprobante
  ) RETURNING id INTO v_mov_inv_id;

  -- 4b. Compra satélite
  IF v_es_compra THEN
    INSERT INTO compras (company_id, fecha, precio_unitario, id_entidad, nro_comprobante, observaciones, id_movimiento_stock)
    VALUES (p_company_id, COALESCE(p_fecha::date, CURRENT_DATE), v_costo_unitario, p_entidad_id, p_nro_comprobante, p_observaciones, NULL)
    RETURNING id INTO v_compra_id;
  END IF;

  -- 4c. movimientos_stock (stock por depósito, como en PHP)
  INSERT INTO movimientos_stock (
    company_id, id_variante, cantidad, id_tipo_deposito_origen, id_tipo_deposito_destino,
    referencia_tipo, referencia_id, fecha, observaciones
  ) VALUES (
    p_company_id, p_variante_id, v_cantidad_uso, p_tipo_deposito_origen_id, p_tipo_deposito_destino_id,
    CASE WHEN v_es_compra THEN 'compra_satelite' ELSE 'interno' END,
    COALESCE(v_compra_id, 0), COALESCE(p_fecha, now()), p_observaciones
  ) RETURNING id INTO v_mov_stock_id;

  IF v_compra_id IS NOT NULL THEN
    UPDATE compras SET id_movimiento_stock = v_mov_stock_id WHERE id = v_compra_id;
  END IF;

  RETURN jsonb_build_object(
    'movimiento_inventario_id', v_mov_inv_id,
    'movimiento_stock_id', v_mov_stock_id,
    'compra_id', v_compra_id,
    'cantidad_uso', v_cantidad_uso,
    'factor_conversion', v_factor
  );
END;
$$;

GRANT EXECUTE ON FUNCTION public.registrar_movimiento_partes(bigint, integer, numeric, integer, integer, text, integer, numeric, timestamptz, text, text) TO authenticated;
```

- [ ] **Step 2: Aplicar la migración**

Run: `npx supabase db push` (desde la raíz del monorepo; si el flujo del proyecto usa otro comando, usar ese).
Expected: migración aplicada sin errores.

- [ ] **Step 3: Escribir tests de integración (al final de `movimientos-inventario.test.ts`)**

```ts
describe('registrar_movimiento_partes (POST /movimientos-partes)', () => {
  let adminToken = ''
  let varianteId = 0
  let parteId = 0
  let deps: Record<string, number> = {}
  let entidadId = 0

  beforeAll(async () => {
    adminToken = await login(MARTIN)
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    const v = await supabase.from('variantes').select('id, id_parte').limit(1)
    varianteId = v.data![0].id
    parteId = v.data![0].id_parte
    const companyId = 2 // empresa demo (igual que compras.test.ts)
    const d = await supabase.from('tipos_depositos').select('id, codigo').eq('company_id', companyId)
    deps = Object.fromEntries(d.data!.map((r) => [r.codigo, r.id]))
    const e = await supabase.from('entidades').select('id').eq('company_id', companyId).limit(1)
    entidadId = e.data![0].id
  })

  it('rechaza movimiento no permitido por la matriz', async () => {
    const app = new Hono().route('/api/v1/movimientos-partes', movimientosPartes)
    const res = await app.request('/api/v1/movimientos-partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_id: varianteId, cantidad: 1,
        tipo_deposito_origen_id: deps['CLIENTE'], tipo_deposito_destino_id: deps['PRODUCCION'],
        tipo_movimiento: 'transferencia_salida',
      }),
    })
    expect(res.status).toBe(400)
    const body = await res.json()
    expect(body.error.message).toContain('movimiento no permitido')
  })

  it('rechaza stock negativo en origen no-AJUSTE/PROVEEDOR', async () => {
    const app = new Hono().route('/api/v1/movimientos-partes', movimientosPartes)
    const res = await app.request('/api/v1/movimientos-partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_id: varianteId, cantidad: 999999,
        tipo_deposito_origen_id: deps['PRODUCCION'], tipo_deposito_destino_id: deps['ALMACEN'],
        tipo_movimiento: 'produccion_ingreso',
      }),
    })
    expect(res.status).toBe(400)
    expect((await res.json()).error.message).toContain('stock insuficiente')
  })

  it('compra PROVEEDOR→ALMACEN exige entidad e importe', async () => {
    const app = new Hono().route('/api/v1/movimientos-partes', movimientosPartes)
    const res = await app.request('/api/v1/movimientos-partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_id: varianteId, cantidad: 1,
        tipo_deposito_origen_id: deps['PROVEEDOR'], tipo_deposito_destino_id: deps['ALMACEN'],
        tipo_movimiento: 'compra_recepcion',
      }),
    })
    expect(res.status).toBe(400)
    expect((await res.json()).error.message).toContain('entidad')
  })

  it('compra con factor_conversion crea compra + cantidad_uso', async () => {
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    await supabase.from('partes').update({ factor_conversion: 10 }).eq('id', parteId)
    const app = new Hono().route('/api/v1/movimientos-partes', movimientosPartes)
    const res = await app.request('/api/v1/movimientos-partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_id: varianteId, cantidad: 2, importe_total: 100,
        tipo_deposito_origen_id: deps['PROVEEDOR'], tipo_deposito_destino_id: deps['ALMACEN'],
        tipo_movimiento: 'compra_recepcion', entidad_id: entidadId,
      }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    expect(body.data.compra_id).toBeGreaterThan(0)
    expect(body.data.cantidad_uso).toBe(20) // 2 x factor 10
    // precio_unitario = (100/2)/10 = 5
    const compra = await supabase.from('compras').select('precio_unitario').eq('id', body.data.compra_id).single()
    expect(Number(compra.data!.precio_unitario)).toBe(5)
    // revert: ajuste inverso + borrar compra
    await app.request('/api/v1/movimientos-partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_id: varianteId, cantidad: 2,
        tipo_deposito_origen_id: deps['ALMACEN'], tipo_deposito_destino_id: deps['AJUSTE'],
        tipo_movimiento: 'ajuste_inventario', observaciones: 'test-revert',
      }),
    })
    await supabase.from('compras').delete().eq('id', body.data.compra_id)
  })
})
```

Nota: agregar el import de la ruta nueva al top del test file: `import { movimientosPartes } from './movimientos-partes'` (se crea en Task A2/A3; escribir este test después de crear la ruta — orden recomendado: completar A2+A3 antes de correr Step 4).

- [ ] **Step 4: Correr tests**

Run: `cd api && npx vitest run src/routes/movimientos-inventario.test.ts`
Expected: PASS (tras completar A2/A3). Si se corre antes, fallará por import inexistente — es el ciclo TDD esperado.

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/20260906000024_registrar_movimiento_partes.sql api/src/routes/movimientos-inventario.test.ts
git commit -m "feat(api): RPC registrar_movimiento_partes con validación de matriz de depósitos, stock y conversión de compra"
```

### Task A2: Endpoint `GET /tipos-depositos/destinos-permitidos` (mapa para el form)

**Files:**
- Modify: `api/src/routes/tipos-depositos.ts` (agregar ruta al final)
- Modify: `api/src/routes/tipos-depositos.test.ts` (test nuevo)

**Interfaces:**
- Produces: `GET /api/v1/tipos-depositos/destinos-permitidos` → `{ data: Array<{ origen_id: number; origen_codigo: string; origen_nombre: string; destinos: Array<{ id: number; codigo: string; nombre: string }> }> }`. Consume la ruta `tiposDepositos` existente (mismo archivo).

- [ ] **Step 1: Test primero (agregar al final de `tipos-depositos.test.ts`)**

```ts
it('destinos-permitidos devuelve mapa origen→destinos', async () => {
  const app = new Hono().route('/api/v1/tipos-depositos', tiposDepositos)
  const res = await app.request('/api/v1/tipos-depositos/destinos-permitidos', {
    headers: { Authorization: `Bearer ${adminToken}` },
  })
  expect(res.status).toBe(200)
  const body = await res.json()
  expect(body.data.length).toBeGreaterThan(0)
  const fila = body.data[0]
  expect(fila.origen_codigo).toBeTruthy()
  expect(Array.isArray(fila.destinos)).toBe(true)
})
```

- [ ] **Step 2: Implementar la ruta (antes del `/:id` para no chocar con el param)**

En `tipos-depositos.ts`, insertar ANTES de `tiposDepositos.get('/:id', ...)`:

```ts
// Mapa origen→destinos permitidos (port de getDestinosPermitidos del PHP)
tiposDepositos.get('/destinos-permitidos', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  // OJO: hay 2 FKs hacia tipos_depositos (fk_tipo_origen / fk_tipo_destino, nombres reales
  // del dump) — PostgREST exige el hint del constraint para desambiguar el embed
  const { data, error } = await supabase
    .from('tipos_depositos_movimientos')
    .select('tipo_deposito_origen_id, tipo_deposito_destino_id, activo, tipos_depositos!fk_tipo_origen(id, codigo, nombre)')
    .eq('activo', true)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  const destinosByOrigen = new Map<number, { origen_codigo: string; origen_nombre: string; destinos: Array<{ id: number; codigo: string; nombre: string }> }>()
  for (const row of data as unknown as Array<{
    tipo_deposito_origen_id: number; tipo_deposito_destino_id: number;
    tipos_depositos: { id: number; codigo: string; nombre: string } | null
  }>) {
    const origen = row.tipos_depositos
    if (!origen) continue
    const entry = destinosByOrigen.get(row.tipo_deposito_origen_id) ?? {
      origen_codigo: origen.codigo, origen_nombre: origen.nombre, destinos: [],
    }
    destinosByOrigen.set(row.tipo_deposito_origen_id, entry)
  }
  // Los destinos requieren segunda query (FK destino)
  const idsDestino = [...new Set((data as unknown as Array<{ tipo_deposito_destino_id: number }>).map((r) => r.tipo_deposito_destino_id))]
  const { data: destData } = await supabase.from('tipos_depositos').select('id, codigo, nombre').in('id', idsDestino)
  const destById = new Map((destData ?? []).map((d: { id: number; codigo: string; nombre: string }) => [d.id, d]))
  for (const row of data as unknown as Array<{ tipo_deposito_origen_id: number; tipo_deposito_destino_id: number }>) {
    const destino = destById.get(row.tipo_deposito_destino_id)
    if (!destino) continue
    const entry = destinosByOrigen.get(row.tipo_deposito_origen_id)
    if (entry && !entry.destinos.some((d) => d.id === destino.id)) {
      entry.destinos.push({ id: destino.id, codigo: destino.codigo, nombre: destino.nombre })
    }
  }
  return c.json({
    data: [...destinosByOrigen.entries()].map(([origen_id, e]) => ({
      origen_id, origen_codigo: e.origen_codigo, origen_nombre: e.origen_nombre, destinos: e.destinos,
    })),
  })
})
```

- [ ] **Step 3: Correr tests**

Run: `cd api && npx vitest run src/routes/tipos-depositos.test.ts`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add api/src/routes/tipos-depositos.ts api/src/routes/tipos-depositos.test.ts
git commit -m "feat(api): endpoint destinos-permitidos para validar movimientos en el frontend"
```

### Task A3: Ruta API `POST /api/v1/movimientos-partes` (port del store PHP)

**Files:**
- Create: `api/src/routes/movimientos-partes.ts`
- Modify: `api/src/index.ts` (montar la ruta)
- Test: los tests escritos en Task A1 Step 3 ya la cubren

**Interfaces:**
- Consumes: RPC `registrar_movimiento_partes` (Task A1).
- Produces: `POST /api/v1/movimientos-partes` con body `{ variante_id: number, cantidad: number, tipo_deposito_origen_id: number, tipo_deposito_destino_id: number, tipo_movimiento: enum(8), entidad_id?: number, importe_total?: number, fecha_hora?: string(ISO), nro_comprobante?: string, observaciones?: string }` → `201 { data }` o `400 { error: { code: 'VALIDATION'|'BUSINESS', message } }`. Export: `export const movimientosPartes = new Hono<AuthEnv>()`.
- Consumed by: `index.ts` (mount en `/api/v1/movimientos-partes`).

- [ ] **Step 1: Crear `api/src/routes/movimientos-partes.ts`**

```ts
import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'

const TIPOS = ['compra_recepcion', 'produccion_ingreso', 'produccion_consumo', 'produccion_descarte', 'ajuste_inventario', 'venta_despacho', 'transferencia_salida', 'transferencia_entrada'] as const

const schema = z.object({
  variante_id: z.number().int().positive(),
  cantidad: z.number().positive(),
  tipo_deposito_origen_id: z.number().int().positive(),
  tipo_deposito_destino_id: z.number().int().positive(),
  tipo_movimiento: z.enum(TIPOS),
  entidad_id: z.number().int().positive().nullable().optional(),
  importe_total: z.number().positive().nullable().optional(),
  fecha_hora: z.string().datetime({ offset: true }).nullable().optional(),
  nro_comprobante: z.string().max(50).nullable().optional(),
  observaciones: z.string().max(500).nullable().optional(),
})

export const movimientosPartes = new Hono<AuthEnv>()
movimientosPartes.use('*', requireAuth)

// Port de MovimientosPartesController::store (PHP)
movimientosPartes.post(
  '/',
  requireRole('Super Administrador', 'Administrador', 'Supervisor'),
  async (c) => {
    const body = await c.req.json().catch(() => null)
    const parsed = schema.safeParse(body)
    if (!parsed.success) {
      return c.json({ error: { code: 'VALIDATION', message: parsed.error.issues[0]?.message ?? 'datos inválidos' } }, 400)
    }
    const d = parsed.data
    const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
    const { data, error } = await supabase.rpc('registrar_movimiento_partes', {
      p_company_id: c.get('companyId'),
      p_variante_id: d.variante_id,
      p_cantidad: d.cantidad,
      p_tipo_deposito_origen_id: d.tipo_deposito_origen_id,
      p_tipo_deposito_destino_id: d.tipo_deposito_destino_id,
      p_tipo_movimiento: d.tipo_movimiento,
      p_entidad_id: d.entidad_id ?? null,
      p_importe_total: d.importe_total ?? null,
      p_fecha: d.fecha_hora ?? null,
      p_nro_comprobante: d.nro_comprobante ?? null,
      p_observaciones: d.observaciones ?? null,
    })
    if (error) {
      const msg = error.message
      const esNegocio = /movimiento no permitido|stock insuficiente|Debe |fecha no puede ser futura|cantidad debe ser positiva|depósito origen\/destino inexistente|inexistente en esta empresa/.test(msg)
      return c.json({ error: { code: esNegocio ? 'BUSINESS' : 'DB_ERROR', message: msg } }, esNegocio ? 400 : 500)
    }
    return c.json({ data }, 201)
  }
)
```

- [ ] **Step 2: Montar en `api/src/index.ts`**

Buscar el bloque de mounts (patrón: `app.route('/api/v1/xxx', xxx)`) y agregar:

```ts
import { movimientosPartes } from './routes/movimientos-partes.js'
// ...
app.route('/api/v1/movimientos-partes', movimientosPartes)
```

- [ ] **Step 3: Correr tests**

Run: `cd api && npx vitest run src/routes/movimientos-inventario.test.ts && npx tsc --noEmit`
Expected: PASS + sin errores de tipos.

- [ ] **Step 4: Commit**

```bash
git add api/src/routes/movimientos-partes.ts api/src/index.ts
git commit -m "feat(api): POST /movimientos-partes port de MovimientosPartesController::store"
```

### Task A4: Frontend — form completo de movimientos (origen/destino/entidad/importe/fecha)

**Files:**
- Modify: `frontend/app/(dashboard)/transacciones/movimientos-partes/movimientos-form.tsx` (reemplazo completo)
- Modify: `frontend/app/(dashboard)/transacciones/movimientos-partes/page.tsx` (cargar tipos de depósito + destinos + entidades)
- Modify: `frontend/app/(dashboard)/transacciones/movimientos-partes/actions.ts` (action nueva)

**Interfaces:**
- Consumes: `GET /api/v1/tipos-depositos/destinos-permitidos`, `GET /api/v1/entidades`, `POST /api/v1/movimientos-partes` (via `apiFetch` con `session.accessToken`).

- [ ] **Step 1: Action nueva en `actions.ts`**

```ts
'use server'
import { apiFetch } from '@/lib/api'
import { getSession } from '@/lib/session'

export async function registrarMovimientoPartes(input: {
  variante_id: number
  cantidad: number
  tipo_deposito_origen_id: number
  tipo_deposito_destino_id: number
  tipo_movimiento: string
  entidad_id?: number | null
  importe_total?: number | null
  fecha_hora?: string | null
  nro_comprobante?: string | null
  observaciones?: string | null
}): Promise<{ ok: boolean; error?: string }> {
  const session = await getSession()
  if (!session) return { ok: false, error: 'Sesión expirada' }
  try {
    await apiFetch('/api/v1/movimientos-partes', session.accessToken, {
      method: 'POST',
      body: JSON.stringify(input),
    })
    return { ok: true }
  } catch (e) {
    return { ok: false, error: e instanceof Error ? e.message : 'Error' }
  }
}
```

- [ ] **Step 2: Reemplazar `movimientos-form.tsx`**

Form con estado: `varianteId`, `origenId`, `destinoId`, `tipo`, `cantidad`, `entidadId`, `importeTotal`, `fechaHora` (input `datetime-local`), `nroComprobante`, `observaciones`. Reglas de UI (port del PHP):

- `destinos` filtrados según el origen elegido (mapa de `destinos-permitidos`).
- Campos condicionales: `{origen_codigo === 'PROVEEDOR' && (/* select de entidades PROVEEDOR/AMBOS + input importe_total obligatorio + input nro_comprobante */)}`.
- `tipo_movimiento` sugerido según origen: PROVEEDOR→ALMACEN = `compra_recepcion`; destino=CLIENTE = `venta_despacho`; AJUSTE→X = `ajuste_inventario`; el usuario puede cambiarlo (select de los 8 tipos).
- Envío: `registrarMovimientoPartes({ ..., fecha_hora: fecha ? new Date(fecha).toISOString() : undefined })`.
- Errores del API (code BUSINESS) se muestran bajo el form (mismo patrón `setError` actual).

Mantener los exports de tipos `VarianteOpt`, `AlmacenOpt` y agregar `DestinosMap = Array<{ origen_id: number; origen_codigo: string; origen_nombre: string; destinos: Array<{ id: number; codigo: string; nombre: string }> }>`.

- [ ] **Step 3: Actualizar `page.tsx`**

En el `Promise.all` agregar `apiFetch('/api/v1/tipos-depositos/destinos-permitidos', ...)` y `apiFetch('/api/v1/entidades?perPage=100', ...)`. Pasar `destinos` y `entidades` como props al form. En la tabla, agregar columna Depósitos leyendo la última fila de `movimientos_stock`? — NO: mantener la tabla de movimientos_inventario como está (el RPC escribe ahí también).

- [ ] **Step 4: Verificar build + tests frontend**

Run: `cd frontend && npm run build && npm test`
Expected: build OK, tests PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend/app/\(dashboard\)/transacciones/movimientos-partes/
git commit -m "feat(frontend): form de movimientos con matriz de depósitos, entidad, importe y fecha"
```

### Task A5: Deploy fase A + verificación en producción

- [ ] **Step 1: Push a main** (deploy automático Vercel) y aplicar migración: `npx supabase db push`.
- [ ] **Step 2: Smoke test** contra `https://api.mimrp.com.ar/api/v1/health` y flujo manual: crear movimiento AJUSTE→ALMACEN desde la UI, intentar PRODUCCION→CLIENTE (debe rechazar).
- [ ] **Step 3: Commit** (si hay fixes): `fix(api|frontend): ajustes fase A`.

---

# Fase B (P11): Export XLSX/PDF de reportes

### Task B1: Lib de export (exceljs + pdf-lib) con tests unitarios

**Files:**
- Create: `api/src/lib/export/xlsx.ts`
- Create: `api/src/lib/export/pdf.ts`
- Create: `api/src/lib/export/export.test.ts`
- Modify: `api/package.json` (deps)

**Interfaces:**
- Produces: `buildXlsx(sheets: Array<{ name: string; rows: (string | number | null)[][] }>): Promise<Buffer>` y `buildPdf(opts: { title: string; subtitle?: string; headers: string[]; rows: (string | number | null)[][] }): Promise<Buffer>`.

- [ ] **Step 1: Instalar deps**

Run: `cd api && npm install exceljs pdf-lib`
Expected: agregadas a `api/package.json`.

- [ ] **Step 2: `api/src/lib/export/xlsx.ts`**

```ts
import ExcelJS from 'exceljs'

export type SheetSpec = { name: string; rows: (string | number | null)[][] }

export async function buildXlsx(sheets: SheetSpec[]): Promise<Buffer> {
  const wb = new ExcelJS.Workbook()
  for (const sheet of sheets) {
    const ws = wb.addWorksheet(sheet.name.slice(0, 31))
    if (sheet.rows.length === 0) continue
    const header = sheet.rows[0]
    ws.addRow(header.map((h) => h ?? ''))
    ws.getRow(1).font = { bold: true }
    for (let i = 1; i < sheet.rows.length; i++) {
      ws.addRow(sheet.rows[i].map((v) => v ?? ''))
    }
    ws.columns.forEach((col, idx) => {
      const maxLen = Math.max(...sheet.rows.map((r) => String(r[idx] ?? '').length), 10)
      col.width = Math.min(maxLen + 2, 60)
    })
  }
  const out = await wb.xlsx.writeBuffer()
  return Buffer.from(out)
}
```

- [ ] **Step 3: `api/src/lib/export/pdf.ts`**

```ts
import { PDFDocument, StandardFonts, rgb } from 'pdf-lib'

const PAGE_W = 595.28 // A4
const MARGIN = 40
const COL_PAD = 6

export type PdfTableOpts = {
  title: string
  subtitle?: string
  headers: string[]
  rows: (string | number | null)[][]
}

// Codifica a WinAnsi (Latin-1) para StandardFonts: reemplaza lo no soportado
function latin(s: unknown): string {
  return String(s ?? '').replace(/[^\x00-\xFF]/g, '?')
}

export async function buildPdf(opts: PdfTableOpts): Promise<Buffer> {
  const doc = await PDFDocument.create()
  const font = await doc.embedFont(StandardFonts.Helvetica)
  const fontBold = await doc.embedFont(StandardFonts.HelveticaBold)
  const colCount = opts.headers.length
  const colW = (PAGE_W - MARGIN * 2) / colCount

  let page = doc.addPage([PAGE_W, 841.89])
  let y = 841.89 - MARGIN

  const ensureSpace = (needed: number) => {
    if (y - needed < MARGIN) {
      page = doc.addPage([PAGE_W, 841.89])
      y = 841.89 - MARGIN
    }
  }

  const drawHeader = () => {
    page.drawText(latin(opts.title), { x: MARGIN, y, size: 14, font: fontBold })
    y -= 18
    if (opts.subtitle) {
      page.drawText(latin(opts.subtitle), { x: MARGIN, y, size: 9, font, color: rgb(0.4, 0.4, 0.4) })
      y -= 14
    }
    page.drawText(latin(opts.headers.join('  |  ')), { x: MARGIN, y, size: 8, font: fontBold })
    y -= 12
  }

  drawHeader()
  for (const row of opts.rows) {
    const cells = row.map((v, i) => {
      const maxChars = Math.floor((colW - COL_PAD * 2) / 4.6)
      return latin(v).slice(0, maxChars)
    })
    ensureSpace(14)
    // una celda por columna, truncada
    cells.forEach((cell, i) => {
      page.drawText(cell, { x: MARGIN + i * colW + COL_PAD, y, size: 8, font })
    })
    y -= 14
  }

  const bytes = await doc.save()
  return Buffer.from(bytes)
}
```

- [ ] **Step 4: Test unitario `export.test.ts`**

```ts
import { describe, it, expect } from 'vitest'
import { buildXlsx } from './xlsx.js'
import { buildPdf } from './pdf.js'

describe('lib/export', () => {
  it('buildXlsx genera un archivo zip (xlsx) válido', async () => {
    const buf = await buildXlsx([{ name: 'Hoja 1', rows: [['Col A', 'Col B'], [1, 'á é í ó ú ñ']] }])
    expect(buf.subarray(0, 2).toString()).toBe('PK')
    expect(buf.length).toBeGreaterThan(500)
  })

  it('buildPdf genera PDF con título y acentos', async () => {
    const buf = await buildPdf({
      title: 'Listado de Ingeniería',
      subtitle: 'Variante: P-01 · cantidad: 2',
      headers: ['Código', 'Detalle', 'Cantidad'],
      rows: [['P-001', 'Tapa con ñ y acentos áéíóú', 2], [null, 'fila corta', 3.5]],
    })
    expect(buf.subarray(0, 5).toString()).toBe('%PDF-')
  })
})
```

- [ ] **Step 5: Correr tests**

Run: `cd api && npx vitest run src/lib/export/export.test.ts`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add api/src/lib/export/ api/package.json api/package-lock.json
git commit -m "feat(api): lib de export XLSX (exceljs) y PDF (pdf-lib) para reportes"
```

### Task B2: Endpoints de export para los 2 reportes con export

**Files:**
- Modify: `api/src/routes/reportes.ts` (refactor: extraer `getListadoIngenieria` y `getPlanificacionProduccion` como funciones puras que devuelven `{ headers, rows, meta }` + 2 endpoints `/listado-ingenieria/export` y `/planificacion-produccion/export`)
- Modify: `api/src/routes/reportes.test.ts` (2 tests)

**Interfaces:**
- Produces: `GET /api/v1/reportes/listado-ingenieria/export?formato=xlsx|pdf&id_variante=&cantidad=&tipo_salida=` y `GET /api/v1/reportes/planificacion-produccion/export?formato=xlsx|pdf&productos=ID:Q,...` → binary con `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet | application/pdf` y `Content-Disposition: attachment; filename="..."`.

- [ ] **Step 1: Refactor mínimo en `reportes.ts`**

Extraer la lógica de los handlers existentes a funciones del mismo archivo:

```ts
type Tabla = { title: string; subtitle: string; headers: string[]; rows: (string | number | null)[][] }

async function getListadoIngenieria(supabase: ReturnType<typeof createUserClient>, params: { id_variante: number; cantidad: number; tipo_salida: string; /* ...resto de params actuales */ }): Promise<Tabla> {
  // MOVER aquí el cuerpo del handler GET /listado-ingenieria actual.
  // En vez de c.json(...), construir y devolver la Tabla:
  // headers = las columnas que hoy arma la respuesta (tipo, código, detalle, cantidad, etc.)
  // rows = los datos de tipos/grupos ya consolidados
}
async function getPlanificacionProduccion(supabase: ..., params: { productos: string }): Promise<Tabla> {
  // ídem con GET /planificacion-produccion (requerimientos de materiales)
}
```

Los handlers GET existentes pasan a ser `const tabla = await getListadoIngenieria(...); return c.json({ data: { tipos: tabla.rows /* o el shape actual */ } })` — **mantener el shape JSON de respuesta EXACTO** (los tests actuales lo validan: `body.data.tipos`, `body.data.requerimientos`).

- [ ] **Step 2: Endpoints de export**

```ts
reportes.get('/listado-ingenieria/export', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const formato = c.req.query('formato') === 'pdf' ? 'pdf' : 'xlsx'
  const tabla = await getListadoIngenieria(supabase, { /* parse de query igual al GET actual */ })
  const filename = `listado-ingenieria.${formato}`
  const buf = formato === 'pdf'
    ? await buildPdf({ title: tabla.title, subtitle: tabla.subtitle, headers: tabla.headers, rows: tabla.rows })
    : await buildXlsx([{ name: 'Listado', rows: [tabla.headers, ...tabla.rows] }])
  return new Response(new Uint8Array(buf), {
    headers: {
      'Content-Type': formato === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'Content-Disposition': `attachment; filename="${filename}"`,
    },
  })
})
```

(ídem para `/planificacion-produccion/export`). **Orden de rutas:** los endpoints `/xxx/export` deben registrarse ANTES de cualquier ruta con `:param` si las hubiera (en reportes no hay params dinámicos, verificar).

- [ ] **Step 3: Tests en `reportes.test.ts`**

```ts
it('export listado-ingenieria xlsx devuelve binario', async () => {
  const app = new Hono().route('/api/v1/reportes', reportes)
  const res = await app.request(`/api/v1/reportes/listado-ingenieria/export?formato=xlsx&id_variante=${varianteId}&cantidad=2&tipo_salida=arbol`, {
    headers: { Authorization: `Bearer ${adminToken}` },
  })
  expect(res.status).toBe(200)
  expect(res.headers.get('content-type')).toContain('spreadsheetml')
  const buf = Buffer.from(await res.arrayBuffer())
  expect(buf.subarray(0, 2).toString()).toBe('PK')
})

it('export planificacion-produccion pdf devuelve binario', async () => {
  const app = new Hono().route('/api/v1/reportes', reportes)
  const res = await app.request(`/api/v1/reportes/planificacion-produccion/export?formato=pdf&productos=${varianteId}:2`, {
    headers: { Authorization: `Bearer ${adminToken}` },
  })
  expect(res.status).toBe(200)
  expect(res.headers.get('content-type')).toBe('application/pdf')
  const buf = Buffer.from(await res.arrayBuffer())
  expect(buf.subarray(0, 5).toString()).toBe('%PDF-')
})
```

- [ ] **Step 4: Correr tests**

Run: `cd api && npx vitest run src/routes/reportes.test.ts && npx tsc --noEmit`
Expected: PASS (tests viejos + nuevos).

- [ ] **Step 5: Commit**

```bash
git add api/src/routes/reportes.ts api/src/routes/reportes.test.ts
git commit -m "feat(api): export XLSX/PDF de listado-ingenieria y planificacion-produccion"
```

### Task B3: Frontend — botones de export con descarga blob

**Files:**
- Create: `frontend/components/reportes/export-buttons.tsx`
- Modify: `frontend/app/(dashboard)/reportes/listado-ingenieria/page.tsx`
- Modify: `frontend/app/(dashboard)/reportes/planificacion-produccion/page.tsx`

**Interfaces:**
- Consumes: los 2 endpoints `/export` (Task B2). `exportButtons(props: { path: string; filename: string })`.

- [ ] **Step 1: Componente `export-buttons.tsx`**

```tsx
'use client'

import { useSession } from '@/lib/use-session'
import { Button } from '@/components/ui/button'
import { toast } from 'sonner'
import { FileDown } from 'lucide-react'

export function ExportButtons({ path, filename }: { path: string; filename: string }) {
  const { session } = useSession()
  const descargar = async (formato: 'xlsx' | 'pdf') => {
    if (!session) return
    const url = `${process.env.NEXT_PUBLIC_API_URL ?? 'https://api.mimrp.com.ar'}${path}&formato=${formato}`
    const res = await fetch(url, { headers: { Authorization: `Bearer ${session.accessToken}` } })
    if (!res.ok) {
      toast.error('Error al exportar')
      return
    }
    const blob = await res.blob()
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `${filename}.${formato}`
    a.click()
    URL.revokeObjectURL(a.href)
    toast.success(`Exportado ${formato.toUpperCase()}`)
  }
  return (
    <div className="flex gap-2">
      <Button variant="outline" size="sm" onClick={() => descargar('xlsx')}>
        <FileDown className="mr-2 h-4 w-4" /> Excel
      </Button>
      <Button variant="outline" size="sm" onClick={() => descargar('pdf')}>
        <FileDown className="mr-2 h-4 w-4" /> PDF
      </Button>
    </div>
  )
}
```

- [ ] **Step 2: Integrar en las 2 páginas** — en el header de cada reporte, junto al form: `<ExportButtons path="/api/v1/reportes/listado-ingenieria/export?id_variante=..." filename="listado-ingenieria" />` con los parámetros ACTUALES del form. Necesita que los parámetros del form viajen a un client component: envolver en un client wrapper que reciba los params del form (patrón: la página ya tiene un form client — agregar los botones DENTRO de ese form client usando `useFormState`/state existente).

- [ ] **Step 3: Verificar build**

Run: `cd frontend && npm run build && npm test`
Expected: OK.

- [ ] **Step 4: Commit**

```bash
git add frontend/components/reportes/ frontend/app/\(dashboard\)/reportes/
git commit -m "feat(frontend): botones export Excel/PDF en reportes con export"
```

### Task B4: Deploy fase B + smoke

- [ ] **Step 1:** Push main → Vercel deploy. Verificar en producción: descargar XLSX y PDF de los 2 reportes.
- [ ] **Step 2:** Commit de fixes si aplica: `fix(...): ajustes fase B`.

---

# Fase C (P12): Multi-empresa (selector de empresa)

### Task C1: Hook JWT multi-empresa (`active_company_id`)

**Files:**
- Create: `supabase/migrations/20260906000025_access_token_hook_multiempresa.sql`
- Modify: `api/src/routes/companies.test.ts` (test de claim con empresa activa)

**Interfaces:**
- Produces: el claim `company_id` del JWT respeta `user_metadata.active_company_id` si el usuario pertenece a esa empresa; si no, cae a la primera fila de `user_company` (comportamiento actual).

- [ ] **Step 1: Migración**

```sql
-- Hook v2: respeta user_metadata.active_company_id si el usuario pertenece a la empresa
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
BEGIN
  v_active := NULLIF(event->'user_metadata'->>'active_company_id', '')::bigint;

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
```

- [ ] **Step 2: Aplicar migración** — `npx supabase db push`.

- [ ] **Step 3: Test de integración en `companies.test.ts`**

```ts
it('hook respeta active_company_id en el claim company_id', async () => {
  const admin = await import('../lib/supabase').then((m) => m.createAdminClient())
  // 1. Empresas de Martin (el trigger fn_super_admin_auto_link ya lo vincula a cada empresa creada)
  const uc = await admin.from('user_company').select('company_id').eq('user_id', martinUserId)
  expect(uc.data!.length).toBeGreaterThanOrEqual(2) // requiere 2+ empresas en el entorno de tests
  const segunda = uc.data!.map((r) => r.company_id).sort((a, b) => b - a)[0]
  // 2. Setear active_company_id
  await admin.auth.admin.updateUserById(martinUserId, { user_metadata: { active_company_id: segunda } })
  // 3. Login → claim
  const token = await loginFresh(MARTIN) // login SIN cache: fetch directo a /auth/v1/token
  const payload = JSON.parse(Buffer.from(token.split('.')[1], 'base64url').toString())
  expect(Number(payload.company_id)).toBe(segunda)
  // 4. Cleanup: volver a la empresa default
  await admin.auth.admin.updateUserById(martinUserId, { user_metadata: { active_company_id: null } })
})
```

Nota: `loginFresh` = copia de `login()` de test-utils sin cache (fetch directo). `martinUserId` se obtiene via `admin.auth.admin.listUsers()` filtrando por email `martin@unik.ar`, o desde `user_company` join `auth.users` (ya hay grant de lectura admin). Si el entorno de test solo tiene 1 empresa, crear una segunda con `POST /api/v1/companies` (Martin es Super Administrador) y borrarla al final con `DELETE /api/v1/companies/:id`.

- [ ] **Step 4: Correr tests**

Run: `cd api && npx vitest run src/routes/companies.test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/20260906000025_access_token_hook_multiempresa.sql api/src/routes/companies.test.ts
git commit -m "feat(api): hook JWT multi-empresa vía user_metadata.active_company_id"
```

### Task C2: Server Action `switchCompany` (frontend)

**Files:**
- Modify: `frontend/lib/session.ts` (agregar `companies` NO — solo si se necesita; NO tocar shape de SessionInfo)
- Modify: `frontend/app/actions/auth.ts` (action nueva)
- Test: `frontend/lib/api.test.ts` NO aplica; probar con build + flujo manual

**Interfaces:**
- Consumes: `GET /api/v1/companies` (ya existe), `supabase.auth.updateUser` + `refreshSession` del server client.

- [ ] **Step 1: Action en `frontend/app/actions/auth.ts`**

```ts
export async function switchCompany(
  companyId: number
): Promise<{ ok: boolean; error?: string }> {
  const supabase = await createClient()
  const { data: { user } } = await supabase.auth.getUser()
  if (!user) return { ok: false, error: 'Sesión expirada' }

  // 1. Validar pertenencia vía API (RLS: el usuario ve sus propios vínculos)
  const { data: { session } } = await supabase.auth.getSession()
  if (!session) return { ok: false, error: 'Sesión expirada' }
  const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL ?? 'https://api.mimrp.com.ar'}/api/v1/companies`, {
    headers: { Authorization: `Bearer ${session.access_token}` },
  })
  const body = await res.json()
  const empresas: Array<{ company_id: number; companies: { id: number; name: string } }> = body.data ?? []
  if (!empresas.some((e) => e.company_id === companyId)) {
    return { ok: false, error: 'No pertenecés a esa empresa' }
  }

  // 2. Guardar empresa activa en user_metadata
  const { error: upErr } = await supabase.auth.updateUser({ data: { active_company_id: companyId } })
  if (upErr) return { ok: false, error: upErr.message }

  // 3. Forzar refresh del JWT para que el hook regenere los claims
  await supabase.auth.refreshSession()

  revalidatePath('/', 'layout')
  return { ok: true }
}
```

- [ ] **Step 2: Verificar** — `cd frontend && npm run build` → OK.

- [ ] **Step 3: Commit**

```bash
git add frontend/app/actions/auth.ts
git commit -m "feat(frontend): server action switchCompany con validación de pertenencia"
```

### Task C3: Selector de empresa en la Navbar

**Files:**
- Modify: `frontend/components/dashboard/navbar.tsx` (dropdown de empresa)
- Create: `frontend/components/dashboard/company-switcher.tsx` (client component que carga empresas y llama la action)

**Interfaces:**
- Consumes: `switchCompany` (Task C2), `apiFetch('/api/v1/companies')` client-side con `session.accessToken` (prop existente en Navbar).

- [ ] **Step 1: `company-switcher.tsx`**

```tsx
'use client'

import { useEffect, useState, useTransition } from 'react'
import { useRouter } from 'next/navigation'
import { Building2, Check, ChevronsUpDown } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { apiFetch } from '@/lib/api'
import { switchCompany } from '@/app/actions/auth'
import { toast } from 'sonner'

type Company = { company_id: number; companies: { id: number; name: string } }

export function CompanySwitcher({ currentCompanyId, accessToken }: { currentCompanyId: number; accessToken: string }) {
  const [empresas, setEmpresas] = useState<Company[]>([])
  const [pending, startTransition] = useTransition()
  const router = useRouter()

  useEffect(() => {
    apiFetch<{ data: Company[] }>('/api/v1/companies', accessToken)
      .then((r) => setEmpresas(r.data ?? []))
      .catch(() => {})
  }, [accessToken])

  if (empresas.length <= 1) {
    const nombre = empresas[0]?.companies?.name
    return <span className="text-sm font-medium">{nombre ?? `Empresa #${currentCompanyId}`}</span>
  }

  const cambiar = (id: number) => {
    startTransition(async () => {
      const res = await switchCompany(id)
      if (res.ok) {
        toast.success('Empresa cambiada')
        router.refresh()
      } else {
        toast.error(res.error ?? 'Error')
      }
    })
  }

  const actual = empresas.find((e) => e.company_id === currentCompanyId)
  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={
          <Button variant="ghost" size="sm" disabled={pending} className="gap-1">
            <Building2 className="h-4 w-4" />
            {actual?.companies?.name ?? `Empresa #${currentCompanyId}`}
            <ChevronsUpDown className="h-3 w-3 opacity-50" />
          </Button>
        }
      />
      <DropdownMenuContent align="start">
        <DropdownMenuLabel>Ingresar a empresa</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {empresas.map((e) => (
          <DropdownMenuItem key={e.company_id} onClick={() => cambiar(e.company_id)}>
            {e.companies?.name ?? `Empresa #${e.company_id}`}
            {e.company_id === currentCompanyId ? <Check className="ml-auto h-4 w-4" /> : null}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
```

- [ ] **Step 2: Integrar en `navbar.tsx`** — reemplazar `{session.role} · Empresa #{session.companyId}` por:

```tsx
<div className="flex items-center gap-3 text-sm text-muted-foreground">
  <CompanySwitcher currentCompanyId={session.companyId} accessToken={session.accessToken} />
  <span>{session.role}</span>
</div>
```

- [ ] **Step 3: Verificar** — `cd frontend && npm run build && npm test` → OK.

- [ ] **Step 4: Commit**

```bash
git add frontend/components/dashboard/company-switcher.tsx frontend/components/dashboard/navbar.tsx
git commit -m "feat(frontend): selector de empresa en navbar con cambio de sesión"
```

### Task C4: Deploy fase C + smoke multi-empresa

- [ ] **Step 1:** Push → deploy. Verificar: login Martin → dropdown con sus empresas → cambiar → los datos cambian de empresa (KPIs y menú).
- [ ] **Step 2:** Commit de fixes si aplica.

---

# Fase D (P13): Persistencia del agente AI en PostgreSQL

### Task D1: Estado de conversación en `agent_conversations`/`agent_messages`

**Files:**
- Modify: `api/src/routes/agent.ts` (helpers de estado → async con Supabase, fallback memoria)
- Modify: `api/src/routes/agent.test.ts` (tests de persistencia)

**Interfaces:**
- Consumes: tablas `agent_conversations` (company_id, id serial, intent, status, metadata jsonb) y `agent_messages` (conversation_id varchar(100), role, content, metadata) — ya existen en el schema (migración 3).
- Produces: mismas respuestas de los endpoints (sin cambios de contrato). El `convId` (uuid string del cliente) se guarda como `conversation_id` en `agent_messages` y como `metadata->>'conv_key'` en `agent_conversations`.

- [ ] **Step 1: Refactor de estado en `agent.ts`**

Reemplazar los helpers de memoria por funciones async que escriben en Postgres con **fallback a memoria** (serverless resilience):

```ts
import { createAdminClient } from '../lib/supabase.js'

type AgentState = { intent: string; data: Record<string, unknown>; step: number }

// Fallback en memoria (si la BD falla, la conversación sigue funcionando sin persistir)
const stateCache = new Map<string, { state: AgentState; expiresAt: number }>()
const TTL_MS = 30 * 60 * 1000

async function dbSave(convId: string, companyId: number, state: AgentState, role: 'user' | 'assistant', lastMessage: string): Promise<void> {
  try {
    const supabase = createAdminClient()
    const { data: existing } = await supabase
      .from('agent_conversations')
      .select('id')
      .eq('company_id', companyId)
      .eq('metadata->>conv_key', convId)
      .maybeSingle()
    if (!existing) {
      await supabase.from('agent_conversations').insert({
        company_id: companyId, intent: state.intent, status: 'active',
        metadata: { conv_key: convId },
      })
    }
    await supabase.from('agent_messages').insert({
      company_id: companyId, conversation_id: convId, role, content: lastMessage,
      metadata: { step: state.step },
    })
  } catch {
    // fallback silencioso: la sesión vive solo en memoria
  }
}

async function dbLoad(convId: string, companyId: number): Promise<AgentState | null> {
  // 1. memoria primero (más rápido y fresco)
  const s = stateCache.get(convId)
  if (s && Date.now() <= s.expiresAt) return s.state
  // 2. DB: reconstruir estado desde el último mensaje con metadata.step + snapshot en metadata
  try {
    const supabase = createAdminClient()
    const { data } = await supabase
      .from('agent_messages')
      .select('metadata')
      .eq('company_id', companyId)
      .eq('conversation_id', convId)
      .order('id', { ascending: false })
      .limit(1)
      .maybeSingle()
    const snap = data?.metadata?.state as AgentState | undefined
    if (snap) {
      stateCache.set(convId, { state: snap, expiresAt: Date.now() + TTL_MS })
      return snap
    }
  } catch { /* noop */ }
  return null
}
```

Además: en cada `saveState` incluir snapshot completo: `metadata: { step: state.step, state }` (el campo `metadata` de agent_messages ya es jsonb). `clearState` borra de memoria y hace `UPDATE agent_conversations SET status='cancelled' WHERE metadata->>conv_key = convId`.

Firma de handlers: `/message`, `/confirm`, `/cancel` pasan a `async` con `await dbLoad/dbSave` — la respuesta HTTP no cambia.

- [ ] **Step 2: Tests en `agent.test.ts`**

```ts
it('persiste la conversación en agent_messages', async () => {
  const app = new Hono().route('/api/v1/agent', agent)
  const convId = crypto.randomUUID()
  await app.request('/api/v1/agent/message', {
    method: 'POST',
    headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ conversation_id: convId, message: 'crear parte' }),
  })
  const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
  const { data: msgs } = await supabase.from('agent_messages').select('id, role').eq('conversation_id', convId)
  expect(msgs!.length).toBeGreaterThanOrEqual(1)
  const { data: conv } = await supabase.from('agent_conversations').select('id').eq('metadata->>conv_key', convId).maybeSingle()
  expect(conv).not.toBeNull()
})
```

- [ ] **Step 3: Correr tests**

Run: `cd api && npx vitest run src/routes/agent.test.ts && npx tsc --noEmit`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add api/src/routes/agent.ts api/src/routes/agent.test.ts
git commit -m "feat(api): persistencia de conversaciones del agente en Postgres con fallback en memoria"
```

### Task D2: Log de llamadas IA en `agent_ai_logs`

**Files:**
- Modify: `api/src/routes/agent.ts` (en la rama `general_query` que llama a Ollama, registrar log)
- Modify: `api/src/routes/agent.test.ts`

**Interfaces:**
- Consumes: tabla `agent_ai_logs` (conversation_id, model_used, provider, response_time_ms, validation_result, tokens_used).
- Produces: fila por llamada IA (fire-and-forget, nunca bloquea la respuesta).

- [ ] **Step 1: Log en la llamada IA**

En el bloque donde se hace `fetch` a Ollama para `general_query`:

```ts
const t0 = Date.now()
const aiRes = await fetch(...) // llamada existente
const elapsed = Date.now() - t0
// fire-and-forget
void createAdminClient().from('agent_ai_logs').insert({
  company_id: companyId, conversation_id: convId,
  model_used: process.env.AGENT_AI_MODEL ?? 'deepseek-v4-flash:0731',
  provider: 'ollama', response_time_ms: elapsed,
  validation_result: ok ? 'valid' : 'invalid',
}).then(() => {}, () => {})
```

- [ ] **Step 2: Test** — después de un POST `/message` con intent no guiado (ej. "hola"), verificar `agent_ai_logs` tiene fila con `conversation_id` (solo si la IA responde; si está offline el test debe tolerar 0 filas: `expect(res.status).toBe(200)` y skip de la verificación si el body indica offline).

- [ ] **Step 3: Correr tests + commit**

```bash
cd api && npx vitest run src/routes/agent.test.ts
git add api/src/routes/agent.ts api/src/routes/agent.test.ts
git commit -m "feat(api): log de llamadas IA del agente en agent_ai_logs"
```

### Task D3: Deploy fase D + smoke

- [ ] **Step 1:** Push → deploy. Smoke: chatear con el agente, recargar página, verificar conversaciones en tabla `agent_conversations` (Supabase dashboard).
- [ ] **Step 2:** Commit de fixes si aplica.

---

# Fase E (P14): Landing pública + registro self-service

### Task E1: Endpoint público `POST /api/v1/register`

**Files:**
- Create: `api/src/routes/register.ts`
- Modify: `api/src/index.ts` (montar — ruta PÚBLICA, sin requireAuth)
- Create: `api/src/routes/register.test.ts`

**Interfaces:**
- Produces: `POST /api/v1/register` body `{ empresa_nombre: string, empresa_cuit?: string, contacto_email: string(email), admin_nombre: string, admin_email: string(email), password: string(min 8) }` → `201 { data: { company_id: number } }`. Errores: 400 VALIDATION (zod/email duplicado→`email ya registrado`), 429 RATE_LIMIT (3/min por IP). Flujo: `admin.auth.admin.createUser` → INSERT company → INSERT user_company (rol "Administrador") → RPC `seed_company` → rollback cascada en error (el trigger `fn_super_admin_auto_link` vincula a Martin automáticamente).

- [ ] **Step 1: `api/src/routes/register.ts`**

```ts
import { Hono } from 'hono'
import { z } from 'zod'
import { createAdminClient } from '../lib/supabase.js'

const schema = z.object({
  empresa_nombre: z.string().min(2).max(100),
  empresa_cuit: z.string().max(20).optional(),
  contacto_email: z.string().email().max(150),
  admin_nombre: z.string().min(2).max(100),
  admin_email: z.string().email().max(150),
  password: z.string().min(8).max(72),
})

// Rate limit simple por IP (serverless: por instancia)
const intentos = new Map<string, { count: number; resetAt: number }>()
const LIMIT = 3
const WINDOW_MS = 60_000

function rateLimited(ip: string): boolean {
  const now = Date.now()
  const e = intentos.get(ip)
  if (!e || now > e.resetAt) {
    intentos.set(ip, { count: 1, resetAt: now + WINDOW_MS })
    return false
  }
  e.count++
  return e.count > LIMIT
}

function slugify(nombre: string): string {
  return nombre.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '').slice(0, 40) || 'empresa'
}

export const register = new Hono()
register.post('/', async (c) => {
  const ip = c.req.header('x-forwarded-for')?.split(',')[0]?.trim() ?? 'unknown'
  if (rateLimited(ip)) return c.json({ error: { code: 'RATE_LIMIT', message: 'Demasiados intentos, probá en un minuto' } }, 429)
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.issues[0]?.message ?? 'datos inválidos' } }, 400)
  const d = parsed.data
  const admin = createAdminClient()

  // 1. Crear usuario admin en Supabase Auth
  const { data: user, error: eUser } = await admin.auth.admin.createUser({
    email: d.admin_email, password: d.password, email_confirm: true,
    user_metadata: { full_name: d.admin_nombre, must_change_password: false },
  })
  if (eUser) return c.json({ error: { code: 'VALIDATION', message: eUser.message.includes('already') ? 'email ya registrado' : eUser.message } }, 400)

  // 2. Crear empresa + vínculo + seed (rollback cascada)
  try {
    const { data: company, error: eCompany } = await admin
      .from('companies')
      .insert({ name: d.empresa_nombre, slug: slugify(d.empresa_nombre), tax_id: d.empresa_cuit ?? null, contact_email: d.contacto_email, status: 'active' })
      .select()
      .single()
    if (eCompany) throw eCompany

    const { data: rol } = await admin.from('roles').select('id').eq('name', 'Administrador').single()
    const { error: eLink } = await admin.from('user_company').insert({
      user_id: user!.id, company_id: company!.id, role_id: rol!.id,
    })
    if (eLink) throw eLink

    const { error: eSeed } = await admin.rpc('seed_company', { p_company_id: company!.id })
    if (eSeed) throw eSeed

    return c.json({ data: { company_id: company!.id } }, 201)
  } catch (e) {
    // rollback best-effort
    await admin.from('user_company').delete().eq('user_id', user!.id)
    await admin.auth.admin.deleteUser(user!.id)
    return c.json({ error: { code: 'DB_ERROR', message: 'No se pudo crear la empresa' } }, 500)
  }
})
```

- [ ] **Step 2: Montar en `index.ts`** — agregar `app.route('/api/v1/register', register)` junto a los demás mounts (sin requireAuth global; las demás rutas lo tienen interno).

- [ ] **Step 3: Test `register.test.ts`**

```ts
import { describe, it, expect, afterAll } from 'vitest'
import { Hono } from 'hono'
import { register } from './register'
import { login, MARTIN } from '../test-utils'

describe('register (público)', () => {
  let companyId = 0
  it('crea empresa + admin + seed', async () => {
    const app = new Hono().route('/api/v1/register', register)
    const email = `admin-test-${Date.now()}@mimrp.com.ar`
    const res = await app.request('/api/v1/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        empresa_nombre: `Empresa Test ${Date.now()}`,
        contacto_email: email, admin_nombre: 'Admin Test',
        admin_email: email, password: 'Test1234!',
      }),
    })
    expect(res.status).toBe(201)
    companyId = (await res.json()).data.company_id
    // verificación: seed aplicado (unidades de la nueva empresa)
    const admin = await import('../lib/supabase').then((m) => m.createAdminClient())
    const um = await admin.from('unidades_medida').select('id').eq('company_id', companyId)
    expect(um.data!.length).toBeGreaterThanOrEqual(29)
  })
  afterAll(async () => {
    if (!companyId) return
    const token = await login(MARTIN)
    await fetch(`${process.env.SUPABASE_URL.replace('supabase.co', 'supabase.co')}`, {}) // noop
    const app = new Hono().route('/api/v1/companies', (await import('./companies')).companies)
    await app.request(`/api/v1/companies/${companyId}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token}` },
    })
  })
})
```

- [ ] **Step 4: Correr tests**

Run: `cd api && npx vitest run src/routes/register.test.ts && npx tsc --noEmit`
Expected: PASS (empresa creada con seed completo y borrada en cleanup).

- [ ] **Step 5: Commit**

```bash
git add api/src/routes/register.ts api/src/routes/register.test.ts api/src/index.ts
git commit -m "feat(api): endpoint público de registro de empresas con seed y rate limit"
```

### Task E2: Landing pública en `/` + mover panel a `/panel`

**Files:**
- Create: `frontend/app/page.tsx` (landing pública — FUERA del grupo `(dashboard)`)
- Create: `frontend/app/(dashboard)/panel/page.tsx` (mover contenido del dashboard actual)
- Delete: `frontend/app/(dashboard)/page.tsx`
- Modify: `frontend/proxy.ts` (permitir `/` y `/registro` sin sesión)
- Modify: `frontend/app/actions/auth.ts` (`signInWithPassword` → `redirect('/panel')`)
- Modify: `frontend/components/dashboard/sidebar.tsx` (link "Panel" → `/panel`)
- Test: actualizar `frontend/app/(dashboard)/configuracion/unidades/page.test.ts` si mockea la ruta `/`

**Interfaces:**
- Consumes: proxy actual (matcher excluye estáticos). Produces: `/` pública, `/panel` protegida.

- [ ] **Step 1: Landing `frontend/app/page.tsx`**

Server component simple con hero + features + CTAs a `/login` y `/registro`:

```tsx
import Link from 'next/link'
import { Button } from '@/components/ui/button'
import { Boxes, Factory, ShoppingCart, ChartLine } from 'lucide-react'

const FEATURES = [
  { icon: Boxes, title: 'Catálogo y variantes', desc: 'Partes, variantes, unidades de medida y BOM multinivel con árbol de composición.' },
  { icon: Factory, title: 'Producción', desc: 'Órdenes con máquina de estados, rutas con operaciones, centros de trabajo y planificación con Gantt.' },
  { icon: ShoppingCart, title: 'Inventario y compras', desc: 'Stock por depósito con matriz de validaciones, movimientos y compras con recepción automática.' },
  { icon: ChartLine, title: 'Sugerencias MRP', desc: 'Cálculo de fabricabilidad y requerimientos de materiales con reportes exportables.' },
]

export default function LandingPage() {
  return (
    <main className="min-h-screen">
      <section className="mx-auto max-w-5xl px-6 py-24 text-center space-y-6">
        <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">MRP · Control de Producción e Inventario</h1>
        <p className="text-lg text-muted-foreground max-w-2xl mx-auto">
          Planificá producción, controlá stock y comprá justo: del catálogo al Gantt en un solo sistema.
        </p>
        <div className="flex justify-center gap-3">
          <Button asChild size="lg"><Link href="/registro">Crear cuenta gratis</Link></Button>
          <Button asChild variant="outline" size="lg"><Link href="/login">Iniciar sesión</Link></Button>
        </div>
      </section>
      <section className="mx-auto max-w-5xl px-6 pb-24 grid gap-6 sm:grid-cols-2">
        {FEATURES.map((f) => (
          <div key={f.title} className="rounded-lg border p-6">
            <f.icon className="h-6 w-6 mb-3" />
            <h3 className="font-semibold">{f.title}</h3>
            <p className="text-sm text-muted-foreground mt-1">{f.desc}</p>
          </div>
        ))}
      </section>
    </main>
  )
}
```

Nota: si `Button` de shadcn (Base UI) no soporta `asChild`, usar el patrón `render` ya usado en el proyecto (`<Button render={<Link href="..."/>} />`) — verificar en `components/ui/button.tsx`.

- [ ] **Step 2: Mover dashboard** — `git mv`-equivalente: copiar contenido de `frontend/app/(dashboard)/page.tsx` a `(dashboard)/panel/page.tsx` y borrar el original. Actualizar el título interno ("Panel inicial" puede quedar).

- [ ] **Step 3: `proxy.ts`** — en el redirect a `/login`, excluir también `/` y `/registro`:

```ts
const PUBLIC = ['/', '/registro', '/login']
if (!user && !PUBLIC.some((p) => request.nextUrl.pathname === p || request.nextUrl.pathname.startsWith(p + '/'))) {
  // redirect a /login (lógica actual)
}
```

- [ ] **Step 4:** `signInWithPassword` → `redirect('/panel')`. Sidebar: item "Panel" → href `/panel`.

- [ ] **Step 5: Verificar**

Run: `cd frontend && npm run build && npm test`
Expected: OK.

- [ ] **Step 6: Commit**

```bash
git add frontend/app frontend/components/dashboard/sidebar.tsx frontend/proxy.ts
git commit -m "feat(frontend): landing pública en / y panel movido a /panel"
```

### Task E3: Página `/registro` (wizard 2 pasos)

**Files:**
- Create: `frontend/app/registro/page.tsx` (client component)
- Test: build + smoke manual

**Interfaces:**
- Consumes: `POST /api/v1/register` (Task E1) — fetch público SIN token.

- [ ] **Step 1: Página wizard**

Client component con 2 pasos (empresa → admin) + confirmación:

- Paso 1: `empresa_nombre`, `empresa_cuit`, `contacto_email` (validación zod local).
- Paso 2: `admin_nombre`, `admin_email`, `password` + `password_confirm` (mínimo 8, coincidencia).
- Submit: `fetch('/api/v1/register', { method: 'POST', body: JSON.stringify(data) })` con `Content-Type: application/json` (sin Authorization).
- Success: pantalla de confirmación "¡Empresa creada!" + botón a `/login` (el usuario ya existe, puede loguear).
- Errores del API (`error.message`) se muestran inline.
- Layout: usar el layout raíz (`frontend/app/layout.tsx`), sin sidebar. Centrado en card, dark/light por `next-themes`.

- [ ] **Step 2: Verificar**

Run: `cd frontend && npm run build`
Expected: OK.

- [ ] **Step 3: Commit**

```bash
git add frontend/app/registro/
git commit -m "feat(frontend): página de registro self-service con wizard de 2 pasos"
```

### Task E4: Deploy fase E + smoke E2E

- [ ] **Step 1:** Push → deploy. Smoke: `/` pública, `/registro` crea empresa real de prueba, login con el admin creado, luego borrar la empresa test con Martin.
- [ ] **Step 2:** Commit de fixes si aplica.

---

# Fase F (P15): Cierre — contraseña temporal + documentación

### Task F1: Cambio de contraseña forzado en primer login

**Files:**
- Modify: `api/src/routes/companies.ts` (crear usuario → `user_metadata.must_change_password: true`)
- Modify: `api/src/routes/empresa.ts` (PATCH /usuarios al cambiar password → limpiar flag; POST /usuarios → setear flag)
- Modify: `frontend/lib/session.ts` (exponer `mustChangePassword` desde el payload)
- Create: `frontend/app/(dashboard)/cambiar-password/page.tsx` + action
- Modify: `frontend/app/(dashboard)/layout.tsx` (redirect si `mustChangePassword`)
- Test: `api/src/routes/empresa.test.ts` (1 test del flag)

**Interfaces:**
- Produces: `SessionInfo.mustChangePassword: boolean` (desde `payload.user_metadata?.must_change_password`). El JWT de Supabase incluye `user_metadata` en el payload — verificar con un decode en el test antes de confiar.

- [ ] **Step 1: API** — en `POST /usuarios` (companies.ts y empresa.ts): agregar `user_metadata: { full_name, must_change_password: true }` al `createUser`. En `PATCH /usuarios/:userId` cuando cambia password: `admin.auth.admin.updateUserById(userId, { password, user_metadata: { must_change_password: false } })` (merge de metadata en Auth Admin API es por claves).

- [ ] **Step 2: Test en `empresa.test.ts`**

```ts
it('crear usuario setea must_change_password', async () => {
  const app = new Hono().route('/api/v1/empresa', empresa)
  const email = `nuevo-${Date.now()}@mimrp.com.ar`
  const res = await app.request('/api/v1/empresa/usuarios', {
    method: 'POST',
    headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, nombre: 'Nuevo', rol_id: 4 }),
  })
  expect(res.status).toBe(201)
  // verificar en auth.users via admin
  const admin = await import('../lib/supabase').then((m) => m.createAdminClient())
  const { data: list } = await admin.auth.admin.listUsers({ page: 1, perPage: 200 })
  const u = list!.users.find((x) => x.email === email)
  expect(u?.user_metadata?.must_change_password).toBe(true)
  // cleanup: desvincular y borrar
  await app.request(`/api/v1/empresa/usuarios/${u!.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${adminToken}` } })
  await admin.auth.admin.deleteUser(u!.id)
})
```

- [ ] **Step 3: Frontend** — `session.ts`: agregar `mustChangePassword: Boolean((payload as Record<string, unknown>).user_metadata && (payload.user_metadata as Record<string, unknown>).must_change_password)` a `SessionInfo`. Layout `(dashboard)/layout.tsx`:

```tsx
if (session.mustChangePassword) redirect('/cambiar-password')
```

- [ ] **Step 4: Página `/cambiar-password`** — client component: `password` actual + nueva + confirmación; server action `changePassword` en `frontend/app/actions/auth.ts`:

```ts
export async function changePassword(current: string, nueva: string): Promise<{ ok: boolean; error?: string }> {
  const supabase = await createClient()
  const { error } = await supabase.auth.updateUser({ password: nueva })
  if (error) return { ok: false, error: error.message }
  await supabase.auth.updateUser({ data: { must_change_password: false } })
  await supabase.auth.refreshSession()
  revalidatePath('/', 'layout')
  redirect('/panel')
}
```

- [ ] **Step 5: Verificar + commit**

```bash
cd api && npx vitest run src/routes/empresa.test.ts && cd ../frontend && npm run build
git add -A
git commit -m "feat: cambio de contraseña forzado para usuarios creados con password temporal"
```

### Task F2: Documentación final

- [ ] **Step 1: Actualizar `docs/migracion-nextjs.md`** — en la sección de Fase 4 y el estado P9, agregar bloque **"Estado P10-P15 (2026-09-06)"** con: validación de movimientos (RPC + matriz), export XLSX/PDF, multi-empresa, persistencia agente, landing/registro, must_change_password. Marcar los checkboxes de Fase 4 que correspondan.
- [ ] **Step 2: Actualizar `api/src/index.ts` comentario de versión** si existe constante VERSION (`0.3.0` → `0.4.0` en `api/src/index.ts` y el healthcheck).
- [ ] **Step 3: Commit**

```bash
git add docs/migracion-nextjs.md api/src/index.ts
git commit -m "docs: estado P10-P15 - paridad funcional con PHP completada"
```

---

## Definition of Done (global)

- [ ] `cd api && npm test` → todos los suites PASS (existentes + nuevos).
- [ ] `cd api && npx tsc --noEmit` → sin errores.
- [ ] `cd frontend && npm run build && npm test` → OK.
- [ ] Smoke en producción por fase (Tasks A5, B4, C4, D3, E4).
- [ ] `docs/migracion-nextjs.md` refleja el estado final.