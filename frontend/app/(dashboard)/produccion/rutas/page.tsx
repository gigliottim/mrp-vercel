import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { RutaForm, type RutaRow, type BomOpt, type CentroOpt } from './ruta-form'
import { columns } from './columns'
import { ClonarRutaDialog } from './clonar-dialog'
import { crear, actualizar, eliminar } from './actions'

export const dynamic = 'force-dynamic'


export default async function RutasPage({ searchParams }: { searchParams: Promise<{ page?: string; perPage?: string }> }) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)
  const [result, boms, centros] = await Promise.all([
    apiFetch<Paginated<RutaRow>>(`/api/v1/rutas-produccion?page=${page}&perPage=${perPage}`, session.accessToken).catch(() => null),
    apiFetch<Paginated<BomOpt>>('/api/v1/bom?perPage=100', session.accessToken).catch(() => null),
    apiFetch<Paginated<CentroOpt>>('/api/v1/centros-trabajo?perPage=100', session.accessToken).catch(() => null),
  ])
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'
  const primerBomId = (result?.data ?? [])[0]?.bom_id ?? (boms?.data ?? [])[0]?.id ?? 0
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Rutas de Producción</h1>
          <p className="text-sm text-muted-foreground">Secuencias de operaciones por BOM</p>
        </div>
        {primerBomId > 0 ? (
          <div className="flex gap-2">
            <a
              href={`/produccion/rutas/${primerBomId}/editor`}
              className="rounded-md border px-4 py-2 text-sm hover:bg-accent"
            >
              Editor de ruta
            </a>
            {canAdmin ? <ClonarRutaDialog bomId={primerBomId} boms={boms?.data ?? []} /> : null}
          </div>
        ) : null}
      </div>
      <CrudPage
        title=""
        canCreate={canAdmin}
        createLabel="Nueva ruta"
        data={result?.data ?? []}
        page={page}
        perPage={perPage}
        total={result?.pagination.total ?? 0}
        columns={columns}
        FormComponent={RutaForm}
        formExtraProps={{ boms: boms?.data ?? [], centros: centros?.data ?? [] }}
        onCreate={crear}
        onUpdate={actualizar}
        onDelete={eliminar}
      />
    </div>
  )
}
