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
  fecha_efectiva: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'fecha inválida (YYYY-MM-DD)'),
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
  const { data, error, count } = await query
    .order('id', { ascending: false })
    .range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

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
  // Mapear el contrato del API (cantidad) a la columna real (cantidad_necesaria)
  const update: Record<string, number> = {}
  if (parsed.data.cantidad) update.cantidad_necesaria = parsed.data.cantidad
  if (parsed.data.unidad_medida_id) update.unidad_medida_id = parsed.data.unidad_medida_id
  const { data, error } = await supabase
    .from('bom_detalle')
    .update(update)
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

bom.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data: cabecera, error: e1 } = await supabase
    .from('bom_cabecera')
    .select('*')
    .eq('id', Number(c.req.param('id')))
    .single()
  if (e1) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  const { data: detalles, error: e2 } = await supabase
    .from('bom_detalle')
    .select('*')
    .eq('bom_id', cabecera.id)
    .order('secuencia')
  if (e2) return c.json({ error: { code: 'DB_ERROR', message: e2.message } }, 500)
  return c.json({ data: { ...cabecera, detalles } })
})

bom.post('/', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = crearSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase.rpc('crear_bom', {
    p_company_id: c.get('companyId'),
    p_variante_padre_id: parsed.data.variante_padre_id,
    p_version: parsed.data.version,
    p_fecha_efectiva: parsed.data.fecha_efectiva,
    p_detalles: parsed.data.detalles,
  })
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

bom.put('/:id/detalle', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = z.array(detalleSchema).min(1).safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
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


// ─── BOM avanzado: árbol, where-used, copiar, reemplazar ────────────────────

bom.get('/tree/:varianteId', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const varianteId = Number(c.req.param('varianteId'))
  if (!Number.isInteger(varianteId) || varianteId <= 0) {
    return c.json({ error: { code: 'VALIDATION', message: 'varianteId inválido' } }, 400)
  }
  const { data, error } = await supabase.rpc('bom_tree', {
    p_variante_id: varianteId,
    p_max_depth: 5,
  })
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data: data ?? [] })
})

bom.get('/where-used/:varianteId', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const varianteId = Number(c.req.param('varianteId'))
  if (!Number.isInteger(varianteId) || varianteId <= 0) {
    return c.json({ error: { code: 'VALIDATION', message: 'varianteId inválido' } }, 400)
  }
  const { data, error } = await supabase.rpc('bom_where_used', {
    p_variante_id: varianteId,
  })
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data: data ?? [] })
})

const copiarSchema = z.object({
  variante_origen_id: z.number().int().positive(),
  variante_destino_id: z.number().int().positive(),
})

bom.post('/copiar', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = copiarSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const { variante_origen_id: origenId, variante_destino_id: destinoId } = parsed.data
  if (origenId === destinoId) {
    return c.json({ error: { code: 'VALIDATION', message: 'La pieza de origen y de destino no pueden ser la misma' } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))

  // BOM activa del origen
  const { data: origenBom } = await supabase
    .from('bom_cabecera')
    .select('id')
    .eq('variante_padre_id', origenId)
    .eq('activa', true)
    .order('created_at', { ascending: false })
    .limit(1)
    .maybeSingle()
  if (!origenBom) {
    return c.json({ error: { code: 'NOT_FOUND', message: 'La pieza de origen no tiene una BOM activa' } }, 404)
  }

  const { data: origenDetalles, error: eDet } = await supabase
    .from('bom_detalle')
    .select('*')
    .eq('bom_id', origenBom.id)
    .order('secuencia')
  if (eDet) return c.json({ error: { code: 'DB_ERROR', message: eDet.message } }, 500)
  if (!origenDetalles || origenDetalles.length === 0) {
    return c.json({ error: { code: 'NOT_FOUND', message: 'La BOM de origen no contiene componentes de nivel 1' } }, 404)
  }

  // BOM activa del destino (o crear cabecera)
  const { data: destinoBom } = await supabase
    .from('bom_cabecera')
    .select('id')
    .eq('variante_padre_id', destinoId)
    .eq('activa', true)
    .order('created_at', { ascending: false })
    .limit(1)
    .maybeSingle()
  let destinoBomId = destinoBom?.id
  if (!destinoBomId) {
    const { data: nueva, error: eNueva } = await supabase
      .from('bom_cabecera')
      .insert({
        variante_padre_id: destinoId,
        activa: true,
        version: '1.0',
        fecha_efectiva: new Date().toISOString().slice(0, 10),
        company_id: c.get('companyId'),
      })
      .select('id')
      .single()
    if (eNueva) return c.json({ error: { code: 'DB_ERROR', message: eNueva.message } }, 500)
    destinoBomId = nueva.id
  }

  // Eliminar detalles actuales del destino
  const { error: eDel } = await supabase.from('bom_detalle').delete().eq('bom_id', destinoBomId)
  if (eDel) return c.json({ error: { code: 'DB_ERROR', message: eDel.message } }, 500)

  // Copiar validando cada componente
  let copiados = 0
  const saltados: string[] = []
  for (const item of origenDetalles) {
    const { data: validacion } = await supabase.rpc('bom_validate_add', {
      p_parent_id: destinoId,
      p_component_id: item.variante_componente_id,
    })
    const valido = validacion?.[0]?.valid ?? false
    if (!valido) {
      saltados.push(`${item.variante_componente_id}: ${validacion?.[0]?.error ?? 'No válido'}`)
      continue
    }
    const { error: eIns } = await supabase.from('bom_detalle').insert({
      bom_id: destinoBomId,
      variante_componente_id: item.variante_componente_id,
      cantidad_necesaria: item.cantidad_necesaria,
      unidad_medida_id: item.unidad_medida_id,
      secuencia: item.secuencia ?? 0,
      company_id: c.get('companyId'),
    })
    if (eIns) return c.json({ error: { code: 'DB_ERROR', message: eIns.message } }, 500)
    copiados++
  }

  return c.json({
    data: {
      copiados,
      eliminados: origenDetalles.length - copiados + saltados.length,
      saltados,
    },
  })
})

const reemplazarSchema = z.object({
  variante_origen_id: z.number().int().positive(),
  variante_nueva_id: z.number().int().positive(),
  bom_ids: z.array(z.number().int().positive()).optional(),
})

bom.post('/reemplazar', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = reemplazarSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const { variante_origen_id: origenId, variante_nueva_id: nuevaId, bom_ids } = parsed.data
  if (origenId === nuevaId) {
    return c.json({ error: { code: 'VALIDATION', message: 'La pieza a reemplazar y la de reemplazo no pueden ser la misma' } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))

  // Si no vienen bom_ids, usar todas las BOMs donde aparece el origen
  let bomIds = bom_ids
  if (!bomIds || bomIds.length === 0) {
    const { data: whereUsed } = await supabase.rpc('bom_where_used', { p_variante_id: origenId })
    bomIds = (whereUsed ?? []).map((r: { bom_id: number }) => Number(r.bom_id))
  }
  if (!bomIds || bomIds.length === 0) {
    return c.json({ error: { code: 'NOT_FOUND', message: 'La pieza seleccionada no aparece en ningún maestro activo' } }, 404)
  }

  const { data, error } = await supabase
    .from('bom_detalle')
    .update({ variante_componente_id: nuevaId })
    .eq('variante_componente_id', origenId)
    .in('bom_id', bomIds)
    .select('id')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)

  return c.json({
    data: {
      reemplazados: data?.length ?? 0,
      boms_afectadas: bomIds.length,
    },
  })
})
