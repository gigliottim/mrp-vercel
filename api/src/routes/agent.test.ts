import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { agent } from './agent'
import { login } from '../test-utils'

let adminToken = ''

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
})

function makeApp() {
  return new Hono().route('/api/v1/agent', agent)
}

describe('agent', () => {
  it('devuelve sugerencias', async () => {
    const app = makeApp()
    const res = await app.request('/api/v1/agent/suggestions', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.length).toBeGreaterThanOrEqual(3)
  })

  it('devuelve lookups', async () => {
    const app = makeApp()
    const res = await app.request('/api/v1/agent/lookup/tipos_partes', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.length).toBeGreaterThan(0)
    expect(body.data[0]).toHaveProperty('value')
    expect(body.data[0]).toHaveProperty('label')
  })

  it('inicia flujo guiado create_part', async () => {
    const app = makeApp()
    const res = await app.request('/api/v1/agent/message', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: 'crear nueva parte' }),
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.status).toBe('clarify')
    expect(body.data.message).toContain('tipo de parte')
    expect(body.data.guided_state.intent).toBe('create_part')
  })

  it('flujo completo create_supplier hasta preview', async () => {
    const app = makeApp()
    let convId = ''
    let state: any = null

    // 1. iniciar
    let res = await app.request('/api/v1/agent/message', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: 'registrar proveedor' }),
    })
    let body = await res.json()
    convId = body.data.conversation_id
    state = body.data.guided_state

    // 2. tipo
    res = await app.request('/api/v1/agent/message', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: convId, message: 'Proveedor', field_value: 'PROVEEDOR', field_label: 'Proveedor' }),
    })
    body = await res.json()
    expect(body.data.status).toBe('clarify')

    // 3. razon social
    res = await app.request('/api/v1/agent/message', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: convId, message: 'E2E Proveedor Test' }),
    })
    body = await res.json()
    expect(body.data.status).toBe('clarify')

    // 4. CUIT (opcional, Enter)
    res = await app.request('/api/v1/agent/message', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: convId, message: '' }),
    })
    body = await res.json()
    expect(body.data.status).toBe('clarify')

    // 5. email (opcional)
    res = await app.request('/api/v1/agent/message', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: convId, message: '' }),
    })
    body = await res.json()
    expect(body.data.status).toBe('clarify')

    // 6. telefono (opcional)
    res = await app.request('/api/v1/agent/message', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: convId, message: '' }),
    })
    body = await res.json()
    expect(body.data.status).toBe('clarify')

    // 7. direccion (opcional) → preview
    res = await app.request('/api/v1/agent/message', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: convId, message: '' }),
    })
    body = await res.json()
    expect(body.data.status).toBe('preview')
    expect(body.data.message).toContain('Confirmamos')

    // 8. confirmar → guardar
    res = await app.request('/api/v1/agent/confirm', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: convId, intent: 'create_supplier', data: body.data.data }),
    })
    body = await res.json()
    expect(body.data.success).toBe(true)

    // limpiar
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    await supabase.from('entidades').delete().eq('razon_social', 'E2E Proveedor Test')
  })

  it('cancela conversación', async () => {
    const app = makeApp()
    const res = await app.request('/api/v1/agent/cancel', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: 'test-conv' }),
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.success).toBe(true)
  })

  it('persiste la conversación en agent_messages', async () => {
    const app = makeApp()
    const convId = crypto.randomUUID()
    const res = await app.request('/api/v1/agent/message', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: convId, message: 'crear parte' }),
    })
    expect(res.status).toBe(200)
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    const { data: msgs } = await supabase.from('agent_messages').select('id, role').eq('conversation_id', convId)
    expect(msgs!.length).toBeGreaterThanOrEqual(1)
    const { data: conv } = await supabase.from('agent_conversations').select('id').eq('metadata->>conv_key', convId).maybeSingle()
    expect(conv).not.toBeNull()
    // cleanup
    await supabase.from('agent_messages').delete().eq('conversation_id', convId)
    await supabase.from('agent_conversations').delete().eq('metadata->>conv_key', convId)
  })

  it('registra log de IA en agent_ai_logs', async () => {
    const app = makeApp()
    const convId = crypto.randomUUID()
    const res = await app.request('/api/v1/agent/message', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: convId, message: 'hola' }),
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    // modo offline (mensaje de fallback): la IA no fue llamada, no hay log que verificar
    if (typeof body.data.message === 'string' && body.data.message.startsWith('Soy el asistente de MRP')) {
      return
    }
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    const { data: logs } = await supabase.from('agent_ai_logs').select('id').eq('conversation_id', convId)
    expect(logs!.length).toBeGreaterThanOrEqual(1)
    // cleanup
    await supabase.from('agent_ai_logs').delete().eq('conversation_id', convId)
    await supabase.from('agent_messages').delete().eq('conversation_id', convId)
    await supabase.from('agent_conversations').delete().eq('metadata->>conv_key', convId)
  })
})
