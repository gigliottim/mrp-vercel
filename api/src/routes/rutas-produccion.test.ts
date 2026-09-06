import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { rutasProduccion } from './rutas-produccion'
import { login } from '../test-utils'

let adminToken = ''
let bomId = 0
let centroId = 0

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
  const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
  const bom = await supabase.from('bom_cabecera').select('id').limit(1)
  bomId = bom.data![0].id
  let centro = await supabase.from('centros_trabajo').select('id').limit(1)
  if (!centro.data || centro.data.length === 0) {
    const { data: nuevo } = await supabase
      .from('centros_trabajo')
      .insert({ codigo: 'E2E-CT', nombre: 'Centro E2E', activo: true, company_id: 2 })
      .select('id')
      .single()
    centro = { data: [nuevo] }
  }
  centroId = centro.data![0].id
})

describe('rutas-produccion editor', () => {
  it('lista operaciones por BOM', async () => {
    const app = new Hono().route('/api/v1/rutas-produccion', rutasProduccion)
    const res = await app.request(`/api/v1/rutas-produccion/bom/${bomId}/operaciones`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(Array.isArray(body.data)).toBe(true)
  })

  it('devuelve resumen de ruta', async () => {
    const app = new Hono().route('/api/v1/rutas-produccion', rutasProduccion)
    const res = await app.request(`/api/v1/rutas-produccion/bom/${bomId}/resumen?cantidad=2`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.tiempos).toHaveProperty('total_mins')
    expect(body.data.costos).toHaveProperty('total')
    expect(Array.isArray(body.data.validaciones)).toBe(true)
  })

  it('agrega, actualiza y elimina operación', async () => {
    const app = new Hono().route('/api/v1/rutas-produccion', rutasProduccion)
    // crear
    const res = await app.request(`/api/v1/rutas-produccion/bom/${bomId}/operaciones`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        secuencia: 99,
        centro_trabajo_id: centroId,
        descripcion: 'E2E operacion test',
        tiempo_setup_mins: 5,
        tiempo_proceso_unitario_mins: 2,
        tiempo_cola_mins: 1,
        tiempo_movimiento_mins: 1,
        costo_operacion_fijo: 10,
        costo_operacion_variable: 0.5,
      }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const opId = body.data.id

    // actualizar
    const upd = await app.request(`/api/v1/rutas-produccion/operaciones/${opId}`, {
      method: 'PATCH',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ descripcion: 'E2E operacion actualizada' }),
    })
    expect(upd.status).toBe(200)

    // eliminar
    const del = await app.request(`/api/v1/rutas-produccion/operaciones/${opId}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })
})
