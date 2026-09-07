import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { PlanificacionForm, type PlanRow, type OrdenOpt, type CentroOpt } from './planificacion-form'
import { columns } from './columns'
import { crear, actualizar, eliminar } from './actions'
import { CalcularAutomatico } from './calcular-automatico'

export const dynamic = 'force-dynamic'


export default async function PlanificacionPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string; perPage?: string; centro_trabajo_id?: string }>
}) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)
  const centroId = sp.centro_trabajo_id ?? ''
  const [result, ordenes, centros] = await Promise.all([
    apiFetch<Paginated<PlanRow>>(
      `/api/v1/planificacion?page=${page}&perPage=${perPage}${
        centroId ? `&centro_trabajo_id=${centroId}` : ''
      }`,
      session.accessToken
    ).catch(() => null),
    apiFetch<Paginated<OrdenOpt>>('/api/v1/ordenes-produccion?perPage=100', session.accessToken).catch(() => null),
    apiFetch<Paginated<CentroOpt>>('/api/v1/centros-trabajo?perPage=100', session.accessToken).catch(() => null),
  ])
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'
  return (
    <div className="space-y-6">
      {canAdmin ? (
        <CalcularAutomatico token={session.accessToken} ordenes={ordenes?.data ?? []} />
      ) : null}
      <form method="get" className="flex items-end gap-3">
        <input type="hidden" name="page" value="1" />
        <input type="hidden" name="perPage" value={String(perPage)} />
        <div className="w-64 space-y-1">
          <label className="text-xs text-muted-foreground" htmlFor="centro">
            Centro de trabajo
          </label>
          <select
            id="centro"
            name="centro_trabajo_id"
            defaultValue={centroId}
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            
          >
            <option value="">Todos</option>
            {(centros?.data ?? []).map((c) => (
              <option key={c.id} value={String(c.id)}>
                {c.nombre} ({c.codigo})
              </option>
            ))}
          </select>
        </div>
        <button type="submit" className="h-10 rounded-md bg-primary px-4 text-sm text-primary-foreground">
          Filtrar
        </button>
      </form>
      <CrudPage
        title="Planificación de Recursos"
        description="Programación de órdenes en centros de trabajo (sin solapamientos)"
        canCreate={canAdmin}
        createLabel="Programar recurso"
        data={result?.data ?? []}
        page={page}
        perPage={perPage}
        total={result?.pagination.total ?? 0}
        columns={columns}
        FormComponent={PlanificacionForm}
        formExtraProps={{ ordenes: ordenes?.data ?? [], centros: centros?.data ?? [] }}
        onCreate={crear}
        onUpdate={actualizar}
        onDelete={eliminar}
      />
    </div>
  )
}
