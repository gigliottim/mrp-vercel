import 'server-only'
import { createClient } from '@/lib/supabase/server'

export type SessionInfo = {
  userId: string
  email: string
  companyId: number
  role: string
  accessToken: string
}

export async function getSession(): Promise<SessionInfo | null> {
  const supabase = await createClient()
  // getUser() autentica contra el servidor de Supabase (los datos de la cookie
  // no son confiables por sí solos — warning de seguridad de @supabase/ssr)
  const { data } = await supabase.auth.getUser()
  const user = data.user
  if (!user) return null
  const session = (await supabase.auth.getSession()).data.session
  if (!session) return null
  const payload = JSON.parse(
    Buffer.from(session.access_token.split('.')[1], 'base64url').toString()
  )
  return {
    userId: user.id,
    email: user.email ?? '',
    companyId: Number(payload.company_id),
    role: String(payload.user_role ?? ''),
    accessToken: session.access_token,
  }
}
