import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { centrosTrabajo } from './centros-trabajo'
import { rutasProduccion } from './rutas-produccion'
import { login } from '../test-utils'

let token = ''
let adminToken = ''
let bomId = 0
let centroId = 0

beforeAll(async () => {
  token = await login('sabrinasmurro22@gmail.com')
  adminToken = await login('martin@unik.ar')
  const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
  const bomC = await supabase.from('bom_cabecera').select('id').limit(1)
  bomId = bomC.data![0].id
})

describe('centros-trabajo', () => {
  it('lista centros de trabajo (puede estar vacio)', async () => {
    const app = new Hono().route('/api/v1/centros-trabajo', centrosTrabajo)
    const res = await app.request('/api/v1/centros-trabajo', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
  })

  it('crea y elimina un centro (rol admin)', async () => {
    const app = new Hono().route('/api/v1/centros-trabajo', centrosTrabajo)
    const ts = Date.now()
    const res = await app.request('/api/v1/centros-trabajo', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: `CT${ts}`.slice(0, 20), nombre: `test-ct-${ts}` }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    centroId = body.data.id
    const del = await app.request(`/api/v1/centros-trabajo/${centroId}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })
})

describe('rutas-produccion', () => {
  it('lista rutas (puede estar vacio)', async () => {
    const app = new Hono().route('/api/v1/rutas-produccion', rutasProduccion)
    const res = await app.request('/api/v1/rutas-produccion', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
  })

  it('crea y elimina una ruta con FK validos', async () => {
    const app = new Hono()
      .route('/api/v1/centros-trabajo', centrosTrabajo)
      .route('/api/v1/rutas-produccion', rutasProduccion)
    const ts = Date.now()
    // crear centro
    const ct = await app.request('/api/v1/centros-trabajo', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: `CT${ts}`.slice(0, 20), nombre: `test-ct-${ts}` }),
    })
    const ctBody = await ct.json()
    // crear ruta
    const res = await app.request('/api/v1/rutas-produccion', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        bom_id: bomId,
        secuencia: 1,
        centro_trabajo_id: ctBody.data.id,
        descripcion: 'test-ruta',
      }),
    })
    expect(res.status).toBe(201)
    const ruta = await res.json()
    // limpiar
    await app.request(`/api/v1/rutas-produccion/${ruta.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    await app.request(`/api/v1/centros-trabajo/${ctBody.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
  })

  it('rechaza centro inexistente', async () => {
    const app = new Hono().route('/api/v1/rutas-produccion', rutasProduccion)
    const res = await app.request('/api/v1/rutas-produccion', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ bom_id: bomId, secuencia: 1, centro_trabajo_id: 999999, descripcion: 'x' }),
    })
    expect(res.status).toBe(400)
  })
})
