'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string }

const PATH = '/api/v1/rutas-produccion'

export async function crear(data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(PATH, session.accessToken, { method: 'POST', body: JSON.stringify(data) })
    revalidatePath('/produccion/rutas')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function eliminar(id: number): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`${PATH}/${id}`, session.accessToken, { method: 'DELETE' })
    revalidatePath('/produccion/rutas')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

/** No-op: módulo sin edición (CRUDPage exige onUpdate). */
export async function noop(): Promise<ActionResult> {
  return { ok: true }
}
