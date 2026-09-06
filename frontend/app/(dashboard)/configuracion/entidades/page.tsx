import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { EntidadesForm, type EntidadesRow } from './entidades-form'
import { crear, actualizar, eliminar } from './actions'
import { Badge } from '@/components/ui/badge'

export const dynamic = 'force-dynamic'

export default async function EntidadesPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string; perPage?: string }>
}) {
  const session = await getSession()
  if (!session) return null

  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)

  const result = await apiFetch<Paginated<EntidadesRow>>(
    `/api/v1/entidades?page=${page}&perPage=${perPage}`,
    session.accessToken
  ).catch(() => null)

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <CrudPage
      title="Clientes y proveedores"
      description="Entidades del sistema"
      canCreate={canAdmin}
      createLabel="Nueva entidad"
      data={result?.data ?? []}
      page={page}
      perPage={perPage}
      total={result?.pagination.total ?? 0}
      columns={[
        { key: 'razon_social', header: 'Razón social', render: (r) => String(r.razon_social ?? '') },
        { key: 'tipo', header: 'Tipo', render: (r) => (r.tipo ? <Badge variant="outline">Sí</Badge> : <Badge variant="secondary">No</Badge>) },
        { key: 'identificacion_tributaria', header: 'CUIT', render: (r) => String(r.identificacion_tributaria ?? '') },
      ]}
      FormComponent={EntidadesForm}
      onCreate={crear}
      onUpdate={actualizar}
      onDelete={eliminar}
    />
  )
}
