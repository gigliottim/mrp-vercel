import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { ReemplazarForm, type VarianteOpt, type WhereUsedRow } from './reemplazar-form'

export const dynamic = 'force-dynamic'

export default async function ReemplazarPage({ searchParams }: { searchParams: Promise<{ id_variante_origen?: string }> }) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const origenId = Number(sp.id_variante_origen ?? 0)

  const [variantes, whereUsed] = await Promise.all([
    apiFetch<{ data: VarianteOpt[] }>('/api/v1/variantes?perPage=500', session.accessToken).catch(() => null),
    origenId > 0
      ? apiFetch<{ data: WhereUsedRow[] }>(`/api/v1/bom/where-used/${origenId}`, session.accessToken).catch(() => null)
      : Promise.resolve(null),
  ])
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Reemplazar Partes en el Maestro</h1>
        <p className="text-sm text-muted-foreground">Reemplaza una pieza X por H en todas las BOMs seleccionadas</p>
      </div>
      {canAdmin ? (
        <ReemplazarForm
          variantes={variantes?.data ?? []}
          whereUsed={whereUsed?.data ?? []}
          onBuscar={(id) => {
            // navegación con query param para recargar where-used
            window.location.href = `/productos/reemplazar-partes?id_variante_origen=${id}`
          }}
        />
      ) : (
        <p className="text-sm text-muted-foreground">Sin permisos para reemplazar partes.</p>
      )}
    </div>
  )
}
