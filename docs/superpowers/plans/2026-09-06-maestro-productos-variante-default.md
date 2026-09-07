# Maestro de Productos + Parte con Variante Default — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Alcanzar paridad funcional completa con el PHP legacy en `/productos/maestro` (CRUD de componentes BOM, selector con búsqueda y filtros) y `/productos/partes` (parte con variante default obligatoria), manteniendo el estilo simple actual de shadcn/ui.

**Architecture:** Monorepo npm workspaces. El API Hono (`api/`) expone endpoints granulares de BOM detalle y cambia `POST /partes` a una RPC transaccional nueva (`crear_parte_con_variante`) aplicada vía `supabase_apply_migration` al proyecto `ooyiahzawilmdfualggx`. El frontend Next.js 16 App Router consume esos endpoints con server actions + `revalidatePath`. Tests de integración existentes en `api/src/routes/*.test.ts` (vitest, contra Supabase real) se extienden con los nuevos endpoints.

**Tech Stack:** Hono 4, Zod 4, Supabase Postgres (RLS multi-tenant por claim `company_id`), Next.js 16 (App Router), React 19, shadcn/ui (Base UI, `render` prop en vez de `asChild`), sonner, react-hook-form + zod.

## Global Constraints

- Estética: shadcn/ui base existente, CERO embellecimiento visual (decisión del usuario explícita).
- El API devuelve errores como `{error: {code, message}}` con códigos `VALIDATION` (400), `NOT_FOUND` (404), `CONFLICT` (409), `DB_ERROR` (500).
- Escrituras BOM/partes: `requireRole('Super Administrador', 'Administrador')` (excepto POST /partes que hoy no lo tiene — mantener igual que ahora).
- Las RPCs SECURITY DEFINER con `p_company_id` DEBEN llevar el tenant guard `IF p_company_id IS DISTINCT FROM (auth.jwt() ->> 'company_id')::bigint THEN RAISE...` (patrón fail-closed de `supabase/migrations/20260906000026_rpc_tenant_guard.sql`).
- Proyecto Supabase: `ooyiahzawilmdfualggx`. Migrations aplicadas con `supabase_apply_migration`.
- PHP legacy como referencia de comportamiento: `app/controllers/Productos/ComposicionController.php` (addItem L117-174, updateItem L273-305, deleteItem L347-363, validateCandidates L176-220), `app/controllers/Admin/PartesVariantesController.php` (validatePart L558-631, buildDefaultVariantData L417-431).
- Tablas: `partes` (PK id, unique `(company_id, codigo)`), `variantes` (FK `id_parte` → partes ON DELETE CASCADE, unique `(id_parte, codigo_variante)`), `bom_cabecera` (unique `(company_id, variante_padre_id, version)`), `bom_detalle`.
- Tests de API corren contra Supabase real: `cd api && npx vitest run src/routes/bom.test.ts` (necesita `.env` raíz con `SUPABASE_URL`, `SUPABASE_ANON_KEY`, `SUPABASE_SERVICE_ROLE_KEY`).
- Verificación de tipos: `cd api && npx tsc --noEmit` y `cd frontend && npx tsc --noEmit`.

---

### Task 1: RPC `crear_parte_con_variante` (SQL)

**Files:**
- Create: `supabase/migrations/20260906000027_crear_parte_con_variante.sql` (solo como registro; la aplicación real es vía `supabase_apply_migration`)

**Interfaces:**
- Produces: RPC `public.crear_parte_con_variante(p_company_id bigint, p_datos jsonb) RETURNS jsonb` → `{parte: {...}, variante: {...}}`. La consume Task 2 (route partes.ts) vía `supabase.rpc('crear_parte_con_variante', {...})`.

- [ ] **Step 1: Escribir la migración**

Guardar el archivo local `supabase/migrations/20260906000027_crear_parte_con_variante.sql` con este contenido exacto (y usar el mismo texto en `supabase_apply_migration` con `project_id: 'ooyiahzawilmdfualggx'`, `name: 'crear_parte_con_variante'`):

```sql
-- crear_parte_con_variante: replica createParteWithDefaultVariant() del PHP
-- (PartesVariantesController). Inserta partes + variante default atomica-
-- mente: si cualquiera falla, rollback total. Invariante: toda parte tiene
-- al menos una variante.

CREATE OR REPLACE FUNCTION public.crear_parte_con_variante(
  p_company_id bigint,
  p_datos jsonb
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_parte_id integer;
  v_codigo text := upper(trim(coalesce(p_datos->>'codigo', '')));
  v_detalle text := trim(coalesce(p_datos->>'detalle', ''));
  v_codigo_variante text;
  v_parte jsonb;
  v_variante jsonb;
BEGIN
  -- Tenant guard fail-closed (patron rpc_tenant_guard)
  IF p_company_id IS DISTINCT FROM (auth.jwt() ->> 'company_id')::bigint THEN
    RAISE EXCEPTION 'company_id no coincide con la sesion';
  END IF;

  IF v_codigo = '' THEN
    RAISE EXCEPTION 'codigo requerido';
  END IF;

  INSERT INTO partes (
    company_id, codigo, id_tipo, id_grupo, detalle,
    largo_alto, id_um_largo_alto, ancho, id_um_ancho,
    espesor_profundidad, id_um_espesor, superficie, id_um_superficie,
    volumen, id_um_volumen, activo,
    id_um_compra, id_um_uso, factor_conversion
  )
  VALUES (
    p_company_id, v_codigo,
    (p_datos->>'id_tipo')::integer,
    (p_datos->>'id_grupo')::integer,
    v_detalle,
    COALESCE((p_datos->>'largo_alto')::numeric, NULL),
    COALESCE((p_datos->>'id_um_largo_alto')::integer, NULL),
    COALESCE((p_datos->>'ancho')::numeric, NULL),
    COALESCE((p_datos->>'id_um_ancho')::integer, NULL),
    COALESCE((p_datos->>'espesor_profundidad')::numeric, NULL),
    COALESCE((p_datos->>'id_um_espesor')::integer, NULL),
    COALESCE((p_datos->>'superficie')::numeric, NULL),
    COALESCE((p_datos->>'id_um_superficie')::integer, NULL),
    COALESCE((p_datos->>'volumen')::numeric, NULL),
    COALESCE((p_datos->>'id_um_volumen')::integer, NULL),
    COALESCE((p_datos->>'activo')::boolean, TRUE),
    COALESCE((p_datos->>'id_um_compra')::integer, NULL),
    COALESCE((p_datos->>'id_um_uso')::integer, NULL),
    COALESCE((p_datos->>'factor_conversion')::numeric, NULL)
  )
  RETURNING id INTO v_parte_id;

  -- Variante default (buildDefaultVariantData del PHP)
  v_codigo_variante := COALESCE(NULLIF(v_codigo, ''), 'BASE-' || v_parte_id::text);
  v_codigo_variante := left(v_codigo_variante, 50);

  INSERT INTO variantes (
    company_id, id_parte, codigo_variante, detalle, estado,
    lote_minimo, punto_pedido
  )
  VALUES (
    p_company_id, v_parte_id, v_codigo_variante,
    COALESCE(NULLIF(v_detalle, ''), 'Variante base'),
    'activa', 1, 0
  );

  SELECT to_jsonb(p) INTO v_parte FROM partes p WHERE p.id = v_parte_id;
  SELECT to_jsonb(v) INTO v_variante FROM variantes v WHERE v.id_parte = v_parte_id ORDER BY v.id LIMIT 1;

  RETURN jsonb_build_object('parte', v_parte, 'variante', v_variante);
END;
$$;

REVOKE EXECUTE ON FUNCTION public.crear_parte_con_variante(bigint, jsonb) FROM PUBLIC, anon;
GRANT EXECUTE ON FUNCTION public.crear_parte_con_variante(bigint, jsonb) TO authenticated, service_role;
```

- [ ] **Step 2: Aplicar la migración**

Usar la tool `supabase_apply_migration` con `project_id: 'ooyiahzawilmdfualggx'`, `name: 'crear_parte_con_variante'`, `query:` el SQL completo de arriba. Confirmar respuesta sin error.

- [ ] **Step 3: Verificar la RPC existe**

