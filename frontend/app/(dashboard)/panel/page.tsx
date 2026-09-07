import Link from 'next/link'
import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { KpiCard } from '@/components/dashboard/kpi-card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

type AnyRow = Record<string, unknown>

type Operaciones = {
  data: {
    metricas: {
      centros_activos: number
      rutas_configuradas: number
      ordenes_activas: number
      capacidad_utilizada: number
    }
    ordenes_recientes: Array<{
      id: number
      numero_orden: string
      estado: string
      cantidad_planificada: number
      cantidad_producida: number
      prioridad: string
      variantes: { codigo_variante: string; detalle: string } | null
    }>
  }
}

type Critico = {
  data: {
    items: Array<Record<string, unknown>>
    stats: { total: number; critico: number; advertencia: number; normal: number }
  }
}

type Sugerencias = {
  data: {
    resumen: { fabricables: number; parciales: number; sin_stock: number; total: number }
    variantes: Array<Record<string, unknown>>
  }
}

const ESTADO_LABEL: Record<string, string> = {
  borrador: 'Borrador',
  planificada: 'Planificada',
  liberada: 'Liberada',
  en_produccion: 'En producción',
  en_proceso: 'En proceso',
  pausada: 'Pausada',
  completada: 'Completada',
  cerrada: 'Cerrada',
  cancelada: 'Cancelada',
}

const ACCIONES = [
  { href: '/produccion/ordenes', label: 'Nueva orden' },
  { href: '/transacciones/movimientos-partes', label: 'Registrar movimiento' },
  { href: '/planeamiento/sugerencias', label: 'Sugerencias MRP' },
  { href: '/productos/maestro', label: 'Maestro de productos' },
]

export default async function DashboardPage() {
  const session = await getSession()
  if (!session) return null // el layout redirige a /login

  const [partes, variantes, operaciones, critico, sugerencias] = await Promise.all([
    apiFetch<Paginated<AnyRow>>('/api/v1/partes?perPage=1', session.accessToken).catch(() => null),
    apiFetch<Paginated<AnyRow>>('/api/v1/variantes?perPage=1', session.accessToken).catch(() => null),
    apiFetch<Operaciones>('/api/v1/operaciones', session.accessToken).catch(() => null),
    apiFetch<Critico>('/api/v1/inventario/critico', session.accessToken).catch(() => null),
    apiFetch<Sugerencias>('/api/v1/sugerencias', session.accessToken).catch(() => null),
  ])

  const metricas = operaciones?.data?.metricas
  const ordenes = operaciones?.data?.ordenes_recientes ?? []
  const statsCritico = critico?.data?.stats
  const resumenSugerencias = sugerencias?.data?.resumen

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Panel inicial</h1>
        <p className="text-sm text-muted-foreground">Estado del MRP y accesos rápidos</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard title="Partes" value={partes?.pagination.total ?? 0} icon="puzzle" />
        <KpiCard title="Variantes" value={variantes?.pagination.total ?? 0} icon="layers" />
        <KpiCard
          title="Órdenes activas"
          value={metricas?.ordenes_activas ?? 0}
          icon="factory"
          subtitle={`${metricas?.centros_activos ?? 0} centros activos`}
        />
        <KpiCard
          title="Stock crítico"
          value={statsCritico?.critico ?? 0}
          icon="layers"
          subtitle={`${statsCritico?.advertencia ?? 0} en advertencia`}
        />
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-base">Órdenes activas</CardTitle>
          </CardHeader>
          <CardContent>
            {(ordenes ?? []).length === 0 ? (
              <p className="text-sm text-muted-foreground">No hay órdenes activas.</p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Número</TableHead>
                    <TableHead>Producto</TableHead>
                    <TableHead className="text-right">Cant.</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Prioridad</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(ordenes ?? []).map((o) => (
                    <TableRow key={o.id}>
                      <TableCell className="font-mono text-xs">{o.numero_orden}</TableCell>
                      <TableCell>
                        {o.variantes?.codigo_variante ?? '—'}
                        <span className="ml-1 text-muted-foreground">
                          {o.variantes?.detalle ?? ''}
                        </span>
                      </TableCell>
                      <TableCell className="text-right font-mono">
                        {o.cantidad_producida}/{o.cantidad_planificada}
                      </TableCell>
                      <TableCell>
                        <Badge variant="secondary">{ESTADO_LABEL[o.estado] ?? o.estado}</Badge>
                      </TableCell>
                      <TableCell className="text-muted-foreground">{o.prioridad ?? '—'}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
            <div className="mt-4">
              <Button variant="outline" size="sm" render={<Link href="/produccion/ordenes" />}>
                Ver todas las órdenes
              </Button>
            </div>
          </CardContent>
        </Card>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Alertas y pendientes</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div className="flex items-center justify-between">
                <span>Stock crítico</span>
                <Link
                  href="/inventario/critico?estado=critico"
                  className="font-medium hover:underline"
                >
                  {statsCritico?.critico ?? 0} ítems
                </Link>
              </div>
              <div className="flex items-center justify-between">
                <span>Stock en advertencia</span>
                <Link
                  href="/inventario/critico?estado=advertencia"
                  className="font-medium hover:underline"
                >
                  {statsCritico?.advertencia ?? 0} ítems
                </Link>
              </div>
              <div className="flex items-center justify-between">
                <span>Sugerencias sin stock</span>
                <Link
                  href="/planeamiento/sugerencias?filtro=sin_stock"
                  className="font-medium hover:underline"
                >
                  {resumenSugerencias?.sin_stock ?? 0} variantes
                </Link>
              </div>
              <div className="flex items-center justify-between">
                <span>MRP sugeridas parcial</span>
                <Link
                  href="/planeamiento/sugerencias?filtro=parcial"
                  className="font-medium hover:underline"
                >
                  {resumenSugerencias?.parciales ?? 0} variantes
                </Link>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Acciones rápidas</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
              {ACCIONES.map((a) => (
                <Button key={a.href} variant="outline" size="sm" render={<Link href={a.href} />}>
                  {a.label}
                </Button>
              ))}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}