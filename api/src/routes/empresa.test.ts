import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { empresa } from './empresa'
import { login } from '../test-utils'

let adminToken = ''

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
})

describe('empresa', () => {
  it('obtiene datos de la empresa', async () => {
    const app = new Hono().route('/api/v1/empresa', empresa)
    const res = await app.request('/api/v1/empresa', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data).toHaveProperty('name')
  })

  it('lista usuarios de la empresa', async () => {
    const app = new Hono().route('/api/v1/empresa', empresa)
    const res = await app.request('/api/v1/empresa/usuarios', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(Array.isArray(body.data)).toBe(true)
    expect(body.data.length).toBeGreaterThan(0)
  })

  it('lista roles de la empresa', async () => {
    const app = new Hono().route('/api/v1/empresa', empresa)
    const res = await app.request('/api/v1/empresa/roles', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(Array.isArray(body.data)).toBe(true)
  })

  it('lista permisos y arbol de menu', async () => {
    const app = new Hono().route('/api/v1/empresa', empresa)
    const res = await app.request('/api/v1/empresa/permisos', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const res2 = await app.request('/api/v1/empresa/permisos/tree', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res2.status).toBe(200)
    const body2 = await res2.json()
    expect(Array.isArray(body2.data)).toBe(true)
  })
})
