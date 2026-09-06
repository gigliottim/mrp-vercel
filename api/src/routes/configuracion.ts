import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, type AuthEnv } from '../middleware/auth'
import { createUserClient } from '../lib/supabase'

const generalSchema = z
  .object({
    decimal_places: z.number().int().min(1).max(10).optional(),
    rounding_mode: z.enum(['half_up', 'half_down', 'half_even', 'truncate']).optional(),
    thousand_separator: z.string().length(1).optional(),
    decimal_separator: z.string().length(1).optional(),
    date_format: z.string().max(20).optional(),
    time_format: z.string().max(20).optional(),
  })
  .refine(
    (v) =>
      !(v.thousand_separator && v.decimal_separator && v.thousand_separator === v.decimal_separator),
    { message: 'separadores deben diferir' }
  )

const kvSchema = z.object({
  valor: z.string(),
  descripcion: z.string().optional(),
  tipo: z.enum(['string', 'number', 'boolean', 'json']).optional(),
})

export const configuracion = new Hono<AuthEnv>()
configuracion.use('*', requireAuth)

// GET / → configuracion_general de la empresa
configuracion.get('/', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('configuracion_general')
    .select('*')
    .eq('company_id', c.get('companyId'))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'Sin configuración' } }, 404)
  return c.json({ data })
})

// PATCH / → actualizar configuracion_general
configuracion.patch('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = generalSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('configuracion_general')
    .update(parsed.data)
    .eq('company_id', c.get('companyId'))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

// GET /kv → lista clave/valor
configuracion.get('/kv', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase.from('configuracion').select('*').order('clave')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

// PUT /kv/:clave → upsert
configuracion.put('/kv/:clave', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = kvSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const clave = c.req.param('clave')
  const { data, error } = await supabase
    .from('configuracion')
    .upsert({ clave, ...parsed.data, company_id: c.get('companyId') }, { onConflict: 'company_id,clave' })
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})
