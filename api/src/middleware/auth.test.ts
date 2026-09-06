import { describe, it, expect } from 'vitest'
import { Hono } from 'hono'
import { requireAuth, type AuthEnv } from './auth.js'

describe('requireAuth', () => {
  it('rechaza sin token', async () => {
    const app = new Hono<AuthEnv>()
    app.get('/x', requireAuth, (c) => c.text('ok'))
    const res = await app.request('/x')
    expect(res.status).toBe(401)
  })

  it('rechaza token inválido', async () => {
    const app = new Hono<AuthEnv>()
    app.get('/x', requireAuth, (c) => c.text('ok'))
    const res = await app.request('/x', {
      headers: { Authorization: 'Bearer token-invalido' },
    })
    expect(res.status).toBe(401)
  })

  it('acepta token real de Supabase', async () => {
    const login = await fetch(`${process.env.SUPABASE_URL}/auth/v1/token?grant_type=password`, {
      method: 'POST',
      headers: { apikey: process.env.SUPABASE_ANON_KEY!, 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: 'sabrinasmurro22@gmail.com', password: 'Temporal123!' }),
    })
    const auth = await login.json()
    expect(auth.access_token).toBeTruthy()

    const app = new Hono<AuthEnv>()
    app.get('/x', requireAuth, (c) =>
      c.json({ companyId: c.get('companyId'), role: c.get('userRole') })
    )
    const res = await app.request('/x', {
      headers: { Authorization: `Bearer ${auth.access_token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.companyId).toBe(2)
    expect(body.role).toBe('Supervisor')
  })
})
