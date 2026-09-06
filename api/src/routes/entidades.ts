import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth'
import { createUserClient } from '../lib/supabase'
import { parsePagination } from '../lib/pagination'

const schema = z.object({
  razon_social: z.string().min(1).max(255),
  tipo: z.enum(['PROVEEDOR', 'CLIENTE', 'AMBOS']).optional(),
  identificacion_tributaria: z.string().max(50).optional(),
  contacto_email: z.string().email().max(255).optional(),
  contacto_telefono: z.string().max(50).optional(),
  direccion: z.string().optional(),
})

export const entidades = new Hono<AuthEnv>()

entidades.use('*', requireAuth)

entidades.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error, count } = await supabase
    .from('entidades')
    .select('*', { count: 'exact' })
    .order('id')
    .range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

entidades.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('entidades')
    .select('*')
    .eq('id', Number(c.req.param('id')))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  return c.json({ data })
})

entidades.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('entidades')
    .insert({ ...parsed.data, company_id: c.get('companyId') })
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

entidades.patch('/:id', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('entidades')
    .update(parsed.data)
    .eq('id', Number(c.req.param('id')))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

entidades.delete(
  '/:id',
  requireRole('Super Administrador', 'Administrador'),
  async (c) => {
    const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
    const { error } = await supabase
      .from('entidades')
      .delete()
      .eq('id', Number(c.req.param('id')))
    if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
    return c.body(null, 204)
  }
)
