import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { Badge } from '@/components/ui/badge'

export const dynamic = 'force-dynamic'

type Sugerencia = {
  bom_id: number
  variante_id: number
  parte_codigo: string
  parte_detalle: string
  variante_codigo: string
  variante_detalle: string
  status: 'fabricable' | 'parcial' | 'sin_stock'
  cobertura_pct: number
  max_unidades: number
  componentes: Array<{
    comp_variante_id: number
    comp_parte_codigo: string
    comp_codigo: string
    comp_detalle: string
    cantidad_necesaria: number
    stock_disponible: number
    unidad_simbolo: string
    ratio: number
    cobertura_pct: number
  }>
}

const STATUS_BADGE: Record<string, string> = {
  fabricable: 'outline',
  parcial: 'default',
  sin_stock: 'destructive',
}

export default async function SugerenciasPage({ searchParams }: { searchParams: Promise<{ filtro?: string }> }) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const filtro = ['fabricable', 'parcial', 'sin_stock'].includes(sp.filtro ?? '') ? sp.filtro : undefined

  const res = await apiFetch<{ data: { resumen: { fabricables: number; parciales: number; sin_stock: number; total: number }; variantes: Sugerencia[] } }>(
    `/api/v1/sugerencias${filtro ? `?filtro=${filtro}` : ''}`,
    session.accessToken
  ).catch(() => null)

  const resumen = res?.data.resumen ?? { fabricables: 0, parciales: 0, sin_stock: 0, total: 0 }
  const variantes = res?.data.variantes ?? []

  const filtros = [
    { key: '', label: 'Todos', count: resumen.total },
    { key: 'fabricable', label: 'Fabricables', count: resumen.fabricables },
    { key: 'parcial', label: 'Parciales', count: resumen.parciales },
    { key: 'sin_stock', label: 'Sin stock', count: resumen.sin_stock },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Sugerencias de Fabricación</h1>
        <p className="text-sm text-muted-foreground">Análisis de fabricabilidad por BOM activa</p>
      </div>

      <div className="flex flex-wrap gap-2">
        {filtros.map((f) => (
          <a
            key={f.key}
            href={`/planeamiento/sugerencias${f.key ? `?filtro=${f.key}` : ''}`}
            className={`rounded-full border px-4 py-1.5 text-sm ${filtro === f.key || (!filtro && f.key === '') ? 'bg-primary text-primary-foreground' : 'bg-background'}`}
          >
            {f.label} ({f.count})
          </a>
        ))}
      </div>

      <div className="grid gap-4 sm:grid-cols-4">
        {filtros.map((f) => (
          <div key={f.key} className="rounded-md border p-4">
            <p className="text-sm text-muted-foreground">{f.label}</p>
            <p className="text-2xl font-bold">{f.count}</p>
          </div>
        ))}
      </div>

      <div className="space-y-3">
        {variantes.map((v) => (
          <details key={v.bom_id} className="rounded-md border">
            <summary className="flex cursor-pointer items-center justify-between p-4">
              <div>
                <p className="font-medium">
                  <code className="text-sm">{v.variante_codigo}</code> — {v.variante_detalle}
                </p>
                <p className="text-xs text-muted-foreground">{v.parte_codigo} · {v.parte_detalle}</p>
              </div>
              <div className="flex items-center gap-3">
                <span className="text-sm">Cobertura {v.cobertura_pct}% · máx {v.max_unidades} uds</span>
                <Badge variant={(STATUS_BADGE[v.status] ?? 'outline') as 'outline'}>{v.status}</Badge>
              </div>
            </summary>
            <div className="border-t p-4">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <th className="p-2">Componente</th>
                    <th className="p-2 text-right">Necesario</th>
                    <th className="p-2 text-right">Stock</th>
                    <th className="p-2 text-right">Cobertura</th>
                  </tr>
                </thead>
                <tbody>
                  {v.componentes.map((c) => (
                    <tr key={c.comp_variante_id} className="border-b">
                      <td className="p-2"><code>{c.comp_codigo}</code> — {c.comp_detalle}</td>
                      <td className="p-2 text-right font-mono">{c.cantidad_necesaria} {c.unidad_simbolo}</td>
                      <td className="p-2 text-right font-mono">{c.stock_disponible}</td>
                      <td className="p-2 text-right font-mono">{c.cobertura_pct}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </details>
        ))}
        {variantes.length === 0 ? (
          <p className="py-8 text-center text-muted-foreground">Sin sugerencias para el filtro seleccionado</p>
        ) : null}
      </div>
    </div>
  )
}
