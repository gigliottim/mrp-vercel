'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string }

export async function agregarOperacion(bomId: number, data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`/api/v1/rutas-produccion/bom/${bomId}/operaciones`, session.accessToken, {
      method: 'POST',
      body: JSON.stringify(data),
    })
    revalidatePath(`/produccion/rutas/${bomId}/editor`)
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function actualizarOperacion(opId: number, data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`/api/v1/rutas-produccion/operaciones/${opId}`, session.accessToken, {
      method: 'PATCH',
      body: JSON.stringify(data),
    })
    revalidatePath(`/produccion/rutas/${data.bom_id}/editor`)
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function eliminarOperacion(opId: number, bomId: number): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`/api/v1/rutas-produccion/operaciones/${opId}`, session.accessToken, { method: 'DELETE' })
    revalidatePath(`/produccion/rutas/${bomId}/editor`)
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function reordenarOperaciones(
  bomId: number,
  ids: number[]
): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`/api/v1/rutas-produccion/bom/${bomId}/operaciones/reordenar`, session.accessToken, {
      method: 'POST',
      body: JSON.stringify({ ids }),
    })
    revalidatePath(`/produccion/rutas/${bomId}/editor`)
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}
