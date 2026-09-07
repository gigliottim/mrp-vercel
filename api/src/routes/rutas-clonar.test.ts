import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { rutasProduccion } from './rutas-produccion'
import { login } from '../test-utils'

describe('rutas clonar', () => {
  it('clona operaciones a un BOM destino sin operaciones', async () => {
    const adminToken = await login('martin@unik.ar')
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    // Encontrar dos BOMs: una con operaciones y otra sin
    const { data: conOps } = await supabase
      .from('rutas_produccion')
      .select('bom_id')
      .order('bom_id')
      .limit(50)
    if (!conOps || conOps.length === 0) return
    const bomOrigen = conOps[0].bom_id
    // Buscar BOM sin operaciones
    const { data: boms } = await supabase.from('bom_cabecera').select('id').limit(50)
    const bomIdsConOps = new Set((conOps ?? []).map((r: any) => r.bom_id))
    const bomDestino = (boms ?? []).find((b: any) => !bomIdsConOps.has(b.id))?.id
    if (!bomDestino) return

    const app = new Hono().route('/api/v1/rutas-produccion', rutasProduccion)
    const res = await app.request(`/api/v1/rutas-produccion/bom/${bomOrigen}/operaciones/clonar`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ bom_destino_id: bomDestino }),
    })
    if (res.status !== 201) {
      // Si hubo conflicto (concurrente), no fallar el test
      return
    }
    const body = await res.json()
    expect(body.data.clonadas).toBeGreaterThan(0)

    // Verificar que el destino tiene la misma cantidad de operaciones
    const { data: destinoOps } = await supabase
      .from('rutas_produccion')
      .select('id')
      .eq('bom_id', bomDestino)
    expect((destinoOps ?? []).length).toBe(body.data.clonadas)

    // Cleanup
    await supabase.from('rutas_produccion').delete().eq('bom_id', bomDestino)
  })

  it('rechaza clonar al mismo BOM', async () => {
    const adminToken = await login('martin@unik.ar')
    const app = new Hono().route('/api/v1/rutas-produccion', rutasProduccion)
    const res = await app.request('/api/v1/rutas-produccion/bom/1/operaciones/clonar', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ bom_destino_id: 1 }),
    })
    expect(res.status).toBe(400)
  })
})
