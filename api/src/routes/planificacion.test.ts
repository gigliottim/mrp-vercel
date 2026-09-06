import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { planificacion } from './planificacion'
import { centrosTrabajo } from './centros-trabajo'
import { login } from '../test-utils'

let adminToken = ''
let ordenId = 0
let centroId = 0

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
  const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
  const o = await supabase.from('ordenes_produccion').select('id').limit(1)
  ordenId = o.data![0].id
  const ct = await supabase.from('centros_trabajo').select('id').limit(1)
  centroId = ct.data && ct.data.length > 0 ? ct.data[0].id : 0
})

describe('planificacion', () => {
  it('crea recurso, detecta solapamiento y limpia', async () => {
    const app = new Hono()
      .route('/api/v1/centros-trabajo', centrosTrabajo)
      .route('/api/v1/planificacion', planificacion)
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())

    // crear centro de trabajo de prueba si no hay
    let ctId = centroId
    if (!ctId) {
      const ts = Date.now()
      const ct = await app.request('/api/v1/centros-trabajo', {
        method: 'POST',
        headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ codigo: `CT${ts}`.slice(0, 20), nombre: `test-ct-${ts}` }),
      })
      ctId = (await ct.json()).data.id
    }

    // recurso A
    const r1 = await app.request('/api/v1/planificacion', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        orden_produccion_id: ordenId,
        centro_trabajo_id: ctId,
        inicio: '2026-09-07T08:00:00Z',
        fin: '2026-09-07T10:00:00Z',
      }),
    })
    expect(r1.status).toBe(201)
    const recA = await r1.json()

    // solapamiento → 400
    const r2 = await app.request('/api/v1/planificacion', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        orden_produccion_id: ordenId,
        centro_trabajo_id: ctId,
        inicio: '2026-09-07T09:00:00Z',
        fin: '2026-09-07T11:00:00Z',
      }),
    })
    expect(r2.status).toBe(400)

    // periodo sin solape → 201
    const r3 = await app.request('/api/v1/planificacion', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        orden_produccion_id: ordenId,
        centro_trabajo_id: ctId,
        inicio: '2026-09-07T14:00:00Z',
        fin: '2026-09-07T16:00:00Z',
      }),
    })
    expect(r3.status).toBe(201)
    const recB = await r3.json()

    // limpiar
    await app.request(`/api/v1/planificacion/${recA.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    await app.request(`/api/v1/planificacion/${recB.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    if (!centroId) {
      await app.request(`/api/v1/centros-trabajo/${ctId}`, {
        method: 'DELETE',
        headers: { Authorization: `Bearer ${adminToken}` },
      })
    }
  })

  it('rechaza inicio despues de fin', async () => {
    const app = new Hono().route('/api/v1/planificacion', planificacion)
    const res = await app.request('/api/v1/planificacion', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        orden_produccion_id: ordenId,
        centro_trabajo_id: centroId || 1,
        inicio: '2026-09-07T10:00:00Z',
        fin: '2026-09-07T08:00:00Z',
      }),
    })
    expect(res.status).toBe(400)
  })
})

describe('planificacion automatica', () => {
  it('rechaza orden sin BOM o sin ruta', async () => {
    const app = new Hono().route('/api/v1/planificacion', planificacion)
    const res = await app.request('/api/v1/planificacion/calcular', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ orden_id: 999999 }),
    })
    expect(res.status).toBe(404)
  })
})
