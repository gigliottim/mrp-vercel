import { describe, it, expect } from 'vitest'
import { ApiError, apiFetch } from './api'

describe('apiFetch', () => {
  it('lanza ApiError con codigo en 401', async () => {
    const original = global.fetch
    global.fetch = (async () =>
      new Response(JSON.stringify({ error: { code: 'UNAUTHORIZED', message: 'x' } }), {
        status: 401,
      })) as typeof fetch
    try {
      await apiFetch('/x', 'token')
      expect.unreachable()
    } catch (e) {
      expect(e).toBeInstanceOf(ApiError)
      expect((e as ApiError).code).toBe('UNAUTHORIZED')
      expect((e as ApiError).status).toBe(401)
    } finally {
      global.fetch = original
    }
  })

  it('parsea respuesta paginada', async () => {
    const original = global.fetch
    global.fetch = (async () =>
      new Response(
        JSON.stringify({ data: [{ id: 1 }], pagination: { page: 1, perPage: 20, total: 1 } }),
        { status: 200 }
      )) as typeof fetch
    const r = await apiFetch<{ data: { id: number }[] }>('/x', 'token')
    expect(r.data[0].id).toBe(1)
    global.fetch = original
  })

  it('envia Authorization Bearer', async () => {
    const original = global.fetch
    let sentHeaders: HeadersInit | undefined
    global.fetch = (async (_url: string | URL | Request, init?: RequestInit) => {
      sentHeaders = init?.headers
      return new Response(JSON.stringify({ ok: true }), { status: 200 })
    }) as typeof fetch
    await apiFetch('/x', 'mi-token')
    const headers = sentHeaders as Record<string, string>
    expect(headers['Authorization']).toBe('Bearer mi-token')
    global.fetch = original
  })
})
