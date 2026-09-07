import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { VarianteSelector } from './variante-selector'
import { MaestroClient, type VarianteOpt, type UmOpt, type TreeRow } from './maestro-client'

export const dynamic = 'force-dynamic'

export default async function MaestroPage({
  searchParams,
}: {
  searchParams: Promise<{ id_variante?: string }>
}) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const varianteId = Number(sp.id_variante ?? 0)

  const [variantesRes, umsRes, tiposRes, treeRes] = await Promise.all([
    apiFetch<Paginated<VarianteOpt>>(
      '/api/v1/variantes?perPage=500&with_parte=1',
      session.accessToken
    ).catch(() => null),
    apiFetch<Paginated<UmOpt>>('/api/v1/unidades-medida?perPage=100', session.accessToken).catch(
      () => null
    ),
    apiFetch<Paginated<{ id: number; codigo: string; nombre: string }>>(
      '/api/v1/tipos-partes?perPage=100',
      session.accessToken
    ).catch(() => null),
    varianteId > 0
      ? apiFetch<{ data: TreeRow[] }>(`/api/v1/bom/tree/${varianteId}`, session.accessToken).catch(
          () => null
        )
      : Promise.resolve(null),
  ])

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Maestro de Productos</h1>
        <p className="text-sm text-muted-foreground">Composición de variantes en árbol</p>
      </div>

      <VarianteSelector
        variantes={variantesRes?.data ?? []}
        tipos={tiposRes?.data ?? []}
        selectedId={varianteId > 0 ? varianteId : null}
      />

      {varianteId > 0 ? (
        <MaestroClient
          filas={treeRes?.data ?? []}
          variantes={variantesRes?.data ?? []}
          ums={umsRes?.data ?? []}
          canAdmin={canAdmin}
        />
      ) : (
        <p className="text-sm text-muted-foreground">
          Buscá y seleccioná un producto maestro para ver su estructura.
        </p>
      )}
    </div>
  )
}