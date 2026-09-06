import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { bom } from './bom'
import { login } from '../test-utils'

let adminToken = ''
let variantePadreId = 0
let varianteHijoId = 0
let umId = 0

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
  const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
  const v1 = await supabase.from('variantes').select('id').limit(1)
  const v2 = await supabase.from('variantes').select('id').order('id').range(1, 1)
  const um = await supabase.from('unidades_medida').select('id').limit(1)
  variantePadreId = v1.data![0].id
  varianteHijoId = v2.data![0].id
  umId = um.data![0].id
})

describe('bom', () => {
  it('lista BOM de la empresa', async () => {
    const app = new Hono().route('/api/v1/bom', bom)
    const res = await app.request('/api/v1/bom', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.pagination.total).toBe(27)
  })

  it('crea BOM atomico, lee y elimina', async () => {
    const app = new Hono().route('/api/v1/bom', bom)
    const res = await app.request('/api/v1/bom', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_padre_id: variantePadreId,
        version: '1.0',
        fecha_efectiva: '2026-09-06',
        detalles: [
          {
            variante_componente_id: varianteHijoId,
            cantidad_necesaria: 2,
            unidad_medida_id: umId,
            secuencia: 1,
          },
          {
            variante_componente_id: varianteHijoId,
            cantidad_necesaria: 1,
            unidad_medida_id: umId,
            secuencia: 2,
          },
        ],
      }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const bomId = body.data.id

    // leer con detalles
    const get = await app.request(`/api/v1/bom/${bomId}`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(get.status).toBe(200)
    const full = await get.json()
    expect(full.data.detalles.length).toBe(2)

    // reemplazar detalles con 1
    const put = await app.request(`/api/v1/bom/${bomId}/detalle`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify([
        { variante_componente_id: varianteHijoId, cantidad_necesaria: 5, unidad_medida_id: umId, secuencia: 1 },
      ]),
    })
    expect(put.status).toBe(200)

    // eliminar
    const del = await app.request(`/api/v1/bom/${bomId}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)

    // verificar limpieza
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    const check = await supabase.from('bom_detalle').select('id').eq('bom_id', bomId)
    expect(check.data!.length).toBe(0)
  })

  it('rechaza variante padre inexistente', async () => {
    const app = new Hono().route('/api/v1/bom', bom)
    const res = await app.request('/api/v1/bom', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_padre_id: 999999,
        version: '1.0',
        fecha_efectiva: '2026-09-06',
        detalles: [{ variante_componente_id: varianteHijoId, cantidad_necesaria: 1, unidad_medida_id: umId }],
      }),
    })
    expect(res.status).toBe(500)
  })
})
