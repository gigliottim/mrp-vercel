'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string }

async function withSession(fn: (token: string) => Promise<void>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await fn(session.accessToken)
    revalidatePath('/productos/maestro')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function agregarComponente(data: {
  variante_padre_id: number
  variante_componente_id: number
  cantidad: number
  unidad_medida_id: number
}): Promise<ActionResult> {
  return withSession((token) =>
    apiFetch('/api/v1/bom/detalle', token, {
      method: 'POST',
      body: JSON.stringify(data),
    })
  )
}

export async function editarComponente(
  detalleId: number,
  data: { cantidad: number; unidad_medida_id: number }
): Promise<ActionResult> {
  return withSession((token) =>
    apiFetch(`/api/v1/bom/detalle/${detalleId}`, token, {
      method: 'PATCH',
      body: JSON.stringify(data),
    })
  )
}

export async function eliminarComponente(detalleId: number): Promise<ActionResult> {
  return withSession((token) =>
    apiFetch(`/api/v1/bom/detalle/${detalleId}`, token, { method: 'DELETE' })
  )
}

export async function reemplazarComponente(data: {
  variante_origen_id: number
  variante_nueva_id: number
  variante_padre_id: number
}): Promise<ActionResult> {
  return withSession(async (token) => {
    // Resolver el BOM ACTIVO del padre (bom_tree no expone bom_id; el filtro
    // activa=true evita reemplazar en una versión inactiva)
    const bom = await apiFetch<{ data: { id: number }[] }>(
      `/api/v1/bom?variante_padre_id=${data.variante_padre_id}&activa=true&perPage=1`,
      token
    )
    const bomId = bom.data?.[0]?.id
    if (!bomId) throw new Error('La variante padre no tiene BOM activa')
    await apiFetch('/api/v1/bom/reemplazar', token, {
      method: 'POST',
      body: JSON.stringify({
        variante_origen_id: data.variante_origen_id,
        variante_nueva_id: data.variante_nueva_id,
        bom_ids: [bomId],
      }),
    })
  })
}