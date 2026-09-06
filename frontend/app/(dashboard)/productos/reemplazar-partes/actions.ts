'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string; data?: { reemplazados: number; boms_afectadas: number } }

export async function reemplazarPartes(data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    const res = await apiFetch<{ data: { reemplazados: number; boms_afectadas: number } }>(
      '/api/v1/bom/reemplazar',
      session.accessToken,
      { method: 'POST', body: JSON.stringify(data) }
    )
    revalidatePath('/productos/reemplazar-partes')
    return { ok: true, data: res.data }
  } catch (e) {
    return { error: (e as Error).message }
  }
}
