import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { reportes } from './reportes'
import { login } from '../test-utils'

let adminToken = ''
let varianteId = 0

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
  const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
  const v = await supabase.from('variantes').select('id').limit(1)
  varianteId = v.data![0].id
})

describe('reportes', () => {
  it('destino-partes devuelve estructura', async () => {
    const app = new Hono().route('/api/v1/reportes', reportes)
    const res = await app.request(`/api/v1/reportes/destino-partes?id_variante=${varianteId}`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(Array.isArray(body.data.variantes)).toBe(true)
    expect(Array.isArray(body.data.rama1)).toBe(true)
    expect(Array.isArray(body.data.arbol)).toBe(true)
    expect(Array.isArray(body.data.plana)).toBe(true)
  })

  it('listado-ingenieria con tipo salida arbol', async () => {
    const app = new Hono().route('/api/v1/reportes', reportes)
    const res = await app.request(
      `/api/v1/reportes/listado-ingenieria?id_variante=${varianteId}&cantidad=2&tipo_salida=arbol`,
      { headers: { Authorization: `Bearer ${adminToken}` } }
    )
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(Array.isArray(body.data.tipos)).toBe(true)
  })

  it('planificacion-produccion con productos', async () => {
    const app = new Hono().route('/api/v1/reportes', reportes)
    const res = await app.request(
      `/api/v1/reportes/planificacion-produccion?productos=${varianteId}:2`,
      { headers: { Authorization: `Bearer ${adminToken}` } }
    )
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(Array.isArray(body.data.requerimientos)).toBe(true)
  })

  it('resumen-grupos devuelve grupos', async () => {
    const app = new Hono().route('/api/v1/reportes', reportes)
    const res = await app.request('/api/v1/reportes/resumen-grupos', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(Array.isArray(body.data.grupos)).toBe(true)
  })

  it('export listado-ingenieria xlsx devuelve binario', async () => {
    const app = new Hono().route('/api/v1/reportes', reportes)
    const res = await app.request(`/api/v1/reportes/listado-ingenieria/export?formato=xlsx&id_variante=${varianteId}&cantidad=2&tipo_salida=arbol`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    expect(res.headers.get('content-type')).toContain('spreadsheetml')
    const buf = Buffer.from(await res.arrayBuffer())
    expect(buf.subarray(0, 2).toString()).toBe('PK')
  })

  it('export planificacion-produccion pdf devuelve binario', async () => {
    const app = new Hono().route('/api/v1/reportes', reportes)
    const res = await app.request(`/api/v1/reportes/planificacion-produccion/export?formato=pdf&productos=${varianteId}:2`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    expect(res.headers.get('content-type')).toBe('application/pdf')
    const buf = Buffer.from(await res.arrayBuffer())
    expect(buf.subarray(0, 5).toString()).toBe('%PDF-')
  })
})
