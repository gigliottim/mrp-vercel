import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { companies } from './companies'

let adminToken = ''
let supervisorToken = ''

import { login } from '../test-utils'

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
  supervisorToken = await login('sabrinasmurro22@gmail.com')
})

describe('companies', () => {
  it('lista empresas del usuario', async () => {
    const app = new Hono().route('/api/v1/companies', companies)
    const res = await app.request('/api/v1/companies', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.length).toBeGreaterThan(0)
  })

  it('crea empresa con seed y la elimina (super admin)', async () => {
    const app = new Hono().route('/api/v1/companies', companies)
    const ts = Date.now()
    const slug = `test-p2-${ts}`.slice(0, 60)
    const res = await app.request('/api/v1/companies', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: `Test P2 ${ts}`,
        slug,
        contact_email: `admin-${ts}@test.com`,
        admin_email: `admin-${ts}@test.com`,
        admin_name: 'Admin Test',
        admin_password: 'Password123!',
      }),
    })
    expect(res.status).toBe(201)
    const body = await res.json()
    const companyId = body.data.id

    // Verificar que seed_company corrió (unidades + depósitos + almacenes)
    const admin = await import('../lib/supabase').then((m) => m.createAdminClient())
    const um = await admin.from('unidades_medida').select('id').eq('company_id', companyId)
    expect(um.data!.length).toBe(29)
    const dep = await admin.from('tipos_depositos').select('id').eq('company_id', companyId)
    expect(dep.data!.length).toBe(6)
    const alm = await admin.from('almacenes').select('id').eq('company_id', companyId)
    expect(alm.data!.length).toBe(2)

    // Eliminar
    const del = await app.request(`/api/v1/companies/${companyId}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(del.status).toBe(204)

    // Verificar limpieza
    const check = await admin.from('unidades_medida').select('id').eq('company_id', companyId)
    expect(check.data!.length).toBe(0)
  })

  it('rechaza alta a supervisor', async () => {
    const app = new Hono().route('/api/v1/companies', companies)
    const res = await app.request('/api/v1/companies', {
      method: 'POST',
      headers: { Authorization: `Bearer ${supervisorToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: 'x', slug: 'x', contact_email: 'x@x.com',
        admin_email: 'x@x.com', admin_name: 'x', admin_password: '12345678',
      }),
    })
    expect(res.status).toBe(403)
  })
})
