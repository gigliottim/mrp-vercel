import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { ordenesProduccion } from './ordenes-produccion'
import { login } from '../test-utils'

let adminToken = ''
let varianteId = 0
let bomId = 0

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
  const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
  const v = await supabase.from('variantes').select('id').limit(1)
  const b = await supabase.from('bom_cabecera').select('id').limit(1)
  varianteId = v.data![0].id
  bomId = b.data![0].id
})

describe('ordenes-produccion', () => {
  it('lista ordenes (empresa 2 tiene 1)', async () => {
    const app = new Hono().route('/api/v1/ordenes-produccion', ordenesProduccion)
    const res = await app.request('/api/v1/ordenes-produccion', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.pagination.total).toBe(1)
  })

  it('crea orden con numero generado y recorre estados', async () => {
    const app = new Hono().route('/api/v1/ordenes-produccion', ordenesProduccion)
    const res = await app.request('/api/v1/ordenes-produccion', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_id: varianteId,
        bom_id_utilizada: bomId,
        cantidad_planificada: 10,
        fecha_inicio_programada: '2026-09-07',
        fecha_fin_programada: '2026-09-10',
        prioridad: 'alta',
      }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const id = body.data.id
    expect(body.data.numero_orden).toMatch(/^OP-\d{8}-2-\d{4}$/)
    expect(body.data.estado).toBe('borrador')

    // recorrer estados válidos
    const estados = ['planificada', 'liberada', 'en_proceso', 'completada', 'cerrada']
    for (const estado of estados) {
      const r = await app.request(`/api/v1/ordenes-produccion/${id}/estado`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ estado }),
      })
      expect(r.status).toBe(200)
    }

    // transición inválida desde cerrada
    const inv = await app.request(`/api/v1/ordenes-produccion/${id}/estado`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ estado: 'borrador' }),
    })
    expect(inv.status).toBe(400)

    // delete en estado != borrador → 400
    const del = await app.request(`/api/v1/ordenes-produccion/${id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(400)

    // limpiar vía admin (la orden quedó cerrada)
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    await supabase.from('ordenes_produccion').delete().eq('id', id)
  })

  it('crea orden en borrador y la elimina', async () => {
    const app = new Hono().route('/api/v1/ordenes-produccion', ordenesProduccion)
    const res = await app.request('/api/v1/ordenes-produccion', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        variante_id: varianteId,
        cantidad_planificada: 5,
        fecha_inicio_programada: '2026-09-07',
        fecha_fin_programada: '2026-09-08',
      }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const del = await app.request(`/api/v1/ordenes-produccion/${body.data.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)
  })
})
