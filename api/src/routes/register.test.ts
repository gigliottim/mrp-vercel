import { describe, it, expect, afterAll } from 'vitest'
import { Hono } from 'hono'
import { register } from './register.js'
import { login, MARTIN } from '../test-utils.js'

// Estado para el cleanup del afterAll:
// - companyId: empresa a borrar vía DELETE /api/v1/companies/:id (borra datos
//   tenant + user_company por company_id, incluyendo el vínculo de Martin que
//   el trigger fn_super_admin_auto_link agregó — Martin queda intacto).
// - adminUserId: auth user creado por el endpoint. DELETE de companies NO borra
//   usuarios de Auth: se elimina vía Admin API REST (service_role).
let companyId = 0
let adminUserId = ''

// Admin API de Auth vía fetch (PostgrestClient no expone .auth)
async function adminGetUserByEmail(email: string): Promise<{ id: string } | undefined> {
  const res = await fetch(`${process.env.SUPABASE_URL}/auth/v1/admin/users?page=1&per_page=200`, {
    headers: {
      apikey: process.env.SUPABASE_SERVICE_ROLE_KEY!,
      Authorization: `Bearer ${process.env.SUPABASE_SERVICE_ROLE_KEY!}`,
    },
  })
  const body = (await res.json()) as { users?: Array<{ email?: string; id: string }> }
  return body.users?.find((u) => u.email === email)
}

async function adminDeleteUser(userId: string): Promise<void> {
  const res = await fetch(`${process.env.SUPABASE_URL}/auth/v1/admin/users/${userId}`, {
    method: 'DELETE',
    headers: {
      apikey: process.env.SUPABASE_SERVICE_ROLE_KEY!,
      Authorization: `Bearer ${process.env.SUPABASE_SERVICE_ROLE_KEY!}`,
    },
  })
  if (!res.ok) console.error(`[cleanup] adminDeleteUser ${userId} -> ${res.status}`)
}

describe('register (público)', () => {
  it('crea empresa + admin + seed', async () => {
    const app = new Hono().route('/api/v1/register', register)
    const email = `admin-test-${Date.now()}@mimrp.com.ar`
    const res = await app.request('/api/v1/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        empresa_nombre: `Empresa Test ${Date.now()}`,
        contacto_email: email, admin_nombre: 'Admin Test',
        admin_email: email, password: 'Test1234!',
      }),
    })
    expect(res.status).toBe(201)
    const body = (await res.json()) as { data: { company_id: number } }
    companyId = body.data.company_id
    expect(companyId).toBeGreaterThan(0)

    // verificación: seed aplicado (unidades de la nueva empresa)
    const admin = await import('../lib/supabase.js').then((m) => m.createAdminClient())
    const um = await admin.from('unidades_medida').select('id').eq('company_id', companyId)
    expect(um.data!.length).toBeGreaterThanOrEqual(29)

    // capturar el user_id del admin creado para el cleanup: SIEMPRE por email
    // vía Admin API (nunca por orden de user_company: el trigger agrega la fila
    // de Martin y el orden no está garantizado)
    const created = await adminGetUserByEmail(email)
    expect(created).toBeDefined()
    adminUserId = created!.id
  })

  afterAll(async () => {
    // 1. Borrar empresa (datos tenant + user_company por company_id: el vínculo
    //    de Martin agregado por el trigger se borra con la empresa, Martin intacto)
    if (companyId) {
      const token = await login(MARTIN)
      const app = new Hono().route('/api/v1/companies', (await import('./companies.js')).companies)
      await app.request(`/api/v1/companies/${companyId}`, {
        method: 'DELETE',
        headers: { Authorization: `Bearer ${token}` },
      })
    }
    // 2. Borrar el auth user creado (best-effort, no enmascara el test)
    if (adminUserId) {
      try {
        await adminDeleteUser(adminUserId)
      } catch (e) {
        console.error('[cleanup] error al borrar auth user:', e)
      }
    }
  })
})
