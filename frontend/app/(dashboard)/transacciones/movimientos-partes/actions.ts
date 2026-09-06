'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string }

export async function registrarMovimiento(data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch('/api/v1/movimientos-inventario', session.accessToken, {
      method: 'POST',
      body: JSON.stringify(data),
    })
    revalidatePath('/transacciones/movimientos-partes')
    revalidatePath('/productos/partes/manager')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}
