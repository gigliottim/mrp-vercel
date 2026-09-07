import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'

const schema = z.object({
  codigo: z.string().min(1).max(50),
  id_tipo: z.number().int().positive(),
  id_grupo: z.number().int().positive(),
  detalle: z.string().min(1).max(255),
  largo_alto: z.number().optional(),
  id_um_largo_alto: z.number().int().positive().optional(),
  ancho: z.number().optional(),
  id_um_ancho: z.number().int().positive().optional(),
  espesor_profundidad: z.number().optional(),
  id_um_espesor: z.number().int().positive().optional(),
  superficie: z.number().optional(),
  id_um_superficie: z.number().int().positive().optional(),
  volumen: z.number().optional(),
  id_um_volumen: z.number().int().positive().optional(),
  atributos_base: z.record(z.string(), z.unknown()).optional(),
  reglas_configuracion: z.record(z.string(), z.unknown()).optional(),
  activo: z.boolean().optional(),
  id_um_compra: z.number().int().positive().optional(),
  id_um_uso: z.number().int().positive().optional(),
  factor_conversion: z.number().optional(),
})

export const partes = new Hono<AuthEnv>()
partes.use('*', requireAuth)

partes.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const q = c.req.query('q') ?? ''
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let query = supabase.from('partes').select('*', { count: 'exact' })
  if (q) query = query.or(`codigo.ilike.%${q}%,detalle.ilike.%${q}%`)
  const { data, error, count } = await query.order('id').range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

partes.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('partes')
    .select('*')
    .eq('id', Number(c.req.param('id')))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  return c.json({ data })
})

partes.get('/:id/variantes', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('variantes')
    .select('*')
    .eq('id_parte', Number(c.req.param('id')))
    .order('id')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

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
    // Chequeos específicos primero; 'duplicate key' como fallback
    if (msg.includes('variantes_id_parte_codigo_variante_key') || (msg.includes('variante') && msg.includes('duplicate'))) {
      return c.json({ error: { code: 'CONFLICT', message: 'El código de variante ya existe para esta parte' } }, 409)
    }
    if (msg.includes('partes_company_codigo_key') || msg.includes('duplicate key')) {
      return c.json({ error: { code: 'CONFLICT', message: 'El código ya existe' } }, 409)
    }
    return c.json({ error: { code: 'DB_ERROR', message: msg } }, 500)
  }
  return c.json({ data }, 201)
})

partes.patch('/:id', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('partes')
    .update(parsed.data)
    .eq('id', Number(c.req.param('id')))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

partes.delete(
  '/:id',
  requireRole('Super Administrador', 'Administrador'),
  async (c) => {
    const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
    const { error } = await supabase
      .from('partes')
      .delete()
      .eq('id', Number(c.req.param('id')))
    if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
    return c.body(null, 204)
  }
)
