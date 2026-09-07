'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string }

const PATH = '/api/v1/ordenes-produccion'

export async function crear(data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(PATH, session.accessToken, { method: 'POST', body: JSON.stringify(data) })
    revalidatePath('/produccion/ordenes')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function cambiarEstado(id: number, estado: string): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`${PATH}/${id}/estado`, session.accessToken, {
      method: 'POST',
      body: JSON.stringify({ estado }),
    })
    revalidatePath(`/produccion/ordenes/${id}`)
    revalidatePath('/produccion/ordenes')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}