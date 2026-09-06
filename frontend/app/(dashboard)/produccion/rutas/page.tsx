import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { RutaForm, type RutaRow, type BomOpt, type CentroOpt } from './ruta-form'
import { columns } from './columns'
import { crear, eliminar } from './actions'

export const dynamic = 'force-dynamic'

export default async function RutasPage({ searchParams }: { searchParams: Promise<{ page?: string; perPage?: string }> }) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)
  const [result, boms, centros] = await Promise.all([
    apiFetch<Paginated<RutaRow>>(`/api/v1/rutas-produccion?page=${page}&perPage=${perPage}`, session.accessToken).catch(() => null),
    apiFetch<Paginated<BomOpt>>('/api/v1/bom?perPage=100', session.accessToken).catch(() => null),
    apiFetch<Paginated<CentroOpt>>('/api/v1/centros-trabajo?perPage=100', session.accessToken).catch(() => null),
  ])
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'
  return (
    <CrudPage
      title="Rutas de Producción"
      description="Secuencias de operaciones por BOM"
      canCreate={canAdmin}
      createLabel="Nueva ruta"
      data={result?.data ?? []}
      page={page}
      perPage={perPage}
      total={result?.pagination.total ?? 0}
      columns={columns}
      FormComponent={(props) => <RutaForm {...props} boms={boms?.data ?? []} centros={centros?.data ?? []} />}
      onCreate={crear}
      onUpdate={async () => ({ ok: true })}
      onDelete={eliminar}
    />
  )
}
