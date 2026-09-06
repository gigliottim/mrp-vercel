import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { movimientosInventario } from './movimientos-inventario'
import { login } from '../test-utils'

let adminToken = ''
let varianteId = 0
let stockInicial = 0

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
  const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
  const v = await supabase.from('variantes').select('id, stock_actual').limit(1)
  varianteId = v.data![0].id
  stockInicial = Number(v.data![0].stock_actual)
})

describe('movimientos-inventario', () => {
  it('ajuste sube stock y consumo lo baja (atomico)', async () => {
    const app = new Hono().route('/api/v1/movimientos-inventario', movimientosInventario)
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())

    // ajuste +5
    const r1 = await app.request('/api/v1/movimientos-inventario', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ variante_id: varianteId, tipo_movimiento: 'ajuste_inventario', cantidad: 5, observaciones: 'test-p3' }),
    })
    expect(r1.status).toBe(201)

    // verificar stock subió 5
    const after1 = await supabase.from('variantes').select('stock_actual').eq('id', varianteId).single()
    expect(Number(after1.data!.stock_actual)).toBe(stockInicial + 5)

    // consumo -5 (produccion_consumo invierte signo)
    const r2 = await app.request('/api/v1/movimientos-inventario', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ variante_id: varianteId, tipo_movimiento: 'produccion_consumo', cantidad: 5, observaciones: 'test-p3-revert' }),
    })
    expect(r2.status).toBe(201)

    const after2 = await supabase.from('variantes').select('stock_actual').eq('id', varianteId).single()
    expect(Number(after2.data!.stock_actual)).toBe(stockInicial)

    // listar movimientos de la variante
    const list = await app.request(`/api/v1/movimientos-inventario?variante_id=${varianteId}`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    const listBody = await list.json()
    const testMovs = listBody.data.filter((m: { observaciones: string }) => m.observaciones === 'test-p3' || m.observaciones === 'test-p3-revert')
    expect(testMovs.length).toBe(2)

    // limpiar movimientos de test
    for (const m of testMovs) {
      await supabase.from('movimientos_inventario').delete().eq('id', m.id)
    }
  })

  it('rechaza variante inexistente', async () => {
    const app = new Hono().route('/api/v1/movimientos-inventario', movimientosInventario)
    const res = await app.request('/api/v1/movimientos-inventario', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ variante_id: 999999, tipo_movimiento: 'ajuste_inventario', cantidad: 1 }),
    })
    expect(res.status).toBe(500)
  })
})
