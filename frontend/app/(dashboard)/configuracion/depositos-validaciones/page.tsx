import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { ValidacionForm, type ValidacionRow, type Deposito } from './validacion-form'
import { crear, actualizar, eliminar } from './actions'
import { Badge } from '@/components/ui/badge'

export const dynamic = 'force-dynamic'

export default async function ValidacionesPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string; perPage?: string }>
}) {
  const session = await getSession()
  if (!session) return null

  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)

  const [result, depositosRes] = await Promise.all([
    apiFetch<Paginated<ValidacionRow>>(
      `/api/v1/tipos-depositos-movimientos?page=${page}&perPage=${perPage}`,
      session.accessToken
    ).catch(() => null),
    apiFetch<Paginated<Deposito>>('/api/v1/tipos-depositos?perPage=100', session.accessToken).catch(
      () => null
    ),
  ])

  const depositos = depositosRes?.data ?? []
  const nombreDeposito = (id: number) =>
    depositos.find((d) => d.id === id)?.nombre ?? `#${id}`

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <CrudPage
      title="Validaciones de movimientos"
      description="Movimientos permitidos entre tipos de depósito"
      canCreate={canAdmin}
      createLabel="Nueva validación"
      data={result?.data ?? []}
      page={page}
      perPage={perPage}
      total={result?.pagination.total ?? 0}
      columns={[
        {
          key: 'origen',
          header: 'Origen',
          render: (r) => nombreDeposito(Number(r.tipo_deposito_origen_id)),
        },
        {
          key: 'destino',
          header: 'Destino',
          render: (r) => nombreDeposito(Number(r.tipo_deposito_destino_id)),
        },
        {
          key: 'activo',
          header: 'Estado',
          render: (r) =>
            r.activo ? <Badge variant="outline">Activo</Badge> : <Badge variant="destructive">Inactivo</Badge>,
        },
      ]}
      FormComponent={(props) => (
        <ValidacionForm {...props} depositos={depositos} />
      )}
      onCreate={crear}
      onUpdate={actualizar}
      onDelete={eliminar}
    />
  )
}
