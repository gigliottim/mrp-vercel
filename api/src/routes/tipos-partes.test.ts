import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { tiposPartes } from './tipos-partes.js'

let token = ''
let adminToken = ''

import { login } from '../test-utils.js'

beforeAll(async () => {
  token = await login('usuario@mimrp.com.ar')
  adminToken = await login('martin@unik.ar')
})

describe('tipos-partes', () => {
  it('lista tipos de partes de la empresa', async () => {
    const app = new Hono().route('/api/v1/tipos-partes', tiposPartes)
    const res = await app.request('/api/v1/tipos-partes', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.length).toBeGreaterThan(0)
  })

  it('crea y elimina un tipo (rol admin)', async () => {
    const app = new Hono().route('/api/v1/tipos-partes', tiposPartes)
    const ts = Date.now()
    const res = await app.request('/api/v1/tipos-partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: `TP${ts}`.slice(0, 50), nombre: `test-tipo-${ts}` }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const del = await app.request(`/api/v1/tipos-partes/${body.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })

  it('rechaza delete a supervisor', async () => {
    const app = new Hono().route('/api/v1/tipos-partes', tiposPartes)
    const res = await app.request('/api/v1/tipos-partes/1', {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(403)
  })
})
