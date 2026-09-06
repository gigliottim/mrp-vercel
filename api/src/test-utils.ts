// Helper de tests: login con cache de módulo.
// Con singleFork todos los test files corren en el mismo proceso, así el
// cache de módulo se comparte y evitamos el rate limit 429 de Supabase Auth.

const cache = new Map<string, string>()

export async function login(email: string): Promise<string> {
  const cached = cache.get(email)
  if (cached) return cached
  const res = await fetch(`${process.env.SUPABASE_URL}/auth/v1/token?grant_type=password`, {
    method: 'POST',
    headers: { apikey: process.env.SUPABASE_ANON_KEY!, 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password: 'Temporal123!' }),
  })
  const body = (await res.json()) as { access_token?: string }
  if (!body.access_token) {
    throw new Error(`login falló para ${email}: ${JSON.stringify(body)}`)
  }
  cache.set(email, body.access_token)
  return body.access_token
}

export const SABRINA = 'sabrinasmurro22@gmail.com'
export const MARTIN = 'martin@unik.ar'
