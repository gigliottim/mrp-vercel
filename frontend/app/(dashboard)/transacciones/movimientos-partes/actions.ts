'use server'

import { revalidatePath } from 'next/cache'
import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export async function registrarMovimientoPartes(input: {
  variante_id: number
  cantidad: number
  tipo_deposito_origen_id: number
  tipo_deposito_destino_id: number
  tipo_movimiento: string
  entidad_id?: number | null
  importe_total?: number | null
  fecha_hora?: string | null
  nro_comprobante?: string | null
  observaciones?: string | null
}): Promise<{ ok: boolean; error?: string }> {
  const session = await getSession()
  if (!session) return { ok: false, error: 'Sesión expirada' }
  try {
    await apiFetch('/api/v1/movimientos-partes', session.accessToken, {
      method: 'POST',
      body: JSON.stringify(input),
    })
    revalidatePath('/transacciones/movimientos-partes')
    return { ok: true }
  } catch (e) {
    return { ok: false, error: e instanceof Error ? e.message : 'Error' }
  }
}
