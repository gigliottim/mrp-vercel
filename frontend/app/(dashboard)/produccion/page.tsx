import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export const dynamic = 'force-dynamic'

type Orden = { id: number; numero_orden: string; estado: string; prioridad: string; cantidad_planificada: number; variantes?: { codigo_variante: string; detalle: string } }

export default async function OperacionesPage() {
  const session = await getSession()
  if (!session) return null
  const res = await apiFetch<{ data: { metricas: { centros_activos: number; rutas_configuradas: number; ordenes_activas: number; capacidad_utilizada: number }; ordenes_recientes: Orden[] } }>(
    '/api/v1/operaciones',
    session.accessToken
  ).catch(() => null)

  const metricas = res?.data.metricas ?? { centros_activos: 0, rutas_configuradas: 0, ordenes_activas: 0, capacidad_utilizada: 0 }
  const ordenes = res?.data.ordenes_recientes ?? []

  const cards = [
    { label: 'Centros activos', value: metricas.centros_activos },
    { label: 'Rutas configuradas', value: metricas.rutas_configuradas },
    { label: 'Órdenes activas', value: metricas.ordenes_activas },
    { label: 'Capacidad utilizada', value: `${metricas.capacidad_utilizada}%` },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Dashboard de Operaciones</h1>
        <p className="text-sm text-muted-foreground">Métricas del módulo de producción</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {cards.map((c) => (
          <div key={c.label} className="rounded-md border p-4">
            <p className="text-sm text-muted-foreground">{c.label}</p>
            <p className="text-3xl font-bold">{c.value}</p>
          </div>
        ))}
      </div>

      <div className="rounded-md border">
        <h2 className="border-b p-4 font-semibold">Órdenes recientes</h2>
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50 text-left">
              <th className="p-2 font-medium">Nº orden</th>
              <th className="p-2 font-medium">Variante</th>
              <th className="p-2 text-right font-medium">Cantidad</th>
              <th className="p-2 font-medium">Prioridad</th>
              <th className="p-2 font-medium">Estado</th>
            </tr>
          </thead>
          <tbody>
            {ordenes.map((o) => (
              <tr key={o.id} className="border-b">
                <td className="p-2"><code className="text-sm">{o.numero_orden}</code></td>
                <td className="p-2">{o.variantes?.codigo_variante ?? ''} — {o.variantes?.detalle ?? ''}</td>
                <td className="p-2 text-right font-mono">{o.cantidad_planificada}</td>
                <td className="p-2">{o.prioridad}</td>
                <td className="p-2">{o.estado}</td>
              </tr>
            ))}
            {ordenes.length === 0 ? (
              <tr><td colSpan={5} className="h-16 text-center text-muted-foreground">Sin órdenes activas</td></tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  )
}
