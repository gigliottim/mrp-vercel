import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { VarianteForm, type VarianteRow, type ParteOpt } from './variantes-form'
import { columns } from './columns'
import { crear, actualizar, eliminar } from './actions'

export const dynamic = 'force-dynamic'

export default async function VariantesPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string; perPage?: string; id_parte?: string }>
}) {
  const session = await getSession()
  if (!session) return null

  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)
  const idParte = sp.id_parte

  const [result, partes] = await Promise.all([
    apiFetch<Paginated<VarianteRow>>(
      `/api/v1/variantes?page=${page}&perPage=${perPage}${idParte ? `&id_parte=${idParte}` : ''}`,
      session.accessToken
    ).catch(() => null),
    apiFetch<Paginated<ParteOpt>>('/api/v1/partes?perPage=100', session.accessToken).catch(() => null),
  ])

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <CrudPage
      title="Gestor de partes"
      description="Variantes de partes con stock"
      canCreate={canAdmin}
      createLabel="Nueva variante"
      data={result?.data ?? []}
      page={page}
      perPage={perPage}
      total={result?.pagination.total ?? 0}
      columns={columns}
      FormComponent={VarianteForm}
      formExtraProps={{ partes: partes?.data ?? [] }}
      onCreate={crear}
      onUpdate={actualizar}
      onDelete={eliminar}
    />
  )
}
