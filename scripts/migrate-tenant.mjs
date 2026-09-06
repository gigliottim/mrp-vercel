// Migra los datos del tenant mrp_tunna a Supabase con company_id=2
// Uso: node scripts/migrate-tenant.mjs
// Requiere SUPABASE_URL y SUPABASE_SERVICE_ROLE_KEY en el entorno.
// Parsea las secciones COPY del dump y las inserta vía REST (service role).

const SUPABASE_URL = process.env.SUPABASE_URL
const SERVICE_ROLE = process.env.SUPABASE_SERVICE_ROLE_KEY

if (!SUPABASE_URL || !SERVICE_ROLE) {
  console.error('Faltan SUPABASE_URL / SUPABASE_SERVICE_ROLE_KEY')
  process.exit(1)
}

const COMPANY_ID = 2
const DUMP = 'database/backups/2026-09-06_09-40-12/mrp_tunna_full_2026-09-06_09-40-12.sql'

// Orden respeta FKs (padres antes que hijos)
const TABLES = [
  'unidades_medida', 'tipos_partes', 'grupos_partes', 'tipos_depositos',
  'tipos_depositos_movimientos', 'entidades', 'almacenes', 'configuracion',
  'configuracion_general', 'partes', 'variantes', 'composicion_variantes',
  'bom_cabecera', 'bom_detalle', 'centros_trabajo', 'rutas_produccion',
  'planificacion_recursos', 'ordenes_produccion',
  'movimientos_stock', 'movimientos_inventario', 'compras',
  'mrp_calculos_cabecera', 'mrp_sugerencias',
  'agent_conversations', 'agent_messages', 'agent_ai_logs',
]

import { readFileSync } from 'node:fs'

function parseCopy(sql, table) {
  const re = new RegExp(`COPY public\\.${table} \\(([^)]+)\\) FROM stdin;\\n([\\s\\S]*?)\\\\.\\n`)
  const m = sql.match(re)
  if (!m) return []
  const cols = m[1].split(',').map((c) => c.trim())
  const rows = []
  for (const line of m[2].split('\n')) {
    if (!line.trim()) continue
    const vals = []
    let cur = '', inStr = false
    for (let i = 0; i < line.length; i++) {
      const ch = line[i]
      if (inStr) {
        if (ch === '\\' && line[i + 1] === '\\') { cur += '\\'; i++ }
        else if (ch === '\\' && line[i + 1] === 't') { cur += '\t'; i++ }
        else if (ch === '\\' && line[i + 1] === 'n') { cur += '\n'; i++ }
        else if (ch === '"') inStr = false
        else cur += ch
      } else {
        if (ch === '"') inStr = true
        else if (ch === '\t') { vals.push(cur); cur = '' }
        else cur += ch
      }
    }
    vals.push(cur)
    rows.push(Object.fromEntries(cols.map((c, i) => [c, vals[i] ?? null])))
  }
  return rows
}

function convert(v) {
  if (v === null || v === '\\N') return null
  if (v === 't') return true
  if (v === 'f') return false
  if (/^-?\d+$/.test(v)) return Number(v)
  if (/^-?\d+\.\d+$/.test(v)) return Number(v)
  return v
}

async function insertRows(table, rows) {
  const body = rows.map((r) => {
    const clean = {}
    for (const [k, v] of Object.entries(r)) {
      if (v !== null) clean[k] = convert(v)
    }
    clean.company_id = COMPANY_ID
    return clean
  })
  const res = await fetch(`${SUPABASE_URL}/rest/v1/${table}`, {
    method: 'POST',
    headers: {
      apikey: SERVICE_ROLE,
      Authorization: `Bearer ${SERVICE_ROLE}`,
      'Content-Type': 'application/json',
      Prefer: 'return=minimal',
    },
    body: JSON.stringify(body),
  })
  if (!res.ok) {
    const text = await res.text()
    throw new Error(`${table}: HTTP ${res.status} ${text.slice(0, 300)}`)
  }
}

async function main() {
  const sql = readFileSync(DUMP, 'utf-8')
  for (const table of TABLES) {
    const rows = parseCopy(sql, table)
    if (rows.length === 0) {
      console.log(`skip ${table} (0 rows)`)
      continue
    }
    // Idempotente: si la tabla ya tiene filas de esta empresa, saltar
    const check = await fetch(
      `${SUPABASE_URL}/rest/v1/${table}?company_id=eq.${COMPANY_ID}&select=id&limit=1`,
      { headers: { apikey: SERVICE_ROLE, Authorization: `Bearer ${SERVICE_ROLE}` } }
    )
    const existing = await check.json()
    if (Array.isArray(existing) && existing.length > 0) {
      console.log(`skip ${table} (ya migrada)`)
      continue
    }
    try {
      await insertRows(table, rows)
      console.log(`ok ${table}: ${rows.length} rows`)
    } catch (e) {
      console.error(`FAIL ${table}:`, e.message)
      process.exit(1)
    }
  }
}

main().catch((e) => { console.error(e); process.exit(1) })
