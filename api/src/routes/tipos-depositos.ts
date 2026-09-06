import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'
import { parsePagination } from '../lib/pagination.js'

const schema = z.object({
  codigo: z.string().min(1).max(50),
  nombre: z.string().min(1).max(100),
  descripcion: z.string().optional(),
  orden: z.number().int().optional(),
  es_sistema: z.boolean().optional(),
  activo: z.boolean().optional(),
})

export const tiposDepositos = new Hono<AuthEnv>()

tiposDepositos.use('*', requireAuth)

tiposDepositos.get('/', async (c) => {
  const { page, perPage, offset } = parsePagination(c.req.query())
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error, count } = await supabase
    .from('tipos_depositos')
    .select('*', { count: 'exact' })
    .order('id')
    .range(offset, offset + perPage - 1)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data, pagination: { page, perPage, total: count } })
})

// Mapa origen→destinos permitidos (port de getDestinosPermitidos del PHP)
tiposDepositos.get('/destinos-permitidos', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  // OJO: hay 2 FKs hacia tipos_depositos (fk_tipo_origen / fk_tipo_destino, nombres reales
  // del dump) — PostgREST exige el hint del constraint para desambiguar el embed
  const { data, error } = await supabase
    .from('tipos_depositos_movimientos')
    .select('tipo_deposito_origen_id, tipo_deposito_destino_id, activo, tipos_depositos!fk_tipo_origen(id, codigo, nombre)')
    .eq('activo', true)
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  const destinosByOrigen = new Map<number, { origen_codigo: string; origen_nombre: string; destinos: Array<{ id: number; codigo: string; nombre: string }> }>()
  for (const row of data as unknown as Array<{
    tipo_deposito_origen_id: number; tipo_deposito_destino_id: number;
    tipos_depositos: { id: number; codigo: string; nombre: string } | null
  }>) {
    const origen = row.tipos_depositos
    if (!origen) continue
    const entry = destinosByOrigen.get(row.tipo_deposito_origen_id) ?? {
      origen_codigo: origen.codigo, origen_nombre: origen.nombre, destinos: [],
    }
    destinosByOrigen.set(row.tipo_deposito_origen_id, entry)
  }
  // Los destinos requieren segunda query (FK destino)
  const idsDestino = [...new Set((data as unknown as Array<{ tipo_deposito_destino_id: number }>).map((r) => r.tipo_deposito_destino_id))]
  const { data: destData } = await supabase.from('tipos_depositos').select('id, codigo, nombre').in('id', idsDestino)
  const destById = new Map((destData ?? []).map((d: { id: number; codigo: string; nombre: string }) => [d.id, d]))
  for (const row of data as unknown as Array<{ tipo_deposito_origen_id: number; tipo_deposito_destino_id: number }>) {
    const destino = destById.get(row.tipo_deposito_destino_id)
    if (!destino) continue
    const entry = destinosByOrigen.get(row.tipo_deposito_origen_id)
    if (entry && !entry.destinos.some((d) => d.id === destino.id)) {
      entry.destinos.push({ id: destino.id, codigo: destino.codigo, nombre: destino.nombre })
    }
  }
  return c.json({
    data: [...destinosByOrigen.entries()].map(([origen_id, e]) => ({
      origen_id, origen_codigo: e.origen_codigo, origen_nombre: e.origen_nombre, destinos: e.destinos,
    })),
  })
})

tiposDepositos.get('/:id', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('tipos_depositos')
    .select('*')
    .eq('id', Number(c.req.param('id')))
    .single()
  if (error) return c.json({ error: { code: 'NOT_FOUND', message: 'No encontrado' } }, 404)
  return c.json({ data })
})

tiposDepositos.post('/', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('tipos_depositos')
    .insert({ ...parsed.data, company_id: c.get('companyId') })
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data }, 201)
})

tiposDepositos.patch('/:id', async (c) => {
  const body = await c.req.json().catch(() => null)
  const parsed = schema.partial().safeParse(body)
  if (!parsed.success) {
    return c.json({ error: { code: 'VALIDATION', message: parsed.error.message } }, 400)
  }
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('tipos_depositos')
    .update(parsed.data)
    .eq('id', Number(c.req.param('id')))
    .select()
    .single()
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
  return c.json({ data })
})

tiposDepositos.delete(
  '/:id',
  requireRole('Super Administrador', 'Administrador'),
  async (c) => {
    const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
    const { error } = await supabase
      .from('tipos_depositos')
      .delete()
      .eq('id', Number(c.req.param('id')))
    if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)
    return c.body(null, 204)
  }
)
