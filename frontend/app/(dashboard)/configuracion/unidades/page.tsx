import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { UnidadForm, type UnidadMedida } from './unidades-form'
import { columns } from './columns'
import { crearUnidad, actualizarUnidad, eliminarUnidad } from './actions'
import { Badge } from '@/components/ui/badge'

export const dynamic = 'force-dynamic'

export default async function UnidadesPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string; perPage?: string }>
}) {
  const session = await getSession()
  if (!session) return null

  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)

  const result = await apiFetch<Paginated<UnidadMedida>>(
    `/api/v1/unidades-medida?page=${page}&perPage=${perPage}`,
    session.accessToken
  ).catch(() => null)

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <CrudPage
      title="Unidades de medida"
      description="Catálogo de unidades del sistema"
      canCreate={canAdmin}
      createLabel="Nueva unidad"
      data={result?.data ?? []}
      page={page}
      perPage={perPage}
      total={result?.pagination.total ?? 0}
      columns={columns}
      FormComponent={UnidadForm}
      onCreate={crearUnidad}
      onUpdate={actualizarUnidad}
      onDelete={eliminarUnidad}
    />
  )
}
