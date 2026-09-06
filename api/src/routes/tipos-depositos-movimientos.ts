import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth'
import { createUserClient } from '../lib/supabase'
import { parsePagination } from '../lib/pagination'

const schema = z
  .object({
    tipo_deposito_origen_id: z.number().int().positive(),
    tipo_deposito_destino_id: z.number().int().positive(),
    activo: z.boolean().optional(),
    observaciones: z.string().optional(),
  })
  .refine((v) => v.tipo_deposito_origen_id !== v.tipo_deposito_destino_id, {
    message: 'origen y destino deben diferir',
  })

export const tiposDepositosMovimientos = new Hono<AuthEnv>()

tiposDepositosMovimientos.use('*', requireAuth)

async function depositoExiste(supabase: ReturnType<typeof createUserClient>, id: number) {
  const { data } = await supabase.from('tipos_depositos').select('id').eq('id', id).single()
  return data !== null
}

tiposDepositosMovimientos.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error, count } = await supabase
    .from('tipos_depositos_movimientos')
    .select('*', { count: 'exact' })
    .order('id')
    .range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

tiposDepositosMovimientos.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('tipos_depositos_movimientos')
    .select('*')
    .eq('id', Number(c.req.param('id')))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  return c.json({ data })
})

tiposDepositosMovimientos.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const origenOk = await depositoExiste(supabase, parsed.data.tipo_deposito_origen_id)
  const destinoOk = await depositoExiste(supabase, parsed.data.tipo_deposito_destino_id)
  if (!origenOk || !destinoOk) {
    return c.json({ error: { code: 'VALIDATION', message: 'deposito origen/destino inexistente' } }, 400)
  }
  const { data, error } = await supabase
    .from('tipos_depositos_movimientos')
    .insert({ ...parsed.data, company_id: c.get('companyId') })
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

tiposDepositosMovimientos.patch('/:id', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  if (parsed.data.tipo_deposito_origen_id) {
    const ok = await depositoExiste(supabase, parsed.data.tipo_deposito_origen_id)
    if (!ok) return c.json({ error: { code: 'VALIDATION', message: 'deposito origen inexistente' } }, 400)
  }
  if (parsed.data.tipo_deposito_destino_id) {
    const ok = await depositoExiste(supabase, parsed.data.tipo_deposito_destino_id)
    if (!ok) return c.json({ error: { code: 'VALIDATION', message: 'deposito destino inexistente' } }, 400)
  }
  const { data, error } = await supabase
    .from('tipos_depositos_movimientos')
    .update(parsed.data)
    .eq('id', Number(c.req.param('id')))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

tiposDepositosMovimientos.delete(
  '/:id',
  requireRole('Super Administrador', 'Administrador'),
  async (c) => {
    const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
    const { error } = await supabase
      .from('tipos_depositos_movimientos')
      .delete()
      .eq('id', Number(c.req.param('id')))
    if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
    return c.body(null, 204)
  }
)
