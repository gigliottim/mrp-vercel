import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { TiposPartesForm, type TiposPartesRow } from './tipos-partes-form'
import { columns } from './columns'
import { crear, actualizar, eliminar } from './actions'

export const dynamic = 'force-dynamic'

export default async function TiposPartesPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string; perPage?: string }>
}) {
  const session = await getSession()
  if (!session) return null

  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)

  const result = await apiFetch<Paginated<TiposPartesRow>>(
    `/api/v1/tipos-partes?page=${page}&perPage=${perPage}`,
    session.accessToken
  ).catch(() => null)

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <CrudPage
      title="Tipos de partes"
      description="Clasificación de partes"
      canCreate={canAdmin}
      createLabel="Nuevo tipo"
      data={result?.data ?? []}
      page={page}
      perPage={perPage}
      total={result?.pagination.total ?? 0}
      columns={columns}
      FormComponent={TiposPartesForm}
      onCreate={crear}
      onUpdate={actualizar}
      onDelete={eliminar}
    />
  )
}