Ejecutar con `supabase_execute_sql` (project `ooyiahzawilmdfualggx`):
```sql
SELECT proname FROM pg_proc WHERE proname = 'crear_parte_con_variante';
```
Expected: 1 fila.

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260906000027_crear_parte_con_variante.sql
git commit -m "feat(db): RPC crear_parte_con_variante (parte + variante default atomica)"
```

---

### Task 2: `POST /partes` usa la RPC

**Files:**
- Modify: `api/src/routes/partes.ts` (POST `/` L66-80)
- Test: `api/src/routes/partes.test.ts`

**Interfaces:**
- Consumes: RPC de Task 1: `supabase.rpc('crear_parte_con_variante', { p_company_id, p_datos })` → `{ data: { parte, variante } }`.
- Produces: `POST /api/v1/partes` → 201 `{ data: { parte, variante } }` (antes devolvía solo la parte). 409 con `"El código ya existe"` si unique `(company_id, codigo)`. Los demás endpoints sin cambios. Task 7 (frontend) depende de que la respuesta sea `{parte, variante}`.

- [ ] **Step 1: Escribir el test (fallará)**

En `api/src/routes/partes.test.ts`, reemplazar el test `crea y elimina una parte (rol admin)` por este (mismo nombre, agrega verificación de variante default):

```ts
  it('crea parte con variante default y elimina', async () => {
    // ids reales de tipos_partes y grupos_partes de la empresa 2
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    const tp = await supabase.from('tipos_partes').select('id').limit(1)
    const gp = await supabase.from('grupos_partes').select('id').limit(1)
    tipoParteId = tp.data![0].id
    grupoParteId = gp.data![0].id

    const app = new Hono().route('/api/v1/partes', partes)
    const ts = Date.now()
    const codigo = `P${ts}`.slice(0, 50)
    const res = await app.request('/api/v1/partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        codigo,
        id_tipo: tipoParteId,
        id_grupo: grupoParteId,
        detalle: `test-parte-${ts}`,
      }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    expect(body.data.parte.codigo).toBe(codigo)
    // Variante default: mismo codigo que la parte, estado activa, lote 1
    expect(body.data.variante.codigo_variante).toBe(codigo)
    expect(body.data.variante.estado).toBe('activa')
    expect(Number(body.data.variante.lote_minimo)).toBe(1)
    expect(body.data.variante.id_parte).toBe(body.data.parte.id)

    // La variante realmente persistida
    const check = await app.request(`/api/v1/partes/${body.data.parte.id}/variantes`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    const variantes = await check.json()
    expect(variantes.data.length).toBe(1)
    expect(variantes.data[0].codigo_variante).toBe(codigo)

    const del = await app.request(`/api/v1/partes/${body.data.parte.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })

  it('rechaza codigo duplicado con 409', async () => {
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    const tp = await supabase.from('tipos_partes').select('id').limit(1)
    const gp = await supabase.from('grupos_partes').select('id').limit(1)
    const app = new Hono().route('/api/v1/partes', partes)
    const ts = Date.now()
    const base = {
      id_tipo: tp.data![0].id,
      id_grupo: gp.data![0].id,
      detalle: `dup-${ts}`,
    }
    const first = await app.request('/api/v1/partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...base, codigo: `DUP${ts}` }),
    })
    expect(first.status).toBe(201)
    const dup = await app.request('/api/v1/partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...base, codigo: `DUP${ts}` }),
    })
    expect(dup.status).toBe(409)
    const errBody = await dup.json()
    expect(errBody.error.message).toContain('ya existe')
  })
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `cd api && npx vitest run src/routes/partes.test.ts`
Expected: FAIL — el test nuevo falla porque la respuesta actual no tiene `data.parte` (devuelve la parte plana) y el 409 no existe (hoy duplicado → 500).

- [ ] **Step 3: Implementar el cambio en partes.ts**

Reemplazar el handler `partes.post('/', ...)` (L66-80) por:

```ts
partes.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase.rpc('crear_parte_con_variante', {
    p_company_id: c.get('companyId'),
    p_datos: parsed.data,
  })
  if (error) {
    const msg = error.message ?? ''
    if (msg.includes('partes_company_codigo_key') || msg.includes('duplicate key')) {
      return c.json({ error: { code: 'CONFLICT', message: 'El código ya existe' } }, 409)
    }
    if (msg.includes('variante') && msg.includes('duplicate')) {
      return c.json({ error: { code: 'CONFLICT', message: 'El código de variante ya existe para esta parte' } }, 409)
    }
    return c.json({ error: { code: 'DB_ERROR', message: msg } }, 500)
  }
  return c.json({ data }, 201)
})
```

Nota: `parsed.data` pasa directo como `p_datos` (jsonb) — zod ya normalizó los campos; la RPC hace COALESCE/trim/upper de `codigo`.

- [ ] **Step 4: Correr el test para verificar que pasa**

Run: `cd api && npx vitest run src/routes/partes.test.ts`
Expected: PASS todos los tests del archivo (incluidos los preexistentes).

- [ ] **Step 5: tsc**

Run: `cd api && npx tsc --noEmit`
Expected: sin errores.

- [ ] **Step 6: Commit**

```bash
git add api/src/routes/partes.ts api/src/routes/partes.test.ts
git commit -m "feat(api): POST /partes crea parte + variante default via RPC atomica"
```

---

### Task 3: Endpoints granulares de BOM detalle

**Files:**
- Modify: `api/src/routes/bom.ts` (agregar al final, antes de la sección "BOM avanzado" o después — orden indiferente en Hono para paths distintos)
- Test: `api/src/routes/bom.test.ts`

**Interfaces:**
- Consumes: RPC existente `bom_validate_add(p_parent_id, p_component_id)` → filas `{valid: boolean, error: string | null}`. Tablas `bom_cabecera`, `bom_detalle`.
- Produces (Task 5/6 las consumen desde server actions):
  - `POST /api/v1/bom/detalle` body `{variante_padre_id: number, variante_componente_id: number, cantidad: number, unidad_medida_id: number}` → 201 `{data: detalle}` | 422 `{error: {message}}` validación | 400 zod.
  - `PATCH /api/v1/bom/detalle/:detalleId` body `{cantidad?: number, unidad_medida_id?: number}` → 200 `{data: detalle}`.
  - `DELETE /api/v1/bom/detalle/:detalleId` → 204.
  - `GET /api/v1/bom/validar-candidatos?variante_padre_id=X&candidate_ids=1,2` → `{data: {valid_ids: number[], invalid: Record<string, string>}}`.

- [ ] **Step 1: Escribir los tests (fallarán)**

En `api/src/routes/bom.test.ts`, agregar dentro del `describe('bom', ...)` al final:

```ts
  it('agrega componente a BOM existente (POST /detalle) y lo elimina', async () => {
    const app = new Hono().route('/api/v1/bom', bom)
    // variantePadreId ya tiene BOM activa (seed) — get-or-create
    const res = await app.request('/api/v1/bom/detalle', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_padre_id: variantePadreId,
        variante_componente_id: varianteHijoId,
        cantidad: 3,
        unidad_medida_id: umId,
      }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    expect(Number(body.data.cantidad_necesaria)).toBe(3)
    const detalleId = body.data.id

    // PATCH cantidad
    const patch = await app.request(`/api/v1/bom/detalle/${detalleId}`, {
      method: 'PATCH',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ cantidad: 7 }),
    })
    expect(patch.status).toBe(200)
    const patched = await patch.json()
    expect(Number(patched.data.cantidad_necesaria)).toBe(7)

    // DELETE
    const del = await app.request(`/api/v1/bom/detalle/${detalleId}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })

  it('rechaza componente recursivo con 422', async () => {
    const app = new Hono().route('/api/v1/bom', bom)
    const res = await app.request('/api/v1/bom/detalle', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_padre_id: variantePadreId,
        variante_componente_id: variantePadreId,
        cantidad: 1,
        unidad_medida_id: umId,
      }),
    })
    expect(res.status).toBe(422)
    const body = await res.json()
    expect(body.error.message).toBeTruthy()
  })

  it('validar-candidatos clasifica ids', async () => {
    const app = new Hono().route('/api/v1/bom', bom)
    const res = await app.request(
      `/api/v1/bom/validar-candidatos?variante_padre_id=${variantePadreId}&candidate_ids=${varianteHijoId},${variantePadreId}`,
      { headers: { Authorization: `Bearer ${adminToken}` } }
    )
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.valid_ids).toContain(varianteHijoId)
    expect(Object.keys(body.data.invalid)).toContain(String(variantePadreId))
  })
```

- [ ] **Step 2: Correr tests para verificar que fallan**

Run: `cd api && npx vitest run src/routes/bom.test.ts`
Expected: FAIL con 404 en los tests nuevos (rutas no existen).

- [ ] **Step 3: Implementar los endpoints en bom.ts**

Agregar después de la línea del handler `bom.delete('/:id', ...)` (antes del comentario `// ─── BOM avanzado`):

```ts
// ─── Componentes individuales (paridad con addItem/updateItem/deleteItem del PHP) ───

const addDetalleSchema = z.object({
  variante_padre_id: z.number().int().positive(),
  variante_componente_id: z.number().int().positive(),
  cantidad: z.number().positive(),
  unidad_medida_id: z.number().int().positive(),
})

const patchDetalleSchema = z.object({
  cantidad: z.number().positive().optional(),
  unidad_medida_id: z.number().int().positive().optional(),
})

async function getOrCreateBomActiva(
  supabase: ReturnType<typeof createUserClient>,
  companyId: number,
  variantePadreId: number
): Promise<{ id: number } | { error: string }> {
  const { data: existing } = await supabase
    .from('bom_cabecera')
    .select('id')
    .eq('variante_padre_id', variantePadreId)
    .eq('activa', true)
    .order('created_at', { ascending: false })
    .limit(1)
    .maybeSingle()
  if (existing) return { id: existing.id }
  const { data: created, error } = await supabase
    .from('bom_cabecera')
    .insert({
      variante_padre_id: variantePadreId,
      activa: true,
      version: '1.0',
      fecha_efectiva: new Date().toISOString().slice(0, 10),
      company_id: companyId,
    })
    .select('id')
    .single()
  if (error) return { error: error.message }
  return { id: created.id }
}

bom.post('/detalle', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = addDetalleSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))

  // Validar con la RPC (recursividad, duplicados) — igual que addItem del PHP
  const { data: validacion } = await supabase.rpc('bom_validate_add', {
    p_parent_id: parsed.data.variante_padre_id,
    p_component_id: parsed.data.variante_componente_id,
  })
  const valido = validacion?.[0]?.valid ?? false
  if (!valido) {
    return c.json(
      { error: { code: 'VALIDATION', message: validacion?.[0]?.error ?? 'Componente no permitido.' } },
      422
    )
  }

  const bomHeader = await getOrCreateBomActiva(supabase, c.get('companyId'), parsed.data.variante_padre_id)
  if ('error' in bomHeader) {
    return c.json({ error: { code: 'DB_ERROR', message: bomHeader.error } }, 500)
  }

  const { data, error } = await supabase
    .from('bom_detalle')
    .insert({
      bom_id: bomHeader.id,
      variante_componente_id: parsed.data.variante_componente_id,
      cantidad_necesaria: parsed.data.cantidad,
      unidad_medida_id: parsed.data.unidad_medida_id,
      company_id: c.get('companyId'),
    })
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

bom.patch('/detalle/:detalleId', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = patchDetalleSchema.safeParse(body)
  if (!parsed.success || (parsed.success && !parsed.data.cantidad && !parsed.data.unidad_medida_id)) {
    return c.json({ error: { code: 'VALIDATION', message: 'Cantidad o unidad requerida' } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('bom_detalle')
    .update(parsed.data)
    .eq('id', Number(c.req.param('detalleId')))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'Detalle no encontrado' } }, 404)
  return c.json({ data })
})

bom.delete('/detalle/:detalleId', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { error } = await supabase
    .from('bom_detalle')
    .delete()
    .eq('id', Number(c.req.param('detalleId')))
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.body(null, 204)
})

bom.get('/validar-candidatos', async (c) => {
  const variantePadre = Number(c.req.query('variante_padre_id') ?? 0)
  const candidateIds = (c.req.query('candidate_ids') ?? '')
    .split(',')
    .map((s) => Number(s.trim()))
    .filter((n) => Number.isInteger(n) && n > 0)
  if (!Number.isInteger(variantePadre) || variantePadre <= 0 || candidateIds.length === 0) {
    return c.json({ error: { code: 'VALIDATION', message: 'variante_padre_id y candidate_ids requeridos' } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const validIds: number[] = []
  const invalid: Record<string, string> = {}
  for (const candidateId of candidateIds) {
    const { data } = await supabase.rpc('bom_validate_add', {
      p_parent_id: variantePadre,
      p_component_id: candidateId,
    })
    if (data?.[0]?.valid) {
      validIds.push(candidateId)
    } else {
      invalid[String(candidateId)] = data?.[0]?.error ?? 'Componente no permitido.'
    }
  }
  return c.json({ data: { valid_ids: validIds, invalid } })
})
```

Nota de orden de rutas: `bom.get('/tree/:varianteId', ...)` y `bom.get('/:id', ...)` — en Hono, `GET /validar-candidatos` NO colisiona con `GET /:id` porque Hono prioriza rutas estáticas sobre params. `POST /detalle` tampoco colisiona con `POST /` (paths distintos).

- [ ] **Step 4: Correr tests para verificar que pasan**

Run: `cd api && npx vitest run src/routes/bom.test.ts`
Expected: PASS todos (incluidos preexistentes).

- [ ] **Step 5: tsc**

Run: `cd api && npx tsc --noEmit`
Expected: sin errores.

- [ ] **Step 6: Commit**

```bash
git add api/src/routes/bom.ts api/src/routes/bom.test.ts
git commit -m "feat(api): endpoints granulares POST/PATCH/DELETE /bom/detalle + validar-candidatos"
```

---

### Task 4: Server actions del maestro

**Files:**
- Modify: `frontend/app/(dashboard)/productos/maestro/actions.ts` (reemplazar `agregarComponente`, agregar editar/reemplazar/eliminar)

**Interfaces:**
- Consumes: endpoints de Task 3 (`POST/PATCH/DELETE /api/v1/bom/detalle`, `GET /api/v1/bom/validar-candidatos`) y `POST /api/v1/bom/reemplazar` (existente, body `{variante_origen_id, variante_nueva_id, bom_ids}`).
- Produces (Task 6 consume): `agregarComponente(data: {variante_padre_id, variante_componente_id, cantidad, unidad_medida_id}): Promise<ActionResult>`, `editarComponente(detalleId: number, data: {cantidad, unidad_medida_id}): Promise<ActionResult>`, `reemplazarComponente(data: {variante_origen_id, variante_nueva_id, variante_padre_id}): Promise<ActionResult>` (resuelve el bom_id server-side), `eliminarComponente(detalleId: number): Promise<ActionResult>`. Tipo `ActionResult = { ok?: boolean; error?: string }` (ya existe).

- [ ] **Step 1: Reescribir actions.ts**

Reemplazar TODO el contenido de `frontend/app/(dashboard)/productos/maestro/actions.ts` por:

```ts
'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string }

async function withSession(fn: (token: string) => Promise<void>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await fn(session.accessToken)
    revalidatePath('/productos/maestro')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function agregarComponente(data: {
  variante_padre_id: number
  variante_componente_id: number
  cantidad: number
  unidad_medida_id: number
}): Promise<ActionResult> {
  return withSession((token) =>
    apiFetch('/api/v1/bom/detalle', token, {
      method: 'POST',
      body: JSON.stringify(data),
    })
  )
}

export async function editarComponente(
  detalleId: number,
  data: { cantidad: number; unidad_medida_id: number }
): Promise<ActionResult> {
  return withSession((token) =>
    apiFetch(`/api/v1/bom/detalle/${detalleId}`, token, {
      method: 'PATCH',
      body: JSON.stringify(data),
    })
  )
}

export async function eliminarComponente(detalleId: number): Promise<ActionResult> {
  return withSession((token) =>
    apiFetch(`/api/v1/bom/detalle/${detalleId}`, token, { method: 'DELETE' })
  )
}

export async function reemplazarComponente(data: {
  variante_origen_id: number
  variante_nueva_id: number
  variante_padre_id: number
}): Promise<ActionResult> {
  return withSession(async (token) => {
    // Resolver el BOM activo del padre (bom_tree no expone bom_id)
    const bom = await apiFetch<{ data: { id: number }[] }>(
      `/api/v1/bom?variante_padre_id=${data.variante_padre_id}&perPage=1`,
      token
    )
    const bomId = bom.data?.[0]?.id
    if (!bomId) throw new Error('La variante padre no tiene BOM activa')
    await apiFetch('/api/v1/bom/reemplazar', token, {
      method: 'POST',
      body: JSON.stringify({
        variante_origen_id: data.variante_origen_id,
        variante_nueva_id: data.variante_nueva_id,
        bom_ids: [bomId],
      }),
    })
  })
}
```

(Observación: `eliminarBom` se elimina — el PHP `deleteItem` borra detalles, no cabeceras; ninguna UI la usa tras la refactorización. Verificar con `grep -rn "eliminarBom" frontend/` antes de commitear: no debe quedar referencia.)

- [ ] **Step 2: tsc**

Run: `cd frontend && npx tsc --noEmit`
Expected: FAIL — `page.tsx` y `maestro-form.tsx` aún importan `agregarComponente`/`eliminarBom` con firma vieja. Esto se resuelve en Task 6; para que este task quede compilable, NO commitear aún. Continuar con Task 5 y 6 antes del commit de este task. (Si se prefiere compilación aislada, puede posponerse el `tsc` al final de Task 6; los commits de Tasks 4-6 pueden agruparse si así lo decide el reviewer.)

- [ ] **Step 3: (commit diferido a Task 6)**

Commit de este archivo junto con Task 6 para mantener el repo compilable.

---

### Task 5: Componentes client del maestro (selector, árbol, panel, dialogs)

**Files:**
- Create: `frontend/app/(dashboard)/productos/maestro/variante-selector.tsx`
- Create: `frontend/app/(dashboard)/productos/maestro/arbol-estructura.tsx`
- Create: `frontend/app/(dashboard)/productos/maestro/detalle-panel.tsx`
- Create: `frontend/app/(dashboard)/productos/maestro/componente-dialogs.tsx`

**Interfaces:**
- Consumes: types `VarianteOpt` (Task 6 lo define en maestro-client.tsx: `{ id, codigo_variante, detalle, parte_codigo?: string | null, tipo_codigo?: string | null }`) y `UmOpt` (`{ id, simbolo }`); `TreeRow` (Task 6: filas del RPC `bom_tree`); server actions de Task 4.
- Produces (Task 6 consume):
  - `VarianteSelector({ variantes, tipos, selectedId }: { variantes: VarianteOpt[]; tipos: { codigo: string; nombre: string }[]; selectedId: number | null })` — combobox con búsqueda + filtros por tipo; navega con `router.push('/productos/maestro?id_variante=' + id)`.
  - `ArbolEstructura({ filas, selectedNodeId, onSelectNode }: { filas: TreeRow[]; selectedNodeId: number | null; onSelectNode: (id: number) => void })` — tabla indentada clicable.
  - `DetallePanel({ nodo, filas, variantes, ums, canAdmin })` — tabla de hijos del nodo con acciones + render de dialogs (agregar/editar/reemplazar/eliminar) usando los actions de Task 4. Props: `nodo: TreeRow | null`, `filas: TreeRow[]` (árbol completo, para calcular hijos), `variantes: VarianteOpt[]`, `ums: UmOpt[]`, `canAdmin: boolean`.
  - `ComponenteDialogs` se usa internamente por `DetallePanel` (no exportar si no es necesario).

- [ ] **Step 1: variante-selector.tsx**

```tsx
'use client'

import { useMemo, useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import type { VarianteOpt } from './maestro-client'

export function VarianteSelector({
  variantes,
  tipos,
  selectedId,
}: {
  variantes: VarianteOpt[]
  tipos: { codigo: string; nombre: string }[]
  selectedId: number | null
}) {
  const router = useRouter()
  const [query, setQuery] = useState('')
  const [tipoFilter, setTipoFilter] = useState('')
  const [open, setOpen] = useState(false)

  const seleccionada = variantes.find((v) => v.id === selectedId) ?? null

  const filtradas = useMemo(() => {
    const q = query.trim().toLowerCase()
    return variantes.filter((v) => {
      if (tipoFilter && v.tipo_codigo !== tipoFilter) return false
      if (!q) return true
      return (
        v.codigo_variante.toLowerCase().includes(q) ||
        (v.detalle ?? '').toLowerCase().includes(q) ||
        (v.parte_codigo ?? '').toLowerCase().includes(q)
      )
    })
  }, [variantes, query, tipoFilter])

  const handleSelect = (id: number) => {
    setOpen(false)
    setQuery('')
    router.push(`/productos/maestro?id_variante=${id}`)
  }

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap gap-2">
        <Button
          type="button"
          variant={tipoFilter === '' ? 'secondary' : 'outline'}
          size="sm"
          onClick={() => setTipoFilter('')}
        >
          Todos
        </Button>
        {tipos.map((t) => (
          <Button
            key={t.codigo}
            type="button"
            variant={tipoFilter === t.codigo ? 'secondary' : 'outline'}
            size="sm"
            title={t.nombre}
            onClick={() => setTipoFilter(tipoFilter === t.codigo ? '' : t.codigo)}
          >
            {t.codigo}
          </Button>
        ))}
      </div>
      <div className="relative w-full max-w-xl">
        <Input
          value={seleccionada && !open ? `${seleccionada.parte_codigo ?? ''} / ${seleccionada.codigo_variante}` : query}
          onChange={(e) => {
            setQuery(e.target.value)
            setOpen(true)
          }}
          onFocus={() => {
            setOpen(true)
            setQuery('')
          }}
          placeholder="Buscar producto maestro por código o descripción..."
        />
        {open ? (
          <div className="absolute top-full left-0 right-0 z-50 mt-1 max-h-80 overflow-y-auto rounded-md border bg-background shadow-md">
            {filtradas.length === 0 ? (
              <div className="p-3 text-sm text-muted-foreground">Sin resultados</div>
            ) : (
              filtradas.map((v) => (
                <button
                  key={v.id}
                  type="button"
                  className={cn(
                    'flex w-full flex-col items-start gap-0.5 border-b px-3 py-2 text-left text-sm hover:bg-muted',
                    v.id === selectedId && 'bg-muted font-medium'
                  )}
                  onClick={() => handleSelect(v.id)}
                >
                  <span className="font-medium">
                    {v.parte_codigo ? `${v.parte_codigo} / ` : ''}
                    {v.codigo_variante}
                  </span>
                  <span className="text-xs text-muted-foreground">{v.detalle}</span>
                </button>
              ))
            )}
          </div>
        ) : null}
      </div>
      {seleccionada ? (
        <p className="text-xs text-muted-foreground">
          Seleccionada: <strong>{seleccionada.codigo_variante}</strong>
          {seleccionada.detalle ? ` — ${seleccionada.detalle}` : ''}
        </p>
      ) : null}
    </div>
  )
}
```

- [ ] **Step 2: arbol-estructura.tsx**

```tsx
'use client'

import { cn } from '@/lib/utils'
import type { TreeRow } from './maestro-client'

export function ArbolEstructura({
  filas,
  selectedNodeId,
  onSelectNode,
}: {
  filas: TreeRow[]
  selectedNodeId: number | null
  onSelectNode: (id: number) => void
}) {
  return (
    <div className="rounded-md border">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b bg-muted/50">
            <th className="p-2 text-left font-medium">Código</th>
            <th className="p-2 text-left font-medium">Detalle</th>
            <th className="p-2 text-right font-medium">Cant.</th>
            <th className="p-2 text-left font-medium">Unidad</th>
          </tr>
        </thead>
        <tbody>
          {filas.map((f) => (
            <tr
              key={`${f.variante_id}-${f.nivel}-${f.bom_detalle_id ?? 'root'}`}
              className={cn(
                'cursor-pointer border-b hover:bg-muted/50',
                selectedNodeId === f.variante_id && 'bg-muted'
              )}
              onClick={() => onSelectNode(f.variante_id)}
            >
              <td className="p-2" style={{ paddingLeft: `${12 + f.nivel * 20}px` }}>
                <span className="font-medium">{f.parte_codigo}</span>
                <span className="text-muted-foreground"> / {f.codigo_variante}</span>
              </td>
              <td className="p-2">{f.variante_detalle}</td>
              <td className="p-2 text-right font-mono">{f.cantidad}</td>
              <td className="p-2">{f.unidad ?? '—'}</td>
            </tr>
          ))}
          {filas.length === 0 ? (
            <tr>
              <td colSpan={4} className="h-16 text-center text-muted-foreground">
                La variante no tiene BOM activa
              </td>
            </tr>
          ) : null}
        </tbody>
      </table>
    </div>
  )
}
```

- [ ] **Step 3: componente-dialogs.tsx**

```tsx
'use client'

import { useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { VarianteOpt, UmOpt, TreeRow } from './maestro-client'
import { agregarComponente, editarComponente, eliminarComponente, reemplazarComponente } from './actions'

type VariantePadre = { id: number; codigo: string }

function useSubmit(cerrar: () => void) {
  const [loading, setLoading] = useState(false)
  const submit = async (fn: () => Promise<{ ok?: boolean; error?: string }>) => {
    setLoading(true)
    const res = await fn()
    setLoading(false)
    if (res.error) {
      toast.error(res.error)
      return
    }
    toast.success('Operación completada')
    cerrar()
  }
  return { loading, submit }
}

export function AgregarComponenteDialog({
  open,
  onOpenChange,
  nodo,
  variantes,
  ums,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  nodo: TreeRow | null
  variantes: VarianteOpt[]
  ums: UmOpt[]
}) {
  const [componenteId, setComponenteId] = useState(0)
  const [cantidad, setCantidad] = useState('1')
  const [umId, setUmId] = useState(0)
  const { loading, submit } = useSubmit(() => onOpenChange(false))

  if (!nodo) return null
  const candidatos = variantes.filter((v) => v.id !== nodo.variante_id)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Agregar componente a {nodo.codigo_variante}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-2">
            <Label>Componente</Label>
            <Select value={String(componenteId)} onValueChange={(v) => setComponenteId(Number(v))}>
              <SelectTrigger>
                <SelectValue placeholder="Seleccionar componente" />
              </SelectTrigger>
              <SelectContent>
                {candidatos.map((v) => (
                  <SelectItem key={v.id} value={String(v.id)}>
                    {v.parte_codigo ? `${v.parte_codigo} / ` : ''}
                    {v.codigo_variante} — {v.detalle}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>Cantidad</Label>
              <Input type="number" step="any" min="0" value={cantidad} onChange={(e) => setCantidad(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>Unidad</Label>
              <Select value={String(umId)} onValueChange={(v) => setUmId(Number(v))}>
                <SelectTrigger>
                  <SelectValue placeholder="Unidad" />
                </SelectTrigger>
                <SelectContent>
                  {ums.map((u) => (
                    <SelectItem key={u.id} value={String(u.id)}>
                      {u.simbolo}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancelar
          </Button>
          <Button
            disabled={loading}
            onClick={() =>
              submit(() =>
                agregarComponente({
                  variante_padre_id: nodo.variante_id,
                  variante_componente_id: componenteId,
                  cantidad: Number(cantidad),
                  unidad_medida_id: umId,
                })
              )
            }
          >
            Agregar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function EditarComponenteDialog({
  open,
  onOpenChange,
  hijo,
  ums,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  hijo: TreeRow | null
  ums: UmOpt[]
}) {
  const [cantidad, setCantidad] = useState(String(hijo?.cantidad ?? ''))
  const [umId, setUmId] = useState(hijo?.unidad_medida_id ?? 0)
  const { loading, submit } = useSubmit(() => onOpenChange(false))

  if (!hijo?.bom_detalle_id) return null

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Editar componente {hijo.codigo_variante}</DialogTitle>
        </DialogHeader>
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label>Cantidad</Label>
            <Input type="number" step="any" min="0" value={cantidad} onChange={(e) => setCantidad(e.target.value)} />
          </div>
          <div className="space-y-2">
            <Label>Unidad</Label>
            <Select value={String(umId)} onValueChange={(v) => setUmId(Number(v))}>
              <SelectTrigger>
                <SelectValue placeholder="Unidad" />
              </SelectTrigger>
              <SelectContent>
                {ums.map((u) => (
                  <SelectItem key={u.id} value={String(u.id)}>
                    {u.simbolo}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancelar
          </Button>
          <Button
            disabled={loading}
            onClick={() =>
              submit(() =>
                editarComponente(hijo.bom_detalle_id!, {
                  cantidad: Number(cantidad),
                  unidad_medida_id: umId,
                })
              )
            }
          >
            Guardar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function ReemplazarComponenteDialog({
  open,
  onOpenChange,
  hijo,
  nodo,
  variantes,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  hijo: TreeRow | null
  nodo: TreeRow | null
  variantes: VarianteOpt[]
}) {
  const [nuevaId, setNuevaId] = useState(0)
  const { loading, submit } = useSubmit(() => onOpenChange(false))

  if (!hijo || !nodo) return null
  const candidatas = variantes.filter((v) => v.id !== hijo.variante_id)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Reemplazar {hijo.codigo_variante}</DialogTitle>
        </DialogHeader>
        <p className="text-sm text-muted-foreground">
          La variante seleccionada será reemplazada en todos los componentes de esta BOM donde aparezca.
        </p>
        <div className="space-y-2">
          <Label>Variante nueva</Label>
          <Select value={String(nuevaId)} onValueChange={(v) => setNuevaId(Number(v))}>
            <SelectTrigger>
              <SelectValue placeholder="Seleccionar variante nueva" />
            </SelectTrigger>
            <SelectContent>
              {candidatas.map((v) => (
                <SelectItem key={v.id} value={String(v.id)}>
                  {v.parte_codigo ? `${v.parte_codigo} / ` : ''}
                  {v.codigo_variante} — {v.detalle}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancelar
          </Button>
          <Button
            disabled={loading || !nuevaId}
            onClick={() =>
              submit(() =>
                reemplazarComponente({
                  variante_origen_id: hijo.variante_id,
                  variante_nueva_id: nuevaId,
                  variante_padre_id: nodo.variante_id,
                })
              )
            }
          >
            Reemplazar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function EliminarComponenteDialog({
  open,
  onOpenChange,
  hijo,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  hijo: TreeRow | null
}) {
  const { loading, submit } = useSubmit(() => onOpenChange(false))
  if (!hijo?.bom_detalle_id) return null

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>¿Eliminar componente {hijo.codigo_variante}?</AlertDialogTitle>
          <AlertDialogDescription>
            Esta acción no se puede deshacer. El componente dejará de formar parte de la BOM.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancelar</AlertDialogCancel>
          <AlertDialogAction
            disabled={loading}
            onClick={() => submit(() => eliminarComponente(hijo.bom_detalle_id!))}
          >
            Eliminar
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
```

- [ ] **Step 4: detalle-panel.tsx**

```tsx
'use client'

import { useMemo, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { VarianteOpt, UmOpt, TreeRow } from './maestro-client'
import {
  AgregarComponenteDialog,
  EditarComponenteDialog,
  EliminarComponenteDialog,
  ReemplazarComponenteDialog,
} from './componente-dialogs'

type DialogState =
  | { kind: 'agregar' }
  | { kind: 'editar'; hijo: TreeRow }
  | { kind: 'reemplazar'; hijo: TreeRow }
  | { kind: 'eliminar'; hijo: TreeRow }
  | null

export function DetallePanel({
  nodo,
  filas,
  variantes,
  ums,
  canAdmin,
}: {
  nodo: TreeRow | null
  filas: TreeRow[]
  variantes: VarianteOpt[]
  ums: UmOpt[]
  canAdmin: boolean
}) {
  const [dialog, setDialog] = useState<DialogState>(null)

  const hijos = useMemo(() => {
    if (!nodo) return []
    return filas.filter((f) => f.parent_id === nodo.variante_id && f.nivel === nodo.nivel + 1)
  }, [filas, nodo])

  if (!nodo) {
    return (
      <div className="rounded-md border p-8 text-center text-sm text-muted-foreground">
        Selecciona un nodo del árbol para ver sus componentes.
      </div>
    )
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-base font-semibold">
          {nodo.parte_codigo} / {nodo.codigo_variante}
          <span className="ml-2 text-sm font-normal text-muted-foreground">{nodo.variante_detalle}</span>
        </h2>
        {canAdmin ? (
          <Button size="sm" onClick={() => setDialog({ kind: 'agregar' })}>
            Agregar componente
          </Button>
        ) : null}
      </div>
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Código</TableHead>
              <TableHead>Detalle</TableHead>
              <TableHead className="text-right">Cantidad</TableHead>
              <TableHead>Unidad</TableHead>
              {canAdmin ? <TableHead className="text-right">Acciones</TableHead> : null}
            </TableRow>
          </TableHeader>
          <TableBody>
            {hijos.map((h) => (
              <TableRow key={h.bom_detalle_id ?? h.variante_id}>
                <TableCell>
                  {h.parte_codigo} / {h.codigo_variante}
                </TableCell>
                <TableCell>
                  {h.variante_detalle}
                  {h.tipo_codigo ? (
                    <span className="ml-1 text-xs text-muted-foreground">({h.tipo_codigo})</span>
                  ) : null}
                </TableCell>
                <TableCell className="text-right font-mono">{h.cantidad}</TableCell>
                <TableCell>{h.unidad ?? '—'}</TableCell>
                {canAdmin ? (
                  <TableCell className="text-right">
                    <DropdownMenu>
                      <DropdownMenuTrigger
                        render={
                          <Button variant="ghost" size="sm">
                            Acciones
                          </Button>
                        }
                      />
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => setDialog({ kind: 'editar', hijo: h })}>
                          Editar
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => setDialog({ kind: 'reemplazar', hijo: h })}>
                          Reemplazar
                        </DropdownMenuItem>
                        <DropdownMenuItem variant="destructive" onClick={() => setDialog({ kind: 'eliminar', hijo: h })}>
                          Eliminar
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                ) : null}
              </TableRow>
            ))}
            {hijos.length === 0 ? (
              <TableRow>
                <TableCell colSpan={canAdmin ? 5 : 4} className="h-16 text-center text-muted-foreground">
                  Este ítem no tiene componentes definidos.
                </TableCell>
              </TableRow>
            ) : null}
          </TableBody>
        </Table>
      </div>

      <AgregarComponenteDialog
        open={dialog?.kind === 'agregar'}
        onOpenChange={(o) => setDialog(o ? { kind: 'agregar' } : null)}
        nodo={nodo}
        variantes={variantes}
        ums={ums}
      />
      <EditarComponenteDialog
        open={dialog?.kind === 'editar'}
        onOpenChange={(o) => setDialog(o ? { kind: 'editar', hijo: (dialog as { hijo: TreeRow }).hijo } : null)}
        hijo={dialog?.kind === 'editar' ? dialog.hijo : null}
        ums={ums}
      />
      <ReemplazarComponenteDialog
        open={dialog?.kind === 'reemplazar'}
        onOpenChange={(o) => setDialog(o ? { kind: 'reemplazar', hijo: (dialog as { hijo: TreeRow }).hijo } : null)}
        hijo={dialog?.kind === 'reemplazar' ? dialog.hijo : null}
        nodo={nodo}
        variantes={variantes}
      />
      <EliminarComponenteDialog
        open={dialog?.kind === 'eliminar'}
        onOpenChange={(o) => setDialog(o ? { kind: 'eliminar', hijo: (dialog as { hijo: TreeRow }).hijo } : null)}
        hijo={dialog?.kind === 'eliminar' ? dialog.hijo : null}
      />
    </div>
  )
}
```

Nota sobre shadcn Base UI: este proyecto usa `render` prop en lugar de `asChild`. Si el `DropdownMenuTrigger` instalado no acepta `render`, ajustar a la firma real del componente en `frontend/components/ui/dropdown-menu.tsx` (verificar antes; el resto de la página usa patrones del repo, p. ej. `crud-page.tsx`). Lo mismo aplica a `DropdownMenuItem` si no soporta onClick directo.

- [ ] **Step 5: tsc (parcial — page.tsx aún no consume)**

Run: `cd frontend && npx tsc --noEmit`
Expected: FAIL solo por `page.tsx`/`maestro-form.tsx` (imports viejos). Los archivos nuevos no deben tener errores propios (verificar en output).

---

### Task 6: Reescribir `page.tsx` del maestro y eliminar `maestro-form.tsx`

**Files:**
- Modify: `api/src/routes/variantes.ts` (GET `/` con join opcional a parte)
- Modify: `frontend/app/(dashboard)/productos/maestro/page.tsx` (reemplazo completo)
- Create: `frontend/app/(dashboard)/productos/maestro/maestro-client.tsx`
- Delete: `frontend/app/(dashboard)/productos/maestro/maestro-form.tsx`
- Modify: `frontend/app/(dashboard)/productos/maestro/actions.ts` (ya hecho en Task 4)

**Interfaces:**
- Consumes: `VarianteSelector`, `ArbolEstructura`, `DetallePanel` (Task 5); actions (Task 4); `apiFetch`.
- Produces: página `/productos/maestro` funcional completa. `GET /api/v1/variantes?with_parte=1` devuelve cada variante con campos adicionales `parte_codigo`, `parte_detalle`, `tipo_codigo` (join a `partes` + `tipos_partes` — replica `allWithPartes()` del PHP). `maestro-client.tsx` exporta tipos `VarianteOpt`, `UmOpt`, `TreeRow` que consumen los componentes de Task 5.

- [ ] **Step 0: Extender GET /variantes con datos de la parte**

En `api/src/routes/variantes.ts`, reemplazar el handler `variantes.get('/', ...)` (L31-40) por:

```ts
variantes.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const idParte = c.req.query('id_parte')
  const withParte = c.req.query('with_parte') === '1'
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const select = withParte
    ? '*, partes(id, codigo, detalle, tipos_partes(codigo))'
    : '*'
  let query = supabase.from('variantes').select(select, { count: 'exact' })
  if (idParte) query = query.eq('id_parte', Number(idParte))
  const { data, error, count } = await query.order('id').range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  const rows = (data ?? []).map((v: Record<string, unknown>) => {
    if (!withParte) return v
    const parte = (v.partes ?? {}) as Record<string, unknown>
    const tipo = (parte.tipos_partes ?? {}) as Record<string, unknown>
    const { partes: _p, ...rest } = v
    return {
      ...rest,
      parte_codigo: parte.codigo ?? null,
      parte_detalle: parte.detalle ?? null,
      tipo_codigo: tipo.codigo ?? null,
    }
  })
  return c.json({ data: rows, pagination: { page, perPage, total: count } })
})
```

Nota: el FK `variantes.id_parte → partes(id)` permite el embed `partes(...)`; y `partes.id_tipo → tipos_partes(id)` permite `tipos_partes(codigo)` (relación por FK, PostgREST la infiere automáticamente).

- [ ] **Step 1: Reemplazar page.tsx completo**

```tsx
import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { VarianteSelector } from './variante-selector'
import { MaestroClient, type VarianteOpt, type UmOpt, type TreeRow } from './maestro-client'

export const dynamic = 'force-dynamic'

export default async function MaestroPage({
  searchParams,
}: {
  searchParams: Promise<{ id_variante?: string }>
}) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const varianteId = Number(sp.id_variante ?? 0)

  const [variantesRes, umsRes, tiposRes, treeRes] = await Promise.all([
    apiFetch<Paginated<VarianteOpt>>(
      '/api/v1/variantes?perPage=500&with_parte=1',
      session.accessToken
    ).catch(() => null),
    apiFetch<Paginated<UmOpt>>('/api/v1/unidades-medida?perPage=100', session.accessToken).catch(
      () => null
    ),
    apiFetch<Paginated<{ id: number; codigo: string; nombre: string }>>(
      '/api/v1/tipos-partes?perPage=100',
      session.accessToken
    ).catch(() => null),
    varianteId > 0
      ? apiFetch<{ data: TreeRow[] }>(`/api/v1/bom/tree/${varianteId}`, session.accessToken).catch(
          () => null
        )
      : Promise.resolve(null),
  ])

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Maestro de Productos</h1>
        <p className="text-sm text-muted-foreground">Composición de variantes en árbol</p>
      </div>

      <VarianteSelector
        variantes={variantesRes?.data ?? []}
        tipos={tiposRes?.data ?? []}
        selectedId={varianteId > 0 ? varianteId : null}
      />

      {varianteId > 0 ? (
        <MaestroClient
          filas={treeRes?.data ?? []}
          variantes={variantesRes?.data ?? []}
          ums={umsRes?.data ?? []}
          canAdmin={canAdmin}
        />
      ) : (
        <p className="text-sm text-muted-foreground">
          Buscá y seleccioná un producto maestro para ver su estructura.
        </p>
      )}
    </div>
  )
}
```

Y crear `frontend/app/(dashboard)/productos/maestro/maestro-client.tsx`:

```tsx
'use client'

import { useState } from 'react'
import { ArbolEstructura } from './arbol-estructura'
import { DetallePanel } from './detalle-panel'

export type VarianteOpt = {
  id: number
  codigo_variante: string
  detalle: string
  parte_codigo?: string | null
  parte_detalle?: string | null
  tipo_codigo?: string | null
}

export type UmOpt = { id: number; simbolo: string }

export type TreeRow = {
  variante_id: number
  parte_codigo: string
  parte_detalle: string
  codigo_variante: string
  variante_detalle: string
  tipo_codigo: string | null
  parent_id: number | null
  bom_detalle_id: number | null
  cantidad: number
  unidad_medida_id: number | null
  unidad: string | null
  nivel: number
}

export function MaestroClient({
  filas,
  variantes,
  ums,
  canAdmin,
}: {
  filas: TreeRow[]
  variantes: VarianteOpt[]
  ums: UmOpt[]
  canAdmin: boolean
}) {
  const [selectedNodeId, setSelectedNodeId] = useState<number | null>(
    filas.length > 0 ? filas[0].variante_id : null
  )
  const nodo = filas.find((f) => f.variante_id === selectedNodeId) ?? null

  return (
    <div className="space-y-6">
      <ArbolEstructura filas={filas} selectedNodeId={selectedNodeId} onSelectNode={setSelectedNodeId} />
      <DetallePanel
        nodo={nodo}
        filas={filas}
        variantes={variantes}
        ums={ums}
        canAdmin={canAdmin}
      />
    </div>
  )
}
```

Nota: `VarianteSelector` (Task 5) importa los tipos de `./maestro-client`, no de `./page` — ajustar su línea de import a `import type { VarianteOpt } from './maestro-client'`. Igualmente `arbol-estructura.tsx`, `detalle-panel.tsx` y `componente-dialogs.tsx` importan `VarianteOpt`, `UmOpt`, `TreeRow` desde `'./maestro-client'`.

- [ ] **Step 2: Eliminar maestro-form.tsx**

```bash
rm frontend/app/\(dashboard\)/productos/maestro/maestro-form.tsx
grep -rn "maestro-form\|MaestroForm\|eliminarBom" frontend/app frontend/components || echo "sin referencias"
```
Expected: "sin referencias".

- [ ] **Step 3: tsc**

Run: `cd frontend && npx tsc --noEmit`
Expected: PASS sin errores.

- [ ] **Step 4: Build frontend**

Run: `cd frontend && npx next build`
Expected: build exitoso.

- [ ] **Step 5: Commit (incluye actions.ts de Task 4 y variantes.ts de Step 0)**

```bash
git add api/src/routes/variantes.ts "frontend/app/(dashboard)/productos/maestro/"
git commit -m "feat(frontend): maestro de productos con selector, arbol clicable y CRUD de componentes"
```

---

### Task 7: Formulario de parte extendido (UM + factor + dimensiones con unidad)

**Files:**
- Modify: `frontend/app/(dashboard)/productos/partes/partes-form.tsx` (reemplazo completo)
- Modify: `frontend/app/(dashboard)/productos/partes/page.tsx` (pasa `unidades` al form)
- Modify: `frontend/app/(dashboard)/productos/partes/actions.ts` (solo mensaje de éxito)

**Interfaces:**
- Consumes: `POST /api/v1/partes` (Task 2 — crea parte+variante); `/api/v1/unidades-medida?perPage=500` (page.tsx las pasa como `unidades`).
- Produces: `ParteForm` con props extendidas: agrega `unidades?: UmOpt[]` (tipo `{ id: number; unidad: string; simbolo: string; tipo: string }`). `ParteRow` extiende con `id_um_largo_alto, id_um_ancho, id_um_espesor, superficie, id_um_superficie, volumen, id_um_volumen, id_um_compra, id_um_uso, factor_conversion` (todos `number | null`). La página y CrudPage se mantienen igual.

- [ ] **Step 1: Reescribir partes-form.tsx**

```tsx
'use client'

import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

export type ParteRow = {
  id: number
  codigo: string
  id_tipo: number
  id_grupo: number
  detalle: string
  largo_alto: number | null
  id_um_largo_alto: number | null
  ancho: number | null
  id_um_ancho: number | null
  espesor_profundidad: number | null
  id_um_espesor: number | null
  superficie: number | null
  id_um_superficie: number | null
  volumen: number | null
  id_um_volumen: number | null
  id_um_compra: number | null
  id_um_uso: number | null
  factor_conversion: number | null
  activo: boolean
}

export type TipoParte = { id: number; codigo: string; nombre: string }
export type GrupoParte = { id: number; codigo: string; nombre: string }
export type UmOpt = { id: number; unidad: string; simbolo: string; tipo: string }

const schema = z
  .object({
    codigo: z.string().min(1).max(50),
    id_tipo: z.coerce.number().int().positive(),
    id_grupo: z.coerce.number().int().positive(),
    detalle: z.string().min(1).max(255),
    largo_alto: z.coerce.number().optional(),
    id_um_largo_alto: z.coerce.number().optional(),
    ancho: z.coerce.number().optional(),
    id_um_ancho: z.coerce.number().optional(),
    espesor_profundidad: z.coerce.number().optional(),
    id_um_espesor: z.coerce.number().optional(),
    superficie: z.coerce.number().optional(),
    id_um_superficie: z.coerce.number().optional(),
    volumen: z.coerce.number().optional(),
    id_um_volumen: z.coerce.number().optional(),
    activo: z.boolean().optional(),
    id_um_compra: z.coerce.number().optional(),
    id_um_uso: z.coerce.number().optional(),
    factor_conversion: z.coerce.number().optional(),
  })
  .superRefine((data, ctx) => {
    // Paridad con validatePart() del PHP: factor requerido si compra != uso
    if (
      data.id_um_compra &&
      data.id_um_uso &&
      data.id_um_compra !== data.id_um_uso &&
      (!data.factor_conversion || data.factor_conversion <= 0)
    ) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['factor_conversion'],
        message: 'El factor de conversión es requerido cuando las unidades son diferentes.',
      })
    }
  })

export function ParteForm({
  initial,
  tipos = [],
  grupos = [],
  unidades = [],
  onSubmit,
  onCancel,
}: {
  initial: ParteRow | null
  tipos?: TipoParte[]
  grupos?: GrupoParte[]
  unidades?: UmOpt[]
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      codigo: initial?.codigo ?? '',
      id_tipo: initial?.id_tipo ?? 0,
      id_grupo: initial?.id_grupo ?? 0,
      detalle: initial?.detalle ?? '',
      largo_alto: initial?.largo_alto ?? undefined,
      id_um_largo_alto: initial?.id_um_largo_alto ?? undefined,
      ancho: initial?.ancho ?? undefined,
      id_um_ancho: initial?.id_um_ancho ?? undefined,
      espesor_profundidad: initial?.espesor_profundidad ?? undefined,
      id_um_espesor: initial?.id_um_espesor ?? undefined,
      superficie: initial?.superficie ?? undefined,
      id_um_superficie: initial?.id_um_superficie ?? undefined,
      volumen: initial?.volumen ?? undefined,
      id_um_volumen: initial?.id_um_volumen ?? undefined,
      activo: initial?.activo ?? true,
      id_um_compra: initial?.id_um_compra ?? undefined,
      id_um_uso: initial?.id_um_uso ?? undefined,
      factor_conversion: initial?.factor_conversion ?? undefined,
    },
  })

  const umCompra = watch('id_um_compra')
  const umUso = watch('id_um_uso')
  const unidadesDistintas = Boolean(umCompra && umUso && umCompra !== umUso)

  // Factor forzado a 1 si las unidades son iguales (paridad con el PHP)
  useEffect(() => {
    if (umCompra && umUso && umCompra === umUso) {
      setValue('factor_conversion', 1)
    }
  }, [umCompra, umUso, setValue])

  const umsPor = (tipo: string) => unidades.filter((u) => u.tipo === tipo)
  const umSelect = (
    name: 'id_um_largo_alto' | 'id_um_ancho' | 'id_um_espesor' | 'id_um_superficie' | 'id_um_volumen' | 'id_um_compra' | 'id_um_uso',
    lista: UmOpt[]
  ) => (
    <Select value={String(watch(name) ?? '')} onValueChange={(v) => setValue(name, v ? Number(v) : undefined)}>
      <SelectTrigger>
        <SelectValue placeholder="UM" />
      </SelectTrigger>
      <SelectContent>
        {lista.map((u) => (
          <SelectItem key={u.id} value={String(u.id)}>
            {u.simbolo}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )

  return (
    <form
      onSubmit={handleSubmit((values) =>
        onSubmit({
          ...values,
          codigo: String(values.codigo ?? '').toUpperCase(),
          // factor 1 explícito cuando unidades iguales
          factor_conversion: unidadesDistintas ? values.factor_conversion : 1,
        } as Record<string, unknown>)
      )}
      className="space-y-4"
    >
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>Código</Label>
          <Input {...register('codigo')} placeholder="P-001" style={{ textTransform: 'uppercase' }} />
          {errors.codigo ? <p className="text-sm text-destructive">{errors.codigo.message}</p> : null}
        </div>
        <div className="space-y-2">
          <Label>Detalle</Label>
          <Input {...register('detalle')} placeholder="Descripción de la parte" />
          {errors.detalle ? <p className="text-sm text-destructive">{errors.detalle.message}</p> : null}
        </div>
        <div className="space-y-2">
          <Label>Tipo</Label>
          <Select value={String(watch('id_tipo') ?? 0)} onValueChange={(v) => setValue('id_tipo', Number(v))}>
            <SelectTrigger>
              <SelectValue placeholder="Tipo de parte" />
            </SelectTrigger>
            <SelectContent>
              {tipos.map((t) => (
                <SelectItem key={t.id} value={String(t.id)}>
                  {t.nombre} ({t.codigo})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.id_tipo ? <p className="text-sm text-destructive">{errors.id_tipo.message}</p> : null}
        </div>
        <div className="space-y-2">
          <Label>Grupo</Label>
          <Select value={String(watch('id_grupo') ?? 0)} onValueChange={(v) => setValue('id_grupo', Number(v))}>
            <SelectTrigger>
              <SelectValue placeholder="Grupo de partes" />
            </SelectTrigger>
            <SelectContent>
              {grupos.map((g) => (
                <SelectItem key={g.id} value={String(g.id)}>
                  {g.nombre} ({g.codigo})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.id_grupo ? <p className="text-sm text-destructive">{errors.id_grupo.message}</p> : null}
        </div>
      </div>

      <div className="space-y-2">
        <Label>Descripción extendida</Label>
        <Textarea {...register('detalle')} rows={2} placeholder="Descripción de la parte" />
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="space-y-2">
          <Label>UM Compra</Label>
          {umSelect('id_um_compra', unidades)}
        </div>
        <div className="space-y-2">
          <Label>UM Uso</Label>
          {umSelect('id_um_uso', unidades)}
        </div>
        {unidadesDistintas ? (
          <div className="space-y-2">
            <Label>Factor de conversión (1 compra = ? uso)</Label>
            <Input type="number" step="any" min="0" {...register('factor_conversion')} />
            {errors.factor_conversion ? (
              <p className="text-sm text-destructive">{errors.factor_conversion.message}</p>
            ) : null}
          </div>
        ) : null}
      </div>

      <div className="grid grid-cols-5 gap-4">
        <div className="space-y-2">
          <Label>Largo / Alto</Label>
          <Input type="number" step="any" {...register('largo_alto')} />
          {umSelect('id_um_largo_alto', umsPor('longitud'))}
        </div>
        <div className="space-y-2">
          <Label>Ancho</Label>
          <Input type="number" step="any" {...register('ancho')} />
          {umSelect('id_um_ancho', umsPor('longitud'))}
        </div>
        <div className="space-y-2">
          <Label>Espesor / Prof.</Label>
          <Input type="number" step="any" {...register('espesor_profundidad')} />
          {umSelect('id_um_espesor', umsPor('longitud'))}
        </div>
        <div className="space-y-2">
          <Label>Superficie</Label>
          <Input type="number" step="any" {...register('superficie')} />
          {umSelect('id_um_superficie', umsPor('superficie'))}
        </div>
        <div className="space-y-2">
          <Label>Volumen</Label>
          <Input type="number" step="any" {...register('volumen')} />
          {umSelect('id_um_volumen', umsPor('volumen'))}
        </div>
      </div>

      <div className="flex items-center gap-2">
        <Switch checked={watch('activo') ?? true} onCheckedChange={(v) => setValue('activo', v)} />
        <Label>Activo</Label>
      </div>
      <p className="text-xs text-muted-foreground">
        Al crear la parte se genera automáticamente una variante base con el mismo código.
      </p>
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancelar
        </Button>
        <Button type="submit">Guardar</Button>
      </div>
    </form>
  )
}
```

- [ ] **Step 2: page.tsx pasa `unidades` al form**

En `frontend/app/(dashboard)/productos/partes/page.tsx`:
1. Agregar al `Promise.all` el fetch de unidades:
```ts
    apiFetch<Paginated<{ id: number; unidad: string; simbolo: string; tipo: string }>>(
      '/api/v1/unidades-medida?perPage=500',
      session.accessToken
    ).catch(() => null),
```
2. Desestructurarlo como `unidades` junto a `[result, tipos, grupos]`.
3. Cambiar `formExtraProps` para incluir las unidades:
```tsx
formExtraProps={{ tipos: tipos?.data ?? [], grupos: grupos?.data ?? [], unidades: unidades?.data ?? [] }}
```

- [ ] **Step 3: actions.ts mensaje de éxito**

En `frontend/app/(dashboard)/productos/partes/actions.ts`, función `crear`: cambiar `return { ok: true }` por:
```ts
    revalidatePath('/productos/partes')
    return { ok: 'Parte creada con su variante base' }
```
Verificar que `CrudPage` muestra el mensaje: en `crud-page.tsx` L80 se hace `toast.success(editing ? 'Actualizado' : 'Creado')` — si solo acepta boolean, dejar `{ ok: true }` y no cambiar actions.ts (el toast genérico alcanza). Inspeccionar el componente antes de decidir; la decisión mínima es no tocar actions.ts.

- [ ] **Step 4: tsc + build**

Run: `cd frontend && npx tsc --noEmit && npx next build`
Expected: PASS ambos.

- [ ] **Step 5: Commit**

```bash
git add frontend/app/\(dashboard\)/productos/partes/
git commit -m "feat(frontend): formulario de parte con UM, factor de conversion y variante default"
```

---

### Task 8: Verificación end-to-end manual + advisors

**Files:**
- Ninguno nuevo (verificación)

**Interfaces:**
- Consumes: todo lo anterior.

- [ ] **Step 1: Correr la suite completa del API**

Run: `cd api && npx vitest run`
Expected: PASS (todos los archivos de test, incluidos bom.test.ts y partes.test.ts extendidos).

- [ ] **Step 2: tsc de ambos workspaces**

Run: `cd api && npx tsc --noEmit && cd ../frontend && npx tsc --noEmit`
Expected: sin errores.

- [ ] **Step 3: Security advisors de Supabase**

Usar `supabase_get_advisors` (project `ooyiahzawilmdfualggx`, type `security`). Revisar que no aparezcan issues nuevos relacionados con la RPC `crear_parte_con_variante` (la RPC es SECURITY DEFINER: verificar que figure con el REVOKE/GRANT aplicado — el advisor de "function search path mutable" no debe aparecer porque la función define `SET search_path = public`).

- [ ] **Step 4: Verificación manual funcional (dev)**

Con `cd frontend && npx next dev` + `cd api && npx tsx watch src/index.ts` (o contra el API deployada):
1. Login → `/productos/partes` → Nueva parte → completar con UM compra/uso distintas y factor → Guardar → verificar en `/productos/partes/manager` que la variante default existe con el mismo código, estado activa, lote 1.
2. Crear parte con código existente → toast de error "El código ya existe".
3. `/productos/maestro` → buscar variante por texto en el combobox → filtrar por tipo → seleccionar → árbol visible.
4. Seleccionar nodo → Agregar componente → elegir componente y cantidad → aparece en el árbol tras revalidate.
5. Intentar agregar la propia variante como componente → toast de error de validación (422).
6. Editar cantidad de un componente → valor actualizado.
7. Reemplazar componente por otra variante → el árbol refleja el cambio.
8. Eliminar componente → desaparece.
9. Como usuario no-admin (sabrinasmurro22@gmail.com / sabrina1986_): botones de escritura ocultos (canAdmin false) y POST directo al API responde 403.

- [ ] **Step 5: Commit final (si hay ajustes menores)**

```bash
git add -A && git commit -m "chore: ajustes de verificacion maestro + parte con variante default"
```
(Solo si la verificación produjo cambios; si no, omitir.)