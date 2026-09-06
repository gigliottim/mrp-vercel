import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { partes } from './partes'
import { login } from '../test-utils'

let token = ''
let adminToken = ''
let tipoParteId = 0
let grupoParteId = 0

beforeAll(async () => {
  token = await login('sabrinasmurro22@gmail.com')
  adminToken = await login('martin@unik.ar')
})

describe('partes', () => {
  it('lista partes de la empresa', async () => {
    const app = new Hono().route('/api/v1/partes', partes)
    const res = await app.request('/api/v1/partes', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.pagination.total).toBe(204)
    expect(body.data.length).toBeGreaterThan(0)
  })

  it('filtra por q', async () => {
    const app = new Hono().route('/api/v1/partes', partes)
    const res = await app.request('/api/v1/partes?q=chapa', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    for (const p of body.data) {
      const text = `${p.codigo} ${p.detalle}`.toLowerCase()
      expect(text).toContain('chapa')
    }
  })

  it('crea y elimina una parte (rol admin)', async () => {
    // ids reales de tipos_partes y grupos_partes de la empresa 2
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    const tp = await supabase.from('tipos_partes').select('id').limit(1)
    const gp = await supabase.from('grupos_partes').select('id').limit(1)
    tipoParteId = tp.data![0].id
    grupoParteId = gp.data![0].id

    const app = new Hono().route('/api/v1/partes', partes)
    const ts = Date.now()
    const res = await app.request('/api/v1/partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        codigo: `P${ts}`.slice(0, 50),
        id_tipo: tipoParteId,
        id_grupo: grupoParteId,
        detalle: `test-parte-${ts}`,
      }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const del = await app.request(`/api/v1/partes/${body.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })

  it('rechaza validacion incompleta', async () => {
    const app = new Hono().route('/api/v1/partes', partes)
    const res = await app.request('/api/v1/partes', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: 'x' }),
    })
    expect(res.status).toBe(400)
  })
})
