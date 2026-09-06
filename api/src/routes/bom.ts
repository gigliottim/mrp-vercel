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
