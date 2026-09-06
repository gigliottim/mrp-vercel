import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export const dynamic = 'force-dynamic'

type Variante = { id: number; codigo_variante: string; detalle: string }
type Detalle = { variante_componente_id: number; cantidad_necesaria: number; unidades_medida?: { simbolo: string }; variantes_componente?: { codigo_variante: string; detalle: string } }
type TreeRow = { variante_id: number; codigo_variante: string; variante_detalle: string; cantidad: number; unidad: string | null; nivel: number }
type WhereUsed = { bom_id: number; padre_codigo: string; padre_detalle: string; cantidad_necesaria: number; unidad_codigo: string }

export default async function DestinoPartesPage({ searchParams }: { searchParams: Promise<{ id_variante?: string }> }) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const varianteId = Number(sp.id_variante ?? 0)

  const res = varianteId > 0
    ? await apiFetch<{ data: { variantes: Variante[]; variante_seleccionada: Variante | null; rama1: Detalle[]; plana: Array<{ componente_codigo: string; componente_detalle: string; cantidad_necesaria: number; unidad_simbolo: string }>; arbol: TreeRow[]; donde_se_utiliza: WhereUsed[] } }>(
        `/api/v1/reportes/destino-partes?id_variante=${varianteId}`, session.accessToken
      ).catch(() => null)
    : null

  const variantes = res?.data.variantes ?? []
  const d = res?.data

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Destino de Partes</h1>
        <p className="text-sm text-muted-foreground">Composición y uso de una variante</p>
      </div>

      <form method="get" className="flex items-end gap-4">
        <div className="w-96 space-y-2">
          <label className="text-sm font-medium">Variante</label>
          <select name="id_variante" defaultValue={varianteId || ''} className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option value="">Seleccionar...</option>
            {variantes.map((v) => (
              <option key={v.id} value={v.id}>{v.codigo_variante} — {v.detalle}</option>
            ))}
          </select>
        </div>
        <button type="submit" className="h-10 rounded-md bg-primary px-4 text-sm text-primary-foreground">Ver</button>
      </form>

      {d ? (
        <>
          <div className="grid gap-4 lg:grid-cols-2">
            <div className="rounded-md border p-4">
              <h2 className="mb-2 font-semibold">Rama 1 (nivel directo)</h2>
              <table className="w-full text-sm">
                <thead><tr className="border-b text-left text-muted-foreground"><th className="p-2">Componente</th><th className="p-2 text-right">Cantidad</th></tr></thead>
                <tbody>
                  {d.rama1.map((r) => (
                    <tr key={r.variante_componente_id} className="border-b">
                      <td className="p-2">{r.variantes_componente?.codigo_variante ?? `#${r.variante_componente_id}`} — {r.variantes_componente?.detalle ?? ''}</td>
                      <td className="p-2 text-right font-mono">{r.cantidad_necesaria} {r.unidades_medida?.simbolo ?? ''}</td>
                    </tr>
                  ))}
                  {d.rama1.length === 0 ? <tr><td colSpan={2} className="p-2 text-muted-foreground">Sin componentes</td></tr> : null}
                </tbody>
              </table>
            </div>
            <div className="rounded-md border p-4">
              <h2 className="mb-2 font-semibold">Composición plana (consolidada)</h2>
              <table className="w-full text-sm">
                <thead><tr className="border-b text-left text-muted-foreground"><th className="p-2">Componente</th><th className="p-2 text-right">Cantidad</th></tr></thead>
                <tbody>
                  {d.plana.map((r, i) => (
                    <tr key={i} className="border-b">
                      <td className="p-2">{r.componente_codigo} — {r.componente_detalle}</td>
                      <td className="p-2 text-right font-mono">{r.cantidad_necesaria} {r.unidad_simbolo}</td>
                    </tr>
                  ))}
                  {d.plana.length === 0 ? <tr><td colSpan={2} className="p-2 text-muted-foreground">Sin componentes</td></tr> : null}
                </tbody>
              </table>
            </div>
          </div>

          <div className="rounded-md border p-4">
            <h2 className="mb-2 font-semibold">Árbol completo</h2>
            <table className="w-full text-sm">
              <thead><tr className="border-b text-left text-muted-foreground"><th className="p-2">Nivel</th><th className="p-2">Código</th><th className="p-2">Detalle</th><th className="p-2 text-right">Cantidad</th></tr></thead>
              <tbody>
                {d.arbol.map((r) => (
                  <tr key={`${r.variante_id}-${r.nivel}`} className="border-b">
                    <td className="p-2 text-muted-foreground">{r.nivel}</td>
                    <td className="p-2" style={{ paddingLeft: `${12 + r.nivel * 20}px` }}><code>{r.codigo_variante}</code></td>
                    <td className="p-2">{r.variante_detalle}</td>
                    <td className="p-2 text-right font-mono">{r.cantidad} {r.unidad ?? ''}</td>
                  </tr>
                ))}
                {d.arbol.length === 0 ? <tr><td colSpan={4} className="p-2 text-muted-foreground">Sin árbol</td></tr> : null}
              </tbody>
            </table>
          </div>

          <div className="rounded-md border p-4">
            <h2 className="mb-2 font-semibold">Dónde se utiliza</h2>
            <table className="w-full text-sm">
              <thead><tr className="border-b text-left text-muted-foreground"><th className="p-2">BOM</th><th className="p-2">Padre</th><th className="p-2 text-right">Cantidad</th></tr></thead>
              <tbody>
                {d.donde_se_utiliza.map((w) => (
                  <tr key={w.bom_id} className="border-b">
                    <td className="p-2">#{w.bom_id}</td>
                    <td className="p-2">{w.padre_codigo} — {w.padre_detalle}</td>
                    <td className="p-2 text-right font-mono">{w.cantidad_necesaria} {w.unidad_codigo}</td>
                  </tr>
                ))}
                {d.donde_se_utiliza.length === 0 ? <tr><td colSpan={3} className="p-2 text-muted-foreground">No se utiliza en ningún maestro</td></tr> : null}
              </tbody>
            </table>
          </div>
        </>
      ) : null}
    </div>
  )
}
