import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { CompraForm, type CompraRow, type EntidadOpt, type VarianteOpt } from './compras-form'
import { columns } from './columns'
import { crear, eliminar } from './actions'

export const dynamic = 'force-dynamic'

export default async function ComprasPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string; perPage?: string }>
}) {
  const session = await getSession()
  if (!session) return null

  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)

  const [result, entidades, variantes] = await Promise.all([
    apiFetch<Paginated<CompraRow>>(`/api/v1/compras?page=${page}&perPage=${perPage}`, session.accessToken).catch(() => null),
    apiFetch<Paginated<EntidadOpt>>('/api/v1/entidades?perPage=100', session.accessToken).catch(() => null),
    apiFetch<Paginated<VarianteOpt>>('/api/v1/variantes?perPage=100', session.accessToken).catch(() => null),
  ])

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <CrudPage
      title="Gestión de Compras"
      description="Compras con recepción automática de stock"
      canCreate={canAdmin}
      createLabel="Nueva compra"
      data={result?.data ?? []}
      page={page}
      perPage={perPage}
      total={result?.pagination.total ?? 0}
      columns={columns}
      FormComponent={(props) => (
        <CompraForm {...props} entidades={entidades?.data ?? []} variantes={variantes?.data ?? []} />
      )}
      onCreate={crear}
      onUpdate={async () => ({ ok: true })}
      onDelete={eliminar}
    />
  )
}
