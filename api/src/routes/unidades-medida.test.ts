import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { unidadesMedida } from './unidades-medida'

let token = ''
let createdId = 0

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
})

describe('unidades-medida', () => {
  it('lista unidades de la empresa', async () => {
    const app = new Hono().route('/api/v1/unidades-medida', unidadesMedida)
    const res = await app.request('/api/v1/unidades-medida', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.length).toBeGreaterThan(0)
    expect(body.pagination.total).toBeGreaterThan(0)
  })

  it('crea y elimina una unidad (rol admin)', async () => {
    const adminToken = await login('martin@unik.ar')
    const app = new Hono().route('/api/v1/unidades-medida', unidadesMedida)
    const ts = Date.now()
    const res = await app.request('/api/v1/unidades-medida', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        tipo: 'unidad',
        unidad: `test-um-${ts}`,
        simbolo: `tu${ts}`.slice(0, 10),
        equivalencia_base: 1,
      }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    createdId = body.data.id
    const del = await app.request(`/api/v1/unidades-medida/${createdId}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })

  it('rechaza delete a supervisor', async () => {
    const app = new Hono().route('/api/v1/unidades-medida', unidadesMedida)
    const res = await app.request('/api/v1/unidades-medida/1', {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(403)
  })

  it('rechaza validacion invalida', async () => {
    const app = new Hono().route('/api/v1/unidades-medida', unidadesMedida)
    const res = await app.request('/api/v1/unidades-medida', {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ tipo: 'invalido', unidad: '', simbolo: '', equivalencia_base: -1 }),
    })
    expect(res.status).toBe(400)
  })
})
