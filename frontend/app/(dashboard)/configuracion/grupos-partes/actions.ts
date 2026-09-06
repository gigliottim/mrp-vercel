'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string }

const PATH = '/api/v1/grupos-partes'

export async function crear(data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(PATH, session.accessToken, { method: 'POST', body: JSON.stringify(data) })
    revalidatePath('/configuracion/grupos-partes')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function actualizar(id: number, data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`${PATH}/${id}`, session.accessToken, { method: 'PATCH', body: JSON.stringify(data) })
    revalidatePath('/configuracion/grupos-partes')
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
    revalidatePath('/configuracion/grupos-partes')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}
