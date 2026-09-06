import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createAdminClient, createUserClient } from '../lib/supabase.js'

const createSchema = z.object({
  name: z.string().min(1).max(120),
  slug: z.string().min(1).max(160).regex(/^[a-z0-9_-]+$/),
  tax_id: z.string().max(50).optional(),
  contact_email: z.string().email().max(120),
  admin_email: z.string().email(),
  admin_name: z.string().min(1).max(120),
  admin_password: z.string().min(8),
})

const TENANT_TABLES = [
  'agent_ai_logs', 'agent_conversations', 'agent_messages', 'almacenes',
  'bom_cabecera', 'bom_detalle', 'centros_trabajo', 'composicion_variantes',
  'compras', 'configuracion', 'configuracion_general', 'entidades',
  'grupos_partes', 'movimientos_inventario', 'movimientos_stock',
  'mrp_calculos_cabecera', 'mrp_sugerencias', 'ordenes_produccion', 'partes',
  'planificacion_recursos', 'rutas_produccion', 'tipos_depositos',
  'tipos_depositos_movimientos', 'tipos_partes', 'unidades_medida', 'variantes',
]

export const companies = new Hono<AuthEnv>()
companies.use('*', requireAuth)

// GET / → empresas del usuario (vía user_company)
companies.get('/', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('user_company')
    .select('company_id, role_id, companies(*)')
    .eq('user_id', c.get('userId'))
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

// POST / → alta de empresa (solo Super Administrador)
companies.post('/', requireRole('Super Administrador'), async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }

  const admin = createAdminClient()

  // 1. Crear empresa (trigger vincula a Martin automáticamente)
  const { data: company, error: e1 } = await admin
    .from('companies')
    .insert({
      name: parsed.data.name,
      slug: parsed.data.slug,
      tax_id: parsed.data.tax_id ?? null,
      contact_email: parsed.data.contact_email,
      status: 'active',
    })
    .select()
    .single()
  if (e1) return c.json({ error: { code: 'DB_ERROR', message: e1.message } }, 500)

  // 2. Crear usuario admin en Supabase Auth (fetch directo: PostgrestClient no expone auth)
  const authRes = await fetch(`${process.env.SUPABASE_URL}/auth/v1/admin/users`, {
    method: 'POST',
    headers: {
      apikey: process.env.SUPABASE_SERVICE_ROLE_KEY!,
      Authorization: `Bearer ${process.env.SUPABASE_SERVICE_ROLE_KEY!}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      email: parsed.data.admin_email,
      password: parsed.data.admin_password,
      email_confirm: true,
      user_metadata: { name: parsed.data.admin_name },
    }),
  })
  const authData = (await authRes.json()) as { id?: string; msg?: string }
  if (!authRes.ok) {
    await admin.from('companies').delete().eq('id', company.id) // rollback
    return c.json({ error: { code: 'DB_ERROR', message: authData.msg ?? 'no se pudo crear el usuario' } }, 500)
  }

  // 3. Vincular admin con rol Administrador (id 2)
  const { error: e3 } = await admin.from('user_company').insert({
    user_id: authData.id,
    company_id: company.id,
    role_id: 2,
  })
  if (e3) {
    await admin.from('companies').delete().eq('id', company.id)
    return c.json({ error: { code: 'DB_ERROR', message: e3.message } }, 500)
  }

  // 4. Sembrar datos base
  const { error: e4 } = await admin.rpc('seed_company', { p_company_id: company.id })
  if (e4) {
    await admin.from('companies').delete().eq('id', company.id)
    return c.json({ error: { code: 'DB_ERROR', message: e4.message } }, 500)
  }

  return c.json({ data: company }, 201)
})

// DELETE /:id → baja de empresa (solo Super Administrador)
companies.delete('/:id', requireRole('Super Administrador'), async (c) => {
  const id = Number(c.req.param('id'))
  if (id === c.get('companyId')) {
    return c.json({ error: { code: 'FORBIDDEN', message: 'No puedes eliminar la empresa activa' } }, 403)
  }
  const admin = createAdminClient()

  // 1. Datos de negocio (26 tablas tenant)
  for (const t of TENANT_TABLES) {
    const { error } = await admin.from(t).delete().eq('company_id', id)
    if (error) return c.json({ error: { code: 'DB_ERROR', message: `${t}: ${error.message}` } }, 500)
  }

  // 2. ACL, vínculos y empresa
  await admin.from('menu_acl').delete().eq('company_id', id)
  await admin.from('user_company').delete().eq('company_id', id)
  const { error } = await admin.from('companies').delete().eq('id', id)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)

  return c.body(null, 204)
})
