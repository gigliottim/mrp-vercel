import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { TiposDepositosForm, type TiposDepositosRow } from './tipos-depositos-form'
import { crear, actualizar, eliminar } from './actions'
import { Badge } from '@/components/ui/badge'

export const dynamic = 'force-dynamic'

export default async function TiposDepositosPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string; perPage?: string }>
}) {
  const session = await getSession()
  if (!session) return null

  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)

  const result = await apiFetch<Paginated<TiposDepositosRow>>(
    `/api/v1/tipos-depositos?page=${page}&perPage=${perPage}`,
    session.accessToken
  ).catch(() => null)

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <CrudPage
      title="Tipos de depósito"
      description="Tipos de depósito del sistema"
      canCreate={canAdmin}
      createLabel="Nuevo tipo"
      data={result?.data ?? []}
      page={page}
      perPage={perPage}
      total={result?.pagination.total ?? 0}
      columns={[
        { key: 'codigo', header: 'Código', render: (r) => String(r.codigo ?? '') },
        { key: 'nombre', header: 'Nombre', render: (r) => String(r.nombre ?? '') },
        { key: 'orden', header: 'Orden', render: (r) => String(r.orden ?? '') },
        { key: 'activo', header: 'Estado', render: (r) => (r.activo ? <Badge variant="outline">Sí</Badge> : <Badge variant="secondary">No</Badge>) },
      ]}
      FormComponent={TiposDepositosForm}
      onCreate={crear}
      onUpdate={actualizar}
      onDelete={eliminar}
    />
  )
}
