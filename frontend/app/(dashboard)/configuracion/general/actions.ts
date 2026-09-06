'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string }

export async function actualizarGeneral(data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch('/api/v1/configuracion', session.accessToken, {
      method: 'PATCH',
      body: JSON.stringify(data),
    })
    revalidatePath('/configuracion/general')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function upsertClave(
  clave: string,
  data: Record<string, unknown>
): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`/api/v1/configuracion/kv/${encodeURIComponent(clave)}`, session.accessToken, {
      method: 'PUT',
      body: JSON.stringify(data),
    })
    revalidatePath('/configuracion/general')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}
