import { Hono } from 'hono'
import { z } from 'zod'
import { createAdminClient } from '../lib/supabase.js'

const schema = z.object({
  empresa_nombre: z.string().min(2).max(100),
  empresa_cuit: z.string().max(20).optional(),
  contacto_email: z.string().email().max(150),
  admin_nombre: z.string().min(2).max(100),
  admin_email: z.string().email().max(150),
  password: z.string().min(8).max(72),
})

// Rate limit simple por IP (serverless: por instancia)
const intentos = new Map<string, { count: number; resetAt: number }>()
const LIMIT = 3
const WINDOW_MS = 60_000

function rateLimited(ip: string): boolean {
  const now = Date.now()
  const e = intentos.get(ip)
  if (!e || now > e.resetAt) {
    intentos.set(ip, { count: 1, resetAt: now + WINDOW_MS })
    return false
  }
  e.count++
  return e.count > LIMIT
}

function slugify(nombre: string): string {
  return nombre.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '').slice(0, 40) || 'empresa'
}

// Admin API de Auth vía fetch directo (PostgrestClient no expone .auth)
async function adminCreateUser(payload: {
  email: string
  password: string
  email_confirm: boolean
  user_metadata: Record<string, unknown>
}): Promise<{ id?: string; msg?: string }> {
  const res = await fetch(`${process.env.SUPABASE_URL}/auth/v1/admin/users`, {
    method: 'POST',
    headers: {
      apikey: process.env.SUPABASE_SERVICE_ROLE_KEY!,
      Authorization: `Bearer ${process.env.SUPABASE_SERVICE_ROLE_KEY!}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  })
  return (await res.json()) as { id?: string; msg?: string }
}

async function adminDeleteUser(userId: string): Promise<void> {
  await fetch(`${process.env.SUPABASE_URL}/auth/v1/admin/users/${userId}`, {
    method: 'DELETE',
    headers: {
      apikey: process.env.SUPABASE_SERVICE_ROLE_KEY!,
      Authorization: `Bearer ${process.env.SUPABASE_SERVICE_ROLE_KEY!}`,
    },
  })
}

export const register = new Hono()
register.post('/', async (c) => {
  const ip = c.req.header('x-forwarded-for')?.split(',')[0]?.trim() ?? 'unknown'
  if (rateLimited(ip)) return c.json({ error: { code: 'RATE_LIMIT', message: 'Demasiados intentos, probá en un minuto' } }, 429)
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) return c.json({ error: { code: 'VALIDATION', message: parsed.error.issues[0]?.message ?? 'datos inválidos' } }, 400)
  const d = parsed.data
  const admin = createAdminClient()

  // 1. Crear usuario admin en Supabase Auth (fetch directo: PostgrestClient no expone auth)
  const user = await adminCreateUser({
    email: d.admin_email, password: d.password, email_confirm: true,
    user_metadata: { full_name: d.admin_nombre, must_change_password: false },
  })
  if (!user.id) {
    const msg = user.msg ?? 'no se pudo crear el usuario'
    return c.json({ error: { code: 'VALIDATION', message: msg.includes('already') ? 'email ya registrado' : msg } }, 400)
  }
  const userId = user.id

  // 2. Crear empresa + vínculo + seed (rollback cascada)
  let companyId: number | null = null
  try {
    const { data: company, error: eCompany } = await admin
      .from('companies')
      .insert({ name: d.empresa_nombre, slug: slugify(d.empresa_nombre), tax_id: d.empresa_cuit ?? null, contact_email: d.contacto_email, status: 'active' })
      .select()
      .single()
    if (eCompany) throw eCompany
    companyId = company.id

    const { data: rol, error: eRol } = await admin.from('roles').select('id').eq('name', 'Administrador').single()
    if (eRol || !rol) throw eRol ?? new Error('rol Administrador no encontrado')

    const { error: eLink } = await admin.from('user_company').insert({
      user_id: userId, company_id: company.id, role_id: rol.id,
    })
    if (eLink) throw eLink

    const { error: eSeed } = await admin.rpc('seed_company', { p_company_id: company.id })
    if (eSeed) throw eSeed

    return c.json({ data: { company_id: company.id } }, 201)
  } catch (e) {
    // rollback best-effort: vínculos (el trigger fn_super_admin_auto_link pudo
    // agregar filas ajenas: limpiar por company_id las cubre), empresa y auth user
    if (companyId) {
      await admin.from('user_company').delete().eq('company_id', companyId)
      await admin.from('companies').delete().eq('id', companyId)
    } else {
      await admin.from('user_company').delete().eq('user_id', userId)
    }
    await adminDeleteUser(userId)
    return c.json({ error: { code: 'DB_ERROR', message: 'No se pudo crear la empresa' } }, 500)
  }
})
