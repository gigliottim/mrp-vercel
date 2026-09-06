import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { menu } from './menu'
import { login } from '../test-utils'

let supervisorToken = ''
let adminToken = ''

beforeAll(async () => {
  supervisorToken = await login('sabrinasmurro22@gmail.com')
  adminToken = await login('martin@unik.ar')
})

describe('menu', () => {
  it('devuelve items segun ACL del supervisor (empresa 2)', async () => {
    const app = new Hono().route('/api/v1/menu', menu)
    const res = await app.request('/api/v1/menu', {
      headers: { Authorization: `Bearer ${supervisorToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(Array.isArray(body.data)).toBe(true)
    // El dump tiene ACL para Supervisor (role 3): panel.inicio (id 1) está permitido
    const codes = body.data.map((i: { code: string }) => i.code)
    expect(codes).toContain('panel.inicio')
  })

  it('devuelve items para super admin', async () => {
    const app = new Hono().route('/api/v1/menu', menu)
    const res = await app.request('/api/v1/menu', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.length).toBeGreaterThan(0)
  })
})
