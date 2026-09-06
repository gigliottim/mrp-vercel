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

// ─── Planificación automática desde la ruta de la orden ─────────────────────
// Port de PlanificacionService::planificarOrdenAutomatica

planificacion.post('/calcular', async (c) => {
  const body = await c.req.json().catch(() => null)
  const ordenId = Number(body?.orden_id ?? 0)
  const fechaInicioInput = body?.fecha_inicio ? String(body.fecha_inicio) : null
  if (!Number.isInteger(ordenId) || ordenId <= 0) {
    return c.json({ error: { code: 'VALIDATION', message: 'orden_id inválido' } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))

  // Orden
  const { data: orden, error: eOrd } = await supabase
    .from('ordenes_produccion')
    .select('id, bom_id_utilizada, cantidad_planificada, fecha_inicio_programada')
    .eq('id', ordenId)
    .single()
  if (eOrd) return c.json({ error: { code: 'NOT_FOUND', message: 'Orden no encontrada' } }, 404)
  if (!orden.bom_id_utilizada) {
    return c.json({ error: { code: 'VALIDATION', message: 'La orden no tiene un BOM asignado' } }, 400)
  }

  // Operaciones de la ruta del BOM
  const { data: ops, error: eOps } = await supabase
    .from('rutas_produccion')
    .select('id, secuencia, centro_trabajo_id, tiempo_setup_mins, tiempo_proceso_unitario_mins, tiempo_cola_mins, tiempo_movimiento_mins')
    .eq('bom_id', orden.bom_id_utilizada)
    .order('secuencia')
  if (eOps) return c.json({ error: { code: 'DB_ERROR', message: eOps.message } }, 500)
  const operaciones = (ops ?? []) as any[]
  if (operaciones.length === 0) {
    return c.json({ error: { code: 'VALIDATION', message: 'No hay operaciones definidas en la ruta' } }, 400)
  }

  const cantidad = Number(orden.cantidad_planificada ?? 1) || 1
  let fechaInicio = fechaInicioInput ? new Date(fechaInicioInput) : new Date(orden.fecha_inicio_programada ?? new Date())
  if (isNaN(fechaInicio.getTime())) fechaInicio = new Date()

  const resultados: Array<Record<string, unknown>> = []
  for (const op of operaciones) {
    const setup = Number(op.tiempo_setup_mins ?? 0)
    const proceso = Number(op.tiempo_proceso_unitario_mins ?? 0) * cantidad
    const cola = Number(op.tiempo_cola_mins ?? 0)
    const movimiento = Number(op.tiempo_movimiento_mins ?? 0)
    const duracionMin = setup + proceso + cola + movimiento

    const fechaFin = new Date(fechaInicio.getTime() + duracionMin * 60000)

    // Verificar solapamiento en el centro
    const { data: solapa } = await supabase.rpc('verificar_solapamiento', {
      p_company_id: c.get('companyId'),
      p_centro_trabajo_id: op.centro_trabajo_id,
      p_inicio: fechaInicio.toISOString(),
      p_fin: fechaFin.toISOString(),
      p_excluir_id: null,
    })
    if (solapa) {
      return c.json({
        error: { code: 'VALIDATION', message: `La operación ${op.secuencia} se solapa con otro recurso del centro ${op.centro_trabajo_id}` },
      }, 400)
    }

    const { data: nueva, error: eIns } = await supabase
      .from('planificacion_recursos')
      .insert({
        orden_produccion_id: ordenId,
        operacion_id: op.id,
        centro_trabajo_id: op.centro_trabajo_id,
        periodo: `[${fechaInicio.toISOString()},${fechaFin.toISOString()})`,
        estado: 'programado',
        company_id: c.get('companyId'),
      })
      .select('id')
      .single()
    if (eIns) return c.json({ error: { code: 'DB_ERROR', message: eIns.message } }, 500)

    resultados.push({
      operacion_id: op.id,
      planificacion_id: nueva.id,
      secuencia: op.secuencia,
      fecha_inicio: fechaInicio.toISOString(),
      fecha_fin: fechaFin.toISOString(),
      duracion_mins: duracionMin,
    })

    fechaInicio = fechaFin
  }

  return c.json({ data: { success: true, planificaciones: resultados } }, 201)
})
