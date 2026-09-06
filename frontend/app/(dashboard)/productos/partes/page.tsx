import { getSession } from '@/lib/session'
import { apiFetch, type Paginated } from '@/lib/api'
import { CrudPage } from '@/components/crud/crud-page'
import { ParteForm, type ParteRow, type TipoParte, type GrupoParte } from './partes-form'
import { columns } from './columns'
import { crear, actualizar, eliminar } from './actions'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export const dynamic = 'force-dynamic'

export default async function PartesPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string; perPage?: string; q?: string }>
}) {
  const session = await getSession()
  if (!session) return null

  const sp = await searchParams
  const page = Number(sp.page ?? 1)
  const perPage = Number(sp.perPage ?? 20)
  const q = sp.q ?? ''

  const [result, tipos, grupos] = await Promise.all([
    apiFetch<Paginated<ParteRow>>(
      `/api/v1/partes?page=${page}&perPage=${perPage}${q ? `&q=${encodeURIComponent(q)}` : ''}`,
      session.accessToken
    ).catch(() => null),
    apiFetch<Paginated<TipoParte>>('/api/v1/tipos-partes?perPage=100', session.accessToken).catch(
      () => null
    ),
    apiFetch<Paginated<GrupoParte>>('/api/v1/grupos-partes?perPage=100', session.accessToken).catch(
      () => null
    ),
  ])

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div className="flex items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold">Listado de Partes</h1>
          <p className="text-sm text-muted-foreground">Catálogo de partes</p>
        </div>
        <form method="get" className="w-64">
          <Label htmlFor="q" className="sr-only">
            Buscar
          </Label>
          <Input
            id="q"
            name="q"
            defaultValue={q}
            placeholder="Buscar por código o detalle..."
            className="w-full"
          />
        </form>
      </div>
      <CrudPage
        title=""
        canCreate={canAdmin}
        createLabel="Nueva parte"
        data={result?.data ?? []}
        page={page}
        perPage={perPage}
        total={result?.pagination.total ?? 0}
        columns={columns}
        FormComponent={ParteForm}
        formExtraProps={{ tipos: tipos?.data ?? [], grupos: grupos?.data ?? [] }}
        onCreate={crear}
        onUpdate={actualizar}
        onDelete={eliminar}
      />
    </div>
  )
}
