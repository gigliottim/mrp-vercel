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

// ─── Editor de Rutas: operaciones por BOM + resumen ─────────────────────────

// GET /bom/:bomId/operaciones → operaciones de la ruta con datos del centro
rutasProduccion.get('/bom/:bomId/operaciones', async (c) => {
  const bomId = Number(c.req.param('bomId'))
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('rutas_produccion')
    .select(
      `id, bom_id, secuencia, centro_trabajo_id, descripcion,
       tiempo_setup_mins, tiempo_proceso_unitario_mins, tiempo_cola_mins,
       tiempo_movimiento_mins, capacidad_requerida, costo_operacion_fijo,
       costo_operacion_variable, instrucciones,
       centros_trabajo!rutas_produccion_centro_trabajo_id_fkey(codigo, nombre, activo)`
    )
    .eq('bom_id', bomId)
    .order('secuencia')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data: data ?? [] })
})

// GET /bom/:bomId/resumen?cantidad=1 → resumen de tiempos, costos y validaciones
rutasProduccion.get('/bom/:bomId/resumen', async (c) => {
  const bomId = Number(c.req.param('bomId'))
  const cantidad = Number(c.req.query('cantidad') ?? 1) || 1
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))

  const { data: ops, error } = await supabase
    .from('rutas_produccion')
    .select(
      `id, secuencia, descripcion, centro_trabajo_id,
       tiempo_setup_mins, tiempo_proceso_unitario_mins, tiempo_cola_mins,
       tiempo_movimiento_mins, costo_operacion_fijo, costo_operacion_variable,
       centros_trabajo!rutas_produccion_centro_trabajo_id_fkey(codigo, nombre, activo)`
    )
    .eq('bom_id', bomId)
    .order('secuencia')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)

  const operaciones = (ops ?? []) as any[]
  if (operaciones.length === 0) {
    return c.json({
      data: {
        operaciones: [],
        tiempos: { total_mins: 0, total_horas: 0, por_operacion: [] },
        costos: { total: 0, por_operacion: [] },
        validaciones: ['La ruta no tiene operaciones definidas'],
        es_valida: false,
      },
    })
  }

  // Tiempos (port de calcularTiempos)
  const porOperacionTiempos = operaciones.map((op) => {
    const setup = Number(op.tiempo_setup_mins ?? 0)
    const proceso = Number(op.tiempo_proceso_unitario_mins ?? 0) * cantidad
    const cola = Number(op.tiempo_cola_mins ?? 0)
    const movimiento = Number(op.tiempo_movimiento_mins ?? 0)
    const duracion = setup + proceso + cola + movimiento
    return {
      secuencia: op.secuencia,
      descripcion: op.descripcion,
      centro: op.centros_trabajo?.nombre ?? '',
      duracion_mins: Math.round(duracion * 100) / 100,
      duracion_horas: Math.round((duracion / 60) * 100) / 100,
    }
  })
  const totalMins = porOperacionTiempos.reduce((s, t) => s + t.duracion_mins, 0)

  // Costos (port de calcularCostos)
  const porOperacionCostos = operaciones.map((op) => {
    const fijo = Number(op.costo_operacion_fijo ?? 0)
    const variable = Number(op.costo_operacion_variable ?? 0) * cantidad
    return {
      secuencia: op.secuencia,
      descripcion: op.descripcion,
      costo_fijo: fijo,
      costo_variable: variable,
      costo_total: fijo + variable,
    }
  })
  const costoTotal = porOperacionCostos.reduce((s, c) => s + c.costo_total, 0)

  // Validaciones (port de validarRuta)
  const validaciones: string[] = []
  for (const op of operaciones) {
    if (!op.centros_trabajo?.activo) {
      validaciones.push(`La operación ${op.secuencia} usa el centro ${op.centros_trabajo?.codigo ?? '?'} que está inactivo`)
    }
  }
  const secuencias = operaciones.map((o) => Number(o.secuencia)).sort((a, b) => a - b)
  for (let i = 1; i < secuencias.length; i++) {
    if (secuencias[i] - secuencias[i - 1] > 50) {
      validaciones.push(`Hay un gap significativo entre las secuencias ${secuencias[i - 1]} y ${secuencias[i]}`)
    }
  }

  return c.json({
    data: {
      operaciones,
      tiempos: { total_mins: totalMins, total_horas: Math.round((totalMins / 60) * 100) / 100, por_operacion: porOperacionTiempos },
      costos: { total: Math.round(costoTotal * 100) / 100, por_operacion: porOperacionCostos },
      validaciones,
      es_valida: validaciones.length === 0,
    },
  })
})

// POST /bom/:bomId/operaciones → agregar operación a la ruta
rutasProduccion.post('/bom/:bomId/operaciones', async (c) => {
  const bomId = Number(c.req.param('bomId'))
  const body = await c.req.json().catch(() => null)
  const parsed = schema.omit({ bom_id: true }).safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const fk = await validarReferencias(supabase, {
    bom_cabecera: bomId,
    centros_trabajo: parsed.data.centro_trabajo_id,
  })
  if (!fk.ok) return c.json({ error: { code: 'VALIDATION', message: fk.error } }, 400)
  const { data, error } = await supabase
    .from('rutas_produccion')
    .insert({ ...parsed.data, bom_id: bomId, company_id: c.get('companyId') })
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

// PATCH /operaciones/:opId → actualizar operación
rutasProduccion.patch('/operaciones/:opId', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.omit({ bom_id: true }).partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('rutas_produccion')
    .update(parsed.data)
    .eq('id', Number(c.req.param('opId')))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

// DELETE /operaciones/:opId → eliminar operación
rutasProduccion.delete('/operaciones/:opId', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { error } = await supabase
    .from('rutas_produccion')
    .delete()
    .eq('id', Number(c.req.param('opId')))
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.body(null, 204)
})
