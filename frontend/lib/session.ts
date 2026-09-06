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
  const { data } = await supabase.auth.getSession()
  const session = data.session
  if (!session) return null
  const payload = JSON.parse(
    Buffer.from(session.access_token.split('.')[1], 'base64url').toString()
  )
  return {
    userId: session.user.id,
    email: session.user.email ?? '',
    companyId: Number(payload.company_id),
    role: String(payload.user_role ?? ''),
    accessToken: session.access_token,
  }
}
