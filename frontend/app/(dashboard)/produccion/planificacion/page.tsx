import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { PlanificacionForm, type PlanRow, type OrdenOpt, type CentroOpt } from './planificacion-form'
import { columns } from './columns'
import { crear, eliminar } from './actions'

export const dynamic = 'force-dynamic'

export default async function PlanificacionPage({ searchParams }: { searchParams: Promise<{ page?: string; perPage?: string }> }) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)
  const [result, ordenes, centros] = await Promise.all([
    apiFetch<Paginated<PlanRow>>(`/api/v1/planificacion?page=${page}&perPage=${perPage}`, session.accessToken).catch(() => null),
    apiFetch<Paginated<OrdenOpt>>('/api/v1/ordenes-produccion?perPage=100', session.accessToken).catch(() => null),
    apiFetch<Paginated<CentroOpt>>('/api/v1/centros-trabajo?perPage=100', session.accessToken).catch(() => null),
  ])
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'
  return (
    <CrudPage
      title="Planificación de Recursos"
      description="Programación de órdenes en centros de trabajo (sin solapamientos)"
      canCreate={canAdmin}
      createLabel="Programar recurso"
      data={result?.data ?? []}
      page={page}
      perPage={perPage}
      total={result?.pagination.total ?? 0}
      columns={columns}
      FormComponent={(props) => <PlanificacionForm {...props} ordenes={ordenes?.data ?? []} centros={centros?.data ?? []} />}
      onCreate={crear}
      onUpdate={async () => ({ ok: true })}
      onDelete={eliminar}
    />
  )
}
