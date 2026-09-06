import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { compras } from './compras'
import { login } from '../test-utils'

let adminToken = ''
let entidadId = 0
let varianteId = 0
let stockInicial = 0

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
  const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
  const e = await supabase.from('entidades').select('id').limit(1)
  const v = await supabase.from('variantes').select('id, stock_actual').limit(1)
  entidadId = e.data![0].id
  varianteId = v.data![0].id
  stockInicial = Number(v.data![0].stock_actual)
})

describe('compras', () => {
  it('crea compra con recepcion y actualiza stock', async () => {
    const app = new Hono().route('/api/v1/compras', compras)
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())

    const res = await app.request('/api/v1/compras', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fecha: '2026-09-06',
        precio_unitario: 10.5,
        id_entidad: entidadId,
        nro_comprobante: `NC-${Date.now()}`.slice(0, 50),
        variante_id: varianteId,
        cantidad: 3,
        observaciones: 'test-p3-compra',
      }),
    })
    expect(res.status).toBe(201)

    // stock subió 3
    const after = await supabase.from('variantes').select('stock_actual').eq('id', varianteId).single()
    expect(Number(after.data!.stock_actual)).toBe(stockInicial + 3)

    // hay movimiento compra_recepcion
    const movs = await supabase.from('movimientos_inventario').select('id').eq('variante_id', varianteId).eq('tipo_movimiento', 'compra_recepcion')
    const movTest = (movs.data ?? []).filter((m) => m.id > (movs.data?.[movs.data.length - 2]?.id ?? 0))
    expect(movTest.length).toBeGreaterThan(0)

    // delete de compra con recepción → 400
    const compraId = (await supabase.from('compras').select('id').eq('observaciones', 'test-p3-compra').single()).data!.id
    const del = await app.request(`/api/v1/compras/${compraId}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(400)

    // limpiar: movimientos_stock, movimientos_inventario, compras
    const ms = await supabase.from('movimientos_stock').select('id').eq('referencia_tipo', 'compra').eq('referencia_id', compraId)
    for (const row of ms.data ?? []) await supabase.from('movimientos_stock').delete().eq('id', row.id)
    const mi = await supabase.from('movimientos_inventario').select('id').eq('observaciones', 'test-p3-compra')
    for (const row of mi.data ?? []) await supabase.from('movimientos_inventario').delete().eq('id', row.id)
    await supabase.from('compras').delete().eq('id', compraId)

    // El trigger de stock solo actúa en INSERT: restaurar stock manualmente
    await supabase.from('variantes').update({ stock_actual: stockInicial }).eq('id', varianteId)
  })

  it('rechaza entidad inexistente', async () => {
    const app = new Hono().route('/api/v1/compras', compras)
    const res = await app.request('/api/v1/compras', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fecha: '2026-09-06',
        precio_unitario: 5,
        id_entidad: 999999,
        variante_id: varianteId,
        cantidad: 1,
      }),
    })
    expect(res.status).toBe(500)
  })
})
