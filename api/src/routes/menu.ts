import { Hono } from 'hono'
import { requireAuth, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'

export const menu = new Hono<AuthEnv>()
menu.use('*', requireAuth)

menu.get('/', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const companyId = c.get('companyId')

  // Obtener role_id del usuario en esta empresa
  const { data: uc } = await supabase
    .from('user_company')
    .select('role_id')
    .eq('company_id', companyId)
    .eq('user_id', c.get('userId'))
    .single()
  if (!uc?.role_id) {
    return c.json({ data: [] })
  }

  // Items permitidos por ACL (default deny)
  const { data: acl } = await supabase
    .from('menu_acl')
    .select('menu_item_id')
    .eq('company_id', companyId)
    .eq('subject_type', 'role')
    .eq('subject_id', uc.role_id)
    .eq('effect', 'allow')

  if (!acl || acl.length === 0) {
    return c.json({ data: [] })
  }
  const allowedIds = acl.map((a) => a.menu_item_id)

  const { data, error } = await supabase
    .from('menu_items')
    .select('*')
    .in('id', allowedIds)
    .eq('is_active', true)
    .order('section_key')
    .order('sort_order')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})
