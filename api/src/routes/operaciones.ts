import { Hono } from 'hono'
import { requireAuth, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'

// ─── Dashboard de Operaciones + Gantt ───────────────────────────────────────

export const operaciones = new Hono<AuthEnv>()
operaciones.use('*', requireAuth)

operaciones.get('/', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))

  const [centros, rutas, ordenes] = await Promise.all([
    supabase.from('centros_trabajo').select('id', { count: 'exact', head: true }).eq('activo', true),
    supabase.from('rutas_produccion').select('id', { count: 'exact', head: true }),
    supabase
      .from('ordenes_produccion')
      .select('id, numero_orden, estado, cantidad_planificada, cantidad_producida, prioridad, variante_id, variantes(id, codigo_variante, detalle)')
      .in('estado', ['planificada', 'en_produccion', 'pausada'])
      .order('created_at', { ascending: false })
      .limit(10),
  ])

  const metricas = {
    centros_activos: centros.count ?? 0,
    rutas_configuradas: rutas.count ?? 0,
    ordenes_activas: ordenes.data?.length ?? 0,
    capacidad_utilizada: 0,
  }

  return c.json({
    data: {
      metricas,
      ordenes_recientes: ordenes.data ?? [],
    },
  })
})

// Gantt: planificaciones por centro con periodo
operaciones.get('/gantt', async (c) => {
  const centroId = c.req.query('centro_id')
  const fechaInicio = c.req.query('fecha_inicio') ?? new Date().toISOString().slice(0, 10)
  const fechaFin = c.req.query('fecha_fin') ?? new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10)

  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let query = supabase
    .from('planificacion_recursos')
    .select(
      `id, periodo, estado, orden_produccion_id, centro_trabajo_id,
       ordenes_produccion!planificacion_recursos_orden_produccion_id_fkey(
         numero_orden, prioridad, cantidad_planificada,
         variantes!ordenes_produccion_variante_id_fkey(id, codigo_variante, detalle)
       ),
       centros_trabajo!planificacion_recursos_centro_trabajo_id_fkey(id, codigo, nombre)`
    )
    .order('centro_trabajo_id')
  if (centroId) query = query.eq('centro_trabajo_id', Number(centroId))

  const { data: ganttRaw, error } = await query
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)

  // Agrupar por centro
  const filas = new Map<number, { centro_id: number; centro_codigo: string; centro_nombre: string; tareas: unknown[] }>()
  const data = (ganttRaw ?? []) as any[]
  for (const pr of data) {
    const centro = pr.centros_trabajo
    const key = Number(pr.centro_trabajo_id)
    if (!filas.has(key)) {
      filas.set(key, {
        centro_id: key,
        centro_codigo: centro?.codigo ?? '',
        centro_nombre: centro?.nombre ?? '',
        tareas: [],
      })
    }
    filas.get(key)!.tareas.push({
      id: pr.id,
      orden_id: pr.orden_produccion_id,
      numero_orden: pr.ordenes_produccion?.numero_orden ?? '',
      variante_codigo: pr.ordenes_produccion?.variantes?.codigo_variante ?? '',
      variante_detalle: pr.ordenes_produccion?.variantes?.detalle ?? '',
      prioridad: pr.ordenes_produccion?.prioridad ?? 'normal',
      cantidad: pr.ordenes_produccion?.cantidad_planificada ?? 0,
      inicio: pr.periodo?.lower ?? null,
      fin: pr.periodo?.upper ?? null,
      estado: pr.estado,
    })
  }

  return c.json({
    data: {
      periodo: { inicio: fechaInicio, fin: fechaFin },
      filas: [...filas.values()],
      total_asignaciones: data?.length ?? 0,
    },
  })
})
