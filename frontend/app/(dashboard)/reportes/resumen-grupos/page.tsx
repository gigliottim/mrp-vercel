import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export const dynamic = 'force-dynamic'

type Grupo = { id: number; nombre: string; color: string; cantidad_partes: number }
type Parte = {
  parte_id: number
  parte_codigo: string
  parte_detalle: string
  tipo_nombre: string
  variantes: Array<{ variante_id: number; codigo_variante: string; variante_detalle: string; variante_estado: string; stock_actual: number; punto_pedido: number; unidad_medida: string; estado_stock: string }>
}

export default async function ResumenGruposPage({ searchParams }: { searchParams: Promise<{ id_grupo?: string }> }) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const grupoId = Number(sp.id_grupo ?? 0)

  const res = await apiFetch<{ data: { grupos: Grupo[]; grupo_seleccionado: Grupo | null; partes_del_grupo: Parte[]; partes_sin_grupo: Parte[]; count_sin_grupo: number } }>(
    `/api/v1/reportes/resumen-grupos${grupoId > 0 ? `?id_grupo=${grupoId}` : ''}`,
    session.accessToken
  ).catch(() => null)

  const grupos = res?.data.grupos ?? []
  const partes = res?.data.partes_del_grupo ?? []
  const sinGrupo = res?.data.partes_sin_grupo ?? []

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Resumen por Grupos</h1>
        <p className="text-sm text-muted-foreground">Partes y variantes agrupadas con stock</p>
      </div>

      <form method="get" className="flex items-end gap-4">
        <div className="w-96 space-y-2">
          <label className="text-sm font-medium">Grupo</label>
          <select name="id_grupo" defaultValue={grupoId || ''} className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option value="">Seleccionar...</option>
            {grupos.map((g) => <option key={g.id} value={g.id}>{g.nombre} ({g.cantidad_partes})</option>)}
          </select>
        </div>
        <button type="submit" className="h-10 rounded-md bg-primary px-4 text-sm text-primary-foreground">Ver</button>
      </form>

      {grupoId > 0 ? (
        <div className="space-y-4">
          {partes.map((p) => (
            <div key={p.parte_id} className="rounded-md border p-4">
              <p className="font-medium"><code className="text-sm">{p.parte_codigo}</code> — {p.parte_detalle} <span className="text-xs text-muted-foreground">({p.tipo_nombre})</span></p>
              <table className="mt-2 w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <th className="p-2">Variante</th>
                    <th className="p-2 text-right">Stock</th>
                    <th className="p-2 text-right">Punto pedido</th>
                    <th className="p-2">Estado</th>
                  </tr>
                </thead>
                <tbody>
                  {p.variantes.map((v) => (
                    <tr key={v.variante_id} className="border-b">
                      <td className="p-2"><code>{v.codigo_variante}</code> — {v.variante_detalle}</td>
                      <td className={`p-2 text-right font-mono ${v.estado_stock === 'critico' ? 'font-bold text-destructive' : ''}`}>{v.stock_actual} {v.unidad_medida}</td>
                      <td className="p-2 text-right font-mono">{v.punto_pedido}</td>
                      <td className="p-2">{v.estado_stock}</td>
                    </tr>
                  ))}
                  {p.variantes.length === 0 ? <tr><td colSpan={4} className="p-2 text-muted-foreground">Sin variantes</td></tr> : null}
                </tbody>
              </table>
            </div>
          ))}
          {partes.length === 0 ? <p className="py-8 text-center text-muted-foreground">El grupo no tiene partes</p> : null}
        </div>
      ) : (
        <div className="rounded-md border p-4">
          <h2 className="mb-2 font-semibold">Partes sin grupo ({res?.data.count_sin_grupo ?? 0})</h2>
          {sinGrupo.map((p) => (
            <p key={p.parte_id} className="border-b py-1.5 text-sm">
              <code>{p.parte_codigo}</code> — {p.parte_detalle} <span className="text-xs text-muted-foreground">({p.tipo_nombre})</span>
            </p>
          ))}
          {sinGrupo.length === 0 ? <p className="text-sm text-muted-foreground">Todas las partes tienen grupo</p> : null}
        </div>
      )}
    </div>
  )
}
