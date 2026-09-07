import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { OrdenesTable, type OrdenRow } from './ordenes-table'
import { NuevaOrdenButton, type VarianteOpt, type BomOpt } from './nueva-orden'
import Link from 'next/link'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { KpiCard } from '@/components/dashboard/kpi-card'

export const dynamic = 'force-dynamic'

const ESTADOS = [
  { value: 'borrador', label: 'Borrador' },
  { value: 'planificada', label: 'Planificada' },
  { value: 'liberada', label: 'Liberada' },
  { value: 'en_proceso', label: 'En proceso' },
  { value: 'pausada', label: 'Pausada' },
  { value: 'completada', label: 'Completada' },
  { value: 'cancelada', label: 'Cancelada' },
  { value: 'cerrada', label: 'Cerrada' },
]

const PRIORIDADES = [
  { value: 'baja', label: 'Baja' },
  { value: 'normal', label: 'Normal' },
  { value: 'alta', label: 'Alta' },
  { value: 'urgente', label: 'Urgente' },
]

export default async function OrdenesPage({
  searchParams,
}: {
  searchParams: Promise<{ estado?: string; prioridad?: string; q?: string }>
}) {
  const session = await getSession()
  if (!session) return null

  const sp = await searchParams
  const estado = sp.estado ?? ''
  const prioridad = sp.prioridad ?? ''
  const q = sp.q ?? ''

  const estadosKpi = ['borrador', 'planificada', 'en_proceso', 'completada']

  const [kpiCounts, ordenes, variantes, boms] = await Promise.all([
    Promise.all(
      estadosKpi.map((est) =>
        apiFetch<Paginated<OrdenRow>>(
          `/api/v1/ordenes-produccion?estado=${est}&perPage=1`,
          session.accessToken
        )
          .then((r) => r.pagination.total)
          .catch(() => 0)
      )
    ),
    apiFetch<Paginated<OrdenRow>>(
      `/api/v1/ordenes-produccion?perPage=100${estado ? `&estado=${estado}` : ''}${
        prioridad ? `&prioridad=${prioridad}` : ''
      }${q ? `&q=${encodeURIComponent(q)}` : ''}`,
      session.accessToken
    ).catch(() => null),
    apiFetch<Paginated<VarianteOpt>>('/api/v1/variantes?perPage=100', session.accessToken).catch(
      () => null
    ),
    apiFetch<Paginated<BomOpt>>('/api/v1/bom?perPage=100', session.accessToken).catch(() => null),
  ])

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'
  const [borradores, planificadas, enProceso, completadas] = kpiCounts

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Órdenes de Producción</h1>
          <p className="text-sm text-muted-foreground">Gestión y flujo de estados</p>
        </div>
        {canAdmin ? (
          <NuevaOrdenButton variantes={variantes?.data ?? []} boms={boms?.data ?? []} />
        ) : null}
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard title="Borradores" value={borradores} icon="puzzle" />
        <KpiCard title="Planificadas" value={planificadas} icon="layers" />
        <KpiCard title="En proceso" value={enProceso} icon="factory" />
        <KpiCard title="Completadas" value={completadas} icon="building" />
      </div>

      <form method="get" className="flex flex-wrap items-end gap-3">
        <div className="w-64 space-y-1">
          <label className="text-xs text-muted-foreground" htmlFor="q">
            Buscar por número
          </label>
          <Input id="q" name="q" defaultValue={q} placeholder="OP-..." />
        </div>
        <div className="w-44 space-y-1">
          <label className="text-xs text-muted-foreground" htmlFor="estado">
            Estado
          </label>
          <select
            id="estado"
            name="estado"
            defaultValue={estado}
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          >
            <option value="">Todos</option>
            {ESTADOS.map((e) => (
              <option key={e.value} value={e.value}>
                {e.label}
              </option>
            ))}
          </select>
        </div>
        <div className="w-44 space-y-1">
          <label className="text-xs text-muted-foreground" htmlFor="prioridad">
            Prioridad
          </label>
          <select
            id="prioridad"
            name="prioridad"
            defaultValue={prioridad}
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          >
            <option value="">Todas</option>
            {PRIORIDADES.map((p) => (
              <option key={p.value} value={p.value}>
                {p.label}
              </option>
            ))}
          </select>
        </div>
        <Button type="submit" size="sm">
          Filtrar
        </Button>
        {estado || prioridad || q ? (
          <Button variant="ghost" size="sm" render={<Link href="/produccion/ordenes" />}>
            Limpiar
          </Button>
        ) : null}
      </form>

      <OrdenesTable ordenes={ordenes?.data ?? []} />
    </div>
  )
}