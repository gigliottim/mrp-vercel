import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { tiposDepositos } from './tipos-depositos.js'
import { tiposDepositosMovimientos } from './tipos-depositos-movimientos.js'

let token = ''
let adminToken = ''

import { login } from '../test-utils.js'

beforeAll(async () => {
  token = await login('sabrinasmurro22@gmail.com')
  adminToken = await login('martin@unik.ar')
})

describe('tipos-depositos', () => {
  it('lista tipos de deposito de la empresa', async () => {
    const app = new Hono().route('/api/v1/tipos-depositos', tiposDepositos)
    const res = await app.request('/api/v1/tipos-depositos', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.length).toBeGreaterThan(0)
  })

  it('crea y elimina un tipo (rol admin)', async () => {
    const app = new Hono().route('/api/v1/tipos-depositos', tiposDepositos)
    const ts = Date.now()
    const res = await app.request('/api/v1/tipos-depositos', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: `TD${ts}`.slice(0, 50), nombre: `test-dep-${ts}` }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const del = await app.request(`/api/v1/tipos-depositos/${body.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })

  it('destinos-permitidos devuelve mapa origen→destinos', async () => {
    const app = new Hono().route('/api/v1/tipos-depositos', tiposDepositos)
    const res = await app.request('/api/v1/tipos-depositos/destinos-permitidos', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.length).toBeGreaterThan(0)
    const fila = body.data[0]
    expect(fila.origen_codigo).toBeTruthy()
    expect(Array.isArray(fila.destinos)).toBe(true)
  })
})

describe('tipos-depositos-movimientos', () => {
  it('lista validaciones de la empresa', async () => {
    const app = new Hono().route('/api/v1/tipos-depositos-movimientos', tiposDepositosMovimientos)
    const res = await app.request('/api/v1/tipos-depositos-movimientos', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.length).toBeGreaterThan(0)
  })

  it('crea y elimina una validacion (rol admin)', async () => {
    const app = new Hono()
      .route('/api/v1/tipos-depositos', tiposDepositos)
      .route('/api/v1/tipos-depositos-movimientos', tiposDepositosMovimientos)
    // ids reales de depositos de la empresa 2
    const list = await app.request('/api/v1/tipos-depositos', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    const depositos = (await list.json()).data
    const origen = depositos[0].id
    const destino = depositos[1].id
    const res = await app.request('/api/v1/tipos-depositos-movimientos', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ tipo_deposito_origen_id: origen, tipo_deposito_destino_id: destino }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const del = await app.request(`/api/v1/tipos-depositos-movimientos/${body.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })

  it('rechaza origen igual a destino', async () => {
    const app = new Hono().route('/api/v1/tipos-depositos-movimientos', tiposDepositosMovimientos)
    const res = await app.request('/api/v1/tipos-depositos-movimientos', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ tipo_deposito_origen_id: 1, tipo_deposito_destino_id: 1 }),
    })
    expect(res.status).toBe(400)
  })
})
