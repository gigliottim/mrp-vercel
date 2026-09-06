import { describe, it, expect } from 'vitest'
import app from './index'

describe('api health', () => {
  it('responde ok en /api/health', async () => {
    const res = await app.request('/api/health')
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.status).toBe('ok')
    expect(body.version).toBe('0.2.0')
  })
})
