import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { configuracion } from './configuracion.js'
import { login } from '../test-utils.js'

let token = ''

beforeAll(async () => {
  token = await login('sabrinasmurro22@gmail.com')
})

describe('configuracion', () => {
  it('obtiene configuracion_general de la empresa', async () => {
    const app = new Hono().route('/api/v1/configuracion', configuracion)
    const res = await app.request('/api/v1/configuracion', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.decimal_places).toBeGreaterThan(0)
  })

  it('actualiza decimal_places', async () => {
    const app = new Hono().route('/api/v1/configuracion', configuracion)
    const res = await app.request('/api/v1/configuracion', {
      method: 'PATCH',
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ decimal_places: 2 }),
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.decimal_places).toBe(2)
    // restaurar
    await app.request('/api/v1/configuracion', {
      method: 'PATCH',
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ decimal_places: 4 }),
    })
  })

  it('rechaza separadores iguales', async () => {
    const app = new Hono().route('/api/v1/configuracion', configuracion)
    const res = await app.request('/api/v1/configuracion', {
      method: 'PATCH',
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ thousand_separator: '.', decimal_separator: '.' }),
    })
    expect(res.status).toBe(400)
  })

  it('upsert y lee clave/valor', async () => {
    const app = new Hono().route('/api/v1/configuracion', configuracion)
    const clave = `test-kv-${Date.now()}`
    const put = await app.request(`/api/v1/configuracion/kv/${clave}`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ valor: 'v1', tipo: 'string' }),
    })
    expect(put.status).toBe(200)
    const get = await app.request('/api/v1/configuracion/kv', {
      headers: { Authorization: `Bearer ${token}` },
    })
    const body = await get.json()
    const found = body.data.find((r: { clave: string }) => r.clave === clave)
    expect(found).toBeTruthy()
    expect(found.valor).toBe('v1')
  })
})
