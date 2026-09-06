import { PostgrestClient } from '@supabase/postgrest-js'

// Cliente REST puro (sin realtime/websocket — compatible Node 20).
// El token del request se pasa como header Authorization; RLS filtra por
// company_id automáticamente. El service_role (solo server) ignora RLS.

export function createUserClient(token: string): PostgrestClient {
  return new PostgrestClient(process.env.SUPABASE_URL!, {
    headers: {
      apikey: process.env.SUPABASE_ANON_KEY!,
      Authorization: `Bearer ${token}`,
    },
  })
}

export function createAdminClient(): PostgrestClient {
  return new PostgrestClient(process.env.SUPABASE_URL!, {
    headers: {
      apikey: process.env.SUPABASE_SERVICE_ROLE_KEY!,
      Authorization: `Bearer ${process.env.SUPABASE_SERVICE_ROLE_KEY!}`,
    },
  })
}
