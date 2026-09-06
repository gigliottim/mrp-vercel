import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { entidades } from './entidades'

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

describe('entidades', () => {
  it('lista entidades de la empresa', async () => {
    const app = new Hono().route('/api/v1/entidades', entidades)
    const res = await app.request('/api/v1/entidades', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.length).toBeGreaterThan(0)
  })

  it('crea y elimina una entidad (rol admin)', async () => {
    const app = new Hono().route('/api/v1/entidades', entidades)
    const ts = Date.now()
    const res = await app.request('/api/v1/entidades', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ razon_social: `test-ent-${ts}`, tipo: 'PROVEEDOR' }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const del = await app.request(`/api/v1/entidades/${body.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })

  it('rechaza tipo invalido', async () => {
    const app = new Hono().route('/api/v1/entidades', entidades)
    const res = await app.request('/api/v1/entidades', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ razon_social: 'x', tipo: 'OTRO' }),
    })
    expect(res.status).toBe(400)
  })
})
