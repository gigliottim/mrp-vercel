'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string; rol?: { id: number; name: string; guard_name: string } }

export async function crearRol(data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    const res = await apiFetch<{ data: { id: number; name: string; guard_name: string } }>(
      '/api/v1/empresa/roles',
      session.accessToken,
      { method: 'POST', body: JSON.stringify(data) }
    )
    revalidatePath('/empresa-usuarios/roles')
    return { ok: true, rol: res.data }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function eliminarRol(id: number): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`/api/v1/empresa/roles/${id}`, session.accessToken, { method: 'DELETE' })
    revalidatePath('/empresa-usuarios/roles')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function actualizarRol(id: number, data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`/api/v1/empresa/roles/${id}`, session.accessToken, { method: 'PATCH', body: JSON.stringify(data) })
    revalidatePath('/empresa-usuarios/roles')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}
