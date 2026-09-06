'use client'

import { createClient } from '@/lib/supabase/client'
import { useEffect, useState } from 'react'
import type { SessionInfo } from './session'

export function useSession() {
  const [session, setSession] = useState<SessionInfo | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const supabase = createClient()
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
