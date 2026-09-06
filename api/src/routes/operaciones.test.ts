import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { operaciones } from './operaciones'
import { login } from '../test-utils'

let adminToken = ''

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
})

describe('operaciones', () => {
  it('devuelve metricas y ordenes recientes', async () => {
    const app = new Hono().route('/api/v1/operaciones', operaciones)
    const res = await app.request('/api/v1/operaciones', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.metricas).toHaveProperty('centros_activos')
    expect(Array.isArray(body.data.ordenes_recientes)).toBe(true)
  })

  it('devuelve gantt agrupado por centro', async () => {
    const app = new Hono().route('/api/v1/operaciones', operaciones)
    const res = await app.request('/api/v1/operaciones/gantt', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(Array.isArray(body.data.filas)).toBe(true)
    expect(body.data.periodo).toHaveProperty('inicio')
  })
})
