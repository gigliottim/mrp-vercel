'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string }

export async function actualizarEmpresa(data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch('/api/v1/empresa', session.accessToken, { method: 'PATCH', body: JSON.stringify(data) })
    revalidatePath('/empresa-usuarios/empresa')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function eliminarEmpresa(id: number): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`/api/v1/companies/${id}`, session.accessToken, { method: 'DELETE' })
    revalidatePath('/empresa-usuarios/empresa')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}
