import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { movimientosInventario } from './movimientos-inventario'
import { movimientosPartes } from './movimientos-partes'
import { login, MARTIN } from '../test-utils'

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

describe('registrar_movimiento_partes (POST /movimientos-partes)', () => {
  let adminToken = ''
  let varianteId = 0
  let parteId = 0
  let deps: Record<string, number> = {}
  let entidadId = 0

  beforeAll(async () => {
    adminToken = await login(MARTIN)
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    const v = await supabase.from('variantes').select('id, id_parte').limit(1)
    varianteId = v.data![0].id
    parteId = v.data![0].id_parte
    const companyId = 2 // empresa demo (igual que compras.test.ts)
    const d = await supabase.from('tipos_depositos').select('id, codigo').eq('company_id', companyId)
    deps = Object.fromEntries(d.data!.map((r) => [r.codigo, r.id]))
    const e = await supabase.from('entidades').select('id').eq('company_id', companyId).limit(1)
    entidadId = e.data![0].id
  })

  it('rechaza movimiento no permitido por la matriz', async () => {
    const app = new Hono().route('/api/v1/movimientos-partes', movimientosPartes)
    const res = await app.request('/api/v1/movimientos-partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_id: varianteId, cantidad: 1,
        tipo_deposito_origen_id: deps['CLIENTE'], tipo_deposito_destino_id: deps['PRODUCCION'],
        tipo_movimiento: 'transferencia_salida',
      }),
    })
    expect(res.status).toBe(400)
    const body = await res.json()
    expect(body.error.message).toContain('movimiento no permitido')
  })

  it('rechaza stock negativo en origen no-AJUSTE/PROVEEDOR', async () => {
    const app = new Hono().route('/api/v1/movimientos-partes', movimientosPartes)
    const res = await app.request('/api/v1/movimientos-partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_id: varianteId, cantidad: 999999,
        tipo_deposito_origen_id: deps['PRODUCCION'], tipo_deposito_destino_id: deps['ALMACEN'],
        tipo_movimiento: 'produccion_ingreso',
      }),
    })
    expect(res.status).toBe(400)
    expect((await res.json()).error.message).toContain('stock insuficiente')
  })

  it('compra PROVEEDOR→ALMACEN exige entidad e importe', async () => {
    const app = new Hono().route('/api/v1/movimientos-partes', movimientosPartes)
    const res = await app.request('/api/v1/movimientos-partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_id: varianteId, cantidad: 1,
        tipo_deposito_origen_id: deps['PROVEEDOR'], tipo_deposito_destino_id: deps['ALMACEN'],
        tipo_movimiento: 'compra_recepcion',
      }),
    })
    expect(res.status).toBe(400)
    expect((await res.json()).error.message).toContain('entidad')
  })

  it('compra con factor_conversion crea compra + cantidad_uso', async () => {
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    // capturar estado previo para restaurar la BD al final (requisito: dejar la BD como estaba)
    const stockAntes = Number((await supabase.from('variantes').select('stock_actual').eq('id', varianteId).single()).data!.stock_actual)
    const factorAntes = Number((await supabase.from('partes').select('factor_conversion').eq('id', parteId).single()).data!.factor_conversion)
    // ids de filas creadas: quedan en 0 si el test falla antes de crearlas (cleanup no-op)
    let compraId = 0
    let movimientoInventarioId = 0
    let movimientoStockId = 0
    try {
      await supabase.from('partes').update({ factor_conversion: 10 }).eq('id', parteId)
      const app = new Hono().route('/api/v1/movimientos-partes', movimientosPartes)
      const res = await app.request('/api/v1/movimientos-partes', {
        method: 'POST',
        headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({
          variante_id: varianteId, cantidad: 2, importe_total: 100,
          tipo_deposito_origen_id: deps['PROVEEDOR'], tipo_deposito_destino_id: deps['ALMACEN'],
          tipo_movimiento: 'compra_recepcion', entidad_id: entidadId,
        }),
      })
      expect(res.status).toBe(201)
      const body = await res.json()
      compraId = body?.data?.compra_id ?? 0
      movimientoInventarioId = body?.data?.movimiento_inventario_id ?? 0
      movimientoStockId = body?.data?.movimiento_stock_id ?? 0
      expect(body.data.compra_id).toBeGreaterThan(0)
      expect(body.data.cantidad_uso).toBe(20) // 2 x factor 10
      // precio_unitario = (100/2)/10 = 5
      const compra = await supabase.from('compras').select('precio_unitario').eq('id', body.data.compra_id).single()
      expect(Number(compra.data!.precio_unitario)).toBe(5)
      // revert: ajuste inverso (deja filas con observaciones 'test-revert')
      await app.request('/api/v1/movimientos-partes', {
        method: 'POST',
        headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({
          variante_id: varianteId, cantidad: 2,
          tipo_deposito_origen_id: deps['ALMACEN'], tipo_deposito_destino_id: deps['AJUSTE'],
          tipo_movimiento: 'ajuste_inventario', observaciones: 'test-revert',
        }),
      })
    } finally {
      // cleanup failure-safe: corre aunque cualquier asercion falle antes.
      // trg_actualizar_stock es AFTER INSERT (borrar filas NO revierte stock_actual)
      // -> borrar filas creadas + restaurar stock y factor manualmente.
      // eq('id', 0) es no-op si el test fallo antes de crear las filas.
      await supabase.from('compras').delete().eq('id', compraId)
      await supabase.from('movimientos_inventario').delete().eq('id', movimientoInventarioId)
      await supabase.from('movimientos_inventario').delete().eq('observaciones', 'test-revert')
      await supabase.from('movimientos_stock').delete().eq('id', movimientoStockId)
      await supabase.from('movimientos_stock').delete().eq('observaciones', 'test-revert')
      await supabase.from('variantes').update({ stock_actual: stockAntes }).eq('id', varianteId)
      await supabase.from('partes').update({ factor_conversion: factorAntes }).eq('id', parteId)
    }
  })
})
