'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string }

export async function guardarPermisos(data: { menu_item_ids: number[]; perms: Array<{ subject_type: string; subject_id: number; effect: string }> }): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch('/api/v1/empresa/permisos/bulk', session.accessToken, { method: 'POST', body: JSON.stringify(data) })
    revalidatePath('/empresa-usuarios/permisos')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}
