import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { almacenes } from './almacenes'

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

describe('almacenes', () => {
  it('lista almacenes de la empresa', async () => {
    const app = new Hono().route('/api/v1/almacenes', almacenes)
    const res = await app.request('/api/v1/almacenes', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.length).toBeGreaterThan(0)
  })

  it('crea y elimina un almacen (rol admin)', async () => {
    const app = new Hono().route('/api/v1/almacenes', almacenes)
    const ts = Date.now()
    const res = await app.request('/api/v1/almacenes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: `AL${ts}`.slice(0, 20), nombre: `test-alm-${ts}` }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const del = await app.request(`/api/v1/almacenes/${body.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })
})
