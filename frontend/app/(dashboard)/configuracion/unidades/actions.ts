'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string }

export async function crearUnidad(data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch('/api/v1/unidades-medida', session.accessToken, {
      method: 'POST',
      body: JSON.stringify(data),
    })
    revalidatePath('/configuracion/unidades')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function actualizarUnidad(
  id: number,
  data: Record<string, unknown>
): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`/api/v1/unidades-medida/${id}`, session.accessToken, {
      method: 'PATCH',
      body: JSON.stringify(data),
    })
    revalidatePath('/configuracion/unidades')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function eliminarUnidad(id: number): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`/api/v1/unidades-medida/${id}`, session.accessToken, {
      method: 'DELETE',
    })
    revalidatePath('/configuracion/unidades')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}
