import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { BomForm, type BomRow, type VarianteOpt, type UmOpt } from './bom-form'
import { columns } from './columns'
import { crear, eliminar, noop } from './actions'

export const dynamic = 'force-dynamic'


export default async function BomPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string; perPage?: string }>
}) {
  const session = await getSession()
  if (!session) return null

  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)

  const [result, variantes, ums] = await Promise.all([
    apiFetch<Paginated<BomRow>>(
      `/api/v1/bom?page=${page}&perPage=${perPage}`,
      session.accessToken
    ).catch(() => null),
    apiFetch<Paginated<VarianteOpt>>('/api/v1/variantes?perPage=100', session.accessToken).catch(
      () => null
    ),
    apiFetch<Paginated<UmOpt>>('/api/v1/unidades-medida?perPage=100', session.accessToken).catch(
      () => null
    ),
  ])

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <CrudPage
      title="BOM activas"
      description="Lista de materiales por variante"
      canCreate={canAdmin}
      createLabel="Nueva BOM"
      data={result?.data ?? []}
      page={page}
      perPage={perPage}
      total={result?.pagination.total ?? 0}
      columns={columns}
      FormComponent={BomForm}
      formExtraProps={{ variantes: variantes?.data ?? [], ums: ums?.data ?? [] }}
      onCreate={crear}
      onUpdate={noop}
      onDelete={eliminar}
    />
  )
}
