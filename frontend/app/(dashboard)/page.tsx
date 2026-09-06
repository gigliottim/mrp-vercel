import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { KpiCard } from '@/components/dashboard/kpi-card'

type AnyRow = Record<string, unknown>

export default async function DashboardPage() {
  const session = await getSession()
  if (!session) return null // el layout redirige a /login

  const [partes, variantes, ordenes] = await Promise.all([
    apiFetch<Paginated<AnyRow>>('/api/v1/partes?perPage=1', session.accessToken).catch(() => null),
    apiFetch<Paginated<AnyRow>>('/api/v1/variantes?perPage=1', session.accessToken).catch(() => null),
    apiFetch<Paginated<AnyRow>>(
      '/api/v1/ordenes-produccion?estado=en_proceso',
      session.accessToken
    ).catch(() => null),
  ])

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Panel inicial</h1>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard title="Partes" value={partes?.pagination.total ?? 0} icon="puzzle" />
        <KpiCard title="Variantes" value={variantes?.pagination.total ?? 0} icon="layers" />
        <KpiCard title="Órdenes en proceso" value={ordenes?.pagination.total ?? 0} icon="factory" />
        <KpiCard
          title="Empresa"
          value={`#${session.companyId}`}
          icon="building"
          subtitle={session.role}
        />
      </div>
    </div>
  )
}
