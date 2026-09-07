import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'
import { validarReferencias } from '../lib/validate-fk.js'

const schema = z.object({
  id_parte: z.number().int().positive(),
  codigo_variante: z.string().min(1).max(50),
  detalle: z.string().min(1).max(255),
  estado: z.enum(['activa', 'obsoleta', 'descontinuada', 'desarrollo']).optional(),
  lote_minimo: z.number().optional(),
  punto_pedido: z.number().optional(),
  stock_seguridad: z.number().optional(),
  anticipo_compra: z.number().int().optional(),
  lead_time_produccion: z.number().int().optional(),
  peso: z.number().optional(),
  id_um_peso: z.number().int().positive().optional(),
  ubicacion_defecto: z.record(z.string(), z.unknown()).optional(),
  atributos: z.record(z.string(), z.unknown()).optional(),
  costo: z.number().optional(),
  ubicacion_cuerpo: z.string().max(100).optional(),
  ubicacion_pasillo: z.string().max(100).optional(),
  ubicacion_estante: z.string().max(100).optional(),
})

export const variantes = new Hono<AuthEnv>()
variantes.use('*', requireAuth)

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
  const { data, error, count } = (await query.order('id').range(offset, offset + perPage - 1)) as {
    data: Record<string, unknown>[] | null
    error: { message: string } | null
    count: number | null
  }
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

variantes.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('variantes')
    .select('*')
    .eq('id', Number(c.req.param('id')))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  return c.json({ data })
})

variantes.get('/:id/stock', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('variantes')
    .select(
      'id, codigo_variante, detalle, stock_actual, punto_pedido, stock_seguridad, lote_minimo, anticipo_compra, lead_time_produccion'
    )
    .eq('id', Number(c.req.param('id')))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  return c.json({ data })
})

variantes.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const fk = await validarReferencias(supabase, { partes: parsed.data.id_parte })
  if (!fk.ok) return c.json({ error: { code: 'VALIDATION', message: fk.error } }, 400)
  const { data, error } = await supabase
    .from('variantes')
    .insert({ ...parsed.data, company_id: c.get('companyId') })
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

variantes.patch('/:id', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  if (parsed.data.id_parte) {
    const fk = await validarReferencias(supabase, { partes: parsed.data.id_parte })
    if (!fk.ok) return c.json({ error: { code: 'VALIDATION', message: fk.error } }, 400)
  }
  const { data, error } = await supabase
    .from('variantes')
    .update(parsed.data)
    .eq('id', Number(c.req.param('id')))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

variantes.delete(
  '/:id',
  requireRole('Super Administrador', 'Administrador'),
  async (c) => {
    const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
    const { error } = await supabase
      .from('variantes')
      .delete()
      .eq('id', Number(c.req.param('id')))
    if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
    return c.body(null, 204)
  }
)
