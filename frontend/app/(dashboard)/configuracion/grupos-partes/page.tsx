import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { GruposPartesForm, type GruposPartesRow } from './grupos-partes-form'
import { crear, actualizar, eliminar } from './actions'
import { Badge } from '@/components/ui/badge'

export const dynamic = 'force-dynamic'

export default async function GruposPartesPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string; perPage?: string }>
}) {
  const session = await getSession()
  if (!session) return null

  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)

  const result = await apiFetch<Paginated<GruposPartesRow>>(
    `/api/v1/grupos-partes?page=${page}&perPage=${perPage}`,
    session.accessToken
  ).catch(() => null)

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <CrudPage
      title="Grupos de partes"
      description="Agrupación de partes"
      canCreate={canAdmin}
      createLabel="Nuevo grupo"
      data={result?.data ?? []}
      page={page}
      perPage={perPage}
      total={result?.pagination.total ?? 0}
      columns={[
        { key: 'codigo', header: 'Código', render: (r) => String(r.codigo ?? '') },
        { key: 'nombre', header: 'Nombre', render: (r) => String(r.nombre ?? '') },
        { key: 'color', header: 'Color', render: (r) => (<span className="inline-block h-4 w-4 rounded border" style={{ backgroundColor: String(r.color) }} />) },
        { key: 'activo', header: 'Estado', render: (r) => (r.activo ? <Badge variant="outline">Sí</Badge> : <Badge variant="secondary">No</Badge>) },
      ]}
      FormComponent={GruposPartesForm}
      onCreate={crear}
      onUpdate={actualizar}
      onDelete={eliminar}
    />
  )
}
