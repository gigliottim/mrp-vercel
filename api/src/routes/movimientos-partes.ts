import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'

const TIPOS = ['compra_recepcion', 'produccion_ingreso', 'produccion_consumo', 'produccion_descarte', 'ajuste_inventario', 'venta_despacho', 'transferencia_salida', 'transferencia_entrada'] as const

const schema = z.object({
  variante_id: z.number().int().positive(),
  cantidad: z.number().positive(),
  tipo_deposito_origen_id: z.number().int().positive(),
  tipo_deposito_destino_id: z.number().int().positive(),
  tipo_movimiento: z.enum(TIPOS),
  entidad_id: z.number().int().positive().nullable().optional(),
  importe_total: z.number().positive().nullable().optional(),
  fecha_hora: z.string().datetime({ offset: true }).nullable().optional(),
  nro_comprobante: z.string().max(50).nullable().optional(),
  observaciones: z.string().max(500).nullable().optional(),
})

export const movimientosPartes = new Hono<AuthEnv>()
movimientosPartes.use('*', requireAuth)

// Port de MovimientosPartesController::store (PHP)
movimientosPartes.post(
  '/',
  requireRole('Super Administrador', 'Administrador', 'Supervisor'),
  async (c) => {
    const body = await c.req.json().catch(() => null)
    const parsed = schema.safeParse(body)
    if (!parsed.success) {
      return c.json({ error: { code: 'VALIDATION', message: parsed.error.issues[0]?.message ?? 'datos inválidos' } }, 400)
    }
    const d = parsed.data
    const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
    const { data, error } = await supabase.rpc('registrar_movimiento_partes', {
      p_company_id: c.get('companyId'),
      p_variante_id: d.variante_id,
      p_cantidad: d.cantidad,
      p_tipo_deposito_origen_id: d.tipo_deposito_origen_id,
      p_tipo_deposito_destino_id: d.tipo_deposito_destino_id,
      p_tipo_movimiento: d.tipo_movimiento,
      p_entidad_id: d.entidad_id ?? null,
      p_importe_total: d.importe_total ?? null,
      p_fecha: d.fecha_hora ?? null,
      p_nro_comprobante: d.nro_comprobante ?? null,
      p_observaciones: d.observaciones ?? null,
    })
    if (error) {
      const msg = error.message
      const esNegocio = /movimiento no permitido|stock insuficiente|Debe |fecha no puede ser futura|cantidad debe ser positiva|depósito origen\/destino inexistente|inexistente en esta empresa/.test(msg)
      return c.json({ error: { code: esNegocio ? 'BUSINESS' : 'DB_ERROR', message: msg } }, esNegocio ? 400 : 500)
    }
    return c.json({ data }, 201)
  }
)
