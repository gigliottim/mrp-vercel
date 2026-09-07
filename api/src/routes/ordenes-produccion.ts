import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'
import { validarReferencias } from '../lib/validate-fk.js'

const ESTADOS = ['borrador', 'planificada', 'liberada', 'en_proceso', 'pausada', 'completada', 'cancelada', 'cerrada'] as const
const TRANSICIONES: Record<string, string[]> = {
  borrador: ['planificada', 'cancelada'],
  planificada: ['liberada', 'cancelada'],
  liberada: ['en_proceso', 'cancelada'],
  en_proceso: ['pausada', 'completada', 'cancelada'],
  pausada: ['en_proceso', 'cancelada'],
  completada: ['cerrada'],
  cancelada: [],
  cerrada: [],
}

const crearSchema = z.object({
  variante_id: z.number().int().positive(),
  bom_id_utilizada: z.number().int().positive().optional(),
  cantidad_planificada: z.number().positive(),
  fecha_inicio_programada: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  fecha_fin_programada: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'fecha inválida (YYYY-MM-DD)'),
  prioridad: z.enum(['baja', 'normal', 'alta', 'urgente']).optional(),
  configuracion_orden: z.record(z.string(), z.unknown()).optional(),
  observaciones: z.string().optional(),
})

const estadoSchema = z.object({ estado: z.enum(ESTADOS) })

export const ordenesProduccion = new Hono<AuthEnv>()
ordenesProduccion.use('*', requireAuth)

ordenesProduccion.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const estado = c.req.query('estado')
  const prioridad = c.req.query('prioridad')
  const q = c.req.query('q') ?? ''
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let query = supabase.from('ordenes_produccion').select('*', { count: 'exact' })
  if (estado) query = query.eq('estado', estado)
  if (prioridad) query = query.eq('prioridad', prioridad)
  if (q) query = query.ilike('numero_orden', `%${q}%`)
  const { data, error, count } = await query
    .order('id', { ascending: false })
    .range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

ordenesProduccion.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('ordenes_produccion')
    .select('*')
    .eq('id', Number(c.req.param('id')))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  return c.json({ data })
})

ordenesProduccion.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = crearSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const fk = await validarReferencias(supabase, { variantes: parsed.data.variante_id })
  if (!fk.ok) return c.json({ error: { code: 'VALIDATION', message: fk.error } }, 400)
  if (parsed.data.bom_id_utilizada) {
    const fkBom = await validarReferencias(supabase, { bom_cabecera: parsed.data.bom_id_utilizada })
    if (!fkBom.ok) return c.json({ error: { code: 'VALIDATION', message: fkBom.error } }, 400)
  }
  const { data: numero, error: eNum } = await supabase.rpc('next_numero_orden', {
    p_company_id: c.get('companyId'),
  })
  if (eNum) return c.json({ error: { code: 'DB_ERROR', message: eNum.message } }, 500)
  const { data, error } = await supabase
    .from('ordenes_produccion')
    .insert({
      ...parsed.data,
      numero_orden: numero,
      company_id: c.get('companyId'),
      estado: 'borrador',
    })
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

ordenesProduccion.patch('/:id', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = crearSchema.partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('ordenes_produccion')
    .update(parsed.data)
    .eq('id', Number(c.req.param('id')))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

ordenesProduccion.post('/:id/estado', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = estadoSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const id = Number(c.req.param('id'))
  const { data: orden } = await supabase
    .from('ordenes_produccion')
    .select('id, estado')
    .eq('id', id)
    .single()
  if (!orden) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
  const permitidos = TRANSICIONES[orden.estado] ?? []
  if (!permitidos.includes(parsed.data.estado)) {
    return c.json(
      { error: { code: 'VALIDATION', message: `Transición inválida: ${orden.estado} → ${parsed.data.estado}` } },
      400
    )
  }
  const update: Record<string, unknown> = { estado: parsed.data.estado }
  if (parsed.data.estado === 'en_proceso') update.fecha_inicio_real = new Date().toISOString()
  if (parsed.data.estado === 'completada') update.fecha_fin_real = new Date().toISOString()
  const { data, error } = await supabase
    .from('ordenes_produccion')
    .update(update)
    .eq('id', id)
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

ordenesProduccion.delete(
  '/:id',
  requireRole('Super Administrador', 'Administrador'),
  async (c) => {
    const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
    const id = Number(c.req.param('id'))
    const { data: orden } = await supabase
      .from('ordenes_produccion')
      .select('id, estado')
      .eq('id', id)
      .single()
    if (!orden) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrada' } }, 404)
    if (orden.estado !== 'borrador') {
      return c.json({ error: { code: 'VALIDATION', message: 'Solo se puede eliminar en estado borrador' } }, 400)
    }
    const { error } = await supabase.from('ordenes_produccion').delete().eq('id', id)
    if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
    return c.body(null, 204)
  }
)
