import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'

const schema = z.object({
  codigo: z.string().min(1).max(20),
  nombre: z.string().min(1).max(100),
  es_deposito_venta: z.boolean().optional(),
  es_deposito_produccion: z.boolean().optional(),
  activo: z.boolean().optional(),
})

export const almacenes = new Hono<AuthEnv>()

almacenes.use('*', requireAuth)

almacenes.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error, count } = await supabase
    .from('almacenes')
    .select('*', { count: 'exact' })
    .order('id')
    .range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

almacenes.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('almacenes')
    .select('*')
    .eq('id', Number(c.req.param('id')))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrado' } }, 404)
  return c.json({ data })
})

almacenes.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('almacenes')
    .insert({ ...parsed.data, company_id: c.get('companyId') })
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

almacenes.patch('/:id', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('almacenes')
    .update(parsed.data)
    .eq('id', Number(c.req.param('id')))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

almacenes.delete(
  '/:id',
  requireRole('Super Administrador', 'Administrador'),
  async (c) => {
    const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
    const { error } = await supabase
      .from('almacenes')
      .delete()
      .eq('id', Number(c.req.param('id')))
    if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
    return c.body(null, 204)
  }
)
