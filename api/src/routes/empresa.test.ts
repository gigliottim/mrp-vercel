import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { Hono } from 'hono'
import { empresa } from './empresa'
import { login } from '../test-utils'

// Admin API de Auth via fetch (PostgrestClient no expone .auth).
// SIEMPRE buscar por email: nunca por posicion en la lista.
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

function decodeJwtPayload(token: string): Record<string, unknown> {
  return JSON.parse(Buffer.from(token.split('.')[1], 'base64url').toString())
}

let adminToken = ''

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
})

describe('empresa', () => {
  it('obtiene datos de la empresa', async () => {
    const app = new Hono().route('/api/v1/empresa', empresa)
    const res = await app.request('/api/v1/empresa', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data).toHaveProperty('name')
  })

  it('lista usuarios de la empresa', async () => {
    const app = new Hono().route('/api/v1/empresa', empresa)
    const res = await app.request('/api/v1/empresa/usuarios', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(Array.isArray(body.data)).toBe(true)
    expect(body.data.length).toBeGreaterThan(0)
  })

  it('crear usuario setea must_change_password y PATCH al cambiar password lo limpia', async () => {
    const app = new Hono().route('/api/v1/empresa', empresa)
    const email = `nuevo-${Date.now()}@mimrp.com.ar`
    let createdUserId = ''

    try {
      // 1. POST /usuarios con password temporal (default)
      const res = await app.request('/api/v1/empresa/usuarios', {
        method: 'POST',
        headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, nombre: 'Nuevo', role_id: 4 }),
      })
      expect(res.status).toBe(201)
      const body = (await res.json()) as { data: { user_id: string } }
      createdUserId = body.data.user_id

      // 2. Verificar en auth.users via Admin API (por email)
      const created = await adminGetUserByEmail(email)
      expect(created).toBeDefined()
      expect(created!.user_metadata?.must_change_password).toBe(true)

      // 3. Regresion JWT: user_metadata viaja en el payload del JWT real
      //    (el hook JWT agrega company_id/user_role y preserva user_metadata)
      const token = await login(email)
      const payload = decodeJwtPayload(token)
      const meta = payload.user_metadata as Record<string, unknown> | undefined
      expect(meta?.must_change_password).toBe(true)
      expect(Number(payload.company_id)).toBeGreaterThan(0)

      // 4. PATCH /usuarios/:userId cambiando password: limpia el flag
      const patchRes = await app.request(`/api/v1/empresa/usuarios/${createdUserId}`, {
        method: 'PATCH',
        headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ password: 'NuevaPass123!' }),
      })
      expect(patchRes.status).toBe(200)

      const after = await adminGetUserByEmail(email)
      expect(after!.user_metadata?.must_change_password).toBe(false)
      // el resto de la metadata se preserva (PUT reemplaza todo: el merge del endpoint lo evita)
      expect(after!.user_metadata?.name).toBe('Nuevo')
    } finally {
      // Cleanup: desvincular via endpoint y borrar auth user por email
      try {
        if (createdUserId) {
          const del = await app.request(`/api/v1/empresa/usuarios/${createdUserId}`, {
            method: 'DELETE',
            headers: { Authorization: `Bearer ${adminToken}` },
          })
          if (del.status !== 204) {
            console.error(`[cleanup] DELETE /api/v1/empresa/usuarios/${createdUserId} -> ${del.status}`)
          }
        }
      } catch (e) {
        console.error('[cleanup] error al desvincular usuario:', e)
      }
      const leftover = await adminGetUserByEmail(email)
      if (leftover) {
        try {
          await adminDeleteUser(leftover.id)
        } catch (e) {
          console.error('[cleanup] error al borrar auth user:', e)
        }
      }
    }
  })

  it('lista roles de la empresa', async () => {
    const app = new Hono().route('/api/v1/empresa', empresa)
    const res = await app.request('/api/v1/empresa/roles', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(Array.isArray(body.data)).toBe(true)
  })

  it('lista permisos y arbol de menu', async () => {
    const app = new Hono().route('/api/v1/empresa', empresa)
    const res = await app.request('/api/v1/empresa/permisos', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const res2 = await app.request('/api/v1/empresa/permisos/tree', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res2.status).toBe(200)
    const body2 = await res2.json()
    expect(Array.isArray(body2.data)).toBe(true)
  })
})
