import type { PostgrestClient } from '@supabase/postgrest-js'

export async function validarReferencias(
  supabase: PostgrestClient,
  refs: Record<string, number>
): Promise<{ ok: boolean; error?: string }> {
  for (const [tabla, id] of Object.entries(refs)) {
    const { data, error } = await supabase.from(tabla).select('id').eq('id', id).single()
    if (error || !data) {
      return { ok: false, error: `Referencia inválida: ${tabla} id=${id} (no existe en esta empresa)` }
    }
  }
  return { ok: true }
}
