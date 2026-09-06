'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string; data?: { copiados: number; eliminados: number; saltados: string[] } }

export async function copiarComponentes(data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    const res = await apiFetch<{ data: { copiados: number; eliminados: number; saltados: string[] } }>(
      '/api/v1/bom/copiar',
      session.accessToken,
      { method: 'POST', body: JSON.stringify(data) }
    )
    revalidatePath('/productos/copiar-componentes')
    return { ok: true, data: res.data }
  } catch (e) {
    return { error: (e as Error).message }
  }
}
