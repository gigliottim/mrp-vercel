import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'
import { validarReferencias } from '../lib/validate-fk.js'

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

export const rutasProduccion = new Hono<AuthEnv>()
rutasProduccion.use('*', requireAuth)

rutasProduccion.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const bomId = c.req.query('bom_id')
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let query = supabase.from('rutas_produccion').select('*', { count: 'exact' })
  if (bomId) query = query.eq('bom_id', Number(bomId))
  const { data, error, count } = await query.order('bom_id').range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

rutasProduccion.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('rutas_produccion')
    .select('*')
    .eq('id', Number(c.req.param('id')))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  return c.json({ data })
})

rutasProduccion.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const fk = await validarReferencias(supabase, {
    bom_cabecera: parsed.data.bom_id,
    centros_trabajo: parsed.data.centro_trabajo_id,
  })
  if (!fk.ok) return c.json({ error: { code: 'VALIDATION', message: fk.error } }, 400)
  const { data, error } = await supabase
    .from('rutas_produccion')
    .insert({ ...parsed.data, company_id: c.get('companyId') })
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

rutasProduccion.patch('/:id', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('rutas_produccion')
    .update(parsed.data)
    .eq('id', Number(c.req.param('id')))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

rutasProduccion.delete('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { error } = await supabase
    .from('rutas_produccion')
    .delete()
    .eq('id', Number(c.req.param('id')))
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.body(null, 204)
})
