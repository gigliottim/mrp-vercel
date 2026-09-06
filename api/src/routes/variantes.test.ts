import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { variantes } from './variantes'
import { login } from '../test-utils'

let token = ''
let adminToken = ''
let parteId = 0

beforeAll(async () => {
  token = await login('sabrinasmurro22@gmail.com')
  adminToken = await login('martin@unik.ar')
  const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
  const parte = await supabase.from('partes').select('id').limit(1)
  parteId = parte.data![0].id
})

describe('variantes', () => {
  it('lista variantes de la empresa', async () => {
    const app = new Hono().route('/api/v1/variantes', variantes)
    const res = await app.request('/api/v1/variantes', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.pagination.total).toBe(266)
  })

  it('filtra por id_parte', async () => {
    const app = new Hono().route('/api/v1/variantes', variantes)
    const res = await app.request(`/api/v1/variantes?id_parte=${parteId}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    for (const v of body.data) {
      expect(v.id_parte).toBe(parteId)
    }
  })

  it('obtiene stock de una variante', async () => {
    const app = new Hono().route('/api/v1/variantes', variantes)
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    const v = await supabase.from('variantes').select('id').limit(1)
    const res = await app.request(`/api/v1/variantes/${v.data![0].id}/stock`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(typeof body.data.stock_actual).toBe('number')
  })

  it('crea y elimina una variante (rol admin)', async () => {
    const app = new Hono().route('/api/v1/variantes', variantes)
    const ts = Date.now()
    const res = await app.request('/api/v1/variantes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id_parte: parteId,
        codigo_variante: `V${ts}`.slice(0, 50),
        detalle: `test-variante-${ts}`,
      }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const del = await app.request(`/api/v1/variantes/${body.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })

  it('rechaza id_parte inexistente', async () => {
    const app = new Hono().route('/api/v1/variantes', variantes)
    const res = await app.request('/api/v1/variantes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ id_parte: 999999, codigo_variante: 'X1', detalle: 'x' }),
    })
    expect(res.status).toBe(400)
  })
})
