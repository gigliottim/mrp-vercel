import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { MovimientoForm, type VarianteOpt, type AlmacenOpt } from './movimientos-form'

export const dynamic = 'force-dynamic'

export type MovimientoRow = {
  id: number
  variante_id: number
  tipo_movimiento: string
  cantidad: number
  signo: number
  observaciones: string | null
  fecha_movimiento: string
}

const TIPO_LABELS: Record<string, string> = {
  compra_recepcion: 'Recepción de compra',
  produccion_ingreso: 'Ingreso de producción',
  produccion_consumo: 'Consumo de producción',
  produccion_descarte: 'Descarte de producción',
  ajuste_inventario: 'Ajuste de inventario',
  venta_despacho: 'Despacho de venta',
  transferencia_salida: 'Salida por transferencia',
  transferencia_entrada: 'Entrada por transferencia',
}

export default async function MovimientosPage() {
  const session = await getSession()
  if (!session) return null

  const [movimientos, variantes, almacenes] = await Promise.all([
    apiFetch<Paginated<MovimientoRow>>('/api/v1/movimientos-inventario?perPage=50', session.accessToken).catch(() => null),
    apiFetch<Paginated<VarianteOpt>>('/api/v1/variantes?perPage=100', session.accessToken).catch(() => null),
    apiFetch<Paginated<AlmacenOpt>>('/api/v1/almacenes?perPage=100', session.accessToken).catch(() => null),
  ])

  const canOperar = ['Super Administrador', 'Administrador', 'Supervisor'].includes(session.role)

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Movimientos de Partes</h1>
        <p className="text-sm text-muted-foreground">Registro de movimientos con actualización automática de stock</p>
      </div>
      {canOperar ? (
        <MovimientoForm variantes={variantes?.data ?? []} almacenes={almacenes?.data ?? []} />
      ) : null}
      <div className="rounded-md border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50">
              <th className="p-2 text-left font-medium">ID</th>
              <th className="p-2 text-left font-medium">Variante</th>
              <th className="p-2 text-left font-medium">Tipo</th>
              <th className="p-2 text-right font-medium">Cantidad</th>
              <th className="p-2 text-left font-medium">Fecha</th>
            </tr>
          </thead>
          <tbody>
            {(movimientos?.data ?? []).map((m) => (
              <tr key={m.id} className="border-b">
                <td className="p-2">{m.id}</td>
                <td className="p-2">#{m.variante_id}</td>
                <td className="p-2">{TIPO_LABELS[m.tipo_movimiento] ?? m.tipo_movimiento}</td>
                <td className={`p-2 text-right font-mono ${m.signo < 0 ? 'text-destructive' : ''}`}>
                  {m.signo < 0 ? '-' : '+'}{m.cantidad}
                </td>
                <td className="p-2 text-muted-foreground">{new Date(m.fecha_movimiento).toLocaleString('es-AR')}</td>
              </tr>
            ))}
            {!movimientos || movimientos.data.length === 0 ? (
              <tr>
                <td colSpan={5} className="h-16 text-center text-muted-foreground">Sin movimientos</td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  )
}
