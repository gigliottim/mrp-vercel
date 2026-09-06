import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { UnidadForm, type UnidadMedida } from './unidades-form'
import { tipoLabel } from './unidades-labels'
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
      columns={[
        { key: 'tipo', header: 'Tipo', render: (r) => tipoLabel(String(r.tipo)) },
        { key: 'unidad', header: 'Unidad', render: (r) => String(r.unidad) },
        { key: 'simbolo', header: 'Símbolo', render: (r) => <code>{String(r.simbolo)}</code> },
        {
          key: 'equivalencia_base',
          header: 'Equivalencia',
          render: (r) => String(r.equivalencia_base),
        },
        {
          key: 'es_base',
          header: 'Base',
          render: (r) => (r.es_base ? <Badge>Base</Badge> : null),
        },
        {
          key: 'activo',
          header: 'Estado',
          render: (r) =>
            r.activo ? <Badge variant="outline">Activo</Badge> : <Badge variant="destructive">Inactivo</Badge>,
        },
      ]}
      FormComponent={UnidadForm}
      onCreate={crearUnidad}
      onUpdate={actualizarUnidad}
      onDelete={eliminarUnidad}
    />
  )
}
