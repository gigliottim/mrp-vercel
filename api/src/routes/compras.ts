import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'

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

compras.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const entidadId = c.req.query('entidad_id')
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let query = supabase.from('compras').select('*', { count: 'exact' })
  if (entidadId) query = query.eq('id_entidad', Number(entidadId))
  const { data, error, count } = await query
    .order('id', { ascending: false })
    .range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

compras.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('compras')
    .select('*')
    .eq('id', Number(c.req.param('id')))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  return c.json({ data })
})

compras.post('/', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = recibirSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
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

compras.patch('/:id', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('compras')
    .update(parsed.data)
    .eq('id', Number(c.req.param('id')))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

compras.delete('/:id', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const id = Number(c.req.param('id'))
  const { data: recibida } = await supabase
    .from('movimientos_stock')
    .select('id')
    .eq('referencia_tipo', 'compra')
    .eq('referencia_id', id)
    .limit(1)
  if (recibida && recibida.length > 0) {
    return c.json({ error: { code: 'VALIDATION', message: 'No se puede eliminar una compra con recepción' } }, 400)
  }
  const { error } = await supabase.from('compras').delete().eq('id', id)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.body(null, 204)
})
