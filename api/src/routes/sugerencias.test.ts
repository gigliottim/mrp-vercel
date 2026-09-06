import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { sugerencias, inventario } from './sugerencias'
import { login } from '../test-utils'

let adminToken = ''

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
})

describe('sugerencias', () => {
  it('lista sugerencias con resumen', async () => {
    const app = new Hono().route('/api/v1/sugerencias', sugerencias)
    const res = await app.request('/api/v1/sugerencias', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.resumen).toHaveProperty('fabricables')
    expect(body.data.resumen).toHaveProperty('parciales')
    expect(body.data.resumen).toHaveProperty('sin_stock')
    expect(Array.isArray(body.data.variantes)).toBe(true)
  })

  it('filtra por fabricable', async () => {
    const app = new Hono().route('/api/v1/sugerencias', sugerencias)
    const res = await app.request('/api/v1/sugerencias?filtro=fabricable', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    for (const v of body.data.variantes) {
      expect(v.status).toBe('fabricable')
    }
  })
})

describe('inventario critico', () => {
  it('lista stock critico con stats', async () => {
    const app = new Hono().route('/api/v1/inventario', inventario)
    const res = await app.request('/api/v1/inventario/critico', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.stats).toHaveProperty('total')
    expect(body.data.stats).toHaveProperty('critico')
    expect(Array.isArray(body.data.items)).toBe(true)
  })

  it('filtra por estado critico', async () => {
    const app = new Hono().route('/api/v1/inventario', inventario)
    const res = await app.request('/api/v1/inventario/critico?estado=critico', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    for (const item of body.data.items) {
      expect(item.estado_stock).toBe('critico')
    }
  })
})
