import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { AccionLiberar } from './liberar-client'

export const dynamic = 'force-dynamic'

type Orden = {
  id: number
  numero_orden: string
  estado: string
  prioridad: string
  cantidad_planificada: number
  cantidad_producida: number
  fecha_inicio: string | null
  fecha_fin: string | null
}

export default async function OrdenesPlanificadasPage() {
  const session = await getSession()
  if (!session) return null
  const res = await apiFetch<Paginated<Orden>>('/api/v1/ordenes-produccion?estado=planificada&perPage=50', session.accessToken).catch(() => null)
  const ordenes = res?.data ?? []
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Órdenes Planificadas</h1>
        <p className="text-sm text-muted-foreground">Órdenes de producción en estado planificada</p>
      </div>
      <div className="rounded-md border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50 text-left">
              <th className="p-2 font-medium">Nº orden</th>
              <th className="p-2 text-right font-medium">Planificada</th>
              <th className="p-2 text-right font-medium">Producida</th>
              <th className="p-2 font-medium">Prioridad</th>
              <th className="p-2 font-medium">Estado</th>
              {canAdmin ? <th className="p-2 font-medium">Acciones</th> : null}
            </tr>
          </thead>
          <tbody>
            {ordenes.map((o) => (
              <tr key={o.id} className="border-b">
                <td className="p-2"><code className="text-sm">{o.numero_orden}</code></td>
                <td className="p-2 text-right font-mono">{o.cantidad_planificada}</td>
                <td className="p-2 text-right font-mono">{o.cantidad_producida}</td>
                <td className="p-2">{o.prioridad}</td>
                <td className="p-2"><Badge variant="outline">{o.estado}</Badge></td>
              {canAdmin ? (
                <td className="p-2">
                  <AccionLiberar id={o.id} />
                </td>
              ) : null}
            </tr>
            ))}
            {ordenes.length === 0 ? (
              <tr>
                <td colSpan={5} className="h-16 text-center text-muted-foreground">Sin órdenes planificadas</td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  )
}
