'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export type ActionResult = { ok?: boolean; error?: string }

export async function agregarComponente(data: Record<string, unknown>): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch('/api/v1/bom', session.accessToken, {
      method: 'POST',
      body: JSON.stringify({
        variante_padre_id: data.variante_padre_id,
        version: '1.0',
        fecha_efectiva: new Date().toISOString().slice(0, 10),
        detalles: [
          {
            variante_componente_id: data.variante_componente_id,
            cantidad_necesaria: data.cantidad_necesaria,
            unidad_medida_id: data.unidad_medida_id,
            secuencia: 1,
          },
        ],
      }),
    })
    revalidatePath('/productos/maestro')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}

export async function eliminarBom(id: number): Promise<ActionResult> {
  const session = await getSession()
  if (!session) return { error: 'Sin sesión' }
  try {
    await apiFetch(`/api/v1/bom/${id}`, session.accessToken, { method: 'DELETE' })
    revalidatePath('/productos/maestro')
    return { ok: true }
  } catch (e) {
    return { error: (e as Error).message }
  }
}
