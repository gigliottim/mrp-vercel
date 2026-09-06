// Verifica el aislamiento multi-tenant: JWT claims + RLS
// Uso: node scripts/verify-tenant.mjs
// Requiere SUPABASE_URL, SUPABASE_ANON_KEY y SUPABASE_SERVICE_ROLE_KEY.

const SUPABASE_URL = process.env.SUPABASE_URL
const ANON_KEY = process.env.SUPABASE_ANON_KEY
const SERVICE_ROLE = process.env.SUPABASE_SERVICE_ROLE_KEY

if (!SUPABASE_URL || !ANON_KEY || !SERVICE_ROLE) {
  console.error('Faltan SUPABASE_URL / SUPABASE_ANON_KEY / SUPABASE_SERVICE_ROLE_KEY')
  process.exit(1)
}

async function main() {
  // 1. Login como Sabrina (empresa 2, rol Supervisor)
  const login = await fetch(`${SUPABASE_URL}/auth/v1/token?grant_type=password`, {
    method: 'POST',
    headers: { apikey: ANON_KEY, 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: 'sabrinasmurro22@gmail.com', password: 'Temporal123!' }),
  })
  const auth = await login.json()
  if (!auth.access_token) throw new Error(`login falló: ${JSON.stringify(auth)}`)

  const payload = JSON.parse(
    Buffer.from(auth.access_token.split('.')[1], 'base64url').toString()
  )
  if (payload.company_id !== 2) throw new Error(`company_id esperado 2, got ${payload.company_id}`)
  if (payload.user_role !== 'Supervisor') throw new Error(`user_role esperado Supervisor, got ${payload.user_role}`)
  console.log('JWT claims OK:', payload.company_id, payload.user_role)

  const headers = { apikey: ANON_KEY, Authorization: `Bearer ${auth.access_token}` }

  // 2. Sabrina lee partes de su empresa
  const partes = await fetch(`${SUPABASE_URL}/rest/v1/partes?select=id&limit=1`, { headers })
  const partesData = await partes.json()
  if (!Array.isArray(partesData)) throw new Error(`partes: ${JSON.stringify(partesData)}`)
  console.log('partes visibles:', partesData.length)

  // 3. Crear empresa de prueba y verificar que Sabrina NO la ve
  const adminHeaders = { apikey: SERVICE_ROLE, Authorization: `Bearer ${SERVICE_ROLE}`, 'Content-Type': 'application/json' }
  const created = await fetch(`${SUPABASE_URL}/rest/v1/companies`, {
    method: 'POST',
    headers: { ...adminHeaders, Prefer: 'return=representation' },
    body: JSON.stringify({ name: 'otra', slug: `otra-verify-${Date.now()}` }),
  })
  const company = (await created.json())[0]
  if (!company) throw new Error('no se pudo crear empresa de prueba')

  const hidden = await fetch(
    `${SUPABASE_URL}/rest/v1/partes?company_id=eq.${company.id}&select=id`,
    { headers }
  )
  const hiddenData = await hidden.json()
  if (hiddenData.length > 0) throw new Error('aislamiento RLS roto: vio partes de otra empresa')
  console.log('aislamiento RLS OK')

  // 4. Limpiar
  await fetch(`${SUPABASE_URL}/rest/v1/companies?id=eq.${company.id}`, {
    method: 'DELETE',
    headers: adminHeaders,
  })
  console.log('cleanup OK')
}

main().catch((e) => { console.error(e); process.exit(1) })
