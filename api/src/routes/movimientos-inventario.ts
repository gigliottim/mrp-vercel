import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'

const TIPOS = ['compra_recepcion', 'produccion_ingreso', 'produccion_consumo', 'produccion_descarte', 'ajuste_inventario', 'venta_despacho', 'transferencia_salida', 'transferencia_entrada'] as const

const schema = z.object({
  variante_id: z.number().int().positive(),
  almacen_id: z.number().int().positive().nullable().optional(),
  orden_produccion_id: z.number().int().positive().nullable().optional(),
  tipo_movimiento: z.enum(TIPOS),
  cantidad: z.number().positive(),
  costo_unitario_snapshot: z.number().optional(),
  observaciones: z.string().optional(),
  referencia_documento: z.string().max(100).optional(),
})

export const movimientosInventario = new Hono<AuthEnv>()
movimientosInventario.use('*', requireAuth)

movimientosInventario.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const varianteId = c.req.query('variante_id')
  const tipo = c.req.query('tipo_movimiento')
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let query = supabase.from('movimientos_inventario').select('*', { count: 'exact' })
  if (varianteId) query = query.eq('variante_id', Number(varianteId))
  if (tipo) query = query.eq('tipo_movimiento', tipo)
  const { data, error, count } = await query
    .order('id', { ascending: false })
    .range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

movimientosInventario.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('movimientos_inventario')
    .select('*')
    .eq('id', Number(c.req.param('id')))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrado' } }, 404)
  return c.json({ data })
})

movimientosInventario.post(
  '/',
  requireRole('Super Administrador', 'Administrador', 'Supervisor'),
  async (c) => {
    const body = await c.req.json().catch(() => null)
    const parsed = schema.safeParse(body)
    if (!parsed.success) {
      return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
    }
    const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
    const { data, error } = await supabase.rpc('movimiento_inventario', {
      p_company_id: c.get('companyId'),
      p_variante_id: parsed.data.variante_id,
      p_almacen_id: parsed.data.almacen_id ?? null,
      p_orden_produccion_id: parsed.data.orden_produccion_id ?? null,
      p_tipo_movimiento: parsed.data.tipo_movimiento,
      p_cantidad: parsed.data.cantidad,
      p_costo_unitario_snapshot: parsed.data.costo_unitario_snapshot ?? 0,
      p_observaciones: parsed.data.observaciones ?? null,
      p_referencia_documento: parsed.data.referencia_documento ?? null,
    })
    if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
    return c.json({ data }, 201)
  }
)
