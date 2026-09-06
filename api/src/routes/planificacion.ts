import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'
import { validarReferencias } from '../lib/validate-fk.js'

const schema = z
  .object({
    orden_produccion_id: z.number().int().positive(),
    operacion_id: z.number().int().positive().nullable().optional(),
    centro_trabajo_id: z.number().int().positive(),
    inicio: z.string().datetime(),
    fin: z.string().datetime(),
    estado: z.enum(['programado', 'en_ejecucion', 'completado']).optional(),
  })
  .refine((v) => new Date(v.inicio) < new Date(v.fin), { message: 'inicio debe ser anterior a fin' })

export const planificacion = new Hono<AuthEnv>()
planificacion.use('*', requireAuth)

planificacion.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const ordenId = c.req.query('orden_produccion_id')
  const centroId = c.req.query('centro_trabajo_id')
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let query = supabase.from('planificacion_recursos').select('*', { count: 'exact' })
  if (ordenId) query = query.eq('orden_produccion_id', Number(ordenId))
  if (centroId) query = query.eq('centro_trabajo_id', Number(centroId))
  const { data, error, count } = await query
    .order('id')
    .range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

planificacion.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const fk = await validarReferencias(supabase, {
    ordenes_produccion: parsed.data.orden_produccion_id,
    centros_trabajo: parsed.data.centro_trabajo_id,
  })
  if (!fk.ok) return c.json({ error: { code: 'VALIDATION', message: fk.error } }, 400)

  const { data: solapa, error: eSol } = await supabase.rpc('verificar_solapamiento', {
    p_company_id: c.get('companyId'),
    p_centro_trabajo_id: parsed.data.centro_trabajo_id,
    p_inicio: parsed.data.inicio,
    p_fin: parsed.data.fin,
    p_excluir_id: null,
  })
  if (eSol) return c.json({ error: { code: 'DB_ERROR', message: eSol.message } }, 500)
  if (solapa) {
    return c.json({ error: { code: 'VALIDATION', message: 'Periodo se solapa con otro recurso del centro de trabajo' } }, 400)
  }

  const { data, error } = await supabase
    .from('planificacion_recursos')
    .insert({
      orden_produccion_id: parsed.data.orden_produccion_id,
      operacion_id: parsed.data.operacion_id ?? null,
      centro_trabajo_id: parsed.data.centro_trabajo_id,
      periodo: `[${parsed.data.inicio},${parsed.data.fin})`,
      estado: parsed.data.estado ?? 'programado',
      company_id: c.get('companyId'),
    })
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

planificacion.patch('/:id', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const id = Number(c.req.param('id'))
  const { data: actual } = await supabase
    .from('planificacion_recursos')
    .select('id, centro_trabajo_id, periodo')
    .eq('id', id)
    .single()
  if (!actual) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrado' } }, 404)

  if (parsed.data.inicio || parsed.data.fin) {
    const inicio = parsed.data.inicio ?? actual.periodo.replace(/[\[\)].*/, '').replace(/\[/, '')
    const fin = parsed.data.fin ?? actual.periodo.replace(/.*,/, '').replace(/\)/, '')
    const { data: solapa, error: eSol } = await supabase.rpc('verificar_solapamiento', {
      p_company_id: c.get('companyId'),
      p_centro_trabajo_id: parsed.data.centro_trabajo_id ?? actual.centro_trabajo_id,
      p_inicio: inicio,
      p_fin: fin,
      p_excluir_id: id,
    })
    if (eSol) return c.json({ error: { code: 'DB_ERROR', message: eSol.message } }, 500)
    if (solapa) {
      return c.json({ error: { code: 'VALIDATION', message: 'Periodo se solapa con otro recurso del centro de trabajo' } }, 400)
    }
  }

  const update: Record<string, unknown> = { ...parsed.data }
  if (parsed.data.inicio || parsed.data.fin) {
    const inicio = parsed.data.inicio ?? actual.periodo.split(',')[0].replace('[', '')
    const fin = parsed.data.fin ?? actual.periodo.split(',')[1].replace(')', '')
    update.periodo = `[${inicio},${fin})`
    delete update.inicio
    delete update.fin
  }
  const { data, error } = await supabase
    .from('planificacion_recursos')
    .update(update)
    .eq('id', id)
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

planificacion.delete('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { error } = await supabase
    .from('planificacion_recursos')
    .delete()
    .eq('id', Number(c.req.param('id')))
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.body(null, 204)
})
