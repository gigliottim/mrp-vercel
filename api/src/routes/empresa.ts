import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createAdminClient, createUserClient } from '../lib/supabase.js'

// ─── Empresa / Usuarios / Roles / Permisos ──────────────────────────────────
// Port de EmpresaUsuariosController + EmpresaUsuariosService + AclService

export const empresa = new Hono<AuthEnv>()
empresa.use('*', requireAuth)

// ─── Empresa ─────────────────────────────────────────────────────────────────

empresa.get('/', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('companies')
    .select('*')
    .eq('id', c.get('companyId'))
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

const empresaSchema = z.object({
  name: z.string().min(1).max(120).optional(),
  tax_id: z.string().max(50).nullable().optional(),
  contact_email: z.string().email().max(120).optional(),
  phone: z.string().max(50).nullable().optional(),
  address: z.string().max(200).nullable().optional(),
  city: z.string().max(100).nullable().optional(),
  province: z.string().max(100).nullable().optional(),
  country: z.string().max(100).nullable().optional(),
  zip_code: z.string().max(20).nullable().optional(),
})

empresa.patch('/', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = empresaSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('companies')
    .update(parsed.data)
    .eq('id', c.get('companyId'))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

// ─── Usuarios ────────────────────────────────────────────────────────────────

empresa.get('/usuarios', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('user_company')
    .select('user_id, role_id, roles(*)')
    .eq('company_id', c.get('companyId'))
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)

  // Enriquecer con email/nombre desde auth.users (admin client)
  const admin = createAdminClient()
  const userIds = (data ?? []).map((r: { user_id: string }) => r.user_id)
  const { data: users } = await admin
    .from('auth.users')
    .select('id, email, raw_user_meta_data')
    .in('id', userIds.length > 0 ? userIds : ['00000000-0000-0000-0000-000000000000'])

  const rows = (data ?? []).map((r: { user_id: string; role_id: number; roles?: unknown }) => {
    const u = (users ?? []).find((x: { id: string }) => x.id === r.user_id)
    return {
      user_id: r.user_id,
      email: u?.email ?? '',
      nombre: u?.raw_user_meta_data?.name ?? u?.raw_user_meta_data?.full_name ?? '',
      role_id: r.role_id,
      role_nombre: (r.roles as { nombre?: string } | null)?.nombre ?? '',
    }
  })
  return c.json({ data: rows })
})

const usuarioSchema = z.object({
  email: z.string().email(),
  nombre: z.string().max(120).optional(),
  role_id: z.number().int().positive(),
  password: z.string().min(8).optional(),
})

empresa.post('/usuarios', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = usuarioSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const admin = createAdminClient()

  // Crear usuario en auth
  const { data: authUser, error: eAuth } = await admin.auth.admin.createUser({
    email: parsed.data.email,
    password: parsed.data.password ?? 'Temporal123!',
    email_confirm: true,
    user_metadata: { name: parsed.data.nombre ?? '' },
  })
  if (eAuth) return c.json({ error: { code: 'AUTH_ERROR', message: eAuth.message } }, 400)

  // Vincular a la empresa
  const { error: eLink } = await admin.from('user_company').insert({
    user_id: authUser.user.id,
    company_id: c.get('companyId'),
    role_id: parsed.data.role_id,
  })
  if (eLink) return c.json({ error: { code: 'DB_ERROR', message: eLink.message } }, 500)

  return c.json({ data: { user_id: authUser.user.id } }, 201)
})

empresa.patch('/usuarios/:userId', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = usuarioSchema.partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const admin = createAdminClient()
  const userId = c.req.param('userId')

  if (parsed.data.role_id) {
    const { error: eRole } = await admin
      .from('user_company')
      .update({ role_id: parsed.data.role_id })
      .eq('user_id', userId)
      .eq('company_id', c.get('companyId'))
    if (eRole) return c.json({ error: { code: 'DB_ERROR', message: eRole.message } }, 500)
  }
  if (parsed.data.password) {
    const { error: ePass } = await admin.auth.admin.updateUserById(userId, {
      password: parsed.data.password,
    })
    if (ePass) return c.json({ error: { code: 'AUTH_ERROR', message: ePass.message } }, 400)
  }
  return c.json({ data: { ok: true } })
})

empresa.delete('/usuarios/:userId', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const admin = createAdminClient()
  const userId = c.req.param('userId')
  const { error: eLink } = await admin
    .from('user_company')
    .delete()
    .eq('user_id', userId)
    .eq('company_id', c.get('companyId'))
  if (eLink) return c.json({ error: { code: 'DB_ERROR', message: eLink.message } }, 500)
  return c.body(null, 204)
})

// ─── Roles ────────────────────────────────────────────────────────────────────

empresa.get('/roles', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('roles')
    .select('*')
    .order('id')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

const roleSchema = z.object({
  name: z.string().min(1).max(100),
  guard_name: z.string().max(100).default('web'),
})

empresa.post('/roles', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = roleSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('roles')
    .insert(parsed.data)
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

empresa.patch('/roles/:id', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = roleSchema.partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('roles')
    .update(parsed.data)
    .eq('id', Number(c.req.param('id')))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

empresa.delete('/roles/:id', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { error } = await supabase
    .from('roles')
    .delete()
    .eq('id', Number(c.req.param('id')))
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.body(null, 204)
})

// ─── Permisos (ACL) ───────────────────────────────────────────────────────────

empresa.get('/permisos', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('menu_acl')
    .select('*')
    .eq('company_id', c.get('companyId'))
    .order('menu_item_id')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

empresa.get('/permisos/tree', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('menu_items')
    .select('*')
    .order('sort_order')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

const aclSchema = z.object({
  menu_item_id: z.number().int().positive(),
  subject_type: z.enum(['role', 'user']),
  subject_id: z.union([z.number().int().positive(), z.string().min(1)]),
  effect: z.enum(['allow', 'deny']),
})

empresa.post('/permisos', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = aclSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('menu_acl')
    .upsert({ ...parsed.data, company_id: c.get('companyId') }, { onConflict: 'company_id,menu_item_id,subject_type,subject_id' })
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

const bulkSchema = z.object({
  menu_item_ids: z.array(z.number().int().positive()).min(1),
  perms: z.array(
    z.object({
      subject_type: z.enum(['role', 'user']),
      subject_id: z.union([z.number().int().positive(), z.string().min(1)]),
      effect: z.enum(['allow', 'deny']),
    })
  ),
})

empresa.post('/permisos/bulk', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = bulkSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const rows = parsed.data.menu_item_ids.flatMap((menuItemId) =>
    parsed.data.perms.map((p) => ({
      menu_item_id: menuItemId,
      subject_type: p.subject_type,
      subject_id: p.subject_id,
      effect: p.effect,
      company_id: c.get('companyId'),
    }))
  )
  const { error } = await supabase.from('menu_acl').upsert(rows, {
    onConflict: 'company_id,menu_item_id,subject_type,subject_id',
  })
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data: { ok: true } })
})

empresa.delete('/permisos/:id', requireRole('Super Administrador', 'Administrador'), async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { error } = await supabase
    .from('menu_acl')
    .delete()
    .eq('id', Number(c.req.param('id')))
    .eq('company_id', c.get('companyId'))
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.body(null, 204)
})
