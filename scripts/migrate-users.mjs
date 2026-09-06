// Migra los 3 usuarios del dump mrp_auth a Supabase Auth
// Uso: node scripts/migrate-users.mjs
// Requiere SUPABASE_URL y SUPABASE_SERVICE_ROLE_KEY en el entorno.
// Los hashes PHP ($2y$12$...) no son migrables: se crean con password temporal
// y email confirmado. El usuario debe cambiarla en el primer login.

const SUPABASE_URL = process.env.SUPABASE_URL
const SERVICE_ROLE = process.env.SUPABASE_SERVICE_ROLE_KEY

if (!SUPABASE_URL || !SERVICE_ROLE) {
  console.error('Faltan SUPABASE_URL / SUPABASE_SERVICE_ROLE_KEY')
  process.exit(1)
}

const USERS = [
  { legacy_id: 2, email: 'sabrinasmurro22@gmail.com', name: 'Sabrina Smurro' },
  { legacy_id: 4, email: 'martin@unik.ar', name: 'Martin Gigliotti' },
  { legacy_id: 6, email: 'usuario@mimrp.com.ar', name: 'pepe' },
]

const TEMP_PASSWORD = 'Temporal123!'

async function createUser(u) {
  const res = await fetch(`${SUPABASE_URL}/auth/v1/admin/users`, {
    method: 'POST',
    headers: {
      apikey: SERVICE_ROLE,
      Authorization: `Bearer ${SERVICE_ROLE}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      email: u.email,
      password: TEMP_PASSWORD,
      email_confirm: true,
      user_metadata: { legacy_id: u.legacy_id, name: u.name },
    }),
  })
  const data = await res.json()
  if (!res.ok) {
    // Si ya existe (re-run), obtener su id
    if (res.status === 422 || data.msg?.includes('already')) {
      const list = await fetch(
        `${SUPABASE_URL}/auth/v1/admin/users?email=${encodeURIComponent(u.email)}`,
        { headers: { apikey: SERVICE_ROLE, Authorization: `Bearer ${SERVICE_ROLE}` } }
      ).then((r) => r.json())
      const existing = list.users?.find((x) => x.email === u.email)
      if (existing) {
        console.log(`exists ${u.email} -> ${existing.id}`)
        return existing.id
      }
    }
    console.error(`FAIL ${u.email}:`, JSON.stringify(data))
    process.exit(1)
  }
  console.log(`created ${u.email} -> ${data.id}`)
  return data.id
}

for (const u of USERS) {
  await createUser(u)
}
