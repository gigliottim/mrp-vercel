import { createMiddleware } from 'hono/factory'

export type AuthEnv = {
  Variables: {
    userId: string
    companyId: number
    userRole: string
  }
}

export const requireAuth = createMiddleware<AuthEnv>(async (c, next) => {
  const header = c.req.header('Authorization')
  if (!header?.startsWith('Bearer ')) {
    return c.json({ error: { code: 'UNAUTHORIZED', message: 'Falta token' } }, 401)
  }
  const token = header.slice(7)

  // Verificar el JWT contra la API REST de Supabase Auth
  // (evita createClient: supabase-js inicializa RealtimeClient que requiere
  // WebSocket nativo, ausente en Node < 22)
  const res = await fetch(`${process.env.SUPABASE_URL}/auth/v1/user`, {
    headers: {
      apikey: process.env.SUPABASE_ANON_KEY!,
      Authorization: `Bearer ${token}`,
    },
  })
  if (!res.ok) {
    return c.json({ error: { code: 'UNAUTHORIZED', message: 'Token inválido' } }, 401)
  }
  const user = (await res.json()) as { id: string }

  // Los claims custom (company_id, user_role) viven en el PAYLOAD del JWT
  // (inyectados por custom_access_token_hook), no en app_metadata.
  const payload = JSON.parse(
    Buffer.from(token.split('.')[1], 'base64url').toString()
  ) as Record<string, unknown>
  const companyId = Number(payload.company_id)
  const userRole = String(payload.user_role ?? '')
  if (!Number.isInteger(companyId) || companyId <= 0) {
    return c.json({ error: { code: 'FORBIDDEN', message: 'Sin empresa asignada' } }, 403)
  }
  c.set('userId', user.id)
  c.set('companyId', companyId)
  c.set('userRole', userRole)
  await next()
})

export const requireRole = (...roles: string[]) =>
  createMiddleware<AuthEnv>(async (c, next) => {
    const role = c.get('userRole')
    if (!roles.includes(role)) {
      return c.json({ error: { code: 'FORBIDDEN', message: 'Rol insuficiente' } }, 403)
    }
    await next()
  })
