import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { ExportButtons } from '@/components/reportes/export-buttons'

export const dynamic = 'force-dynamic'

type Variante = { id: number; codigo_variante: string; detalle: string }
type Req = {
  variante_id: number
  codigo: string
  parte_codigo: string
  detalle: string
  unidad: string
  um_compra: string
  tipo: string
  programado: number
  stock: number
  faltante: number
  a_comprar: number
  a_comprar_uso: number
  a_comprar_um: string
  stock_final: number
  precio_unitario: number
  a_comprar_precio: number
}

export default async function PlanificacionProduccionPage({ searchParams }: { searchParams: Promise<{ productos?: string; fecha_costo?: string }> }) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const productosRaw = sp.productos ?? ''
  const fechaCosto = sp.fecha_costo ?? new Date().toISOString().slice(0, 10)

  const query = `productos=${encodeURIComponent(productosRaw)}&fecha_costo=${fechaCosto}`

  const res = await apiFetch<{ data: { variantes: Variante[]; productos: Record<string, number>; requerimientos: Req[]; fecha_costo: string } }>(
    `/api/v1/reportes/planificacion-produccion?${query}`,
    session.accessToken
  ).catch(() => null)

  const variantes = res?.data.variantes ?? []
  const productos = res?.data.productos ?? {}
  const requerimientos = res?.data.requerimientos ?? []

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Planificación de la Producción</h1>
        <p className="text-sm text-muted-foreground">Requerimientos de materiales para productos programados</p>
      </div>

      <form method="get" className="flex flex-wrap items-end gap-4">
        <div className="w-72 space-y-2">
          <label className="text-sm font-medium">Producto</label>
          <select name="variante_id" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option value="">Seleccionar...</option>
            {variantes.map((v) => <option key={v.id} value={v.id}>{v.codigo_variante} — {v.detalle}</option>)}
          </select>
        </div>
        <div className="w-24 space-y-2">
          <label className="text-sm font-medium">Cantidad</label>
          <input name="cantidad" type="number" step="any" min={0} defaultValue={1} className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
        </div>
        <button type="submit" className="h-10 rounded-md bg-primary px-4 text-sm text-primary-foreground">Agregar</button>
        {Object.keys(productos).length > 0 ? <ExportButtons path={`/api/v1/reportes/planificacion-produccion/export?${query}`} filename="planificacion-produccion" /> : null}
      </form>

      {Object.keys(productos).length > 0 ? (
        <div className="rounded-md border p-4">
          <p className="text-sm font-medium">Productos programados:</p>
          <div className="mt-2 flex flex-wrap gap-2">
            {Object.entries(productos).map(([vid, qty]) => {
              const v = variantes.find((x) => x.id === Number(vid))
              return (
                <a
                  key={vid}
                  href={`/reportes/planificacion-produccion?productos=${Object.entries(productos).filter(([k]) => k !== vid).map(([k, q]) => `${k}:${q}`).join(',')}`}
                  className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm"
                >
                  {v?.codigo_variante ?? vid} × {qty}
                  <span className="text-muted-foreground">✕</span>
                </a>
              )
            })}
          </div>
        </div>
      ) : null}

      <div className="rounded-md border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50 text-left">
              <th className="p-2 font-medium">Código</th>
              <th className="p-2 font-medium">Detalle</th>
              <th className="p-2 text-right font-medium">Programado</th>
              <th className="p-2 text-right font-medium">Stock</th>
              <th className="p-2 text-right font-medium">Faltante</th>
              <th className="p-2 text-right font-medium">A comprar</th>
              <th className="p-2 text-right font-medium">Stock final</th>
              <th className="p-2 text-right font-medium">Costo</th>
            </tr>
          </thead>
          <tbody>
            {requerimientos.map((r) => (
              <tr key={r.variante_id} className="border-b">
                <td className="p-2"><code className="text-sm">{r.codigo}</code></td>
                <td className="p-2">{r.detalle}</td>
                <td className="p-2 text-right font-mono">{r.programado} {r.unidad}</td>
                <td className="p-2 text-right font-mono">{r.stock}</td>
                <td className={`p-2 text-right font-mono ${r.faltante > 0 ? 'font-bold text-destructive' : ''}`}>{r.faltante}</td>
                <td className="p-2 text-right font-mono">{r.a_comprar_uso > 0 ? `${r.a_comprar_uso} ${r.a_comprar_um}` : '—'}</td>
                <td className="p-2 text-right font-mono">{r.stock_final}</td>
                <td className="p-2 text-right font-mono">{r.a_comprar_precio > 0 ? r.a_comprar_precio.toFixed(2) : '—'}</td>
              </tr>
            ))}
            {requerimientos.length === 0 ? (
              <tr><td colSpan={8} className="h-16 text-center text-muted-foreground">Agregá productos para calcular requerimientos</td></tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  )
}
