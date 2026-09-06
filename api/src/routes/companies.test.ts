import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { companies } from './companies.js'

let adminToken = ''
let supervisorToken = ''

import { login, MARTIN } from '../test-utils.js'

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

// login SIN cache de módulo: fetch directo a Supabase Auth (cada llamada
// dispara el custom_access_token_hook y genera un JWT nuevo)
async function loginFresh(email: string): Promise<string> {
  const PASSWORDS: Record<string, string> = {
    'martin@unik.ar': 'M#vua%f5A$Ja7K%N#6tH',
  }
  const res = await fetch(`${process.env.SUPABASE_URL}/auth/v1/token?grant_type=password`, {
    method: 'POST',
    headers: { apikey: process.env.SUPABASE_ANON_KEY!, 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password: PASSWORDS[email] ?? 'Temporal123!' }),
  })
  const body = (await res.json()) as { access_token?: string }
  if (!body.access_token) {
    throw new Error(`loginFresh falló para ${email}: ${JSON.stringify(body)}`)
  }
  return body.access_token
}

function decodeJwtPayload(token: string): { company_id?: unknown; user_role?: unknown } {
  return JSON.parse(Buffer.from(token.split('.')[1], 'base64url').toString())
}

// Admin API de Auth vía fetch (PostgrestClient no expone .auth)
async function adminGetUserByEmail(email: string): Promise<{ id: string; user_metadata?: Record<string, unknown> } | undefined> {
  const res = await fetch(`${process.env.SUPABASE_URL}/auth/v1/admin/users?page=1&per_page=200`, {
    headers: {
      apikey: process.env.SUPABASE_SERVICE_ROLE_KEY!,
      Authorization: `Bearer ${process.env.SUPABASE_SERVICE_ROLE_KEY!}`,
    },
  })
  const body = (await res.json()) as { users?: Array<{ email?: string; id: string; user_metadata?: Record<string, unknown> }> }
  return body.users?.find((u) => u.email === email)
}

// Nota: la Admin API de Auth solo acepta PUT en /admin/users/:id (PATCH da 405).
// PUT hace merge de user_metadata; un valor JSON null ELIMINA la clave (verificado),
// así que el cleanup con { active_company_id: null } remueve la clave del todo.
async function adminUpdateUserMetadata(userId: string, metadata: Record<string, unknown>): Promise<void> {
  const res = await fetch(`${process.env.SUPABASE_URL}/auth/v1/admin/users/${userId}`, {
    method: 'PUT',
    headers: {
      apikey: process.env.SUPABASE_SERVICE_ROLE_KEY!,
      Authorization: `Bearer ${process.env.SUPABASE_SERVICE_ROLE_KEY!}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ user_metadata: metadata }),
  })
  if (!res.ok) throw new Error(`adminUpdateUserMetadata falló: ${res.status} ${await res.text()}`)
}

describe('hook JWT active_company_id', () => {
  it('hook respeta active_company_id en el claim company_id', async () => {
    const app = new Hono().route('/api/v1/companies', companies)
    const admin = await import('../lib/supabase').then((m) => m.createAdminClient())

    // martinUserId: listUsers de la Admin API filtrando por email
    const martinUser = await adminGetUserByEmail(MARTIN)
    expect(martinUser).toBeDefined()
    const martinUserId = martinUser!.id

    const uc = await admin.from('user_company').select('company_id').eq('user_id', martinUserId)
    expect(uc.error).toBeNull()

    let createdCompanyId = 0
    let empresaDefault = 0
    try {
      // requiere 2+ empresas en el entorno de tests: si Martin tiene solo 1,
      // creamos otra vía POST /api/v1/companies (es Super Administrador y el
      // trigger fn_super_admin_auto_link lo vincula automáticamente)
      if (uc.data!.length < 2) {
        const ts = Date.now()
        const res = await app.request('/api/v1/companies', {
          method: 'POST',
          headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
          body: JSON.stringify({
            name: `Test Hook ${ts}`,
            slug: `test-hook-${ts}`.slice(0, 60),
            contact_email: `admin-${ts}@test.com`,
            admin_email: `admin-${ts}@test.com`,
            admin_name: 'Admin Test',
            admin_password: 'Password123!',
          }),
        })
        expect(res.status).toBe(201)
        createdCompanyId = ((await res.json()).data as { id: number }).id
      }

      const uc2 = await admin.from('user_company').select('company_id').eq('user_id', martinUserId)
      expect(uc2.error).toBeNull()
      expect(uc2.data!.length).toBeGreaterThanOrEqual(2) // requiere 2+ empresas en el entorno de tests

      const ids = uc2.data!.map((r) => r.company_id).sort((a, b) => a - b)
      empresaDefault = Number(ids[0]) // lo que el hook elige sin active_company_id (ORDER BY company_id)
      const segunda = ids[ids.length - 1]
      expect(Number(segunda)).toBeGreaterThan(empresaDefault)

      // Setear active_company_id y loguear SIN cache
      await adminUpdateUserMetadata(martinUserId, { active_company_id: segunda })
      const token = await loginFresh(MARTIN)
      const payload = decodeJwtPayload(token)
      expect(Number(payload.company_id)).toBe(Number(segunda))
      expect(String(payload.user_role)).toBe('Super Administrador')
    } finally {
      // Cleanup best-effort: nunca enmascara el error original del test
      try {
        if (createdCompanyId > 0) {
          const del = await app.request(`/api/v1/companies/${createdCompanyId}`, {
            method: 'DELETE',
            headers: { Authorization: `Bearer ${adminToken}` },
          })
          if (del.status !== 204) {
            console.error(`[cleanup] DELETE /api/v1/companies/${createdCompanyId} -> ${del.status}`)
          }
        }
      } catch (e) {
        console.error('[cleanup] error al borrar empresa de test:', e)
      }
      // Restaurar metadata (JSON null -> hook cae al fallback ORDER BY company_id)
      await adminUpdateUserMetadata(martinUserId, { active_company_id: null })
    }

    // Regresión: sin active_company_id efectivo el claim vuelve a la empresa default
    const token2 = await loginFresh(MARTIN)
    const payload2 = decodeJwtPayload(token2)
    expect(Number(payload2.company_id)).toBe(empresaDefault)
  })
})
