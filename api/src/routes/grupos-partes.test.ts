import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { gruposPartes } from './grupos-partes'

let token = ''
let adminToken = ''

async function login(email: string) {
  const res = await fetch(`${process.env.SUPABASE_URL}/auth/v1/token?grant_type=password`, {
    method: 'POST',
    headers: { apikey: process.env.SUPABASE_ANON_KEY!, 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password: 'Temporal123!' }),
  })
  return (await res.json()).access_token
}

beforeAll(async () => {
  token = await login('sabrinasmurro22@gmail.com')
  adminToken = await login('martin@unik.ar')
})

describe('grupos-partes', () => {
  it('lista grupos de partes de la empresa', async () => {
    const app = new Hono().route('/api/v1/grupos-partes', gruposPartes)
    const res = await app.request('/api/v1/grupos-partes', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.length).toBeGreaterThan(0)
  })

  it('crea y elimina un grupo (rol admin)', async () => {
    const app = new Hono().route('/api/v1/grupos-partes', gruposPartes)
    const ts = Date.now()
    const res = await app.request('/api/v1/grupos-partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: `GP${ts}`.slice(0, 20), nombre: `test-grupo-${ts}`, color: '#123456' }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const del = await app.request(`/api/v1/grupos-partes/${body.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })

  it('rechaza color invalido', async () => {
    const app = new Hono().route('/api/v1/grupos-partes', gruposPartes)
    const res = await app.request('/api/v1/grupos-partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: 'X1', nombre: 'x', color: 'rojo' }),
    })
    expect(res.status).toBe(400)
  })
})
