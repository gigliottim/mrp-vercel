import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { EditorClient, type Operacion, type Centro, type Resumen } from './editor-client'

export const dynamic = 'force-dynamic'

export default async function EditorRutaPage({ params }: { params: Promise<{ id: string }> }) {
  const session = await getSession()
  if (!session) return null
  const { id } = await params
  const bomId = Number(id)

  const [ops, centros, resumen] = await Promise.all([
    apiFetch<{ data: Operacion[] }>(`/api/v1/rutas-produccion/bom/${bomId}/operaciones`, session.accessToken).catch(() => null),
    apiFetch<{ data: Centro[] }>('/api/v1/centros-trabajo?perPage=100', session.accessToken).catch(() => null),
    apiFetch<{ data: Resumen }>(`/api/v1/rutas-produccion/bom/${bomId}/resumen`, session.accessToken).catch(() => null),
  ])
  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Editor de Ruta — BOM #{bomId}</h1>
        <p className="text-sm text-muted-foreground">Operaciones de fabricación con tiempos y costos</p>
      </div>
      <EditorClient
        bomId={bomId}
        operaciones={ops?.data ?? []}
        centros={centros?.data ?? []}
        resumen={resumen?.data ?? null}
        canAdmin={canAdmin}
      />
    </div>
  )
}
