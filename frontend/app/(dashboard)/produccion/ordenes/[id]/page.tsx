import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { OrdenAcciones } from './orden-acciones'
import Link from 'next/link'

export const dynamic = 'force-dynamic'

type Orden = {
  id: number
  numero_orden: string
  variante_id: number
  bom_id_utilizada: number | null
  cantidad_planificada: number
  cantidad_producida: number
  cantidad_desechada: number
  fecha_inicio_programada: string
  fecha_fin_programada: string
  fecha_inicio_real: string | null
  fecha_fin_real: string | null
  estado: string
  prioridad: string
  observaciones: string | null
  fecha_creacion: string
  fecha_actualizacion: string
  variantes: { codigo_variante: string; detalle: string } | null
}

const ESTADO_LABEL: Record<string, string> = {
  borrador: 'Borrador',
  planificada: 'Planificada',
  liberada: 'Liberada',
  en_proceso: 'En proceso',
  pausada: 'Pausada',
  completada: 'Completada',
  cancelada: 'Cancelada',
  cerrada: 'Cerrada',
}

const ESTADO_BADGE: Record<string, string> = {
  borrador: 'secondary',
  planificada: 'info',
  liberada: 'warning',
  en_proceso: 'default',
  pausada: 'warning',
  completada: 'success',
  cerrada: 'outline',
  cancelada: 'destructive',
}

const TIMELINE: Array<{ estado: string; label: string }> = [
  { estado: 'borrador', label: 'Creación' },
  { estado: 'planificada', label: 'Planificada' },
  { estado: 'liberada', label: 'Liberada' },
  { estado: 'en_proceso', label: 'En proceso' },
  { estado: 'completada', label: 'Completada' },
  { estado: 'cerrada', label: 'Cerrada' },
]

export default async function OrdenDetallePage({ params }: { params: Promise<{ id: string }> }) {
  const session = await getSession()
  if (!session) return null
  const { id } = await params

  const res = await apiFetch<{ data: Orden }>(
    `/api/v1/ordenes-produccion/${id}`,
    session.accessToken
  ).catch(() => null)

  if (!res) {
    return <p className="text-sm text-muted-foreground">Orden no encontrada.</p>
  }

  const o = res.data
  const planificada = Number(o.cantidad_planificada)
  const producida = Number(o.cantidad_producida)
  const avance = planificada > 0 ? Math.min(100, Math.round((producida / planificada) * 100)) : 0

  // Timeline: paridad con _orden_timeline.php (creación + estado actual)
  const timeline = [
    { estado: 'borrador', label: 'Creación', fecha: o.fecha_creacion },
    { estado: o.estado, label: ESTADO_LABEL[o.estado] ?? o.estado, fecha: o.fecha_actualizacion },
  ]

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="font-mono text-2xl font-bold">{o.numero_orden}</h1>
            <Badge variant={(ESTADO_BADGE[o.estado] ?? 'secondary') as 'default'}>
              {ESTADO_LABEL[o.estado] ?? o.estado}
            </Badge>
            <Badge variant="outline">{o.prioridad}</Badge>
          </div>
          <p className="mt-1 text-sm text-muted-foreground">
            {o.variantes?.codigo_variante ?? '—'} — {o.variantes?.detalle ?? ''}
          </p>
        </div>
        <div className="flex gap-2">
          <OrdenAcciones id={o.id} estado={o.estado} />
          <Button variant="outline" size="sm" render={<Link href="/produccion/ordenes" />}>
            Volver
          </Button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-base">Información general</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm md:grid-cols-3">
              <div>
                <p className="text-muted-foreground">Cantidad planificada</p>
                <p className="font-mono">{o.cantidad_planificada}</p>
              </div>
              <div className="text-muted-foreground">
                <p className="text-muted-foreground">Cantidad producida</p>
                <p className="font-mono">{o.cantidad_producida}</p>
              </div>
              <div className="text-muted-foreground">
                <p className="text-muted-foreground">Desechada</p>
                <p className="font-mono">{o.cantidad_desechada}</p>
              </div>
              <div>
                <p className="text-muted-foreground">Inicio programado</p>
                <p>{o.fecha_inicio_programada}</p>
              </div>
              <div>
                <p className="text-muted-foreground">Fin programado</p>
                <p>{o.fecha_fin_programada}</p>
              </div>
              <div>
                <p className="text-muted-foreground">BOM utilizada</p>
                <p>{o.bom_id_utilizada ?? '—'}</p>
              </div>
            </div>
            <div className="space-y-1">
              <div className="flex justify-between text-xs text-muted-foreground">
                <span>Avance</span>
                <span>{avance}%</span>
              </div>
              <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                  className="h-full rounded-full bg-primary"
                  style={{ width: `${avance}%` }}
                />
              </div>
            </div>
            {o.observaciones ? (
              <p className="text-sm text-muted-foreground">{o.observaciones}</p>
            ) : null}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Historial de estados</CardTitle>
          </CardHeader>
          <CardContent>
            <ol className="space-y-4">
              {timeline.map((h, i) => (
                <li key={i} className="flex gap-3">
                  <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" />
                  <div>
                    <p className="text-sm font-medium">{h.label}</p>
                    <p className="text-xs text-muted-foreground">
                      {h.fecha ? new Date(h.fecha).toLocaleString('es-AR') : '—'}
                    </p>
                  </div>
                </li>
              ))}
            </ol>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}