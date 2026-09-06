import { createClient } from '@/lib/supabase/server'
import { createClient as createBrowserClient } from '@/lib/supabase/client'
import { useEffect, useState } from 'react'

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

export function useSession() {
  const [session, setSession] = useState<SessionInfo | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const supabase = createBrowserClient()
    supabase.auth.getSession().then(({ data }) => {
      const s = data.session
      if (!s) {
        setLoading(false)
        return
      }
      const payload = JSON.parse(
        Buffer.from(s.access_token.split('.')[1], 'base64url').toString()
      )
      setSession({
        userId: s.user.id,
        email: s.user.email ?? '',
        companyId: Number(payload.company_id),
        role: String(payload.user_role ?? ''),
        accessToken: s.access_token,
      })
      setLoading(false)
    })
  }, [])

  return { session, loading }
}
