import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { OrdenesTable, type OrdenRow } from './ordenes-table'
import { NuevaOrdenButton, type VarianteOpt, type BomOpt } from './nueva-orden'

export const dynamic = 'force-dynamic'

export default async function OrdenesPage({
  searchParams,
}: {
  searchParams: Promise<{ estado?: string }>
}) {
  const session = await getSession()
  if (!session) return null

  const sp = await searchParams
  const estado = sp.estado ?? ''

  const [ordenes, variantes, boms] = await Promise.all([
    apiFetch<Paginated<OrdenRow>>(
      `/api/v1/ordenes-produccion?perPage=100${estado ? `&estado=${estado}` : ''}`,
      session.accessToken
    ).catch(() => null),
    apiFetch<Paginated<VarianteOpt>>('/api/v1/variantes?perPage=100', session.accessToken).catch(
      () => null
    ),
    apiFetch<Paginated<BomOpt>>('/api/v1/bom?perPage=100', session.accessToken).catch(() => null),
  ])

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Órdenes de Producción</h1>
          <p className="text-sm text-muted-foreground">Gestión y flujo de estados</p>
        </div>
        {canAdmin ? (
          <NuevaOrdenButton variantes={variantes?.data ?? []} boms={boms?.data ?? []} />
        ) : null}
      </div>
      <OrdenesTable ordenes={ordenes?.data ?? []} />
    </div>
  )
}
