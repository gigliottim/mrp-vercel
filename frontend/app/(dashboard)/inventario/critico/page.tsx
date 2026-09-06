import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { Badge } from '@/components/ui/badge'

export const dynamic = 'force-dynamic'

type Item = {
  id: number
  codigo_variante: string
  detalle: string
  stock_actual: number
  punto_pedido: number
  stock_seguridad: number
  lote_minimo: number
  codigo_parte: string
  detalle_parte: string
  tipo_nombre: string
  grupo_nombre: string
  grupo_color: string
  unidad_medida: string
  estado_stock: 'critico' | 'advertencia' | 'normal'
  faltante: number
}

const ESTADO_BADGE: Record<string, string> = {
  critico: 'destructive',
  advertencia: 'default',
  normal: 'outline',
}

export default async function CriticoPage({ searchParams }: { searchParams: Promise<{ estado?: string }> }) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const estado = ['todos', 'critico', 'advertencia', 'normal'].includes(sp.estado ?? '') ? sp.estado : 'todos'

  const res = await apiFetch<{ data: { items: Item[]; stats: { total: number; critico: number; advertencia: number; normal: number } } }>(
    `/api/v1/inventario/critico?estado=${estado}`,
    session.accessToken
  ).catch(() => null)

  const items = res?.data.items ?? []
  const stats = res?.data.stats ?? { total: 0, critico: 0, advertencia: 0, normal: 0 }

  const filtros = [
    { key: 'todos', label: 'Todos', count: stats.total },
    { key: 'critico', label: 'Crítico', count: stats.critico },
    { key: 'advertencia', label: 'Advertencia', count: stats.advertencia },
    { key: 'normal', label: 'Normal', count: stats.normal },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Stock Crítico</h1>
        <p className="text-sm text-muted-foreground">Variantes con stock bajo punto de pedido</p>
      </div>

      <div className="flex flex-wrap gap-2">
        {filtros.map((f) => (
          <a
            key={f.key}
            href={`/inventario/critico${f.key !== 'todos' ? `?estado=${f.key}` : ''}`}
            className={`rounded-full border px-4 py-1.5 text-sm ${estado === f.key ? 'bg-primary text-primary-foreground' : 'bg-background'}`}
          >
            {f.label} ({f.count})
          </a>
        ))}
      </div>

      <div className="rounded-md border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50 text-left">
              <th className="p-2 font-medium">Código</th>
              <th className="p-2 font-medium">Detalle</th>
              <th className="p-2 text-right font-medium">Stock</th>
              <th className="p-2 text-right font-medium">Punto pedido</th>
              <th className="p-2 text-right font-medium">Faltante</th>
              <th className="p-2 font-medium">Grupo</th>
              <th className="p-2 font-medium">Estado</th>
            </tr>
          </thead>
          <tbody>
            {items.map((i) => (
              <tr key={i.id} className="border-b">
                <td className="p-2"><code className="text-sm">{i.codigo_variante}</code></td>
                <td className="p-2">{i.detalle}</td>
                <td className={`p-2 text-right font-mono ${i.estado_stock === 'critico' ? 'font-bold text-destructive' : ''}`}>{i.stock_actual} {i.unidad_medida}</td>
                <td className="p-2 text-right font-mono">{i.punto_pedido}</td>
                <td className="p-2 text-right font-mono">{i.faltante}</td>
                <td className="p-2">
                  {i.grupo_nombre ? (
                    <span className="inline-flex items-center gap-1.5">
                      <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: i.grupo_color ?? '#888' }} />
                      {i.grupo_nombre}
                    </span>
                  ) : '—'}
                </td>
                <td className="p-2">
                  <Badge variant={(ESTADO_BADGE[i.estado_stock] ?? 'outline') as 'outline'}>{i.estado_stock}</Badge>
                </td>
              </tr>
            ))}
            {items.length === 0 ? (
              <tr>
                <td colSpan={7} className="h-16 text-center text-muted-foreground">Sin variantes en este estado</td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  )
}
